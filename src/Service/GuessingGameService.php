<?php

namespace App\Service;

use App\Repository\JeudedevinetteRepository;

class GuessingGameService
{
    private JeudedevinetteRepository $jeuDevinetteRepository;

    public function __construct(JeudedevinetteRepository $jeuDevinetteRepository)
    {
        $this->jeuDevinetteRepository = $jeuDevinetteRepository;
    }

    public function getWordsForAdmin(string $language, int $level): array
    {
        return $this->jeuDevinetteRepository->findByLanguageAndLevel($language, $level);
    }

    public function addLot(string $rightWords, string $wrongWord, string $theme, string $language, int $level): void
    {
        $this->jeuDevinetteRepository->addLot($rightWords, $wrongWord, $theme, $language, $level);
    }

    public function deleteLot(string $rightWord, string $wrongWord, string $theme): void
    {
        $this->jeuDevinetteRepository->deleteLot($rightWord, $wrongWord, $theme);
    }
}