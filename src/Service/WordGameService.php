<?php

namespace App\Service;

use App\Entity\Images;
use App\Entity\Level;
use App\Entity\Feedback;
use App\Entity\Child;
use App\Entity\Game;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class WordGameService
{
    private EntityManagerInterface $em;
    private SessionInterface $session;
    private int $childId = 1; // Changed to 3 as requested
    private int $gameId = 3; // Default game ID
    private string $selectedLanguage;
    private string $uploadDir;

    // Game-specific rules (stages, max points per stage, target time per stage)
    private array $gameRules = [
        1 => ['stagesPerLevel' => 3, 'maxPointsPerStage' => 3, 'targetTimePerStage' => 6], // Game 1
        2 => ['stagesPerLevel' => 3, 'maxPointsPerStage' => 4, 'targetTimePerStage' => 5], // Game 2
        3 => ['stagesPerLevel' => 3, 'maxPointsPerStage' => 3, 'targetTimePerStage' => 6], // Picture Game
        4 => ['stagesPerLevel' => 5, 'maxPointsPerStage' => 2, 'targetTimePerStage' => 4], // Game 4
        5 => ['stagesPerLevel' => 4, 'maxPointsPerStage' => 5, 'targetTimePerStage' => 8], // Drag-and-Drop Game
    ];

    public function __construct(EntityManagerInterface $em, SessionInterface $session, string $uploadDir)
    {
        $this->em = $em;
        $this->session = $session;
        $this->uploadDir = $uploadDir;
        
        $this->ensureParentExists();
        $this->ensureChildExists();
        $this->ensureGamesExist();
        
        $this->selectedLanguage = $this->fetchChildLanguage();
        $this->initializeSession();
    }

    private function ensureGamesExist(): void
    {
        $conn = $this->em->getConnection();
        $games = [
            ['id' => 1, 'name' => 'Game 1'],
            ['id' => 2, 'name' => 'Game 2'],
            ['id' => 3, 'name' => 'Picture Game'],
            ['id' => 4, 'name' => 'Game 4'],
            ['id' => 5, 'name' => 'Drag-and-Drop Game'],
        ];
        foreach ($games as $game) {
            $exists = $conn->executeQuery('SELECT 1 FROM game WHERE id = ?', [$game['id']])->fetchOne();
            if (!$exists) {
                $conn->executeStatement('INSERT INTO game (id, name) VALUES (?, ?)', [$game['id'], $game['name']]);
            }
        }
    }

    private function initializeSession(): void
    {
        if (!$this->session->has('game_state')) {
            $this->session->set('game_state', [
                'currentLevel' => 1,
                'currentStage' => 1,
                'currentLevelPoints' => 0,
                'totalTriesInLevel' => 0,
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
        $state = [
            'currentLevel' => min(max(1, $level), 3),
            'currentStage' => 1,
            'currentLevelPoints' => 0,
            'totalTriesInLevel' => 0,
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
        $state = $this->session->get('game_state');
        $numberOfImages = 2 + ($state['currentLevel'] - 1);
        
        $conn = $this->em->getConnection();
        $sql = 'SELECT * FROM word ORDER BY RAND() LIMIT ?';
        $stmt = $conn->prepare($sql);
        $stmt->bindValue(1, $numberOfImages, \PDO::PARAM_INT);
        $result = $stmt->executeQuery();
        
        $images = [];
        foreach ($result->fetchAllAssociative() as $row) {
            $images[] = [
                'id' => $row['id'],
                'url' => $row['image_url'],
                'word' => $row['word']
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
        $state = $this->session->get('game_state');
        $attemptTime = min((microtime(true) - $state['startTime']), 10);
        $state['totalTriesInLevel']++;
        $state['totalTimeInLevel'] += $attemptTime;
        $isCorrect = $selectedImageUrl === $state['correctImageUrl'];

        error_log("checkAnswer: childId={$this->childId}, gameId={$this->gameId}, level={$state['currentLevel']}, stage={$state['currentStage']}, isCorrect=$isCorrect, attemptTime=$attemptTime, stagesPerLevel=" . $this->getStagesPerLevel());

        if ($isCorrect) {
            $points = $this->calculatePoints($attemptTime * 1000);
            $state['currentLevelPoints'] += $points;

            if ($state['currentStage'] === $this->getStagesPerLevel()) {
                error_log("Level completed, saving: childId={$this->childId}, gameId={$this->gameId}, level={$state['currentLevel']}");
                $this->saveLevelCompletion();
                if ($state['currentLevel'] >= $state['highestLevelReached'] && $state['currentLevel'] < 3) {
                    $state['highestLevelReached'] = $state['currentLevel'] + 1;
                }
            }
        }

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
        $state = $this->session->get('game_state');
        if (!$isCorrect) {
            $this->loadNextRound();
        } elseif ($state['currentStage'] < $this->getStagesPerLevel()) {
            $state['currentStage']++;
            $this->session->set('game_state', $state);
            $this->loadNextRound();
        } elseif ($state['currentLevel'] < 3) {
            $state['currentLevel']++;
            $state['currentStage'] = 1;
            $state['currentLevelPoints'] = 0;
            $state['totalTriesInLevel'] = 0;
            $state['totalTimeInLevel'] = 0;
            $this->session->set('game_state', $state);
            $this->loadNextRound();
        } else {
            return true; // Game completed
        }
        return false;
    }

    public function resetDatabase(): void
    {
        $this->em->createQuery('DELETE FROM App\Entity\Level l WHERE l.childId = :childId AND l.gameId = :gameId')
            ->setParameter('childId', $this->childId)
            ->setParameter('gameId', $this->gameId)
            ->execute();

        $this->em->createQuery('DELETE FROM App\Entity\Feedback f WHERE f.childId = :childId AND f.gameId = :gameId')
            ->setParameter('childId', $this->childId)
            ->setParameter('gameId', $this->gameId)
            ->execute();

        $state = $this->session->get('game_state');
        $state['currentLevel'] = 1;
        $state['currentStage'] = 1;
        $state['currentLevelPoints'] = 0;
        $state['totalTriesInLevel'] = 0;
        $state['totalTimeInLevel'] = 0;
        $state['highestLevelReached'] = 1;
        $this->session->set('game_state', $state);
    }

    private function ensureParentExists(int $parentId = 1): void
    {
        $conn = $this->em->getConnection();
        
        $parentExists = $conn->executeQuery(
            'SELECT 1 FROM parent WHERE parentId = ?',
            [$parentId]
        )->fetchOne();

        if (!$parentExists) {
            $conn->executeStatement(
                'INSERT INTO parent (parentId, email, password) VALUES (?, ?, ?)',
                [
                    $parentId,
                    'default@example.com',
                    password_hash('defaultpassword', PASSWORD_DEFAULT)
                ]
            );
        }
    }

    private function ensureChildExists(): void
    {
        $conn = $this->em->getConnection();
        
        $this->ensureParentExists();
        
        $childExists = $conn->executeQuery(
            'SELECT 1 FROM child WHERE childId = ?',
            [$this->childId]
        )->fetchOne();

        if (!$childExists) {
            $conn->executeStatement(
                'INSERT INTO child (childId, parentId, name, age, language) VALUES (?, ?, ?, ?, ?)',
                [
                    $this->childId,
                    1,
                    'Default Child',
                    8,
                    'English'
                ]
            );
        }
    }

    public function saveFeedback(string $feedbackText, int $rating, int $gameId): void
    {
        $child = $this->em->getRepository(Child::class)->find($this->childId);
        if (!$child) {
            throw new \Exception('Child not found for ID: ' . $this->childId);
        }

        $feedback = new Feedback();
        $feedback->setChild($child);
        $feedback->setGameId($gameId);
        $feedback->setFeedbackText($feedbackText);
        $feedback->setRating($rating);

        $this->em->persist($feedback);
        $this->em->flush();
    }

    private function fetchChildLanguage(): string
    {
        $child = $this->em->getRepository(Child::class)->find($this->childId);
        return $child && $child->getLanguage() ? $child->getLanguage() : 'English';
    }

    private function calculatePoints(float $elapsedTimeMillis): int
    {
        if ($elapsedTimeMillis <= 3000) return 3;
        if ($elapsedTimeMillis <= 6000) return 2;
        return 1;
    }

    private function saveLevelCompletion(): void
    {
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
            $level->setScore($state['currentLevelPoints']); // Set initial score for new level
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
        return $this->gameRules[$this->gameId]['stagesPerLevel'] ?? 3;
    }

    public function getMaxScorePerLevel(): int
    {
        $rules = $this->gameRules[$this->gameId] ?? ['stagesPerLevel' => 3, 'maxPointsPerStage' => 3];
        return $rules['stagesPerLevel'] * $rules['maxPointsPerStage'];
    }

    public function getTargetMaxTimePerLevel(): int
    {
        $rules = $this->gameRules[$this->gameId] ?? ['stagesPerLevel' => 3, 'targetTimePerStage' => 6];
        return $rules['stagesPerLevel'] * $rules['targetTimePerStage'];
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

    public function getChildId(): int
    {
        return $this->childId;
    }

    public function getGameId(): int
    {
        return $this->gameId;
    }

    public function setChildId(int $childId): void
    {
        error_log("Setting childId to $childId");
        $this->childId = $childId;
        $this->ensureChildExists();
        $this->selectedLanguage = $this->fetchChildLanguage();
    }

    public function setGameId(int $gameId): void
    {
        $this->gameId = $gameId;
    }

    public function getAllImages(): array
    {
        return $this->em->getRepository(Images::class)->findAll();
    }

    public function getImageById(int $id): ?Images
    {
        return $this->em->getRepository(Images::class)->find($id);
    }

    public function saveImage(Images $image): void
    {
        $this->em->persist($image);
        $this->em->flush();
    }

    public function deleteImage(int $id): void
    {
        $image = $this->getImageById($id);
        if ($image) {
            $filePath = $this->uploadDir . '/' . basename($image->getImage_url());
            if (file_exists($filePath)) {
                unlink($filePath);
            }
            $this->em->remove($image);
            $this->em->flush();
        }
    }

    public function handleImageUpload(?UploadedFile $file, ?string $existingUrl = null): string
    {
        if ($file) {
            $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
            if (!in_array($file->getMimeType(), $allowedTypes)) {
                throw new \Exception('Invalid file type. Only JPEG, PNG, and GIF are allowed.');
            }

            if ($file->getSize() > 5 * 1024 * 1024) {
                throw new \Exception('File size exceeds 5MB limit.');
            }

            $filename = md5(uniqid()) . '.' . $file->guessExtension();
            $file->move($this->uploadDir, $filename);

            return '/uploads/images/' . $filename;
        }

        if ($existingUrl) {
            return $existingUrl;
        }

        throw new \Exception('An image file is required.');
    }
}