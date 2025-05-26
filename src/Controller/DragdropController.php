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
use App\Entity\CompletedSentence;

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
        EntityManagerInterface $em,
        LoggerInterface $logger
    ): Response {
        $logger->info('Entering dragdrop game for childId: {childId}', ['childId' => $childId]);

        $child = $childRepository->findOneBy(['childId' => $childId]);
        if (!$child) {
            $logger->error('Child not found', ['childId' => $childId]);
            throw $this->createNotFoundException('Child not found.');
        }

        $logger->info('Child found', ['name' => $child->getName(), 'language' => $child->getLanguage()]);

        // Get or create the Game entity for dragdrop
        $game = $em->getRepository(Game::class)->find(5);
        if (!$game) {
            $game = new Game();
            $game->setId(5);
            $game->setName('drag and drop');
            $em->persist($game);
            $em->flush();
        }

        // Find the latest level progress for this child and game
        $level = $em->getRepository(Level::class)->findOneBy(
            ['childId' => $child, 'gameId' => $game],
            ['id' => 'DESC']
        );

        // Get all available sentences for level 1
        $availableSentences = $dragdropRepository->findBy([
            'niveau' => 1,
            'langue' => $child->getLanguage()
        ]);

        // Get completed sentences for this child
        $completedSentences = $em->getRepository(CompletedSentence::class)
            ->createQueryBuilder('cs')
            ->select('IDENTITY(cs.sentence)')
            ->join('cs.sentence', 's')
            ->where('cs.child = :child')
            ->andWhere('s.niveau = :level')
            ->setParameter('child', $child)
            ->setParameter('level', 1)
            ->getQuery()
            ->getArrayResult();

        $completedSentenceIds = array_map(function ($item) {
            return $item[1];
        }, $completedSentences);

        // Initialize game state with saved progress or default values
        if ($level) {
            $currentLevel = $level->getId();

            // Check if all level 1 sentences are completed
            if ($currentLevel === 1 && count($completedSentenceIds) >= count($availableSentences)) {
                $currentLevel = 2;

                // Create new level for level 2
                $newLevel = new Level();
                $newLevel->setChildId($child);
                $newLevel->setGameId($game);
                $newLevel->setId(2);
                $newLevel->setScore($level->getScore()); // Keep the accumulated score
                $newLevel->setNbtries(0);
                $newLevel->setTime(0);
                $em->persist($newLevel);
                $em->flush();

                $level = $newLevel;
            }

            $gameState = [
                'score' => $level->getScore(),
                'correctSentences' => count($completedSentenceIds),
                'failedAttempts' => 0,
                'usedSentences' => $completedSentenceIds,
                'currentLevel' => $currentLevel,
                'startTime' => time(),
                'totalTries' => $level->getNbtries(),
                'currentSentenceTries' => 0,
                'availableSentencesCount' => count($availableSentences),
            ];
        } else {
            $currentLevel = 1;
            $gameState = [
                'score' => 0,
                'correctSentences' => 0,
                'failedAttempts' => 0,
                'usedSentences' => [],
                'currentLevel' => 1,
                'startTime' => time(),
                'totalTries' => 0,
                'currentSentenceTries' => 0,
                'availableSentencesCount' => count($availableSentences),
            ];
        }

        $session->set('game_state', $gameState);
        $gameService->setLanguage($child->getLanguage());
        $gameService->setLevel($currentLevel);

        try {
            // Get unused sentences
            $unusedSentences = array_filter($availableSentences, function ($sentence) use ($completedSentenceIds) {
                return !in_array($sentence->getId(), $completedSentenceIds);
            });

            if (empty($unusedSentences)) {
                if ($currentLevel === 1) {
                    // Move to level 2
                    $gameState['currentLevel'] = 2;
                    $currentLevel = 2;
                    $gameState['correctSentences'] = 0;
                    $gameState['usedSentences'] = [];
                    $session->set('game_state', $gameState);

                    // Get sentences for level 2
                    $unusedSentences = $dragdropRepository->findBy([
                        'niveau' => 2,
                        'langue' => $child->getLanguage()
                    ]);
                } else {
                    throw new \Exception('No more sentences available for this level.');
                }
            }

            // Select a random unused sentence
            $randomSentence = $unusedSentences[array_rand($unusedSentences)];
            $gameService->setCurrentSentence($randomSentence);

            $originalPhrase = $randomSentence->getPhrase();
            $session->set('current_phrase', $originalPhrase);

            $logger->info('Sentence loaded', [
                'phrase' => $originalPhrase,
                'sentenceId' => $randomSentence->getId(),
                'level' => $currentLevel
            ]);

        } catch (\Exception $e) {
            $logger->error('Failed to load sentence', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return $this->render('Dragdrop/dragdrop_game.html.twig', [
                'child' => $child,
                'shuffledWords' => [],
                'arabicTranslation' => 'Error: ' . $e->getMessage(),
                'level' => $currentLevel,
                'score' => $gameState['score'],
                'originalPhrase' => '',
            ]);
        }

        return $this->render('Dragdrop/dragdrop_game.html.twig', [
            'child' => $child,
            'shuffledWords' => $gameService->getShuffledWords(),
            'arabicTranslation' => $gameService->getArabicTranslation(),
            'level' => $currentLevel,
            'score' => $gameState['score'],
            'originalPhrase' => $originalPhrase,
            'progress' => [
                'completed' => count($completedSentenceIds),
                'total' => count($availableSentences)
            ]
        ]);
    }

    #[Route('/dragdrop/check-sentence/{childId}', name: 'app_dragdrop_check_sentence', methods: ['POST'])]
    public function checkSentence(
        Request $request,
        EntityManagerInterface $em,
        int $childId,
        SessionInterface $session,
        DragdropGameService $gameService,
        DragdropRepository $dragdropRepository
    ): JsonResponse {
        try {
            $userSentence = trim($request->request->get('sentence'));
            $gameState = $session->get('game_state', []);

            $originalPhrase = $session->get('current_phrase');
            if (!$originalPhrase) {
                $this->logger->error('No current phrase found in session', ['childId' => $childId, 'userSentence' => $userSentence]);
                return new JsonResponse([
                    'error' => 'No current phrase found',
                    'debug' => ['userSentence' => $userSentence]
                ], 400);
            }

            $child = $em->getRepository(Child::class)->find($childId);
            if (!$child) {
                $this->logger->error('Child not found', ['childId' => $childId]);
                return new JsonResponse(['error' => 'Child not found'], 404);
            }

            $gameState['totalTries'] = ($gameState['totalTries'] ?? 0) + 1;
            $gameState['currentSentenceTries'] = ($gameState['currentSentenceTries'] ?? 0) + 1;
            $attempts = $gameState['currentSentenceTries'];

            $normalizedUser = trim(preg_replace('/\s+/', ' ', mb_strtolower($userSentence)));
            $normalizedCorrect = trim(preg_replace('/\s+/', ' ', mb_strtolower($originalPhrase)));

            $isCorrect = $normalizedUser === $normalizedCorrect;

            if ($isCorrect) {
                // Store previous score before updating
                $gameState['previousScore'] = $gameState['score'];

                $points = $attempts === 1 ? 5 : ($attempts === 2 ? 3 : 1);
                $gameState['score'] += $points;
                $gameState['correctSentences']++;

                // Find the current sentence in Dragdrop entity
                $currentSentence = $dragdropRepository->findOneBy(['phrase' => $originalPhrase]);
                if ($currentSentence) {
                    // Record the completed sentence
                    $completedSentence = new CompletedSentence();
                    $completedSentence->setChild($child);
                    $completedSentence->setSentence($currentSentence);
                    $em->persist($completedSentence);
                }

                $gameState['currentSentenceTries'] = 0;

                // Check if all sentences in current level are completed
                $availableSentences = $dragdropRepository->findBy([
                    'niveau' => $gameState['currentLevel'],
                    'langue' => $child->getLanguage()
                ]);

                $completedSentences = $em->getRepository(CompletedSentence::class)
                    ->createQueryBuilder('cs')
                    ->select('IDENTITY(cs.sentence)')
                    ->join('cs.sentence', 's')
                    ->where('cs.child = :child')
                    ->andWhere('s.niveau = :level')
                    ->setParameter('child', $child)
                    ->setParameter('level', $gameState['currentLevel'])
                    ->getQuery()
                    ->getArrayResult();

                if (count($completedSentences) >= count($availableSentences) && $gameState['currentLevel'] === 1) {
                    $this->saveLevelProgress($em, $child, $gameState);

                    $gameState['currentLevel'] = 2;
                    $gameState['correctSentences'] = 0;
                    $gameState['startTime'] = time();
                    $gameState['previousScore'] = $gameState['score'];

                    // Create new level for level 2
                    $game = $em->getRepository(Game::class)->find(5);
                    $newLevel = new Level();
                    $newLevel->setChildId($child);
                    $newLevel->setGameId($game);
                    $newLevel->setId(2);
                    $newLevel->setScore($gameState['score']);
                    $newLevel->setNbtries(0);
                    $newLevel->setTime(0);
                    $em->persist($newLevel);
                }

                $em->flush();
                $session->set('game_state', $gameState);
            } else {
                $gameState['failedAttempts'] = ($gameState['failedAttempts'] ?? 0) + 1;
                $session->set('game_state', $gameState);
            }

            $this->logger->info('Sentence Verification', [
                'childId' => $childId,
                'userSentence' => $userSentence,
                'originalPhrase' => $originalPhrase,
                'normalizedUser' => $normalizedUser,
                'normalizedCorrect' => $normalizedCorrect,
                'isCorrect' => $isCorrect,
                'attempts' => $attempts,
                'pointsEarned' => $points ?? 0,
                'score' => $gameState['score'],
                'level' => $gameState['currentLevel'],
                'totalTries' => $gameState['totalTries'],
            ]);

            return new JsonResponse([
                'success' => true,
                'isCorrect' => $isCorrect,
                'attempts' => $attempts,
                'score' => $gameState['score'],
                'level' => $gameState['currentLevel'],
                'levelCompleted' => $gameState['currentLevel'] === 2 && $gameState['correctSentences'] === 0,
                'debug' => [
                    'userSentence' => $userSentence,
                    'originalPhrase' => $originalPhrase,
                    'normalizedUser' => $normalizedUser,
                    'normalizedCorrect' => $normalizedCorrect,
                    'totalTries' => $gameState['totalTries'],
                    'currentSentenceTries' => $gameState['currentSentenceTries'],
                    'failedAttempts' => $gameState['failedAttempts'] ?? 0,
                ]
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Error in checkSentence', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'childId' => $childId,
            ]);
            return new JsonResponse([
                'error' => 'An error occurred',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    private function saveLevelProgress(EntityManagerInterface $em, Child $child, array $gameState): void
    {
        try {
            // Utiliser directement l'ID 5 pour le jeu 'drag and drop'
            $game = $em->getRepository(Game::class)->find(5);
            if (!$game) {
                // Si l'ID 5 n'existe pas (peu probable d'après votre message), créer l'entité avec l'ID spécifique
                $game = new Game();
                $game->setId(5);
                $game->setName('drag and drop');
                $em->persist($game);
                $em->flush();
                $this->logger->info('Created Game entity for drag and drop with id 5', ['gameId' => $game->getId()]);
            } else {
                $this->logger->info('Using existing Game entity with id 5', ['gameId' => $game->getId()]);
            }

            // Find existing level or create new one
            $level = $em->getRepository(Level::class)->findOneBy([
                'childId' => $child,
                'gameId' => $game,
                'id' => $gameState['currentLevel'],
            ]);

            if (!$level) {
                $level = new Level();
                $level->setChildId($child);
                $level->setGameId($game);
                $level->setId($gameState['currentLevel']);
                $level->setScore(0); // Initialize score for new level
                $level->setNbtries(0);
                $level->setTime(0);
            }

            // Update level data - accumulate score and tries
            $startTime = $gameState['startTime'] ?? time();
            $timeSpent = time() - $startTime;

            // Accumulate the score instead of overwriting
            $currentScore = $level->getScore();
            $level->setScore($currentScore + ($gameState['score'] - ($gameState['previousScore'] ?? 0)));
            $level->setNbtries($gameState['totalTries'] ?? 0);
            $level->setTime($level->getTime() + $timeSpent);

            $em->persist($level);
            $em->flush();

            $this->logger->info('Level progress saved', [
                'childId' => $child->getChildId(),
                'levelId' => $gameState['currentLevel'],
                'previousScore' => $currentScore,
                'newScore' => $level->getScore(),
                'nbtries' => $gameState['totalTries'] ?? 0,
                'time' => $timeSpent,
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to save level progress', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'childId' => $child->getChildId(),
            ]);
            throw $e;
        }
    }

    #[Route('/dragdrop/next-sentence/{childId}', name: 'app_dragdrop_next_sentence', methods: ['POST'])]
    public function nextSentence(
        int $childId,
        DragdropGameService $gameService,
        SessionInterface $session,
        ChildRepository $childRepository,
        EntityManagerInterface $em,
        DragdropRepository $dragdropRepository
    ): JsonResponse {
        try {
            $child = $childRepository->find($childId);
            if (!$child) {
                return new JsonResponse(['error' => 'Child not found'], 404);
            }

            $state = $session->get('game_state', []);
            if (empty($state)) {
                return new JsonResponse(['error' => 'Game state not found - please restart the game'], 400);
            }

            if (isset($state['gameCompleted'])) {
                return new JsonResponse(['error' => 'Game is already completed'], 400);
            }

            $gameService->setLanguage($child->getLanguage());
            $gameService->setLevel($state['currentLevel']);

            // Get available sentences for current level
            $availableSentences = $dragdropRepository->findBy([
                'niveau' => $state['currentLevel'],
                'langue' => $child->getLanguage()
            ]);

            if (empty($availableSentences)) {
                return new JsonResponse([
                    'error' => 'No sentences available for current level',
                    'level' => $state['currentLevel'],
                    'language' => $child->getLanguage()
                ], 404);
            }

            // Get completed sentences for current level
            $completedSentences = $em->getRepository(CompletedSentence::class)
                ->createQueryBuilder('cs')
                ->select('IDENTITY(cs.sentence)')
                ->join('cs.sentence', 's')
                ->where('cs.child = :child')
                ->andWhere('s.niveau = :level')
                ->setParameter('child', $child)
                ->setParameter('level', $state['currentLevel'])
                ->getQuery()
                ->getArrayResult();

            $completedSentenceIds = array_map(function ($item) {
                return $item[1];
            }, $completedSentences);

            // Filter out completed sentences
            $unusedSentences = array_filter($availableSentences, function ($sentence) use ($completedSentenceIds) {
                return !in_array($sentence->getId(), $completedSentenceIds);
            });

            if (empty($unusedSentences)) {
                // If no unused sentences in current level, try to move to next level
                if ($state['currentLevel'] === 1) {
                    $state['currentLevel'] = 2;
                    $gameService->setLevel(2);

                    // Get sentences for level 2
                    $unusedSentences = $dragdropRepository->findBy([
                        'niveau' => 2,
                        'langue' => $child->getLanguage()
                    ]);

                    if (empty($unusedSentences)) {
                        return new JsonResponse(['error' => 'No sentences available for level 2'], 404);
                    }
                } else {
                    return new JsonResponse(['error' => 'No more sentences available for this level'], 404);
                }
            }

            // Select a random unused sentence
            $randomSentence = $unusedSentences[array_rand($unusedSentences)];
            $gameService->setCurrentSentence($randomSentence);

            $originalPhrase = $randomSentence->getPhrase();
            $session->set('current_phrase', $originalPhrase);

            $state['currentSentenceTries'] = 0;
            $session->set('game_state', $state);

            $this->logger->info('New sentence loaded', [
                'childId' => $childId,
                'originalPhrase' => $originalPhrase,
                'level' => $state['currentLevel'],
                'language' => $child->getLanguage()
            ]);

            return new JsonResponse([
                'success' => true,
                'shuffledWords' => $gameService->getShuffledWords(),
                'arabicTranslation' => $gameService->getArabicTranslation(),
                'originalPhrase' => $originalPhrase,
                'currentLevel' => $state['currentLevel'],
                'score' => $state['score'],
                'progress' => [
                    'completed' => count($completedSentenceIds),
                    'total' => count($availableSentences)
                ]
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Error in nextSentence', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'childId' => $childId
            ]);

            return new JsonResponse([
                'error' => 'Failed to load next sentence',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}