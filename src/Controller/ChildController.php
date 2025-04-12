<?php

namespace App\Controller;

use App\Entity\Child;
use App\Entity\Level;
use App\Repository\ChildRepository;
use App\Repository\JeudedevinetteRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Annotation\Route;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class ChildController extends AbstractController
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

    #[Route('/child/{parentId}/{childId}', name: 'child_dashboard', methods: ['GET'])]
    public function dashboard(int $parentId, int $childId): Response
    {
        $child = $this->childRepository->findOneBy(['parentId' => $parentId, 'childId' => $childId]);
        if (!$child) {
            throw $this->createNotFoundException('Child not found');
        }

        $gameProgress = $this->childRepository->findGameProgress($childId);
        $score = array_reduce($gameProgress, fn($carry, $item) => $carry + $item['score'], 0);

        return $this->render('child/dashboard.html.twig', [
            'child' => $child,
            'username' => $child->getName(),
            'level' => $child->getLevels()->last() ? $child->getLevels()->last()->getId() : 1,
            'score' => $score,
            'language' => $this->mapLanguageCodeToName($child->getLanguage()),
            'gameProgress' => $gameProgress,
        ]);
    }

    #[Route('/child/{parentId}/{childId}/game/{gameIndex}', name: 'child_game', methods: ['GET'], requirements: ['gameIndex' => '\d+'])]
    public function showGameDetailView(int $parentId, int $childId, int $gameIndex, SessionInterface $session): Response
    {
        $child = $this->childRepository->findOneBy(['parentId' => $parentId, 'childId' => $childId]);
        if (!$child) {
            throw $this->createNotFoundException('Child not found');
        }

        if ($gameIndex === 1) {
            $level = $child->getLevels()->last() ? $child->getLevels()->last()->getId() : 1;
            return $this->render('game/main_guessing_game.html.twig', [
                'child' => $child,
                'level' => $level,
                'language' => $this->mapLanguageCodeToName($child->getLanguage()),
            ]);
        }

        $this->addFlash('error', 'Unknown game index: ' . $gameIndex);
        return $this->redirectToRoute('child_dashboard', ['parentId' => $parentId, 'childId' => $childId]);
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

            // Initialize session
            $session->start();
            $gameState = $session->get('guessing_game_state');

            if (!$gameState) {
                $level = $child->getLevels()->last() ? $child->getLevels()->last()->getId() : 1;
                $this->logger->info('Loading words', ['language' => $language, 'level' => $level]);

                $lots = $this->loadWordLots($language, $level);
                if (empty($lots)) {
                    $this->logger->error('No words found in jeudedevinette', ['language' => $language, 'level' => $level]);
                    throw $this->createNotFoundException('No words available for language: ' . $language . ' and level: ' . $level);
                }

                // Shuffle lots
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
                $this->logger->info('Game state initialized', ['gameState' => $gameState]);
            }

            // Prepare current lot words
            $currentLot = [];
            if (isset($gameState['lots'][$gameState['currentLotIndex']])) {
                $lot = $gameState['lots'][$gameState['currentLotIndex']];
                $correctWords = array_filter(array_map('trim', explode(',', $lot['rightword'])), fn($word) => !empty($word));
                $currentLot = array_merge($correctWords, [$lot['wrongword']]);
                shuffle($currentLot);
            }

            return $this->render('game/guessing_game.html.twig', [
                'child' => $child,
                'level' => $gameState['level'],
                'score' => $gameState['score'],
                'attempts' => $gameState['attempts'],
                'incorrectAttempts' => $gameState['incorrectAttempts'],
                'successfulLots' => $gameState['successfulLots'],
                'current_lot' => $currentLot,
                'theme' => $gameState['themes'][$gameState['currentLotIndex']] ?? 'Aucun thème',
                'word_text' => 'اختر الكلمة الدخيلة',
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Play game error', ['error' => $e->getMessage(), 'trace' => $e->getTrace()]);
            throw $this->createNotFoundException('Game initialization failed');
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

                if ($gameState['successfulLots'] >= 5) {
                    $this->updateLevel($child, $gameState);
                    $gameState['level']++;
                    $gameState['score'] = 0;
                    $gameState['successfulLots'] = 0;
                    $gameState['currentLotIndex'] = 0;
                    $gameState['lots'] = $this->loadWordLots($this->normalizeLanguageForDb($child->getLanguage()), $gameState['level']);
                    shuffle($gameState['lots']);
                    $gameState['themes'] = array_column($gameState['lots'], 'theme');
                    $gameState['intruder'] = $gameState['lots'][0]['wrongword'] ?? '';
                    $gameState['theme'] = $gameState['themes'][0] ?? '';
                    $gameState['startTime'] = time();
                }
            } else {
                $gameState['attempts']--;
                $gameState['incorrectAttempts']++;
                $response['isCorrect'] = false;
            }

            $response['score'] = $gameState['score'];
            $response['attempts'] = $gameState['attempts'];
            $this->updateLevel($child, $gameState);
            $session->set('guessing_game_state', $gameState);

            $this->logger->info('Word check response', ['response' => $response]);
            return new JsonResponse($response);

        } catch (\Exception $e) {
            $this->logger->error('Check word error', ['error' => $e->getMessage(), 'trace' => $e->getTrace()]);
            return new JsonResponse(['error' => 'Server error'], 500);
        }
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
            $correctWords = array_filter(array_map('trim', explode(',', $lot['rightword'])), fn($word) => !empty($word));
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

    private function updateLevel(Child $child, array $gameState): void
    {
        try {
            $game = $this->entityManager->getRepository(\App\Entity\Game::class)->findOneBy(['name' => 'guessing game']);
            if (!$game) {
                $this->logger->error('Game "guessing game" not found');
                return;
            }

            $level = $this->entityManager->getRepository(Level::class)->findOneBy([
                'child' => $child,
                'game' => $game,
                'id' => $gameState['level'],
            ]);

            if (!$level) {
                $level = new Level();
                $level->setId($gameState['level']);
                $level->setChild($child);
                $level->setGame($game);
            }

            $level->setScore($gameState['score']);
            $level->setTime((time() - $gameState['startTime']) / 60);
            $level->setNbTries($gameState['incorrectAttempts']);

            $this->entityManager->persist($level);
            $this->entityManager->flush();

            $this->logger->info('Level updated', [
                'childId' => $child->getChildId(),
                'level' => $gameState['level'],
                'score' => $gameState['score']
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Update level error', [
                'childId' => $child->getChildId(),
                'error' => $e->getMessage()
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