<?php

namespace App\Service;

use App\Entity\Jeudedevinette;
use App\Repository\JeudedevinetteRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class GuessingGameService
{
    private JeudedevinetteRepository $jeudedevinetteRepository;
    private EntityManagerInterface $entityManager;

    public function __construct(JeudedevinetteRepository $jeudedevinetteRepository, EntityManagerInterface $entityManager)
    {
        $this->jeudedevinetteRepository = $jeudedevinetteRepository;
        $this->entityManager = $entityManager;
    }

    // src/Service/GuessingGameService.php

    public function deleteLot(
        string $rightWord,
        string $wrongWord,
        string $theme,
        string $language,
        string $level,
        LoggerInterface $logger
    ): void {
        $logger->info('Attempting to delete lot', [
            'originalRightWord' => $rightWord,
            'normalizedRightWord' => $rightWord,
            'originalWrongWord' => $wrongWord,
            'normalizedWrongWord' => $wrongWord,
            'theme' => $theme,
            'language' => $language,
            'level' => $level
        ]);

        $word = $this->entityManager->getRepository(Jeudedevinette::class)->findOneBy([
            'rightword' => $rightWord,    // minuscule comme dans l'entité
            'wrongword' => $wrongWord,   // minuscule comme dans l'entité
            'thème' => $theme,          // accent grave comme dans l'entité
            'language' => $language,
            'level' => $level
        ]);

        if ($word) {
            $this->entityManager->remove($word);
            $this->entityManager->flush();
        }
    }
    public function getWordsForAdmin(string $language, string $level): array
    {
        return $this->jeudedevinetteRepository->findBy([
            'language' => $language,
            'level' => $level,
        ]);
    }

    public function addLot(string $rightWords, string $wrongWord, string $theme, string $language, string $level): void
    {
        // Normalize separators for consistency when adding
        $normalize = fn(string $input): string => trim(preg_replace('/[\s,-]+/', ' ', $input));

        $lot = new Jeudedevinette();
        $lot->setRightWord($normalize($rightWords));
        $lot->setWrongWord($normalize($wrongWord));
        $lot->setThème($theme);
        $lot->setLanguage($language);
        $lot->setLevel($level);

        $this->entityManager->persist($lot);
        $this->entityManager->flush();
    }
}