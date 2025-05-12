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
        private EntityManagerInterface $entityManager
    ) {
    }

    #[Route('/{childId}', name: 'jeu_accueil', requirements: ['childId' => '\d+'], methods: ['GET'])]
    public function accueil(Request $request, int $childId): Response
    {
        $child = $this->childRepository->find($childId);
        if (!$child) {
            throw $this->createNotFoundException('Enfant non trouvé');
        }

        if ($child->getLevels() === null) {
            $reflection = new \ReflectionClass($child);
            $property = $reflection->getProperty('levels');
            $property->setAccessible(true);
            $property->setValue($child, new ArrayCollection());
        }

        $levels = $child->getLevels();
        $level = $levels->isEmpty() ? null : $levels->first();
        if (!$level) {
            $level = new Level();
            $level->setChildId($child);
            $maxId = $this->entityManager->createQuery('SELECT MAX(l.id) FROM App\Entity\Level l')->getSingleScalarResult() ?? 0;
            $level->setId($maxId + 1);
            $game = $this->gameRepository->findOneBy([]) ?? (function () {
                $game = new Game();
                $game->setId(1);
                $game->setName('Pronunciation Game');
                $reflection = new \ReflectionClass($game);
                $property = $reflection->getProperty('levels');
                $property->setAccessible(true);
                $property->setValue($game, new ArrayCollection());
                $this->entityManager->persist($game);
                return $game;
            })();
            $level->setGameId($game);
            $level->setScore(0);
            $level->setNbtries(0);
            $level->setTime(0);
            $child->addLevel($level);
            $this->entityManager->persist($level);
            $this->entityManager->flush();
        }

        $levelNumber = 0;
        $contents = $this->pronunciationContentRepository->findBy([
            'level' => $levelNumber,
            'langue' => $child->getLanguage(),
        ], ['id' => 'ASC']);

        $contentSize = count($contents);
        $contentIndex = $request->query->getInt('contentIndex', 0);
        $contentIndex = max(0, min($contentIndex, $contentSize - 1));
        $currentContent = $contentSize > 0 ? $contents[$contentIndex] : null;

        if ($contentSize === 0) {
            return $this->render('jeu/accueil.html.twig', [
                'child' => $child,
                'contents' => [],
                'current_content' => null,
                'content_index' => 0,
                'content_size' => 0,
                'levels' => $child->getLevels(),
                'average_score' => 0,
            ]);
        }

        $averageScore = $child->getLevels()->isEmpty() ? 0 : array_reduce(
            $child->getLevels()->toArray(),
            fn($carry, $level) => $carry + ($level->getScore() ?? 0),
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
        ]);
    }

    #[Route('/verifier', name: 'verifier_pronunciation', methods: ['POST'])]
    public function verifierPrononciation(Request $request): JsonResponse
    {
        if ($request->files->has('audio')) {
            $audioFile = $request->files->get('audio');
            $spokenText = $this->simulateSpeechToText($audioFile, $request->request->get('expectedText'));
        } else {
            $spokenText = $request->request->get('spokenText');
        }

        $expectedText = $request->request->get('expectedText');
        $childId = (int) $request->request->get('childId');
        $contentId = (int) $request->request->get('contentId');

        if (!$spokenText || !$expectedText || !$childId || !$contentId) {
            return new JsonResponse(['error' => 'Données manquantes'], 400);
        }

        $child = $this->childRepository->find($childId);
        if (!$child) {
            return new JsonResponse(['error' => 'Enfant non trouvé'], 404);
        }

        $level = $child->getLevels()->first();
        if (!$level) {
            return new JsonResponse(['error' => 'Niveau non trouvé'], 404);
        }

        $spokenText = trim(strtolower($spokenText));
        $expectedText = trim(strtolower($expectedText));

        // Extremely lenient acceptance: any non-empty input is considered correct
        $isCorrect = !empty($spokenText) && $spokenText !== 'dummy';

        $maxAttempts = 2;
        $attempts = $level->getAttemptsPerContent() ?? 0;
        $attempts++;
        $level->setAttemptsPerContent($attempts);
        $level->setNbtries(($level->getNbtries() ?? 0) + 1);

        if ($isCorrect || $attempts >= $maxAttempts) {
            $points = $isCorrect ? 3 : 1;
            $level->setScore(($level->getScore() ?? 0) + $points);
            $level->setAttemptsPerContent(0);
            $this->entityManager->persist($level);
            $this->entityManager->flush();

            $message = $isCorrect
                ? "ممتاز! لقد حصلت على $points نجوم! (Super ! Tu as gagné $points étoiles !)"
                : "Bien essayé ! Tu as $points étoile pour ton effort ! On passe au suivant !";
            return new JsonResponse([
                'result' => 'correct',
                'message' => $message,
                'gif' => '/images/gifs/sahyt.gif',
                'son' => '/sons/correct.mp3',
                'next' => true,
                'debug' => [
                    'spoken' => $spokenText,
                    'expected' => $expectedText,
                    'isCorrect' => $isCorrect,
                    'points' => $points,
                    'attempts' => $attempts,
                ],
            ]);
        } else {
            $points = 1;
            $level->setScore(($level->getScore() ?? 0) + $points);
            $this->entityManager->persist($level);
            $this->entityManager->flush();
            $remainingAttempts = $maxAttempts - $attempts;
            return new JsonResponse([
                'result' => 'incorrect',
                'message' => "حاول مجدداً! (Essaie encore !) Tu as $points étoile pour ton effort ! Il te reste $remainingAttempts essai(s) !",
                'gif' => '/images/gifs/error.gif',
                'son' => '/sons/incorrect.mp3',
                'remaining_attempts' => $remainingAttempts,
                'debug' => [
                    'spoken' => $spokenText,
                    'expected' => $expectedText,
                    'points' => $points,
                    'attempts' => $attempts,
                ],
            ]);
        }
    }

    private function simulateSpeechToText($audioFile, ?string $expectedText = null): string
    {
        // If expectedText is provided, return it to match the AI's pronunciation
        if ($expectedText) {
            return trim(strtolower($expectedText));
        }
        // Otherwise, simulate a child's input with a random letter or word
        $possibleInputs = ['a', 'b', 'c', 'd', 'e', 'yes', 'no', 'hi', 'ay', 'bee', 'see'];
        return $possibleInputs[array_rand($possibleInputs)];
    }
}