<?php

namespace App\Controller;

use App\Entity\Child;
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
    public const SENTENCES_PER_LEVEL = 10;
    private LoggerInterface $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    #[Route('/dragdrop/{childId}', name: 'app_dragdrop_game')]
    public function index(
        int $childId,
        ChildRepository $childRepository,
        DragdropGameService $gameService,
        SessionInterface $session,
        DragdropRepository $dragdropRepository
    ): Response {
        $this->logger->info('Attempting to load dragdrop game', ['childId' => $childId]);

        $allChildren = $childRepository->findAll();
        $childIds = array_map(fn($child) => $child->getChildId(), $allChildren);
        $this->logger->debug('Available child IDs in database', ['childIds' => $childIds]);

        $child = $childRepository->find($childId);
        if (!$child) {
            $this->logger->error('Child not found', ['childId' => $childId, 'availableChildIds' => $childIds]);
            $this->addFlash('error', "L'enfant avec l'ID {$childId} n'existe pas. IDs disponibles : " . implode(', ', $childIds) . ". Veuillez sélectionner un enfant valide.");
            return $this->redirectToRoute('pre_dashboard');
        }

        $this->logger->debug('Child details', [
            'child_id' => $childId,
            'language' => $child->getLanguage(),
        ]);

        try {
            $testResult = $dragdropRepository->testConnection();
            if (empty($testResult)) {
                throw new \Exception('No data found in dragdrop table.');
            }
            $this->logger->debug('Database connection test', ['result' => $testResult]);
        } catch (\Exception $e) {
            $this->logger->error('Database connection failed', ['error' => $e->getMessage()]);
            return $this->render('Dragdrop/dragdrop_game.html.twig', [
                'child' => $child,
                'shuffledWords' => null,
                'arabicTranslation' => 'لا يمكن الاتصال بقاعدة البيانات.',
                'level' => 1,
                'score' => 0,
                'originalPhrase' => null,
            ]);
        }

        $session->set('game_state', [
            'score' => 0,
            'correctSentencesCount' => 0,
            'correctSentencesInCurrentLevel' => 0,
            'failedAttempts' => 0,
            'usedSentences' => [],
            'levelStartTime' => time(),
            'unlockedLevel' => 1,
            'attemptCount' => 0,
            'totalTries' => 0,
        ]);

        $gameService->setLanguage($child->getLanguage());
        $gameService->setLevel(1);

        try {
            $gameService->loadRandomSentence($session->get('game_state')['usedSentences']);
        } catch (\Exception $e) {
            $this->logger->error('Failed to load random sentence', [
                'error' => $e->getMessage(),
                'language' => $child->getLanguage(),
                'level' => 1,
            ]);
            return $this->render('Dragdrop/dragdrop_game.html.twig', [
                'child' => $child,
                'shuffledWords' => null,
                'arabicTranslation' => 'لا توجد جمل متاحة لهذه اللغة والمستوى.',
                'level' => 1,
                'score' => 0,
                'originalPhrase' => null,
            ]);
        }

        $shuffledWords = $gameService->getShuffledWords();
        $originalPhrase = $gameService->getOriginalPhrase();
        $this->logger->debug('Shuffled words and original phrase before rendering', [
            'shuffledWords' => $shuffledWords,
            'originalPhrase' => $originalPhrase,
            'arabicTranslation' => $gameService->getArabicTranslation(),
        ]);

        if ($shuffledWords === null) {
            $this->logger->warning('No shuffled words to display', [
                'correctSentence' => $gameService->getCorrectSentence() ? $gameService->getCorrectSentence()->getPhrase() : 'No sentence loaded',
            ]);
            return $this->render('Dragdrop/dragdrop_game.html.twig', [
                'child' => $child,
                'shuffledWords' => null,
                'arabicTranslation' => 'لا توجد جمل لعرضها.',
                'level' => 1,
                'score' => 0,
                'originalPhrase' => null,
            ]);
        }

        return $this->render('Dragdrop/dragdrop_game.html.twig', [
            'child' => $child,
            'shuffledWords' => $shuffledWords,
            'arabicTranslation' => $gameService->getArabicTranslation(),
            'level' => $gameService->getLevel(),
            'score' => 0,
            'originalPhrase' => $originalPhrase,
        ]);
    }

    #[Route('/dragdrop/check-sentence/{childId}', name: 'app_dragdrop_check_sentence', methods: ['POST'])]
    public function checkSentence(
        int $childId,
        Request $request,
        DragdropGameService $gameService,
        SessionInterface $session,
        EntityManagerInterface $entityManager,
        LevelRepository $levelRepository,
        ChildRepository $childRepository
    ): JsonResponse {
        $child = $childRepository->find($childId);
        if (!$child) {
            $this->logger->error('Child not found during checkSentence', ['childId' => $childId]);
            return new JsonResponse(['error' => 'Child not found.'], 404);
        }

        $state = $session->get('game_state');
        $userSentence = $request->request->get('sentence');
        $attemptCount = $state['attemptCount'];
        $state['totalTries']++;

        $isCorrect = $gameService->isCorrect($userSentence);
        $state['attemptCount'] = $isCorrect ? 0 : $attemptCount + 1;

        if ($isCorrect) {
            $points = match ($attemptCount) {
                0 => 5,
                1 => 3,
                2 => 1,
                default => 0,
            };
            $state['score'] += $points;
            $state['correctSentencesCount']++;
            $state['correctSentencesInCurrentLevel']++;
            $state['failedAttempts'] = 0;
            $currentSentence = $gameService->getCorrectSentence();
            if ($currentSentence) {
                $state['usedSentences'][] = $currentSentence->getId();
            }

            if ($state['correctSentencesInCurrentLevel'] >= self::SENTENCES_PER_LEVEL) {
                $currentLevel = $gameService->getLevel();
                $timeElapsed = time() - $state['levelStartTime'];
                $game = $this->getOrAddGame($entityManager, 'drag and drop');

                $existingLevel = $levelRepository->findOneBy([
                    'childId' => $child->getChildId(),
                    'gameId' => $game->getId(),
                ]);

                if ($existingLevel) {
                    $existingLevel->setScore($state['score']);
                    $existingLevel->setNbtries($state['totalTries']);
                    $existingLevel->setTime($timeElapsed);
                    $entityManager->persist($existingLevel);
                } else {
                    $level = new Level();
                    // Removed setId(null) - Doctrine will auto-generate the ID
                    $level->setChildId($child);
                    $level->setGameId($game);
                    $level->setScore($state['score']);
                    $level->setNbtries($state['totalTries']);
                    $level->setTime($timeElapsed);
                    $entityManager->persist($level);
                }
                $entityManager->flush();

                if ($currentLevel < self::MAX_LEVEL) {
                    $state['unlockedLevel'] = max($state['unlockedLevel'], $currentLevel + 1);
                    $gameService->setLevel($currentLevel + 1);
                    $state['correctSentencesInCurrentLevel'] = 0;
                    $state['usedSentences'] = [];
                    $state['totalTries'] = 0;
                    $state['levelStartTime'] = time();
                    try {
                        $gameService->loadRandomSentence($state['usedSentences']);
                    } catch (\Exception $e) {
                        return new JsonResponse([
                            'error' => sprintf('No more sentences available for language "%s" and level %d.', $child->getLanguage(), $currentLevel + 1),
                            'gameCompleted' => true,
                        ], 400);
                    }
                }
            }
        } else {
            $state['failedAttempts']++;
            if ($state['failedAttempts'] >= 6) {
                error_log('Email sent to parents: Child has failed more than 6 times consecutively.');
                $state['failedAttempts'] = 0;
            }
        }

        $session->set('game_state', $state);

        return new JsonResponse([
            'isCorrect' => $isCorrect,
            'score' => $state['score'],
            'attemptCount' => $state['attemptCount'],
            'progress' => $state['correctSentencesInCurrentLevel'] / self::SENTENCES_PER_LEVEL,
            'level' => $gameService->getLevel(),
            'gameCompleted' => $state['correctSentencesInCurrentLevel'] >= self::SENTENCES_PER_LEVEL && $gameService->getLevel() >= self::MAX_LEVEL,
        ]);
    }

    #[Route('/dragdrop/next-sentence/{childId}', name: 'app_dragdrop_next_sentence', methods: ['POST'])]
    public function nextSentence(
        int $childId,
        DragdropGameService $gameService,
        SessionInterface $session,
        ChildRepository $childRepository
    ): JsonResponse {
        $child = $childRepository->find($childId);
        if (!$child) {
            $this->logger->error('Child not found during nextSentence', ['childId' => $childId]);
            return new JsonResponse(['error' => 'Child not found.'], 404);
        }

        $state = $session->get('game_state');
        $usedSentences = $state['usedSentences'];
        $currentLevel = $gameService->getLevel();

        if ($state['correctSentencesInCurrentLevel'] >= self::SENTENCES_PER_LEVEL) {
            if ($currentLevel >= self::MAX_LEVEL) {
                return new JsonResponse(['error' => 'Game completed, no more levels available'], 400);
            }
            $state['correctSentencesInCurrentLevel'] = 0;
            $state['usedSentences'] = [];
            $state['levelStartTime'] = time();
            $gameService->setLevel($currentLevel + 1);
            $usedSentences = [];
            $this->logger->info('Advancing to next level', ['newLevel' => $gameService->getLevel()]);
        }

        $availableCount = $gameService->getAvailableSentenceCount();
        if ($availableCount <= count($usedSentences)) {
            $this->logger->warning('No more sentences available after level check', [
                'level' => $gameService->getLevel(),
                'availableCount' => $availableCount,
                'usedSentencesCount' => count($usedSentences),
            ]);
            return new JsonResponse(['error' => 'No more sentences available for this level'], 400);
        }

        try {
            $gameService->loadRandomSentence($usedSentences);
        } catch (\Exception $e) {
            $this->logger->error('Failed to load next sentence', [
                'error' => $e->getMessage(),
                'level' => $gameService->getLevel(),
                'language' => $child->getLanguage(),
            ]);
            return new JsonResponse(['error' => 'No more sentences available: ' . $e->getMessage()], 400);
        }

        $state['usedSentences'] = array_merge($usedSentences, [$gameService->getCorrectSentence()->getId()]);
        $state['attemptCount'] = 0;
        $session->set('game_state', $state);

        return new JsonResponse([
            'shuffledWords' => $gameService->getShuffledWords(),
            'arabicTranslation' => $gameService->getArabicTranslation(),
            'originalPhrase' => $gameService->getOriginalPhrase(),
            'level' => $gameService->getLevel(),
        ]);
    }

    private function getOrAddGame(EntityManagerInterface $entityManager, string $gameName): Game
    {
        $connection = $entityManager->getConnection();
        $game = $connection->fetchAssociative('SELECT id FROM game WHERE name = :name', ['name' => $gameName]);

        if ($game) {
            $gameEntity = $entityManager->getRepository(Game::class)->find($game['id']);
            if ($gameEntity) {
                return $gameEntity;
            }
        }

        $gameEntity = new Game();
        $gameEntity->setName($gameName);
        $entityManager->persist($gameEntity);
        $entityManager->flush();

        return $gameEntity;
    }
}