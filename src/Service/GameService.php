<?php

namespace App\Service;

use App\Entity\GameResult;
use App\Entity\Theme;
use App\Entity\Word;
use Doctrine\ORM\EntityManagerInterface;

class GameService
{
    private $entityManager;
    private $apiKey;

    public function __construct(EntityManagerInterface $entityManager, string $apiKey)
    {
        $this->entityManager = $entityManager;
        $this->apiKey = $apiKey;

        if (!$this->apiKey) {
            throw new \RuntimeException('Clé API Gemini manquante');
        }
    }

    public function getThemes(string $language, string $level): array
    {
        $themes = $this->entityManager->getRepository(Theme::class)->findBy([
            'language' => $language,
            'level' => $level,
            'isValidated' => true,
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

    public function generateThemes(string $language, string $level, int $themeCount = 5, int $wordsPerTheme = 5): array
    {
        $generatedThemes = [];
        $themeNames = $this->generateThemeNames($level, $themeCount);

        foreach ($themeNames as $index => $themeName) {
            $theme = new Theme();
            $theme->setName($themeName);
            $theme->setLanguage($language);
            $theme->setLevel($level);
            $theme->setStage($index + 1);
            $theme->setIsValidated(isValidated: false);

            $words = $this->generateWordsForTheme($themeName, $language, $level, $wordsPerTheme);
            foreach ($words as $wordData) {
                $word = new Word();
                $word->setWord($wordData['mot']);
                $word->setSynonym($wordData['synonyme']['ar']);
                $word->setTheme($theme);
                $theme->addWord($word);
                $this->entityManager->persist($word);
            }

            $this->entityManager->persist($theme);
            $generatedThemes[] = $theme;
        }

        $this->entityManager->flush();
        return $generatedThemes;
    }

    private function generateThemeNames(string $level, int $count): array
    {
        $prompt = sprintf(
    "Génère %d noms de thèmes adaptés au niveau '%s' pour un jeu éducatif destiné aux enfants de 4 à 12 ans. Les thèmes doivent être simples, engageants et liés à des sujets quotidiens ou scolaires, même pour le niveau 'Difficile', qui doit rester compréhensible et accessible pour cet âge. Retourne une liste au format JSON.",
    $count,
    $level
);

        $data = [
            'contents' => [
                ['role' => 'user', 'parts' => [['text' => $prompt]]]
            ],
            'generationConfig' => [
                'response_mime_type' => 'application/json',
            ]
        ];

        try {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, "https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key={$this->apiKey}");
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            $response = curl_exec($ch);
            curl_close($ch);

            $result = json_decode($response, true);
            $themeNames = json_decode($result['candidates'][0]['content']['parts'][0]['text'], true);
            if (!is_array($themeNames) || count($themeNames) < $count) {
                throw new \RuntimeException('Réponse IA invalide pour les noms de thèmes');
            }
            return array_slice($themeNames, 0, $count);
        } catch (\Exception $e) {
            $themesByLevel = [
                'Facile' => ['Couleurs', 'Nombres', 'Famille', 'Animaux', 'Fruits', 'Jouets', 'Formes'],
                'Moyen' => ['Vêtements', 'Légumes', 'Maison', 'École', 'Sports', 'Aliments', 'Météo'],
                'Difficile' => ['Professions', 'Voyages', 'Amis', 'Nature', 'Technologie', 'Culture', 'Histoire'],
            ];
            $availableThemes = $themesByLevel[$level] ?? ['Divers'];
            shuffle($availableThemes);
            return array_slice($availableThemes, 0, $count);
        }
    }

    private function generateWordsForTheme(string $theme, string $language, string $level, int $count): array
    {
        $prompt = sprintf(
            "Génère %d paires mot-synonyme pour le thème '%s' en langue '%s' pour un jeu éducatif de niveau '%s'. Chaque mot doit être adapté au niveau (Facile : mots simples, Moyen : mots intermédiaires, Difficile : mots complexes). Le synonyme doit être en arabe. Retourne un tableau JSON avec la structure : [{'mot': 'mot', 'synonyme': {'ar': 'synonyme'}}].",
            $count,
            $theme,
            $language,

            $level
        );

        $data = [
            'contents' => [
                ['role' => 'user', 'parts' => [['text' => $prompt]]]
            ],
            'generationConfig' => [
                'response_mime_type' => 'application/json',
            ]
        ];

        try {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, "https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key={$this->apiKey}");
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            $response = curl_exec($ch);
            curl_close($ch);

            $result = json_decode($response, true);
            $words = json_decode($result['candidates'][0]['content']['parts'][0]['text'], true);
            if (!is_array($words) || count($words) < $count) {
                throw new \RuntimeException('Réponse IA invalide pour les mots');
            }
            return array_slice($words, 0, $count);
        } catch (\Exception $e) {
            $defaultWords = [
                ['mot' => 'Mot1', 'synonyme' => ['ar' => 'مرادف1']],
                ['mot' => 'Mot2', 'synonyme' => ['ar' => 'مرادف2']],
                ['mot' => 'Mot3', 'synonyme' => ['ar' => 'مرادف3']],
                ['mot' => 'Mot4', 'synonyme' => ['ar' => 'مرادف4']],
                ['mot' => 'Mot5', 'synonyme' => ['ar' => 'مرادف5']],
            ];
            return array_slice($defaultWords, 0, $count);
        }
    }

    public function calculateScore(int $attemptsUsed): int
    {
        return max(0, 100 - ($attemptsUsed * 10));
    }

    public function saveLevelResult(int $childId, int $gameId, int $totalScore, int $totalAttemptsUsed, int $totalTimeSpent, string $language, string $level): void
    {
        $gameResult = new GameResult();
        $gameResult->setChildId($childId);
        $gameResult->setGameId($gameId);
        $gameResult->setTotalScore($totalScore);
        $gameResult->setTotalAttemptsUsed($totalAttemptsUsed);
        $gameResult->setTotalTimeSpent($totalTimeSpent);
        $gameResult->setLanguage($language);
        $gameResult->setLevel($level);
        $gameResult->setPlayedAt(new \DateTime());

        $this->entityManager->persist($gameResult);
        $this->entityManager->flush();
    }
}