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

/**
 * Service for managing the word game logic, including game state, scoring, and image handling.
 */
class WordGameService
{
    private EntityManagerInterface $em;
    private SessionInterface $session;
    private ?int $childId = null; // Child ID, set by controller
    private ?int $gameId = null; // Game ID, set by controller
    private string $selectedLanguage; // Language preference for the child
    private string $uploadDir; // Directory for uploaded images

    // Game rules defining stages, points, and target times per game
    private array $gameRules = [
        1 => ['stagesPerLevel' => 3, 'maxPointsPerStage' => 3, 'targetTimePerStage' => 6], // Game 1
        2 => ['stagesPerLevel' => 3, 'maxPointsPerStage' => 4, 'targetTimePerStage' => 5], // Game 2
        3 => ['stagesPerLevel' => 3, 'maxPointsPerStage' => 3, 'targetTimePerStage' => 6], // Picture Game
        4 => ['stagesPerLevel' => 5, 'maxPointsPerStage' => 2, 'targetTimePerStage' => 4], // Game 4
        5 => ['stagesPerLevel' => 4, 'maxPointsPerStage' => 5, 'targetTimePerStage' => 8], // Drag-and-Drop Game
    ];

    /**
     * Constructor to initialize dependencies.
     *
     * @param EntityManagerInterface $em Doctrine entity manager
     * @param SessionInterface $session Symfony session interface
     * @param string $uploadDir Directory path for image uploads
     */
    public function __construct(EntityManagerInterface $em, SessionInterface $session, string $uploadDir)
    {
        $this->em = $em;
        $this->session = $session;
        $this->uploadDir = $uploadDir;

        // Ensure a default parent exists
        $this->ensureParentExists();

        // Initialize session for game state
        $this->initializeSession();
    }

    /**
     * Ensures all games are present in the database.
     *
     * @throws \Exception If gameId is not set
     */
    private function ensureGamesExist(): void
    {
        if ($this->gameId === null) {
            throw new \Exception('Game ID must be set before ensuring games exist.');
        }

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

    /**
     * Initializes the game state in the session if not already set.
     */
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

    /**
     * Starts a new game at the specified level.
     *
     * @param int $level The level to start
     * @throws \Exception If childId or gameId is not set
     */
    public function startGame(int $level): void
    {
        if ($this->childId === null) {
            throw new \Exception('Child ID must be set before starting the game.');
        }
        if ($this->gameId === null) {
            throw new \Exception('Game ID must be set before starting the game.');
        }

        $this->ensureGamesExist();
        $this->ensureChildExists();
        $this->selectedLanguage = $this->fetchChildLanguage();

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

    /**
     * Loads the next round of images and sets the correct word.
     *
     * @throws \Exception If insufficient images are available
     */
    public function loadNextRound(): void
    {
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

    /**
     * Retrieves the translated word based on the child's language.
     *
     * @param array $imageRow Image data from the database
     * @param string $language Selected language
     * @return string Translated or default word
     */
    private function getTranslationFromRow(array $imageRow, string $language): string
    {
        return match ($language) {
            'French' => $imageRow['french_translation'] ?? $imageRow['word'],
            'Spanish' => $imageRow['spanish_translation'] ?? $imageRow['word'],
            'German' => $imageRow['german_translation'] ?? $imageRow['word'],
            default => $imageRow['word'],
        };
    }

    /**
     * Checks if the selected image is correct and updates the game state.
     *
     * @param string $selectedImageUrl URL of the selected image
     * @return array Result of the answer check
     * @throws \Exception If childId or gameId is not set
     */
    public function checkAnswer(string $selectedImageUrl): array
    {
        if ($this->childId === null || $this->gameId === null) {
            throw new \Exception('Child ID and Game ID must be set before checking answers.');
        }

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

    /**
     * Proceeds to the next stage or level, or retries if incorrect.
     *
     * @param bool $isCorrect Whether the previous answer was correct
     * @return bool True if the game is completed, false otherwise
     */
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

    /**
     * Resets the database for the current child and game.
     *
     * @throws \Exception If childId or gameId is not set
     */
    public function resetDatabase(): void
    {
        if ($this->childId === null || $this->gameId === null) {
            throw new \Exception('Child ID and Game ID must be set before resetting the database.');
        }

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

    /**
     * Ensures a default parent exists in the database.
     *
     * @param int $parentId Parent ID to check or create
     */
    private function ensureParentExists(int $parentId = 1): void
    {
        $conn = $this->em->getConnection();
        $parentExists = $conn->executeQuery('SELECT 1 FROM parent WHERE parentId = ?', [$parentId])->fetchOne();

        if (!$parentExists) {
            $conn->executeStatement(
                'INSERT INTO parent (parentId, email, password) VALUES (?, ?, ?)',
                [$parentId, 'default@example.com', password_hash('defaultpassword', PASSWORD_DEFAULT)]
            );
        }
    }

    /**
     * Ensures the child exists in the database.
     *
     * @throws \Exception If childId is not set
     */
    private function ensureChildExists(): void
    {
        if ($this->childId === null) {
            throw new \Exception('Child ID must be set before ensuring child exists.');
        }

        $conn = $this->em->getConnection();
        $this->ensureParentExists();
        $childExists = $conn->executeQuery('SELECT 1 FROM child WHERE childId = ?', [$this->childId])->fetchOne();

        if (!$childExists) {
            $conn->executeStatement(
                'INSERT INTO child (childId, parentId, name, age, language) VALUES (?, ?, ?, ?, ?)',
                [$this->childId, 1, 'Default Child', 8, 'English']
            );
        }
    }

    /**
     * Saves feedback for the game.
     *
     * @param string $feedbackText Feedback text
     * @param int $rating Rating value
     * @param int $gameId Game ID
     * @throws \Exception If childId is not set or child not found
     */
    public function saveFeedback(string $feedbackText, int $rating, int $gameId): void
    {
        if ($this->childId === null) {
            throw new \Exception('Child ID must be set before saving feedback.');
        }

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

    /**
     * Fetches the child's language preference.
     *
     * @return string Language preference or default 'English'
     * @throws \Exception If childId is not set
     */
    private function fetchChildLanguage(): string
    {
        if ($this->childId === null) {
            throw new \Exception('Child ID must be set before fetching child language.');
        }

        $child = $this->em->getRepository(Child::class)->find($this->childId);
        return $child && $child->getLanguage() ? $child->getLanguage() : 'English';
    }

    /**
     * Calculates points based on response time.
     *
     * @param float $elapsedTimeMillis Time taken in milliseconds
     * @return int Points awarded
     */
    private function calculatePoints(float $elapsedTimeMillis): int
    {
        if ($elapsedTimeMillis <= 3000) {
            return 3;
        }
        if ($elapsedTimeMillis <= 6000) {
            return 2;
        }
        return 1;
    }

    /**
     * Saves the completion data for the current level.
     *
     * @throws \Exception If childId, gameId, child, or game is not set/found
     */
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

    /**
     * Gets the highest level reached by the child for the current game.
     *
     * @return int Highest level reached (1 to 3)
     */
    public function getHighestLevelReached(): int
    {
        if ($this->childId === null || $this->gameId === null) {
            return 1; // Default if IDs not set
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

    /**
     * Gets the number of stages per level for the current game.
     *
     * @return int Number of stages
     */
    public function getStagesPerLevel(): int
    {
        if ($this->gameId === null) {
            return 3; // Default if gameId not set
        }
        return $this->gameRules[$this->gameId]['stagesPerLevel'] ?? 3;
    }

    /**
     * Gets the maximum score possible per level.
     *
     * @return int Maximum score
     */
    public function getMaxScorePerLevel(): int
    {
        if ($this->gameId === null) {
            return 9; // Default if gameId not set
        }
        $rules = $this->gameRules[$this->gameId] ?? ['stagesPerLevel' => 3, 'maxPointsPerStage' => 3];
        return $rules['stagesPerLevel'] * $rules['maxPointsPerStage'];
    }

    /**
     * Gets the target maximum time per level.
     *
     * @return int Target time in seconds
     */
    public function getTargetMaxTimePerLevel(): int
    {
        if ($this->gameId === null) {
            return 18; // Default if gameId not set
        }
        $rules = $this->gameRules[$this->gameId] ?? ['stagesPerLevel' => 3, 'targetTimePerStage' => 6];
        return $rules['stagesPerLevel'] * $rules['targetTimePerStage'];
    }

    /**
     * Gets the current set of images for the game.
     *
     * @return array Current images
     */
    public function getCurrentImages(): array
    {
        return $this->session->get('game_state')['currentImages'] ?? [];
    }

    /**
     * Gets the correct word for the current round.
     *
     * @return ?string Correct word or null
     */
    public function getCorrectWord(): ?string
    {
        return $this->session->get('game_state')['correctWord'] ?? null;
    }

    /**
     * Gets the current level.
     *
     * @return int Current level
     */
    public function getCurrentLevel(): int
    {
        return $this->session->get('game_state')['currentLevel'] ?? 1;
    }

    /**
     * Gets the current stage.
     *
     * @return int Current stage
     */
    public function getCurrentStage(): int
    {
        return $this->session->get('game_state')['currentStage'] ?? 1;
    }

    /**
     * Gets the current points for the level.
     *
     * @return int Current points
     */
    public function getCurrentLevelPoints(): int
    {
        return $this->session->get('game_state')['currentLevelPoints'] ?? 0;
    }

    /**
     * Gets the current child ID.
     *
     * @return ?int Child ID or null
     */
    public function getChildId(): ?int
    {
        return $this->childId;
    }

    /**
     * Gets the current game ID.
     *
     * @return ?int Game ID or null
     */
    public function getGameId(): ?int
    {
        return $this->gameId;
    }

    /**
     * Sets the child ID and initializes child-related data.
     *
     * @param int $childId Child ID
     */
    public function setChildId(int $childId): void
    {
        error_log("Setting childId to $childId");
        $this->childId = $childId;
        $this->ensureChildExists();
        $this->selectedLanguage = $this->fetchChildLanguage();
    }

    /**
     * Sets the game ID and ensures games exist.
     *
     * @param int $gameId Game ID
     */
    public function setGameId(int $gameId): void
    {
        error_log("Setting gameId to $gameId");
        $this->gameId = $gameId;
        $this->ensureGamesExist();
    }

    /**
     * Gets all images from the database.
     *
     * @return array List of images
     */
    public function getAllImages(): array
    {
        return $this->em->getRepository(Images::class)->findAll();
    }

    /**
     * Gets an image by its ID.
     *
     * @param int $id Image ID
     * @return ?Images Image entity or null
     */
    public function getImageById(int $id): ?Images
    {
        return $this->em->getRepository(Images::class)->find($id);
    }

    /**
     * Saves an image to the database.
     *
     * @param Images $image Image entity
     */
    public function saveImage(Images $image): void
    {
        $this->em->persist($image);
        $this->em->flush();
    }

    /**
     * Deletes an image from the database and file system.
     *
     * @param int $id Image ID
     */
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

    /**
     * Handles image upload and validation.
     *
     * @param ?UploadedFile $file Uploaded file
     * @param ?string $existingUrl Existing image URL
     * @return string Image URL
     * @throws \Exception If file type or size is invalid, or no file/URL provided
     */
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