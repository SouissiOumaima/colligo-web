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

        $gameState = $session->get('guessing_game_state');
        if (!$gameState) {
            $level = $child->getLevels()->last() ? $child->getLevels()->last()->getId() : 1;
            $this->logger->info('Loading words', ['language' => $language, 'level' => $level]);
            $lots = $this->loadWordLots($language, $level);
            if (empty($lots)) {
                $this->logger->error('No words found in jeudedevinette', ['language' => $language, 'level' => $level]);
                throw $this->createNotFoundException('No words available for language: ' . $language . ' and level: ' . $level);
            }
            shuffle($lots);
            $gameState = [
                'level' => $level,
                'score' => 0,
                'attempts' => 3,
                'incorrectAttempts' => 0,
                'successfulLots' => 0,
                'currentLotIndex' => 0,
                'lots' => $lots,
                'intruder' => $lots[0]['wrongword'] ?? '',
                'theme' => $lots[0]['theme'] ?? '',
                'startTime' => time(),
            ];
            $session->set('guessing_game_state', $gameState);
        }

        $currentLot = [];
        if ($gameState['currentLotIndex'] < count($gameState['lots'])) {
            $lot = $gameState['lots'][$gameState['currentLotIndex']];
            $correctWords = array_filter(array_map('trim', explode(',', $lot['rightword'])), fn($word) => !empty($word));
            $currentLot = array_merge($correctWords, [$lot['wrongword']]);
            shuffle($currentLot);
            $this->logger->info('Current lot', [
                'lotIndex' => $gameState['currentLotIndex'],
                'words' => $currentLot,
                'theme' => $lot['theme'],
                'intruder' => $lot['wrongword']
            ]);
        } else {
            $this->logger->warning('No more lots available', [
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
            'theme' => $gameState['theme'] ?? 'Aucun thème',
            'word_text' => 'اختر الكلمة الدخيلة',
        ]);
    }

    #[Route('/child/{parentId}/{childId}/game/1/check', name: 'guessing_game_check', methods: ['POST'])]
    public function checkWord(int $parentId, int $childId, Request $request, SessionInterface $session): JsonResponse
    {
        $child = $this->childRepository->findOneBy(['parentId' => $parentId, 'childId' => $childId]);
        if (!$child) {
            return new JsonResponse(['error' => 'Child not found'], 404);
        }

        $data = json_decode($request->getContent(), true);
        $word = $data['word'] ?? '';
        $gameState = $session->get('guessing_game_state');

        if (!$gameState || $gameState['currentLotIndex'] >= count($gameState['lots'])) {
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
                $gameState['intruder'] = $gameState['lots'][0]['wrongword'] ?? '';
                $gameState['theme'] = $gameState['lots'][0]['theme'] ?? '';
                $gameState['startTime'] = time();
            }
        } else {
            $gameState['attempts']--;
            $gameState['incorrectAttempts']++;
            $response['isCorrect'] = false;
        }

        $response['score'] = $gameState['score'];
        $this->updateLevel($child, $gameState);
        $session->set('guessing_game_state', $gameState);

        return new JsonResponse($response);
    }

    #[Route('/child/{parentId}/{childId}/game/1/next', name: 'guessing_game_next_lot', methods: ['POST'])]
    public function nextLot(int $parentId, int $childId, SessionInterface $session): JsonResponse
    {
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
        }

        $lot = $gameState['lots'][$gameState['currentLotIndex']];
        $correctWords = array_filter(array_map('trim', explode(',', $lot['rightword'])), fn($word) => !empty($word));
        $currentLot = array_merge($correctWords, [$lot['wrongword']]);
        shuffle($currentLot);
        $gameState['intruder'] = $lot['wrongword'];
        $gameState['theme'] = $lot['theme'];

        $session->set('guessing_game_state', $gameState);

        return new JsonResponse([
            'score' => $gameState['score'],
            'theme' => $lot['theme'],
            'currentLot' => $currentLot,
        ]);
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
            'lots' => $lots
        ]);

        return $lots;
    }

    private function updateLevel(Child $child, array $gameState): void
    {
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
            'français', 'fr', 'francais' => 'Français', // Correspond à la base
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