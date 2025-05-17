<?php

namespace App\Service;

use App\Entity\Level;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Service for managing game progress, including scores and metrics, and ensuring necessary entities exist.
 */
class ProgressService
{
    private EntityManagerInterface $em;
    private ?int $childId = null;
    private ?int $gameId = null;
    private array $gameRules = [
        1 => ['stagesPerLevel' => 10, 'maxPointsPerStage' => 5],
        2 => ['stagesPerLevel' => 10, 'maxPointsPerStage' => 5],
        3 => ['stagesPerLevel' => 10, 'maxPointsPerStage' => 5],
        4 => ['stagesPerLevel' => 10, 'maxPointsPerStage' => 5],
        5 => ['stagesPerLevel' => 10, 'maxPointsPerStage' => 5],
    ];

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
    }

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

    public function ensureChildExists(int $childId): void
    {
        $conn = $this->em->getConnection();
        $this->ensureParentExists();
        $childExists = $conn->executeQuery('SELECT 1 FROM child WHERE childId = ?', [$childId])->fetchOne();

        if (!$childExists) {
            $conn->executeStatement(
                'INSERT INTO child (childId, parentId, name, age, language) VALUES (?, ?, ?, ?, ?)',
                [$childId, 1, 'Default Child', 8, 'English']
            );
        }
    }

    public function ensureGamesExist(int $gameId): void
    {
        $conn = $this->em->getConnection();
        $games = [
            ['id' => 1, 'name' => 'guessing game'],
            ['id' => 2, 'name' => 'fill in the blank'],
            ['id' => 3, 'name' => 'Picture Game'],
            ['id' => 4, 'name' => 'word game'],
            ['id' => 5, 'name' => 'Drag-and-Drop Game'],
        ];

        foreach ($games as $game) {
            $exists = $conn->executeQuery('SELECT 1 FROM game WHERE id = ?', [$game['id']])->fetchOne();
            if (!$exists) {
                $conn->executeStatement('INSERT INTO game (id, name) VALUES (?, ?)', [$game['id'], $game['name']]);
            }
        }
    }

    public function getMaxScorePerLevel(): int
    {
        if ($this->gameId === null) {
            return 0;
        }
        $stages = $this->gameRules[$this->gameId]['stagesPerLevel'] ?? 10;
        $maxPointsPerStage = $this->gameRules[$this->gameId]['maxPointsPerStage'] ?? 5;
        return $stages * $maxPointsPerStage;
    }

    public function getProgressData(int $childId, int $gameId): array
    {
        $this->childId = $childId;
        $this->gameId = $gameId;

        $scores = [1 => 0, 2 => 0, 3 => 0];
        $times = [1 => 0, 2 => 0, 3 => 0];
        $tries = [1 => 0, 2 => 0, 3 => 0];
        $maxScores = [1 => 0, 2 => 0, 3 => 0];
        $scoreComparisons = [
            1 => ['achieved' => 0, 'max' => 0, 'percentage' => 0],
            2 => ['achieved' => 0, 'max' => 0, 'percentage' => 0],
            3 => ['achieved' => 0, 'max' => 0, 'percentage' => 0]
        ];

        $levels = $this->em->getRepository(Level::class)->findBy(
            ['childId' => $childId, 'gameId' => $gameId],
            ['id' => 'ASC']
        );

        foreach ($levels as $index => $level) {
            $levelNumber = $index + 1;
            if ($levelNumber > 3) {
                break;
            }

            $scores[$levelNumber] = $level->getScore() ?? 0;
            $times[$levelNumber] = $level->getTime() ?? 0;
            $tries[$levelNumber] = $level->getNbtries() ?? 0;
        }

        $maxScorePerLevel = $this->getMaxScorePerLevel();
        for ($level = 1; $level <= 3; $level++) {
            $maxScores[$level] = $maxScorePerLevel;
            $scoreComparisons[$level] = [
                'achieved' => $scores[$level],
                'max' => $maxScores[$level],
                'percentage' => $maxScores[$level] > 0 ? round(($scores[$level] / $maxScores[$level]) * 100) : 0
            ];
        }

        $maxScoresValue = !empty($scores) ? max($scores) : 0;
        $maxTimesValue = !empty($times) ? max($times) : 0;
        $maxTriesValue = !empty($tries) ? max($tries) : 0;
        $maxMaxScoresValue = !empty($maxScores) ? max($maxScores) : 0;
        $yAxisMax = ceil(max($maxScoresValue, $maxTimesValue, $maxTriesValue, $maxMaxScoresValue) * 1.1);

        return [
            'scores' => $scores,
            'times' => $times,
            'tries' => $tries,
            'maxScores' => $maxScores,
            'scoreComparisons' => $scoreComparisons,
            'yAxisMax' => $yAxisMax,
        ];
    }

    public function setChildId(int $childId): void
    {
        $this->childId = $childId;
    }

    public function setGameId(int $gameId): void
    {
        $this->gameId = $gameId;
    }
}