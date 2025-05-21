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
        $this->initializeSession();
    }

    private function initializeSession(): void
    {
        if (!$this->session->has('game_state')) {
            $this->levelStartTime = new \DateTime();
            $this->session->set('game_state', [
                'currentLevel' => 1,
                'currentStage' => 1,
                'currentLevelPoints' => 0,
                'totalTriesInLevel' => 1,
                'currentStageTries' => 0,
                'currentImages' => [],
                'correctWord' => null,
                'correctImageUrl' => null,
                'highestLevelReached' => $this->getHighestLevelReached() ?? 1,
                'levelStartTime' => $this->levelStartTime,
            ]);
        }
    }

    public function getGameState(): array
    {
        return $this->session->get('game_state', [
            'currentLevel' => 1,
            'currentStage' => 1,
            'currentLevelPoints' => 0,
            'totalTriesInLevel' => 1,
            'currentStageTries' => 0,
            'currentImages' => [],
            'correctWord' => null,
            'correctImageUrl' => null,
            'highestLevelReached' => $this->getHighestLevelReached() ?? 1,
            'levelStartTime' => new \DateTime(),
        ]);
    }

    public function startGame(int $level): void
    {
        if ($this->childId === null || $this->gameId === null) {
            throw new \Exception('Child ID and Game ID must be set before starting the game.');
        }

        $this->selectedLanguage = $this->fetchChildLanguage();
        $this->levelStartTime = new \DateTime();
        $state = [
            'currentLevel' => min(max(1, $level), 3),
            'currentStage' => 1,
            'currentLevelPoints' => 0,
            'totalTriesInLevel' => 1,
            'currentStageTries' => 0,
            'currentImages' => [],
            'correctWord' => null,
            'correctImageUrl' => null,
            'highestLevelReached' => $this->getHighestLevelReached() ?? 1,
            'levelStartTime' => $this->levelStartTime,
        ];
        $this->session->set('game_state', $state);
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
        $this->session->set('game_state', $state);

        error_log("Loaded next round: level={$state['currentLevel']}, stage={$state['currentStage']}");
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
        $previousTries = $state['currentStageTries'];
        $state['currentStageTries']++;

        $isCorrect = $selectedImageUrl === $state['correctImageUrl'];

        if (!$isCorrect && $selectedImageUrl !== null) {
            $state['totalTriesInLevel']++;
        }

        error_log("checkAnswer: childId={$this->childId}, gameId={$this->gameId}, level={$state['currentLevel']}, stage={$state['currentStage']}, isCorrect=$isCorrect, currentStageTries={$state['currentStageTries']}, previousTries=$previousTries, totalTriesInLevel={$state['totalTriesInLevel']}");

        $points = 0;
        $maxTriesReached = $state['currentStageTries'] >= self::MAX_TRIES_PER_STAGE;

        if ($isCorrect) {
            $attemptNumber = $previousTries + 1;
            $points = $this->calculatePoints($attemptNumber);
            $state['currentLevelPoints'] += $points;
        } else if ($maxTriesReached) {
            $points = 0; // 0 points when max tries reached
        }

        $state['currentStageTries'] = min($state['currentStageTries'], self::MAX_TRIES_PER_STAGE);
        $this->session->set('game_state', $state);

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
        error_log("proceedOrRetry: isCorrect=$isCorrect, maxTriesReached=$maxTriesReached, currentStage={$state['currentStage']}, stagesPerLevel=$stagesPerLevel, currentLevel={$state['currentLevel']}, currentStageTries={$state['currentStageTries']}, totalTriesInLevel={$state['totalTriesInLevel']}");

        // Treat max tries reached the same as a correct answer for progression
        if ($isCorrect || $maxTriesReached) {
            error_log("Proceeding: currentStage={$state['currentStage']} vs stagesPerLevel=$stagesPerLevel");
            if ($state['currentStage'] < $stagesPerLevel) {
                $state['currentStage']++;
                $state['currentStageTries'] = 0;
                $this->session->set('game_state', $state);
                $this->loadNextRound();
            } else {
                if ($state['currentLevel'] < 3) {
                    $state['currentLevel']++;
                    $state['currentStage'] = 1;
                    $state['currentLevelPoints'] = 0;
                    $state['totalTriesInLevel'] = 1;
                    $state['currentStageTries'] = 0;
                    $state['levelStartTime'] = new \DateTime();
                    $this->session->set('game_state', $state);
                    $this->loadNextRound();
                } else {
                    return true; // Signal game completion
                }
            }
        } else {
            // Retry the current stage
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

        if (!$level) {
            $level = new Level();
            $level->setId($state['currentLevel']);
            $level->setChildId($child);
            $level->setGameId($game);
            $level->setScore($state['currentLevelPoints']);
            $level->setNbtries($state['totalTriesInLevel']);
            $level->setTime($timeTaken);
        } else {
            $level->setScore(max($level->getScore(), $state['currentLevelPoints']));
            $level->setNbtries($state['totalTriesInLevel']);
            $level->setTime($level->getTime() + $timeTaken);
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

        $highestLevel = (int) $result->fetchOne();
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
}