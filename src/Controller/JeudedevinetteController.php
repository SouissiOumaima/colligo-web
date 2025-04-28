<?php

namespace App\Controller;

use App\Entity\Child;
use App\Entity\Level;
use App\Repository\ChildRepository;
use App\Repository\JeudedevinetteRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Annotation\Route;

class JeudedevinetteController extends AbstractController
{
    private ChildRepository $childRepository;
    private JeudedevinetteRepository $JeudedevinetteRepository;
    private EntityManagerInterface $entityManager;
    private LoggerInterface $logger;

    public function __construct(
        ChildRepository $childRepository,
        JeudedevinetteRepository $JeudedevinetteRepository,
        EntityManagerInterface $entityManager,
        LoggerInterface $logger
    ) {
        $this->childRepository = $childRepository;
        $this->JeudedevinetteRepository = $JeudedevinetteRepository;
        $this->entityManager = $entityManager;
        $this->logger = $logger;
    }

    #[Route('/Jeudedevinette/{id}', name: 'Jeudedevinette_show', methods: ['GET'])]
    public function show(int $id): Response
    {
        $Jeudedevinette = $this->JeudedevinetteRepository->find($id);

        if (!$Jeudedevinette) {
            throw $this->createNotFoundException('Jeudedevinette not found');
        }

        return $this->render('Jeudedevinette/show.html.twig', [
            'Jeudedevinette' => $Jeudedevinette,
        ]);
    }

    #[Route('/child/{parentId}/{childId}/game/{gameIndex}/play', name: 'child_game_play', methods: ['GET'], requirements: ['gameIndex' => '\d+'])]
    public function playGuessingGame(int $parentId, int $childId, int $gameIndex, SessionInterface $session): Response
    {
        try {
            if ($gameIndex !== 1) {
                $this->addFlash('error', 'Invalid game index for play: ' . $gameIndex);
                return $this->redirectToRoute('child_dashboard', ['parentId' => $parentId, 'childId' => $childId]);
            }

            $child = $this->childRepository->findOneBy(['parentId' => $parentId, 'childId' => $childId]);
            if (!$child) {
                throw $this->createNotFoundException('Child not found');
            }

            $rawLanguage = $child->getLanguage();
            $language = $this->normalizeLanguageForDb($rawLanguage);
            $this->logger->info('Child language', [
                'childId' => $childId,
                'rawLanguage' => $rawLanguage,
                'normalizedLanguage' => $language
            ]);

            // Récupérer le dernier niveau de l'enfant depuis la table level
            $lastLevel = $child->getLevels()->last() ? $child->getLevels()->last()->getId() : 1;

            // Initialize session
            $session->start();
            $gameState = $session->get('guessing_game_state', []);

            // Vérifier si gameState est vide ou si le niveau dans gameState est différent du dernier niveau
            if (empty($gameState) || (isset($gameState['level']) && $gameState['level'] !== $lastLevel)) {
                $this->logger->info('Initializing or re-syncing game state', [
                    'language' => $language,
                    'lastLevelFromDb' => $lastLevel,
                    'gameStateLevel' => $gameState['level'] ?? 'N/A'
                ]);

                // Charger les lots pour le dernier niveau
                $lots = $this->loadWordLots($language, $lastLevel);
                if (empty($lots)) {
                    $this->logger->error('No words found in jeudedevinette', ['language' => $language, 'level' => $lastLevel]);
                    throw $this->createNotFoundException('No words available for language: ' . $language . ' and level: ' . $lastLevel);
                }

                // Mélanger les lots
                shuffle($lots);
                $themes = array_column($lots, 'theme');

                // Réinitialiser gameState avec les nouveaux lots et le dernier niveau
                $gameState = [
                    'level' => $lastLevel,
                    'score' => 0,
                    'attempts' => 3,
                    'incorrectAttempts' => 0,
                    'successfulLots' => 0,
                    'currentLotIndex' => 0,
                    'lots' => $lots,
                    'themes' => $themes,
                    'intruder' => $lots[0]['wrongword'] ?? '',
                    'theme' => $themes[0] ?? '',
                    'startTime' => time(),
                ];
                $session->set('guessing_game_state', $gameState);
                $this->logger->info('Game state initialized or re-synced', ['gameState' => $gameState]);
            }

            // Récupérer le thème à partir des lots pour le niveau actuel
            $currentTheme = $gameState['themes'][$gameState['currentLotIndex']] ?? 'Aucun thème';

            // Préparer les mots du lot actuel
            $currentLot = [];
            if (isset($gameState['lots'][$gameState['currentLotIndex']])) {
                $lot = $gameState['lots'][$gameState['currentLotIndex']];
                $correctWords = array_filter(array_map('trim', preg_split('/[,\/\-\s]+/', $lot['rightword'], -1, PREG_SPLIT_NO_EMPTY)), fn($word) => !empty($word));
                $currentLot = array_merge($correctWords, [$lot['wrongword']]);
                shuffle($currentLot);
            } else {
                $this->logger->warning('No more lots available for the current level', [
                    'currentLotIndex' => $gameState['currentLotIndex'],
                    'totalLots' => count($gameState['lots'])
                ]);
            }

            return $this->render('game/guessing_game.html.twig', [
                'child' => $child,
                'level' => $gameState['level'],
                'score' => $gameState['score'],
                'attempts' => $gameState['attempts'],
                'incorrectAttempts' => $gameState['incorrectAttempts'],
                'successfulLots' => $gameState['successfulLots'],
                'current_lot' => $currentLot,
                'theme' => $currentTheme,
                'word_text' => 'اختر الكلمة الدخيلة',
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Play game error', ['error' => $e->getMessage(), 'trace' => $e->getTrace()]);
            throw $this->createNotFoundException('Game initialization failed: ' . $e->getMessage());
        }
    }

    #[Route('/child/{parentId}/{childId}/game/1/check', name: 'guessing_game_check', methods: ['POST'])]
    public function checkWord(int $parentId, int $childId, Request $request, SessionInterface $session): JsonResponse
    {
        try {
            $this->logger->info('Check word request received', ['request' => $request->getContent()]);

            $child = $this->childRepository->findOneBy(['parentId' => $parentId, 'childId' => $childId]);
            if (!$child) {
                $this->logger->error('Child not found', ['parentId' => $parentId, 'childId' => $childId]);
                return new JsonResponse(['error' => 'Child not found'], 404);
            }

            $data = json_decode($request->getContent(), true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->logger->error('Invalid JSON', ['content' => $request->getContent()]);
                return new JsonResponse(['error' => 'Invalid JSON'], 400);
            }

            $word = $data['word'] ?? '';
            if (empty($word)) {
                $this->logger->error('Empty word received');
                return new JsonResponse(['error' => 'No word provided'], 400);
            }

            $gameState = $session->get('guessing_game_state');
            if (!$gameState) {
                $this->logger->error('Game state not found in session');
                return new JsonResponse(['error' => 'Game session expired'], 400);
            }

            if (!isset($gameState['lots'][$gameState['currentLotIndex']])) {
                $this->logger->error('Invalid lot index', ['currentLotIndex' => $gameState['currentLotIndex']]);
                return new JsonResponse(['error' => 'Invalid game state'], 400);
            }

            $lot = $gameState['lots'][$gameState['currentLotIndex']];
            $response = [];

            if ($word === $lot['wrongword']) {
                $attemptsUsed = 4 - $gameState['attempts'];
                $points = match ($attemptsUsed) {
                    1 => 5,
                    2 => 3,
                    3 => 1,
                    default => 0,
                };
                $gameState['score'] += $points;
                $gameState['successfulLots']++;
                $gameState['attempts'] = 3;
                $response['isCorrect'] = true;
                $response['successfulLots'] = $gameState['successfulLots'];

                // Update level in database immediately when correct
                $this->updateLevel($child, $gameState);

                if ($gameState['successfulLots'] >= 5) {
                    $newLevel = $gameState['level'] + 1;
                    $this->logger->info('Level completed, moving to next level', ['currentLevel' => $gameState['level'], 'newLevel' => $newLevel]);

                    $lots = $this->loadWordLots($this->normalizeLanguageForDb($child->getLanguage()), $newLevel);
                    if (empty($lots)) {
                        $this->logger->info('All levels completed', ['childId' => $childId, 'lastLevel' => $gameState['level']]);
                        // Rendre la page de fin de tous les niveaux
                        $response['allLevelsCompleted'] = true;
                        $response['redirectUrl'] = $this->generateUrl('all_levels_completed', [
                            'parentId' => $parentId,
                            'childId' => $childId
                        ]);
                        $session->set('guessing_game_state', null); // Réinitialiser l'état du jeu
                        return new JsonResponse($response);
                    }

                    shuffle($lots);
                    $themes = array_column($lots, 'theme');

                    $gameState = [
                        'level' => $newLevel,
                        'score' => 0,
                        'attempts' => 3,
                        'incorrectAttempts' => 0,
                        'successfulLots' => 0,
                        'currentLotIndex' => 0,
                        'lots' => $lots,
                        'themes' => $themes,
                        'intruder' => $lots[0]['wrongword'] ?? '',
                        'theme' => $themes[0] ?? '',
                        'startTime' => time(),
                    ];

                    // Persist the new level in the database
                    $this->updateLevel($child, $gameState);

                    $session->set('guessing_game_state', $gameState);

                    $response['levelUp'] = true;
                    $response['newLevel'] = $newLevel;
                }
            } else {
                $gameState['attempts']--;
                $gameState['incorrectAttempts']++;
                $response['isCorrect'] = false;

                // Update level in database even on incorrect attempt to track progress
                $this->updateLevel($child, $gameState);
            }

            $response['score'] = $gameState['score'];
            $response['attempts'] = $gameState['attempts'];
            $session->set('guessing_game_state', $gameState);

            $this->logger->info('Word check response', ['response' => $response]);
            return new JsonResponse($response);

        } catch (\Exception $e) {
            $this->logger->error('Check word error', ['error' => $e->getMessage(), 'trace' => $e->getTrace()]);
            return new JsonResponse(['error' => 'Server error: ' . $e->getMessage()], 500);
        }
    }

    #[Route('/child/{parentId}/{childId}/all-levels-completed', name: 'all_levels_completed', methods: ['GET'])]
    public function allLevelsCompleted(int $parentId, int $childId): Response
    {
        $child = $this->childRepository->findOneBy(['parentId' => $parentId, 'childId' => $childId]);
        if (!$child) {
            throw $this->createNotFoundException('Child not found');
        }

        return $this->render('level/all_levels_completed.html.twig', [
            'child' => $child,
        ]);
    }

    #[Route('/child/{parentId}/{childId}/game/1/next', name: 'guessing_game_next_lot', methods: ['POST'])]
    public function nextLot(int $parentId, int $childId, SessionInterface $session): JsonResponse
    {
        try {
            $child = $this->childRepository->findOneBy(['parentId' => $parentId, 'childId' => $childId]);
            if (!$child) {
                return new JsonResponse(['error' => 'Child not found'], 404);
            }

            $gameState = $session->get('guessing_game_state');
            if (!$gameState) {
                return new JsonResponse(['error' => 'Invalid game state'], 400);
            }

            $gameState['currentLotIndex']++;
            $gameState['attempts'] = 3;

            if ($gameState['currentLotIndex'] >= count($gameState['lots'])) {
                $gameState['currentLotIndex'] = 0;
                shuffle($gameState['lots']);
                $gameState['themes'] = array_column($gameState['lots'], 'theme');
            }

            $lot = $gameState['lots'][$gameState['currentLotIndex']];
            $correctWords = array_filter(array_map('trim', preg_split('/[,\/\-\s]+/', $lot['rightword'], -1, PREG_SPLIT_NO_EMPTY)), fn($word) => !empty($word));
            $currentLot = array_merge($correctWords, [$lot['wrongword']]);
            shuffle($currentLot);
            $gameState['intruder'] = $lot['wrongword'];
            $gameState['theme'] = $gameState['themes'][$gameState['currentLotIndex']];

            $session->set('guessing_game_state', $gameState);

            return new JsonResponse([
                'score' => $gameState['score'],
                'theme' => $gameState['themes'][$gameState['currentLotIndex']],
                'currentLot' => $currentLot,
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Next lot error', ['error' => $e->getMessage()]);
            return new JsonResponse(['error' => 'Server error'], 500);
        }
    }

    #[Route('/child/{parentId}/{childId}/game/1/complete', name: 'level_complete', methods: ['GET'])]
    public function levelComplete(int $parentId, int $childId, SessionInterface $session): Response
    {
        $child = $this->childRepository->findOneBy(['parentId' => $parentId, 'childId' => $childId]);
        if (!$child) {
            throw $this->createNotFoundException('Child not found');
        }

        $gameState = $session->get('guessing_game_state', ['level' => 1]);

        return $this->render('level/level_complete.html.twig', [
            'child' => $child,
            'level' => $gameState['level'],
        ]);
    }

    private function loadWordLots(string $language, int $level): array
    {
        try {
            $entities = $this->JeudedevinetteRepository->findByLanguageAndLevel($language, $level);
            $lots = [];

            foreach ($entities as $entity) {
                $lots[] = [
                    'rightword' => $entity->getRightword(),
                    'wrongword' => $entity->getWrongword(),
                    'theme' => $entity->getThème(),
                ];
            }

            $this->logger->info('Loaded words', [
                'language' => $language,
                'level' => $level,
                'count' => count($lots),
            ]);

            return $lots;
        } catch (\Exception $e) {
            $this->logger->error('Load words error', [
                'language' => $language,
                'level' => $level,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    private function resetGameState(SessionInterface $session, string $language, int $level): array
    {
        $this->logger->info('Resetting game state for new level', ['language' => $language, 'level' => $level]);

        $lots = $this->loadWordLots($language, $level);
        if (empty($lots)) {
            $this->logger->error('No words found in jeudedevinette during reset', ['language' => $language, 'level' => $level]);
            throw new \Exception('No words available for language: ' . $language . ' and level: ' . $level);
        }

        shuffle($lots);
        $themes = array_column($lots, 'theme');

        $gameState = [
            'level' => $level,
            'score' => 0,
            'attempts' => 3,
            'incorrectAttempts' => 0,
            'successfulLots' => 0,
            'currentLotIndex' => 0,
            'lots' => $lots,
            'themes' => $themes,
            'intruder' => $lots[0]['wrongword'] ?? '',
            'theme' => $themes[0] ?? '',
            'startTime' => time(),
        ];

        $session->set('guessing_game_state', $gameState);
        $this->logger->info('Game state reset', ['gameState' => $gameState]);

        return $gameState;
    }

    private function updateLevel(Child $child, array $gameState): void
    {
        try {
            $this->logger->info('Attempting to update level', [
                'childId' => $child->getChildId(),
                'gameState' => $gameState,
            ]);

            if (!isset($gameState['level'])) {
                $this->logger->error('Level not found in gameState', ['gameState' => $gameState]);
                return;
            }

            $game = $this->entityManager->getRepository(\App\Entity\Game::class)->findOneBy(['name' => 'guessing game']);
            if (!$game) {
                $this->logger->error('Game "guessing game" not found');
                return;
            }

            $level = $this->entityManager->getRepository(Level::class)->findOneBy([
                'id' => $gameState['level'],
                'childId' => $child,
                'gameId' => $game,
            ]);

            if (!$level) {
                $level = new Level();
                $level->setId($gameState['level']);
                $level->setChildId($child);
                $level->setGameId($game);
                $this->logger->info('Creating new level record', [
                    'childId' => $child->getChildId(),
                    'level' => $gameState['level'],
                    'gameId' => $game->getId(),
                ]);
            }

            $score = $gameState['score'] ?? 0;
            $time = isset($gameState['startTime']) ? (time() - $gameState['startTime']) / 60 : 0;
            $nbTries = $gameState['incorrectAttempts'] ?? 0;

            $this->logger->info('Values to be persisted', [
                'score' => $score,
                'time' => $time,
                'nbTries' => $nbTries,
            ]);

            $level->setScore($score);
            $level->setTime($time);
            $level->setNbtries($nbTries);

            $this->logger->info('Before persisting level', [
                'childId' => $child->getChildId(),
                'levelId' => $level->getId(),
                'score' => $level->getScore(),
                'time' => $level->getTime(),
                'nbTries' => $level->getNbtries(),
            ]);

            $this->entityManager->persist($level);
            $this->logger->info('Persisting level entity');
            $this->entityManager->flush();

            $this->logger->info('Level update successful', [
                'childId' => $child->getChildId(),
                'level' => $gameState['level'],
                'score' => $level->getScore(),
                'attempts' => $level->getNbtries(),
                'time' => $level->getTime(),
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Level update failed', [
                'childId' => $child->getChildId(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    private function mapLanguageCodeToName(string $code): string
    {
        return match (strtolower($code)) {
            'fr' => 'Français',
            'en' => 'Anglais',
            'de' => 'Allemand',
            'es' => 'Espagnol',
            'ar' => 'العربية',
            'français' => 'Français',
            'anglais' => 'Anglais',
            'allemand' => 'Allemand',
            'espagnol' => 'Espagnol',
            default => 'Français',
        };
    }

    private function normalizeLanguageForDb(string $language): string
    {
        $language = strtolower($language);
        $normalized = match ($language) {
            'français', 'fr', 'francais' => 'Français',
            'anglais', 'en' => 'Anglais',
            'allemand', 'de' => 'Allemand',
            'espagnol', 'es' => 'Espagnol',
            'العربية', 'ar' => 'ar',
            default => 'Français',
        };
        $this->logger->info('Language normalization', [
            'input' => $language,
            'output' => $normalized
        ]);
        return $normalized;
    }
}