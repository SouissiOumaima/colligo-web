<?php

namespace App\Controller;

use App\Entity\Child;
use App\Entity\Level;
use App\Entity\Game;
use App\Entity\PronunciationContent;
use App\Repository\ChildRepository;
use App\Repository\GameRepository;
use App\Repository\PronunciationContentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/jeu')]
class JeuController extends AbstractController
{
    public function __construct(
        private ChildRepository $childRepository,
        private PronunciationContentRepository $pronunciationContentRepository,
        private GameRepository $gameRepository,
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger
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
            $child->setLanguage('fr'); // Set to French
            $this->entityManager->persist($child);
            try {
                $this->entityManager->flush();
                $this->logger->info('New child created', ['childId' => $childId]);
            } catch (\Exception $e) {
                $this->logger->error('Failed to create child', [
                    'error' => $e->getMessage(),
                    'childId' => $childId,
                ]);
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

        // Ensure sample data exists for levels 1, 2, 3, langue 'fr'
        $language = 'fr';
        for ($level = 1; $level <= 3; $level++) {
            $contentSizeForLevel = count($this->pronunciationContentRepository->findBy(['level' => $level, 'langue' => $language]));
            if ($contentSizeForLevel === 0) {
                $this->logger->warning("No contents found for level $level, langue $language. Inserting sample data.");
                $sampleContents = [];
                if ($level === 1) {
                    $sampleContents = [
                        ['content' => 'A', 'level' => 1, 'langue' => 'fr'],
                        ['content' => 'B', 'level' => 1, 'langue' => 'fr'],
                        ['content' => 'C', 'level' => 1, 'langue' => 'fr'],
                    ];
                } elseif ($level === 2) {
                    $sampleContents = [
                        ['content' => 'D', 'level' => 2, 'langue' => 'fr'],
                        ['content' => 'E', 'level' => 2, 'langue' => 'fr'],
                        ['content' => 'F', 'level' => 2, 'langue' => 'fr'],
                    ];
                } elseif ($level === 3) {
                    $sampleContents = [
                        ['content' => 'G', 'level' => 3, 'langue' => 'fr'],
                        ['content' => 'H', 'level' => 3, 'langue' => 'fr'],
                        ['content' => 'I', 'level' => 3, 'langue' => 'fr'],
                    ];
                }
                foreach ($sampleContents as $sample) {
                    $pronunciationContent = new PronunciationContent();
                    $pronunciationContent->setContent($sample['content']);
                    $pronunciationContent->setLevel($sample['level']);
                    $pronunciationContent->setLangue($sample['langue']);
                    $this->entityManager->persist($pronunciationContent);
                }
                try {
                    $this->entityManager->flush();
                    $this->logger->info('Sample data inserted successfully', ['count' => count($sampleContents), 'level' => $level]);
                } catch (\Exception $e) {
                    $this->logger->error('Failed to insert sample data', [
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        // Get the child's current level
        $levels = $child->getLevels();
        $level = $levels->isEmpty() ? null : $levels->last();

        // Create a new level if none exists or if the current level is complete
        $contentSizeForLevel = count($this->pronunciationContentRepository->findBy(['level' => $level ? $level->getId() : 1, 'langue' => $language]));
        if (!$level || $level->getNbtries() >= $contentSizeForLevel) {
            $level = new Level();
            $maxId = $this->entityManager->createQuery('SELECT COALESCE(MAX(l.id), 0) FROM App\Entity\Level l WHERE l.childId = :childId')
                ->setParameter('childId', $child)
                ->getSingleScalarResult();
            $level->setId($maxId + 1);
            $game = $this->gameRepository->findOneBy([]) ?? (function () {
                $game = new Game();
                $game->setId(1);
                $game->setName('Jeu de Prononciation');
                $reflection = new \ReflectionClass($game);
                $property = $reflection->getProperty('levels');
                $property->setAccessible(true);
                $property->setValue($game, new ArrayCollection());
                $this->entityManager->persist($game);
                $this->entityManager->flush();
                return $game;
            })();
            $level->setGameId($game);
            $level->setChildId($child);
            $level->setScore(0);
            $level->setNbtries(0);
            $level->setTime(0);
            $child->addLevel($level);
            $this->entityManager->persist($level);
            try {
                $this->entityManager->flush();
                $this->logger->info('New level created', [
                    'level_id' => $level->getId(),
                    'childId' => $childId,
                ]);
            } catch (\Exception $e) {
                $this->logger->error('Failed to create level', [
                    'error' => $e->getMessage(),
                    'childId' => $childId,
                ]);
            }
        }

        $levelNumber = $level->getId(); // Levels start at 1, no subtraction
        $language = $child->getLanguage();

        $this->logger->info('Fetching contents for level and language', [
            'level' => $levelNumber,
            'language' => $language,
            'childId' => $childId,
        ]);

        // Fetch all contents for debugging
        $allContents = $this->pronunciationContentRepository->findAll();
        $this->logger->info('All contents in pronunciation_content table', [
            'total' => count($allContents),
            'contents' => array_map(fn($c) => [
                'content' => $c->getContent(),
                'level' => $c->getLevel(),
                'langue' => $c->getLangue(),
                'id' => $c->getId(),
            ], $allContents),
        ]);

        // Fetch contents for the current level and language
        $contents = $this->pronunciationContentRepository->findBy([
            'level' => $levelNumber,
            'langue' => $language,
        ], ['id' => 'ASC']);

        $this->logger->info('Contents retrieved', [
            'count' => count($contents),
            'contents' => array_map(fn($c) => $c->getContent(), $contents),
            'level' => $levelNumber,
            'language' => $language,
        ]);

        $contentSize = count($contents);
        $contentIndex = $request->query->getInt('contentIndex', 0);
        $contentIndex = max(0, min($contentIndex, $contentSize - 1));
        $currentContent = $contentSize > 0 ? $contents[$contentIndex] : null;

        // Calculate average score
        $averageScore = $child->getLevels()->isEmpty() ? 0 : array_reduce(
            $child->getLevels()->toArray(),
            fn($carry, $level) => $carry + $level->getScore(),
            0
        ) / max(1, $child->getLevels()->count());

        return $this->render('jeu/accueil.html.twig', [
            'child' => $child,
            'contents' => $contents,
            'current_content' => $currentContent,
            'content_index' => $contentIndex,
            'content_size' => $contentSize,
            'levels' => $child->getLevels(),
            'average_score' => round($averageScore, 2),
            'level_number' => $levelNumber,
            'error' => $contentSize === 0 ? 'No content available for this level.' : null,
        ]);
    }

    #[Route('/verifier', name: 'verifier_pronunciation', methods: ['POST'])]
    public function verifierPrononciation(Request $request): JsonResponse
    {
        // Retrieve form data
        $expectedText = $request->request->get('expectedText', 'default_expected');
        $childId = (int) $request->request->get('childId', 1);
        $contentId = (int) $request->request->get('contentId', 1);
        $audioFile = $request->files->get('audio');

        $this->logger->info('Verification Inputs', [
            'expectedText' => $expectedText,
            'childId' => $childId,
            'contentId' => $contentId,
            'audioFile' => $audioFile ? $audioFile->getClientOriginalName() : 'none',
        ]);

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
                return new JsonResponse(['error' => 'Failed to create child'], 500);
            }
        }

        // Get or create level
        $level = $child->getLevels()->last();
        if (!$level) {
            $level = new Level();
            $maxId = $this->entityManager->createQuery('SELECT COALESCE(MAX(l.id), 0) FROM App\Entity\Level l WHERE l.childId = :childId')
                ->setParameter('childId', $child)
                ->getSingleScalarResult();
            $level->setId($maxId + 1);
            $game = $this->gameRepository->findOneBy([]) ?? new Game();
            $game->setId(1);
            $game->setName('Jeu de Prononciation');
            $level->setGameId($game);
            $level->setChildId($child);
            $level->setScore(0);
            $level->setNbtries(0);
            $level->setTime(0);
            $child->addLevel($level);
            $this->entityManager->persist($level);
            try {
                $this->entityManager->flush();
                $this->logger->info('New level created', ['level_id' => $level->getId(), 'childId' => $childId]);
            } catch (\Exception $e) {
                $this->logger->error('Failed to create level', ['error' => $e->getMessage()]);
                return new JsonResponse(['error' => 'Failed to create level'], 500);
            }
        }

        // Placeholder for audio processing (simulating correct pronunciation)
        $isCorrect = true; // TODO: Implement actual audio-to-text comparison
        $points = $isCorrect ? 3 : 1;

        // Update level
        $level->setNbtries($level->getNbtries() + 1);
        $level->setScore($level->getScore() + $points);

        try {
            $this->entityManager->persist($child);
            $this->entityManager->persist($level);
            $this->entityManager->flush();
            $this->logger->info('Level Updated Successfully', [
                'score' => $level->getScore(),
                'nbtries' => $level->getNbtries(),
                'childId' => $childId,
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to Update Level', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return new JsonResponse(['error' => 'Failed to update level'], 500);
        }

        // Check if level is complete
        $contentSize = count($this->pronunciationContentRepository->findBy(['level' => $level->getId(), 'langue' => 'fr']));
        $nextLevel = $level->getId();
        $next = false;
        if ($level->getNbtries() >= $contentSize) {
            $nextLevel++;
            $newLevel = new Level();
            $newLevel->setChildId($child);
            $newLevel->setId($nextLevel);
            $newLevel->setGameId($level->getGameId());
            $newLevel->setScore(0);
            $newLevel->setNbtries(0);
            $newLevel->setTime(0);
            $child->addLevel($newLevel);
            try {
                $this->entityManager->persist($newLevel);
                $this->entityManager->flush();
                $this->logger->info('New level created for next', ['level_id' => $nextLevel]);
                $next = true;
            } catch (\Exception $e) {
                $this->logger->error('Failed to create next level', ['error' => $e->getMessage()]);
            }
        }

        $message = $next
            ? "Excellent ! Vous avez obtenu {$level->getScore()} étoiles au niveau {$level->getId()} ! 🎉 Passons au niveau suivant !"
            : "Bon effort ! Vous avez obtenu {$points} étoiles ! 🌟";
        $response = [
            'result' => $isCorrect ? 'correct' : 'incorrect',
            'message' => $message,
            'gif' => $isCorrect ? '/images/gifs/sahyt.gif' : '/images/gifs/tryagain.gif',
            'son' => $isCorrect ? '/sons/correct1.mp3' : '/sons/incorrect1.mp3',
            'next' => $next,
            'debug' => [
                'expected' => $expectedText,
                'isCorrect' => $isCorrect,
                'points' => $points,
                'score' => $level->getScore(),
                'nbtries' => $level->getNbtries(),
                'audioReceived' => $audioFile ? $audioFile->getClientOriginalName() : 'none',
            ],
        ];

        $this->logger->info('Response Sent', $response);
        return new JsonResponse($response);
    }
}