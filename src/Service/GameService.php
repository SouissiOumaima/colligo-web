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
    private const MAX_TRIES_PER_STAGE = 3;
    private ?\DateTime $levelStartTime = null;

    public function __construct(EntityManagerInterface $em, SessionInterface $session)
    {
        $this->em = $em;
        $this->session = $session;
    }

    private function initializeSession(): void
    {
        if ($this->childId === null) {
            throw new \Exception('Child ID must be set before initializing session.');
        }

        $sessionKey = "game_state_child_{$this->childId}";
        if (!$this->session->has($sessionKey)) {
            $this->levelStartTime = new \DateTime();
            $this->session->set($sessionKey, [
                'currentLevel' => 1,
                'currentStage' => 1,
                'currentLevelPoints' => 0,
                'totalTriesInLevel' => 0,
                'currentStageTries' => 0,
                'currentImages' => [],
                'correctWord' => null,
                'correctImageUrl' => null,
                'highestLevelReached' => $this->getHighestLevelReached() ?? 1,
                'levelStartTime' => $this->levelStartTime->getTimestamp(),
                'stagePoints' => [],
                'stageTries' => [],
            ]);
        }
    }

    public function getGameState(): array
{
    if ($this->childId === null) {
        throw new \Exception('Child ID must be set before retrieving game state.');
    }

    $sessionKey = "game_state_child_{$this->childId}";
    $state = $this->session->get($sessionKey, [
        'currentLevel' => 1,
        'currentStage' => 1,
        'currentLevelPoints' => 0,
        'totalTriesInLevel' => 1,
        'currentStageTries' => 0,
        'currentImages' => [],
        'correctWord' => null,
        'correctImageUrl' => null,
        'highestLevelReached' => $this->getHighestLevelReached() ?? 1,
        'levelStartTime' => (new \DateTime())->getTimestamp(),
        'stagePoints' => [],
        'stageTries' => [],
    ]);

    if (!isset($state['stagePoints']) || !is_array($state['stagePoints'])) {
        $state['stagePoints'] = [];
    }
    if (!isset($state['stageTries']) || !is_array($state['stageTries'])) {
        $state['stageTries'] = [];
    }

    error_log("getGameState: childId={$this->childId}, level={$state['currentLevel']}, stage={$state['currentStage']}, totalTriesInLevel={$state['totalTriesInLevel']}, fullState=" . json_encode($state));

    return $state;
}

    public function startGame(int $level): void
{
    if ($this->childId === null || $this->gameId === null) {
        throw new \Exception('Child ID and Game ID must be set before starting the game.');
    }

    $this->selectedLanguage = $this->fetchChildLanguage();
    $this->levelStartTime = new \DateTime();
    $this->initializeSession();
    $state = $this->getGameState();
    $stagesPerLevel = $this->getStagesPerLevel();
    $highestLevelReached = $this->getHighestLevelReached();

    error_log("startGame: childId={$this->childId}, requestedLevel=$level, currentLevel={$state['currentLevel']}, currentStage={$state['currentStage']}, highestLevelReached=$highestLevelReached, stagesPerLevel=$stagesPerLevel");

    // Only reset state for new levels or completed levels
    if ($level > $highestLevelReached || ($state['currentLevel'] === $level && $state['currentStage'] > $stagesPerLevel)) {
        error_log("Resetting state: childId={$this->childId}, newLevel=$level, newStage=1");
        $state = [
            'currentLevel' => min(max(1, $level), 3),
            'currentStage' => 1,
            'currentLevelPoints' => 0,
            'totalTriesInLevel' => 1,
            'currentStageTries' => 0,
            'currentImages' => [],
            'correctWord' => null,
            'correctImageUrl' => null,
            'highestLevelReached' => $highestLevelReached,
            'levelStartTime' => $this->levelStartTime->getTimestamp(),
            'stagePoints' => [],
            'stageTries' => [],
        ];
    } else {
        $state['currentStageTries'] = 0;
        $state['levelStartTime'] = $this->levelStartTime->getTimestamp();
        error_log("Resuming game: childId={$this->childId}, level={$state['currentLevel']}, stage={$state['currentStage']}, totalTriesInLevel={$state['totalTriesInLevel']}");
    }

    $this->session->set("game_state_child_{$this->childId}", $state);
    $this->session->save(); // Force session save
    $this->loadNextRound();
}

    public function loadNextRound(): void
    {
        if ($this->selectedLanguage === null) {
            throw new \Exception('Selected language must be set before loading the next round.');
        }

        $state = $this->getGameState();
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
                'french_translation' => $row['french_translation'],
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
        $this->session->set("game_state_child_{$this->childId}", $state);

        error_log("Loaded next round: childId={$this->childId}, level={$state['currentLevel']}, stage={$state['currentStage']}");
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

    public function checkAnswer(?string $selectedImageUrl): array
{
    if ($this->childId === null || $this->gameId === null) {
        throw new \Exception('Child ID and Game ID must be set before checking answers.');
    }

    $state = $this->getGameState();
    $attemptStartTime = new \DateTime();
    // Only increment currentStageTries if it's not the first attempt
    if ($state['currentStageTries'] === 0) {
        $state['currentStageTries'] = 1; // Start at 1 for display
    } else {
        $state['currentStageTries']++;
    }
    $isCorrect = $selectedImageUrl === $state['correctImageUrl'];

    // Increment totalTriesInLevel for every attempt
    $state['totalTriesInLevel']++;
    // Update stage-specific tries
    $stageTries = $state['stageTries'];
    $stageTries[$state['currentStage']] = ($stageTries[$state['currentStage']] ?? 0) + 1;
    $state['stageTries'] = $stageTries;

    error_log("checkAnswer: childId={$this->childId}, gameId={$this->gameId}, level={$state['currentLevel']}, stage={$state['currentStage']}, isCorrect=$isCorrect, currentStageTries={$state['currentStageTries']}, totalTriesInLevel={$state['totalTriesInLevel']}");

    $points = 0;
    $maxTriesReached = $state['currentStageTries'] >= self::MAX_TRIES_PER_STAGE;

    if ($isCorrect) {
        $attemptNumber = $state['currentStageTries'];
        $points = $this->calculatePoints($attemptNumber);
        $state['currentLevelPoints'] += $points;
        $state['stagePoints'][$state['currentStage']] = $points;
    } elseif ($maxTriesReached) {
        $points = 0;
        $state['stagePoints'][$state['currentStage']] = 0;
    }

    $attemptEndTime = new \DateTime();
    $attemptDuration = $attemptEndTime->getTimestamp() - $attemptStartTime->getTimestamp();
    $totalTime = ($state['levelStartTime'] ? (new \DateTime())->getTimestamp() - $state['levelStartTime'] : 0) + $attemptDuration;

    $state['currentStageTries'] = min($state['currentStageTries'], self::MAX_TRIES_PER_STAGE);
    $this->session->set("game_state_child_{$this->childId}", $state);

    $this->saveLevelProgress($totalTime);

    return [
        'isCorrect' => $isCorrect,
        'currentStage' => $state['currentStage'],
        'currentLevel' => $state['currentLevel'],
        'points' => $points,
        'currentStageTries' => $state['currentStageTries'],
        'maxTriesReached' => $maxTriesReached,
        'totalTriesInLevel' => $state['totalTriesInLevel'],
    ];
}

        public function proceedOrRetry(bool $isCorrect, bool $maxTriesReached): bool
{
    if ($this->childId === null || $this->gameId === null) {
        throw new \Exception('Child ID and Game ID must be set before proceeding or retrying.');
    }

    if ($this->selectedLanguage === null) {
        $this->selectedLanguage = $this->fetchChildLanguage();
    }

    $state = $this->getGameState();
    $stagesPerLevel = $this->getStagesPerLevel();
    error_log("proceedOrRetry: childId={$this->childId}, isCorrect=$isCorrect, maxTriesReached=$maxTriesReached, currentStage={$state['currentStage']}, stagesPerLevel=$stagesPerLevel, currentLevel={$state['currentLevel']}, currentStageTries={$state['currentStageTries']}, totalTriesInLevel={$state['totalTriesInLevel']}");

    if ($isCorrect || $maxTriesReached) {
        error_log("Proceeding: childId={$this->childId}, currentStage={$state['currentStage']} vs stagesPerLevel=$stagesPerLevel");
        if ($state['currentStage'] < $stagesPerLevel) {
            $state['currentStage']++;
            $state['currentStageTries'] = 0;
            $state['stageTries'][$state['currentStage']] = 0;
            $this->session->set("game_state_child_{$this->childId}", $state);
            $this->session->save(); // Force session save
            error_log("Advanced to stage: childId={$this->childId}, newStage={$state['currentStage']}, totalTriesInLevel={$state['totalTriesInLevel']}");
            $this->loadNextRound();
        } else {
            $this->saveLevelCompletion($state['levelStartTime'] ? (new \DateTime())->getTimestamp() - $state['levelStartTime'] : 0);
            if ($state['currentLevel'] < 3) {
                $state['currentLevel']++;
                $state['currentStage'] = 1;
                $state['currentLevelPoints'] = 0;
                $state['totalTriesInLevel'] = 1;
                $state['currentStageTries'] = 0;
                $state['levelStartTime'] = (new \DateTime())->getTimestamp();
                $state['stagePoints'] = [];
                $state['stageTries'] = [];
                $this->session->set("game_state_child_{$this->childId}", $state);
                $this->session->save(); // Force session save
                error_log("Advanced to level: childId={$this->childId}, newLevel={$state['currentLevel']}, stage=1");
                $this->loadNextRound();
            } else {
                $this->session->remove("game_state_child_{$this->childId}");
                error_log("Game completed: childId={$this->childId}");
                return true;
            }
        }
    } else {
        $state['currentStageTries'] = min($state['currentStageTries'], self::MAX_TRIES_PER_STAGE);
        $this->session->set("game_state_child_{$this->childId}", $state);
        $this->session->save(); // Force session save
        error_log("Retrying stage: childId={$this->childId}, stage={$state['currentStage']}");
        $this->loadNextRound();
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

    private function calculatePoints(int $attemptNumber): int
    {
        return match ($attemptNumber) {
            1 => 5,
            2 => 3,
            3 => 1,
            default => 0,
        };
    }

    private function saveLevelProgress(int $timeTaken): void
{
    if ($this->childId === null || $this->gameId === null) {
        throw new \Exception('Child ID and Game ID must be set before saving level progress.');
    }

    $state = $this->getGameState();
    error_log("saveLevelProgress: childId={$this->childId}, gameId={$this->gameId}, level={$state['currentLevel']}, score={$state['currentLevelPoints']}, tries={$state['totalTriesInLevel']}, time={$timeTaken}");

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

    $isNewLevel = !$level;
    if ($isNewLevel) {
        $level = new Level();
        $level->setId($state['currentLevel']);
        $level->setChildId($child);
        $level->setGameId($game);
        $level->setScore(0); // Initialize score for new level
        $level->setNbtries(0);
        $level->setTime(0);
    }

    // Accumulate existing time instead of overwriting
    $existingTime = $level->getTime() ?? 0;
    $level->setTime($existingTime + $timeTaken);

    // Update score and tries
    $currentScore = $isNewLevel ? 0 : $level->getScore();
    $level->setScore(max($currentScore, $state['currentLevelPoints']));
    $level->setNbtries($state['totalTriesInLevel']);

    try {
        $this->em->persist($level);
        $this->em->flush();
        error_log("Level progress saved: childId={$this->childId}, gameId={$this->gameId}, level={$state['currentLevel']}, score={$level->getScore()}, tries={$level->getNbtries()}, time={$level->getTime()}");

        // Reset levelStartTime in session after saving
        $state['levelStartTime'] = (new \DateTime())->getTimestamp();
        $this->session->set("game_state_child_{$this->childId}", $state);
    } catch (\Exception $e) {
        error_log("Failed to save level progress: childId={$this->childId}, gameId={$this->gameId}, level={$state['currentLevel']}, error=" . $e->getMessage());
        throw new \Exception("Failed to save level progress: " . $e->getMessage());
    }
}

    public function saveLevelCompletion(int $timeTaken): void
{
    if ($this->childId === null || $this->gameId === null) {
        throw new \Exception('Child ID and Game ID must be set before saving level completion.');
    }

    $state = $this->getGameState();
    error_log("saveLevelCompletion: childId={$this->childId}, gameId={$this->gameId}, level={$state['currentLevel']}, score={$state['currentLevelPoints']}, tries={$state['totalTriesInLevel']}, time={$timeTaken}");

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

    $isNewLevel = !$level;
    if ($isNewLevel) {
        $level = new Level();
        $level->setId($state['currentLevel']);
        $level->setChildId($child);
        $level->setGameId($game);
        $level->setScore(0); // Initialize score for new level
        $level->setNbtries(0);
        $level->setTime(0);
    }

    // Accumulate existing time instead of overwriting
    $existingTime = $level->getTime() ?? 0;
    $level->setTime($existingTime + $timeTaken);

    // Update score and tries
    $currentScore = $isNewLevel ? 0 : $level->getScore();
    $level->setScore(max($currentScore, $state['currentLevelPoints']));
    $level->setNbtries($state['totalTriesInLevel']);

    try {
        $this->em->persist($level);
        $this->em->flush();
        error_log("Level saved successfully: childId={$this->childId}, gameId={$this->gameId}, level={$state['currentLevel']}, score={$level->getScore()}, tries={$level->getNbtries()}, time={$level->getTime()}");

        // Reset levelStartTime in session after saving
        $state['levelStartTime'] = (new \DateTime())->getTimestamp();
        $this->session->set("game_state_child_{$this->childId}", $state);
    } catch (\Exception $e) {
        error_log("Failed to save level: childId={$this->childId}, gameId={$this->gameId}, level={$state['currentLevel']}, error=" . $e->getMessage());
        throw new \Exception("Failed to save level: " . $e->getMessage());
    }

    $newHighest = max($state['highestLevelReached'], $state['currentLevel'] + 1);
    $state['highestLevelReached'] = min($newHighest, 3);
    $this->session->set("game_state_child_{$this->childId}", $state);
}

    public function getHighestLevelReached(): int
{
    if ($this->childId === null || $this->gameId === null) {
        return 1;
    }

    $conn = $this->em->getConnection();
    $sql = 'SELECT MAX(id) FROM level WHERE childId = ? AND gameId = ? AND score >= ?';
    $stmt = $conn->prepare($sql);
    $stmt->bindValue(1, $this->childId);
    $stmt->bindValue(2, $this->gameId);
    $stmt->bindValue(3, 10); // Minimum score for completing 10 stages (e.g., 1 point per stage)
    $result = $stmt->executeQuery();

    $highestLevel = (int)$result->fetchOne();
    $highestLevelReached = max(1, min($highestLevel + 1, 3));
    error_log("getHighestLevelReached: childId={$this->childId}, gameId={$this->gameId}, highestLevelReached=$highestLevelReached");
    return $highestLevelReached;
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
        return $this->getGameState()['currentImages'] ?? [];
    }

    public function getCorrectWord(): ?string
    {
        return $this->getGameState()['correctWord'] ?? null;
    }

    public function getCurrentLevel(): int
    {
        return $this->getGameState()['currentLevel'] ?? 1;
    }

    public function getCurrentStage(): int
    {
        return $this->getGameState()['currentStage'] ?? 1;
    }

    public function getCurrentLevelPoints(): int
    {
        return $this->getGameState()['currentLevelPoints'] ?? 0;
    }

    public function getCurrentStageTries(): int
    {
        return $this->getGameState()['currentStageTries'] ?? 0;
    }

    public function getTotalTriesInLevel(): int
    {
        return $this->getGameState()['totalTriesInLevel'] ?? 0;
    }

    public function getMaxTriesPerStage(): int
    {
        return self::MAX_TRIES_PER_STAGE;
    }

    public function setChildId(int $childId): void
    {
        error_log("Setting childId to $childId");
        $this->childId = $childId;
        $this->initializeSession(); // Initialize session for the new child
    }

    public function setGameId(int $gameId): void
    {
        error_log("Setting gameId to $gameId");
        $this->gameId = $gameId;
    }

    public function getProgressDataForChart(): array
    {
        if ($this->childId === null || $this->gameId === null) {
            return [];
        }

        $conn = $this->em->getConnection();
        $sql = 'SELECT id AS level, score, nbtries AS tries, time 
                FROM level 
                WHERE childId = ? AND gameId = ? 
                ORDER BY id ASC';
        $stmt = $conn->prepare($sql);
        $stmt->bindValue(1, $this->childId);
        $stmt->bindValue(2, $this->gameId);
        $result = $stmt->executeQuery();

        return $result->fetchAllAssociative();
    }
    public function isGameComplete(): bool
{
    if ($this->childId === null || $this->gameId === null) {
        return false;
    }

    // Check the database for the highest level completed with sufficient score
    $conn = $this->em->getConnection();
    $sql = 'SELECT MAX(id) FROM level WHERE childId = ? AND gameId = ? AND score >= ?';
    $stmt = $conn->prepare($sql);
    $stmt->bindValue(1, $this->childId);
    $stmt->bindValue(2, $this->gameId);
    $stmt->bindValue(3, 10); // Minimum score for completing 10 stages
    $result = $stmt->executeQuery();

    $highestLevel = (int)$result->fetchOne();
    $isComplete = $highestLevel >= 3; // Game is complete if level 3 has been completed
    error_log("isGameComplete: childId={$this->childId}, gameId={$this->gameId}, highestLevel=$highestLevel, isComplete=$isComplete");
    return $isComplete;
}
    
}
