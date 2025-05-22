<?php

namespace App\Controller;

use App\Entity\Child;
use App\Entity\Dragdrop;
use App\Entity\Level;
use App\Entity\Game;
use App\Repository\ChildRepository;
use App\Repository\LevelRepository;
use App\Service\DragdropGameService;
use App\Repository\DragdropRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Annotation\Route;
use Psr\Log\LoggerInterface;

class DragdropController extends AbstractController
{
    private const MAX_LEVEL = 3;
    private const SENTENCES_PER_LEVEL = 10;
    private LoggerInterface $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    #[Route('/dragdrop/{childId}', name: 'app_dragdrop_game', methods: ['GET'])]
    public function index(
        int $childId,
        ChildRepository $childRepository,
        DragdropGameService $gameService,
        SessionInterface $session,
        DragdropRepository $dragdropRepository,
        LoggerInterface $logger
    ): Response {
        $logger->info('Entering dragdrop game for childId: {childId}', ['childId' => $childId]);

        $child = $childRepository->findOneBy(['childId' => $childId]);
        if (!$child) {
            $logger->error('Child not found', ['childId' => $childId]);
            throw $this->createNotFoundException('Child not found.');
        }

        $logger->info('Child found', ['name' => $child->getName(), 'language' => $child->getLanguage()]);

        $session->set('game_state', [
            'score' => 0,
            'correctSentences' => 0,
            'failedAttempts' => 0,
            'usedSentences' => [],
            'currentLevel' => 1,
            'startTime' => time(),
            'totalTries' => 0,
        ]);

        $gameService->setLanguage($child->getLanguage());
        $gameService->setLevel(1);

        try {
            $logger->info('Attempting to load random sentence', ['language' => $child->getLanguage(), 'level' => 1]);
            $gameService->loadRandomSentence($session->get('game_state')['usedSentences']);
            $logger->info('Sentence loaded', ['phrase' => $gameService->getOriginalPhrase()]);
        } catch (\Exception $e) {
            $logger->error('Failed to load initial sentence', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return $this->render('Dragdrop/dragdrop_game.html.twig', [
                'child' => $child,
                'shuffledWords' => [],
                'arabicTranslation' => 'Error: ' . $e->getMessage(),
                'level' => 1,
                'score' => 0,
                'originalPhrase' => '',
            ]);
        }

        $shuffledWords = $gameService->getShuffledWords();
        $logger->info('Rendering template', ['shuffledWords' => $shuffledWords]);

        return $this->render('Dragdrop/dragdrop_game.html.twig', [
            'child' => $child,
            'shuffledWords' => $shuffledWords,
            'arabicTranslation' => $gameService->getArabicTranslation(),
            'level' => $gameService->getLevel(),
            'score' => 0,
            'originalPhrase' => $gameService->getOriginalPhrase(),
        ]);
    }
    #[Route('/dragdrop/check-sentence/{childId}', name: 'app_dragdrop_check_sentence', methods: ['POST'])]
    public function checkSentence(Request $request, EntityManagerInterface $em, int $childId): JsonResponse
    {
        $userSentence = trim($request->request->get('sentence'));

        // Récupérer la phrase correcte pour l’enfant et le niveau
        $child = $em->getRepository(Child::class)->find($childId);
        $level = $child->getLevel();

        $sentenceEntity = $em->getRepository(Dragdrop::class)->findOneBy([
            'level' => $level,
            'language' => $child->getLanguage()
        ]);

        if (!$sentenceEntity) {
            return new JsonResponse(['error' => 'Phrase introuvable']);
        }

        $correctSentence = trim($sentenceEntity->getSentence());

        // Normalisation simple (tu peux adapter)
        $normalizedUser = preg_replace('/\s+/', ' ', $userSentence);
        $normalizedCorrect = preg_replace('/\s+/', ' ', $correctSentence);

        $isCorrect = $normalizedUser === $normalizedCorrect;

        if ($isCorrect) {
            $child->setScore($child->getScore() + 10);
            $child->setLevel($level + 1);
            $em->flush();
        }

        return new JsonResponse([
            'isCorrect' => $isCorrect,
            'score' => $child->getScore(),
            'level' => $child->getLevel()
        ]);
    }




    #[Route('/dragdrop/next-sentence/{childId}', name: 'app_dragdrop_next_sentence', methods: ['POST'])]
    public function nextSentence(
        int $childId,
        DragdropGameService $gameService,
        SessionInterface $session
    ): JsonResponse {
        $state = $session->get('game_state', []);
        if (empty($state) || isset($state['gameCompleted'])) {
            return new JsonResponse(['error' => 'Game not in progress'], 400);
        }

        if (($state['correctSentences'] ?? 0) >= self::SENTENCES_PER_LEVEL || $gameService->getAvailableSentenceCount() <= count($state['usedSentences'])) {
            return new JsonResponse(['error' => 'No more sentences available'], 400);
        }

        try {
            $gameService->loadRandomSentence($state['usedSentences']);
            $state['usedSentences'][] = $gameService->getCorrectSentence()->getId();
            $session->set('game_state', $state);
        } catch (\Exception $e) {
            $this->logger->error('Failed to load next sentence', ['error' => $e->getMessage()]);
            return new JsonResponse(['error' => $e->getMessage()], 500);
        }

        return new JsonResponse([
            'shuffledWords' => $gameService->getShuffledWords(),
            'arabicTranslation' => $gameService->getArabicTranslation(),
            'originalPhrase' => $gameService->getOriginalPhrase(),
        ]);
    }
}