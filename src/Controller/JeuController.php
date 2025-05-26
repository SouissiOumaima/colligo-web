<?php

namespace App\Controller;

use App\Entity\Child;
use App\Entity\Level;
use App\Entity\Game;
use App\Entity\PronunciationContent;
use App\Repository\ChildRepository;
use App\Repository\GameRepository;
use App\Repository\PronunciationContentRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/jeu')]
class JeuController extends AbstractController
{
    private const LEVEL_DESCRIPTIONS = [
        1 => 'Alphabets',
        2 => 'Mots',
        3 => 'Phrases'
    ];

    public function __construct(
        private ChildRepository $childRepository,
        private PronunciationContentRepository $pronunciationContentRepository,
        private GameRepository $gameRepository,
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger,
        private SessionInterface $session
    ) {
    }

    #[Route('/{childId}', name: 'jeu_accueil', requirements: ['childId' => '\d+'], methods: ['GET'])]
    public function accueil(Request $request, int $childId): Response
    {
        // Fetch or create child
        $child = $this->childRepository->find($childId);
        if (!$child) {
            $child = new Child();
            $child->setChildId($childId);
            $child->setName('Enfant Test');
            $child->setLanguage('fr');
            $this->entityManager->persist($child);
            try {
                $this->entityManager->flush();
                $this->logger->info('New child created', ['childId' => $childId]);
            } catch (\Exception $e) {
                $this->logger->error('Failed to create child', ['error' => $e->getMessage()]);
                return $this->render('jeu/accueil.html.twig', [
                    'child' => null,
                    'contents' => [],
                    'current_content' => null,
                    'content_index' => 0,
                    'content_size' => 0,
                    'levels' => new ArrayCollection(),
                    'average_score' => 0,
                    'level_number' => 1,
                    'error' => 'Failed to create child. Please try again.',
                    'level_description' => self::LEVEL_DESCRIPTIONS[1]
                ]);
            }
        }

        // Initialize levels if null
        if ($child->getLevels() === null) {
            $reflection = new \ReflectionClass($child);
            $property = $reflection->getProperty('levels');
            $property->setAccessible(true);
            $property->setValue($child, new ArrayCollection());
        }

        // Get or create game entity
        $game = $this->gameRepository->find(1);
        if (!$game) {
            $game = new Game();
            $game->setId(1);
            $game->setName('Jeu de Prononciation');
            $this->entityManager->persist($game);
            $this->entityManager->flush();
        }

        // Get the child's current level
        $levels = $child->getLevels();
        $currentLevel = $levels->isEmpty() ? null : $levels->last();

        // Determine the appropriate level (1, 2, or 3)
        $levelNumber = 1;
        if ($currentLevel) {
            $contentForCurrentLevel = $this->pronunciationContentRepository->findBy([
                'level' => $currentLevel->getId(),
                'langue' => $child->getLanguage()
            ]);

            // Check if current level is complete
            if ($currentLevel->getNbtries() >= count($contentForCurrentLevel)) {
                $levelNumber = min($currentLevel->getId() + 1, 3);
            } else {
                $levelNumber = $currentLevel->getId();
            }
        }

        // Create a new level if needed
        if (!$currentLevel || $currentLevel->getId() !== $levelNumber) {
            $newLevel = new Level();
            $newLevel->setId($levelNumber);
            $newLevel->setGameId($game);
            $newLevel->setChildId($child);
            $newLevel->setScore(0);
            $newLevel->setNbtries(0);
            $newLevel->setTime(0);
            $child->addLevel($newLevel);
            $this->entityManager->persist($newLevel);
            $this->entityManager->flush();
            $currentLevel = $newLevel;
        }

        // Fetch contents for the current level and language
        $contents = $this->pronunciationContentRepository->findBy([
            'level' => $levelNumber,
            'langue' => $child->getLanguage()
        ], ['id' => 'ASC']);

        $this->logger->info('Contents retrieved', [
            'count' => count($contents),
            'level' => $levelNumber,
            'language' => $child->getLanguage(),
            'level_type' => self::LEVEL_DESCRIPTIONS[$levelNumber]
        ]);

        $contentSize = count($contents);
        $contentIndex = $request->query->getInt('contentIndex', 0);
        $contentIndex = max(0, min($contentIndex, $contentSize - 1));
        $currentContent = $contentSize > 0 ? $contents[$contentIndex] : null;

        // Calculate progress
        $progress = [
            'current' => $contentIndex + 1,
            'total' => $contentSize,
            'percentage' => $contentSize > 0 ? round(($contentIndex + 1) / $contentSize * 100) : 0
        ];

        // Calculate average score
        $averageScore = $child->getLevels()->isEmpty() ? 0 : array_reduce(
            $child->getLevels()->toArray(),
            fn($carry, $level) => $carry + $level->getScore(),
            0
        ) / max(1, $child->getLevels()->count());

        // Reset attempts for new content
        if ($currentContent) {
            $attempts = $this->session->get('content_attempts', []);
            if (!isset($attempts[$currentContent->getId()])) {
                $attempts[$currentContent->getId()] = 0;
                $this->session->set('content_attempts', $attempts);
            }
        }

        return $this->render('jeu/accueil.html.twig', [
            'child' => $child,
            'contents' => $contents,
            'current_content' => $currentContent,
            'content_index' => $contentIndex,
            'content_size' => $contentSize,
            'levels' => $child->getLevels(),
            'average_score' => round($averageScore, 2),
            'level_number' => $levelNumber,
            'level_description' => self::LEVEL_DESCRIPTIONS[$levelNumber],
            'progress' => $progress,
            'error' => $contentSize === 0 ? 'No content available for this level.' : null,
        ]);
    }

    #[Route('/verifier', name: 'verifier_pronunciation', methods: ['POST'])]
    public function verifierPrononciation(Request $request): JsonResponse
    {
        $expectedText = $request->request->get('expectedText', '');
        $transcribedText = $request->request->get('transcribedText', '');
        $alternatives = json_decode($request->request->get('alternatives', '[]'), true);
        $childId = (int) $request->request->get('childId', 0);
        $contentId = (int) $request->request->get('contentId', 0);
        $currentLevel = (int) $request->request->get('level', 1);

        $this->logger->info('Verification Inputs', [
            'expectedText' => $expectedText,
            'transcribedText' => $transcribedText,
            'alternatives' => $alternatives,
            'childId' => $childId,
            'contentId' => $contentId,
            'level' => $currentLevel
        ]);

        $child = $this->childRepository->find($childId);
        if (!$child) {
            return new JsonResponse(['error' => 'Child not found'], 404);
        }

        // Get current level
        $level = $child->getLevels()->filter(fn($l) => $l->getId() === $currentLevel)->first();
        if (!$level) {
            return new JsonResponse(['error' => 'Level not found'], 404);
        }

        $startTime = $request->request->get('startTime', microtime(true));
        $endTime = microtime(true);
        $timeSpent = (int) (($endTime - $startTime) * 1000);
        $level->setTime($level->getTime() + $timeSpent);

        $attempts = $this->session->get('content_attempts', []);
        $attemptCount = ($attempts[$contentId] ?? 0) + 1;
        $attempts[$contentId] = $attemptCount;
        $this->session->set('content_attempts', $attempts);

        // Extremely lenient normalization
        $normalize = function (string $text): string {
            // Convert to lowercase
            $text = mb_strtolower($text, 'UTF-8');

            // Remove all special characters, keeping only letters and spaces
            $text = preg_replace('/[^a-z ]/i', '', $text);

            // Remove all spaces
            $text = str_replace(' ', '', $text);

            return $text;
        };

        $expected = $normalize($expectedText);
        $transcribed = $normalize($transcribedText);

        // Always accept if there's any input at all
        $isCorrect = true;
        $points = 0;
        $message = '';
        $finalSimilarity = 0;

        // Only calculate similarity if there's input
        if (!empty($transcribed)) {
            // Calculate basic similarity
            similar_text($expected, $transcribed, $similarity);
            $finalSimilarity = $similarity;

            // Award points based on very lenient criteria
            if ($similarity >= 50) {
                $points = 5;
                $message = 'ممتاز ! أنت بطل حقيقي ! 🌟';
            } elseif ($similarity >= 30) {
                $points = 4;
                $message = 'برافو ! واصل هكا ! 🎉';
            } else {
                $points = 3;
                $message = 'باهي ! راك تتقدم ! 👏';
            }

            // Always give bonus points for trying
            if ($attemptCount === 1) {
                $points += 2;
                $message = 'رائع ! من أول مرة ! 🌟🌟🌟';
            }
        } else {
            // If no input detected, encourage another try
            $isCorrect = false;
            $message = 'لم أسمعك جيداً. حاول مرة أخرى ! 🎤';
        }

        // Always update progress if there was any input
        if ($isCorrect) {
            $level->setScore($level->getScore() + $points);
            $level->setNbtries($level->getNbtries() + 1);
            $this->entityManager->persist($level);
            $this->entityManager->flush();
        }

        // Check if level is complete
        $totalContents = count($this->pronunciationContentRepository->findBy([
            'level' => $currentLevel,
            'langue' => $child->getLanguage()
        ]));

        $isLevelComplete = $level->getNbtries() >= $totalContents;
        $nextLevel = $isLevelComplete && $currentLevel < 3 ? $currentLevel + 1 : null;

        return new JsonResponse([
            'success' => true,
            'isCorrect' => $isCorrect,
            'attempts' => $attemptCount,
            'points' => $points,
            'score' => $level->getScore(),
            'similarity' => round($finalSimilarity, 2),
            'isLevelComplete' => $isLevelComplete,
            'nextLevel' => $nextLevel,
            'levelDescription' => $nextLevel ? self::LEVEL_DESCRIPTIONS[$nextLevel] : null,
            'message' => $message,
            'retry' => !$isCorrect,
            'gif' => $isCorrect ? '/gifs/excellent.gif' : '/gifs/encourage.gif',
            'son' => $isCorrect ? '/sounds/correct1.mp3' : '/sounds/incorrect1.mp3'
        ]);
    }

    private function getEncouragingMessage(int $attempts): string
    {
        $messages = [
            1 => [
                'عاود المرة الجاية، تنجم تعملها ! 🌟',
                'قريب برشة ! زيد جرب مرة أخرى ! 🎯',
                'واصل، ما بقالك شي ! 🚀'
            ],
            2 => [
                'بدّيت تتحسّن ! شد صحيح ! 💫',
                'آخر محاولة ! تنجم عليها ! 🌈',
                'ما تستسلمش، راك في الطريق الصحيح ! 🌟'
            ],
            3 => [
                'يعطيك الصحة على المجهود ! نعدّيو للي بعدو ! 🎉',
                'محاولة باهية ! نكملو المغامرة ! 🌟',
                'مشاركة هايلة ! يلا نواصلو ! 🌈'
            ]
        ];



        $messageSet = $messages[$attempts] ?? $messages[1];
        return $messageSet[array_rand($messageSet)];
    }

    private function getAppropriateGif(bool $isCorrect, float $similarity): string
    {
        if (!$isCorrect) {
            return '/gifs/encourage.gif';
        }

        if ($similarity >= 85) {
            return '/gifs/excellent.gif';
        } elseif ($similarity >= 70) {
            return '/gifs/good.gif';
        } else {
            return '/gifs/ok.gif';
        }
    }
}