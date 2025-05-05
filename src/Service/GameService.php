<?php

namespace App\Service;

use App\Entity\GameResult;
use App\Entity\Theme;
use App\Entity\Word;
use Doctrine\ORM\EntityManagerInterface;

class GameService
{
    private $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    public function getThemes(string $language, string $level): array
    {
        $themes = $this->entityManager->getRepository(Theme::class)->findBy([
            'language' => $language,
            'level' => $level,
        ], ['stage' => 'ASC']);

        $themesData = [];
        foreach ($themes as $theme) {
            $words = [];
            foreach ($theme->getWords() as $word) {
                $words[$word->getWord()] = $word->getSynonym();
            }
            $themesData[$theme->getName()] = $words;
        }

        return $themesData;
    }

    public function calculateScore(int $attemptsUsed): int
    {
        return max(0, 100 - ($attemptsUsed * 10));
    }

    public function saveLevelResult(int $childId, int $gameId, int $totalScore, int $totalAttemptsUsed, int $totalTimeSpent): void
    {
        $gameResult = new GameResult();
        $gameResult->setChildId($childId);
        $gameResult->setGameId($gameId);
        $gameResult->setTotalScore($totalScore);
        $gameResult->setTotalAttemptsUsed($totalAttemptsUsed);
        $gameResult->setTotalTimeSpent($totalTimeSpent);
        $gameResult->setLanguage('fr'); // À ajuster selon la langue actuelle
        $gameResult->setLevel('Facile'); // À ajuster selon le niveau actuel
        $gameResult->setPlayedAt(new \DateTime());

        $this->entityManager->persist($gameResult);
        $this->entityManager->flush();
    }
}