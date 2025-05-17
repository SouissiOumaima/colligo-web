<?php

namespace App\Service;

use App\Entity\Child;
use App\Entity\Game;
use App\Entity\Level;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

/**
 * Service for managing the word game logic, including game state and gameplay mechanics.
 */
class GameService
{
    private EntityManagerInterface $em;
    private SessionInterface $session;
    private ?int $childId = null;
    private ?int $gameId = null;
    private ?string $selectedLanguage = null;
    private array $gameRules = [
        1 => ['stagesPerLevel' => 10, 'maxPointsPerStage' => 5],
        2 => ['stagesPerLevel' => 10, 'maxPointsPerStage' => 5],
        3 => ['stagesPerLevel' => 10, 'maxPointsPerStage' => 5],
        4 => ['stagesPerLevel' => 10, 'maxPointsPerStage' => 5],
        5 => ['stagesPerLevel' => 10, 'maxPointsPerStage' => 5],
    ];

    public function __construct(EntityManagerInterface $em, SessionInterface $session)
    {
        $this->em = $em;
        $this->session = $session;
        $this->initializeSession();
    }

    private function initializeSession(): void
    {
        if (!$this->session->has('game_state')) {
            $this->session->set('game_state', [
                'currentLevel' => 1,
                'currentStage' => 1,
                'currentLevelPoints' => 0,
                'totalTriesInLevel' => 1,
                'totalTimeInLevel' => 0,
                'currentImages' => [],
                'correctWord' => null,
                'correctImageUrl' => null,
                'startTime' => null,
                'highestLevelReached' => $this->getHighestLevelReached() ?? 1,
            ]);
        }
    }

    public function startGame(int $level): void
    {
        if ($this->childId === null || $this->gameId === null) {
            throw new \Exception('Child ID and Game ID must be set before starting the game.');
        }

        $this->selectedLanguage = $this->fetchChildLanguage();
        $state = [
            'currentLevel' => min(max(1, $level), 3),
            'currentStage' => 1,
            'currentLevelPoints' => 0,
            'totalTriesInLevel' => 1,
            'totalTimeInLevel' => 0,
            'currentImages' => [],
            'correctWord' => null,
            'correctImageUrl' => null,
            'startTime' => microtime(true),
            'highestLevelReached' => $this->getHighestLevelReached() ?? 1,
        ];
        $this->session->set('game_state', $state);
        $this->loadNextRound();
    }

    public function loadNextRound(): void
{
    if ($this->selectedLanguage === null) {
        throw new \Exception('Selected language must be set before loading the next round.');
    }

    $state = $this->session->get('game_state');
    $numberOfImages = 2 + ($state['currentLevel'] - 1);

    $conn = $this->em->getConnection();
    $sql = 'SELECT * FROM images ORDER BY RAND() LIMIT ?';
    $stmt = $conn->prepare($sql);
    $stmt->bindValue(1, $numberOfImages, \PDO::PARAM_INT);
    $result = $stmt->executeQuery();

    $images = [];
    foreach ($result->fetchAllAssociative() as $row) {
        $images[] = [
            'id' => $row['id'],
            'url' => $row['image_url'],
            'word' => $row['word'],
            'french_translation' => $row['french_translation'], // Add translation columns
            'spanish_translation' => $row['spanish_translation'],
            'german_translation' => $row['german_translation'],
        ];
    }

    if (count($images) < $numberOfImages) {
        throw new \Exception('غير كافٍ من الصور في قاعدة البيانات.');
    }

    $correctIndex = array_rand($images);
    $correctImage = $images[$correctIndex];
    $state['correctWord'] = $this->getTranslationFromRow($correctImage, $this->selectedLanguage);
    $state['correctImageUrl'] = $correctImage['url'];
    $state['currentImages'] = $images;
    $state['startTime'] = microtime(true);
    $this->session->set('game_state', $state);
}

    private function getTranslationFromRow(array $imageRow, string $language): string
    {
        return match ($language) {
            'French' => $imageRow['french_translation'] ?? $imageRow['word'],
            'Spanish' => $imageRow['spanish_translation'] ?? $imageRow['word'],
            'German' => $imageRow['german_translation'] ?? $imageRow['word'],
            default => $imageRow['word'],
        };
    }

    public function checkAnswer(string $selectedImageUrl): array
{
    if ($this->childId === null || $this->gameId === null) {
        throw new \Exception('Child ID and Game ID must be set before checking answers.');
    }

    $state = $this->session->get('game_state');
    $attemptTime = min((microtime(true) - $state['startTime']), 10);
    $state['totalTimeInLevel'] += $attemptTime;
    $isCorrect = $selectedImageUrl === $state['correctImageUrl'];

    if (!$isCorrect) {
        $state['totalTriesInLevel']++;
    }

    error_log("checkAnswer: childId={$this->childId}, gameId={$this->gameId}, level={$state['currentLevel']}, stage={$state['currentStage']}, isCorrect=$isCorrect, attemptTime=$attemptTime, tries={$state['totalTriesInLevel']}, stagesPerLevel=" . $this->getStagesPerLevel());

    if ($isCorrect) {
        $points = $this->calculatePoints($attemptTime * 1000);
        $state['currentLevelPoints'] += $points;
        // Update session state immediately to ensure points are saved
        $this->session->set('game_state', $state);

        // Save level completion if this is the last stage, after updating points
        if ($state['currentStage'] === $this->getStagesPerLevel()) {
            error_log("Level completed, saving: childId={$this->childId}, gameId={$this->gameId}, level={$state['currentLevel']}");
            $this->saveLevelCompletion();
            if ($state['currentLevel'] >= $state['highestLevelReached'] && $state['currentLevel'] < 3) {
                $state['highestLevelReached'] = $state['currentLevel'] + 1;
            }
        }
    }

    // Update session state again for any other changes (e.g., tries, highestLevelReached)
    $this->session->set('game_state', $state);

    return [
        'isCorrect' => $isCorrect,
        'currentStage' => $state['currentStage'],
        'currentLevel' => $state['currentLevel'],
        'points' => $state['currentLevelPoints'],
        'attemptTime' => $attemptTime,
    ];
}

public function proceedOrRetry(bool $isCorrect): bool
{
    if ($this->childId === null || $this->gameId === null) {
        throw new \Exception('Child ID and Game ID must be set before proceeding or retrying.');
    }

    // Ensure the language is set before proceeding
    if ($this->selectedLanguage === null) {
        $this->selectedLanguage = $this->fetchChildLanguage();
    }

    $state = $this->session->get('game_state');
    if (!$isCorrect) {
        $this->loadNextRound();
    } elseif ($state['currentStage'] < $this->getStagesPerLevel()) {
        $state['currentStage']++;
        $this->session->set('game_state', $state);
        $this->loadNextRound();
    } else {
        if ($state['currentLevel'] < 3) {
            $state['currentLevel']++;
            $state['currentStage'] = 1;
            $state['currentLevelPoints'] = 0;
            $state['totalTriesInLevel'] = 1;
            $state['totalTimeInLevel'] = 0;
            $this->session->set('game_state', $state);
            $this->loadNextRound();
        } else {
            // Game fully completed (Level 3, Stage 10), return true to redirect
            return true;
        }
    }
    return false;
}

    private function fetchChildLanguage(): string
    {
        if ($this->childId === null) {
            throw new \Exception('Child ID must be set before fetching child language.');
        }

        $child = $this->em->getRepository(Child::class)->find($this->childId);
        if (!$child) {
            throw new \Exception("Child with ID {$this->childId} not found in the database.");
        }

        $language = $child->getLanguage();
        if (!$language) {
            throw new \Exception("Language for child with ID {$this->childId} is not set in the database.");
        }

        return $language;
    }

    private function calculatePoints(float $elapsedTimeMillis): int
    {
        if ($elapsedTimeMillis <= 3000) {
            return 5;
        } elseif ($elapsedTimeMillis <= 6000) {
            return 3;
        } else {
            return 1;
        }
    }

    private function saveLevelCompletion(): void
    {
        if ($this->childId === null || $this->gameId === null) {
            throw new \Exception('Child ID and Game ID must be set before saving level completion.');
        }

        $state = $this->session->get('game_state');
        error_log("saveLevelCompletion: childId={$this->childId}, gameId={$this->gameId}, level={$state['currentLevel']}, score={$state['currentLevelPoints']}, tries={$state['totalTriesInLevel']}, time=" . (int)$state['totalTimeInLevel']);

        $child = $this->em->getRepository(Child::class)->find($this->childId);
        $game = $this->em->getRepository(Game::class)->find($this->gameId);

        if (!$child || !$game) {
            $error = "Child ID {$this->childId} or Game ID {$this->gameId} not found";
            error_log($error);
            throw new \Exception($error);
        }

        $level = $this->em->getRepository(Level::class)->findOneBy([
            'id' => $state['currentLevel'],
            'childId' => $child,
            'gameId' => $game,
        ]);

        if (!$level) {
            $level = new Level();
            $level->setId($state['currentLevel']);
            $level->setChildId($child);
            $level->setGameId($game);
            $level->setScore($state['currentLevelPoints']);
            $level->setNbtries($state['totalTriesInLevel']);
            $level->setTime((int)$state['totalTimeInLevel']);
        } else {
            $level->setScore(max($level->getScore(), $state['currentLevelPoints']));
            $level->setNbtries($state['totalTriesInLevel']);
            $level->setTime((int)$state['totalTimeInLevel']);
        }

        try {
            $this->em->persist($level);
            $this->em->flush();
            error_log("Level saved successfully: childId={$this->childId}, gameId={$this->gameId}, level={$state['currentLevel']}, score={$level->getScore()}, tries={$level->getNbtries()}, time={$level->getTime()}");
        } catch (\Exception $e) {
            error_log("Failed to save level: childId={$this->childId}, gameId={$this->gameId}, level={$state['currentLevel']}, error=" . $e->getMessage());
            throw new \Exception("Failed to save level: " . $e->getMessage());
        }

        $newHighest = max($state['highestLevelReached'], $state['currentLevel'] + 1);
        $state['highestLevelReached'] = min($newHighest, 3);
        $this->session->set('game_state', $state);
    }

    public function getHighestLevelReached(): int
    {
        if ($this->childId === null || $this->gameId === null) {
            return 1;
        }

        $conn = $this->em->getConnection();
        $sql = 'SELECT MAX(id) FROM level WHERE childId = ? AND gameId = ? AND score > 0';
        $stmt = $conn->prepare($sql);
        $stmt->bindValue(1, $this->childId);
        $stmt->bindValue(2, $this->gameId);
        $result = $stmt->executeQuery();

        $highestLevel = (int)$result->fetchOne();
        return max(1, min($highestLevel + 1, 3));
    }

    public function getStagesPerLevel(): int
    {
        if ($this->gameId === null) {
            return 10;
        }
        return $this->gameRules[$this->gameId]['stagesPerLevel'] ?? 10;
    }

    public function getCurrentImages(): array
    {
        return $this->session->get('game_state')['currentImages'] ?? [];
    }

    public function getCorrectWord(): ?string
    {
        return $this->session->get('game_state')['correctWord'] ?? null;
    }

    public function getCurrentLevel(): int
    {
        return $this->session->get('game_state')['currentLevel'] ?? 1;
    }

    public function getCurrentStage(): int
    {
        return $this->session->get('game_state')['currentStage'] ?? 1;
    }

    public function getCurrentLevelPoints(): int
    {
        return $this->session->get('game_state')['currentLevelPoints'] ?? 0;
    }

    public function setChildId(int $childId): void
    {
        error_log("Setting childId to $childId");
        $this->childId = $childId;
    }

    public function setGameId(int $gameId): void
    {
        error_log("Setting gameId to $gameId");
        $this->gameId = $gameId;
    }
}