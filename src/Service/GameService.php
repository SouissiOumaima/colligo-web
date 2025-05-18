<?php

namespace App\Service;

use App\Entity\Child;
use App\Entity\Game;
use App\Entity\Level;
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
        $levelText = $this->convertLevel($level);
        $themes = $this->entityManager->getRepository(Theme::class)->findBy([
            'language' => $language,
            'level' => $levelText,
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

    public function generateThemes(string $language, string $level, int $themeCount = 30, int $wordsPerTheme = 5): array
    {
        $generatedThemes = [];
        $themeNames = $this->generateThemeNames($level, $themeCount);

        foreach ($themeNames as $index => $themeName) {
            $theme = new Theme();
            $theme->setName($themeName);
            $theme->setLanguage($language);
            $theme->setLevel($this->convertLevel($level));
            $theme->setStage($index + 1);
            $theme->setIsValidated(false);

            try {
                $words = $this->generateWordsForTheme($themeName, $language, $level, $wordsPerTheme);
                if (empty($words)) {
                    continue;
                }

                foreach ($words as $wordData) {
                    if (preg_match('/^Mot\d+$/', $wordData['mot'])) {
                        throw new \RuntimeException("Mot invalide détecté : {$wordData['mot']}");
                    }

                    $word = new Word();
                    $word->setWord($wordData['mot']);
                    $word->setSynonym($wordData['synonyme']['ar']);
                    $word->setTheme($theme);
                    $theme->addWord($word);
                    $this->entityManager->persist($word);
                }

                $this->entityManager->persist($theme);
                $generatedThemes[] = $theme;
            } catch (\Exception $e) {
                error_log("Erreur lors de la génération du thème '$themeName': " . $e->getMessage());
                continue;
            }
        }

        if (!empty($generatedThemes)) {
            $this->entityManager->flush();
        }

        return $generatedThemes;
    }

    private function generateThemeNames(string $level, int $count): array
    {
        $levelText = $this->convertLevel($level);
        $prompt = sprintf(
            "Génère %d noms de thèmes uniques et variés adaptés au niveau '%s' pour un jeu éducatif destiné aux enfants de 4 à 12 ans. Les thèmes doivent être simples, engageants et liés à des sujets quotidiens, scolaires ou aux centres d'intérêt des enfants (par exemple, la nature, les animaux, les sciences, les fêtes, les sports, etc.). Assure-toi qu'il n'y a aucune répétition dans les noms des thèmes. Même pour le niveau 'Difficile', les thèmes doivent rester compréhensibles et accessibles pour cet âge. Retourne une liste au format JSON.",
            $count,
            $levelText
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
                'Facile' => [
                    'Couleurs', 'Nombres', 'Famille', 'Animaux', 'Fruits', 'Jouets', 'Formes', 'Véhicules', 'Saisons', 'Émotions',
                    'Fleurs', 'Étoiles', 'Lune', 'Soleil', 'Arc-en-ciel', 'Arbres', 'Rivières', 'Montagnes', 'Plages', 'Forêts',
                    'Oiseaux', 'Insectes', 'Poissons', 'Chiens', 'Chats', 'Ciel', 'Nuages', 'Pluie', 'Neige', 'Vent'
                ],
                'Moyen' => [
                    'Vêtements', 'Légumes', 'Maison', 'École', 'Sports', 'Aliments', 'Météo', 'Voyages', 'Amis', 'Jardin',
                    'Musique', 'Livres', 'Jeux', 'Fêtes', 'Océan', 'Camping', 'Plantes', 'Bateaux', 'Voitures', 'Avions',
                    'Trains', 'Films', 'Théâtre', 'Danse', 'Cuisine', 'Marché', 'Parc', 'Zoo', 'Cirque', 'Foire'
                ],
                'Difficile' => [
                    'Cultures du monde', 'Livres célèbres', 'Artistes connus', 'Musique classique', 'Sciences amusantes', 'Histoire des jouets',
                    'Contes traditionnels', 'Peinture', 'Théâtre pour enfants', 'Danses du monde', 'Instruments de musique', 'Écrivains célèbres',
                    'Fêtes culturelles', 'Histoire des costumes', 'Animaux légendaires', 'Découverte des planètes', 'Inventions simples',
                    'Explorateurs célèbres', 'Monuments célèbres', 'Jeux anciens', 'Nature et environnement', 'Étoiles et constellations',
                    'Histoire des sports', 'Cuisine du monde', 'Chansons traditionnelles', 'Artisanat', 'Jardins célèbres', 'Récits d’aventure',
                    'Symboles culturels', 'Mythes et légendes'
                ],
            ];
            $availableThemes = $themesByLevel[$levelText] ?? ['Divers'];
            shuffle($availableThemes);
            return array_slice($availableThemes, 0, $count);
        }
    }

    private function generateWordsForTheme(string $theme, string $language, string $level, int $count): array
    {
        $levelText = $this->convertLevel($level);
        $prompt = sprintf(
            "Génère %d paires mot-synonyme pour le thème '%s' en langue '%s' pour un jeu éducatif de niveau '%s'. Chaque mot doit être adapté au niveau (Facile : mots simples, Moyen : mots intermédiaires, Difficile : mots complexes). Le synonyme doit être en arabe. Retourne un tableau JSON avec la structure : [{'mot': 'mot', 'synonyme': {'ar': 'synonyme'}}].",
            $count,
            $theme,
            $language,
            $levelText
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
            throw new \RuntimeException("Échec de la génération des mots pour le thème '$theme': " . $e->getMessage());
        }
    }

    public function convertLevel(string $level): string
    {
        $levelMap = [
            '1' => 'Facile',
            '2' => 'Moyen',
            '3' => 'Difficile',
        ];
        return $levelMap[$level] ?? 'Facile';
    }

    public function calculateScore(int $attemptsUsed): int
    {
        return max(0, 100 - ($attemptsUsed * 10));
    }

    public function saveLevelResult(int $childId, int $gameId, int $totalScore, int $totalAttemptsUsed, int $totalTimeSpent): void
    {
        $child = $this->entityManager->getRepository(Child::class)->find($childId);
        $game = $this->entityManager->getRepository(Game::class)->find($gameId);

        if (!$child || !$game) {
            throw new \RuntimeException('Child or Game not found');
        }

        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('COALESCE(MAX(l.id), 0) + 1')
           ->from(Level::class, 'l')
           ->where('l.childId = :child')
           ->andWhere('l.gameId = :game')
           ->setParameter('child', $child)
           ->setParameter('game', $game);
        $nextId = $qb->getQuery()->getSingleScalarResult();

        $level = new Level();
        $level->setId($nextId);
        $level->setChildId($child);
        $level->setGameId($game);
        $level->setScore($totalScore);
        $level->setNbtries($totalAttemptsUsed);
        $level->setTime($totalTimeSpent);

        $this->entityManager->persist($level);
        $this->entityManager->flush();
    }
}