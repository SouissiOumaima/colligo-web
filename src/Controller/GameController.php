<?php

namespace App\Controller;

use App\Entity\Theme;
use App\Entity\Word;
use App\Service\GameService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\VarDumper\VarDumper;

class GameController extends AbstractController
{
    #[Route('/language-selection', name: 'language_selection')]
    public function languageSelection(Request $request, SessionInterface $session): Response
    {
        if ($request->isMethod('POST')) {
            $language = $request->request->get('language');
            $validLanguages = ['fr', 'en', 'de', 'es'];
            if (!$language || !in_array($language, $validLanguages)) {
                $this->addFlash('error', 'Langue invalide ou non sélectionnée.');
                return $this->render('game/language_selection.html.twig');
            }

            $session->set('language', $language);
            $session->set('difficulty', '1'); // Définir le niveau Facile par défaut

            return $this->redirectToRoute('start_game');
        }

        return $this->render('game/language_selection.html.twig');
    }

    #[Route('/start-game', name: 'start_game', methods: ['GET', 'POST'])]
    public function startGame(Request $request, SessionInterface $session, GameService $gameService): Response
    {
        $difficulty = $session->get('difficulty', '1'); // Par défaut à 1 (Facile) si non défini
        $language = $session->get('language');
        $validLanguages = ['fr', 'en', 'de', 'es'];
        if (!$language || !in_array($language, $validLanguages)) {
            $this->addFlash('error', 'Langue invalide ou non sélectionnée.');
            return $this->redirectToRoute('language_selection');
        }

        $session->set('difficulty', $difficulty);
        $session->set('childId', 1);
        $session->set('gameId', 4);
        $session->set('stagesCompleted', 0);
        $session->set('successfulStagesCompleted', 0);
        $session->set('totalScore', 0);
        $session->set('totalAttemptsUsed', 0);
        $session->set('totalTimeSpent', 0);
        $session->set('usedThemes', []);
        $session->set('successfulThemes', []); // Ajout pour suivre les thèmes réussis
        $session->set('failedThemes', []);
        $session->set('selectedThemes', []);

        $levelName = $this->getLevelName($difficulty);
        $themes = $gameService->getThemes($language, $levelName);
        $availableThemes = array_keys($themes);

        if (count($availableThemes) < 5) {
            $this->addFlash('error', "Pas assez de thèmes disponibles pour le niveau '$levelName' en langue '$language'. Veuillez ajouter des données dans la base de données.");
            return $this->redirectToRoute('language_selection');
        }

        shuffle($availableThemes);
        $selectedThemes = array_slice($availableThemes, 0, 5);
        $session->set('selectedThemes', $selectedThemes);

        $currentTheme = $selectedThemes[0];
        $usedThemes = [$currentTheme];
        $session->set('usedThemes', $usedThemes);
        $session->set('currentTheme', $currentTheme);

        $words = $themes[$currentTheme];
        $shuffledWords = array_keys($words);
        $shuffledSynonyms = array_values($words);
        shuffle($shuffledWords);
        shuffle($shuffledSynonyms);

        $timeLeft = match ($difficulty) {
            '1' => 60,
            '2' => 45,
            '3' => 30,
            default => 60,
        };

        $session->set('timeLeft', $timeLeft);
        $session->set('shuffledWords', $shuffledWords);
        $session->set('shuffledSynonyms', $shuffledSynonyms);

        return $this->render('game/game.html.twig', [
            'language' => $language,
            'difficulty' => $difficulty,
            'theme' => $currentTheme,
            'words' => $shuffledWords,
            'synonyms' => $shuffledSynonyms,
            'timeLeft' => $timeLeft,
            'attemptsLeft' => 3,
            'score' => $session->get('totalScore', 0),
        ]);
    }

    #[Route('/game-action', name: 'game_action', methods: ['POST'])]
    public function gameAction(Request $request, SessionInterface $session, GameService $gameService): Response
    {
        $action = $request->request->get('action');
        $language = $session->get('language');
        $difficulty = $session->get('difficulty');
        $currentTheme = $session->get('currentTheme');
        $childId = $session->get('childId');
        $gameId = $session->get('gameId');
        $stagesCompleted = $session->get('stagesCompleted', 0);
        $successfulStagesCompleted = $session->get('successfulStagesCompleted', 0);
        $totalScore = $session->get('totalScore', 0);
        $totalAttemptsUsed = $session->get('totalAttemptsUsed', 0);
        $totalTimeSpent = $session->get('totalTimeSpent', 0);
        $usedThemes = $session->get('usedThemes', []);
        $successfulThemes = $session->get('successfulThemes', []); // Ajout pour suivre les thèmes réussis
        $failedThemes = $session->get('failedThemes', []);
        $selectedThemes = $session->get('selectedThemes', []);
        $attemptsLeft = (int)$request->request->get('attemptsLeft', 3);
        $attemptsUsedInStage = (int)$request->request->get('attemptsUsedInStage', 0);

        $timeLeft = $session->get('timeLeft', 60);
        $shuffledWords = $session->get('shuffledWords', []);
        $shuffledSynonyms = $session->get('shuffledSynonyms', []);

        if ($action !== 'match') {
            return $this->json(['error' => 'Invalid action']);
        }

        $themes = $gameService->getThemes($language, $this->getLevelName($difficulty));
        $words = $themes[$currentTheme];

        $firstWord = $request->request->get('firstWord');
        $secondWord = $request->request->get('secondWord');
        $isWord = $request->request->get('isWord') === 'true';
        $timeSpent = (int)$request->request->get('timeSpent', 0);

        $correctMatch = false;
        if ($isWord) {
            if (isset($words[$secondWord]) && $words[$secondWord] === $firstWord) {
                $correctMatch = true;
            }
        } else {
            $word = array_search($firstWord, $words);
            if ($word !== false && $word === $secondWord) {
                $correctMatch = true;
            }
        }

        if ($correctMatch) {
            $matchedWords = $session->get('matchedWords', []);
            $matchedWords[] = $isWord ? $secondWord : $firstWord;
            $session->set('matchedWords', $matchedWords);

            VarDumper::dump(['matchedWords' => $matchedWords, 'totalWords' => count($words)]); // Débogage

            if (count($matchedWords) >= count($words)) {
                $score = $gameService->calculateScore($attemptsUsedInStage);
                $totalScore += $score;
                $totalAttemptsUsed += $attemptsUsedInStage;
                $totalTimeSpent += $timeSpent;

                $session->set('totalScore', $totalScore);
                $session->set('totalAttemptsUsed', $totalAttemptsUsed);
                $session->set('totalTimeSpent', $totalTimeSpent);

                // Marquer le thème comme réussi et passer au suivant
                $successfulThemes[] = $currentTheme;
                $session->set('successfulThemes', $successfulThemes);
                $successfulStagesCompleted++;
                $session->set('successfulStagesCompleted', $successfulStagesCompleted);
                $stagesCompleted++;
                $session->set('stagesCompleted', $stagesCompleted);

                if ($stagesCompleted < count($selectedThemes)) {
                    // Trouver le prochain thème non encore réussi
                    $nextTheme = null;
                    foreach ($selectedThemes as $theme) {
                        if (!in_array($theme, $successfulThemes)) {
                            $nextTheme = $theme;
                            break;
                        }
                    }

                    if ($nextTheme) {
                        $usedThemes[] = $nextTheme;
                        $session->set('usedThemes', $usedThemes);
                        $session->set('currentTheme', $nextTheme);
                        $session->set('matchedWords', []);

                        $nextWords = $themes[$nextTheme];
                        $newShuffledWords = array_keys($nextWords);
                        $newShuffledSynonyms = array_values($nextWords);
                        shuffle($newShuffledWords);
                        shuffle($newShuffledSynonyms);

                        $session->set('shuffledWords', $newShuffledWords);
                        $session->set('shuffledSynonyms', $newShuffledSynonyms);

                        return $this->json([
                            'status' => 'next_theme',
                            'theme' => $nextTheme,
                            'words' => $newShuffledWords,
                            'synonyms' => $newShuffledSynonyms,
                            'timeLeft' => $timeLeft - $timeSpent,
                            'attemptsLeft' => 3,
                            'score' => $totalScore,
                            'correctMatch' => true
                        ]);
                    }
                }

                // Si tous les stages sont terminés avec succès
                if ($successfulStagesCompleted === count($selectedThemes) && (int)$difficulty < 3) {
                    $nextDifficulty = (int)$difficulty + 1;
                    $session->set('difficulty', $nextDifficulty);
                    $session->set('stagesCompleted', 0);
                    $session->set('successfulStagesCompleted', 0);
                    $session->set('usedThemes', []);
                    $session->set('successfulThemes', []);
                    $session->set('failedThemes', []);
                    $session->set('selectedThemes', []);

                    $newLevelName = $this->getLevelName((string)$nextDifficulty);
                    $newThemes = $gameService->getThemes($language, $newLevelName);
                    $newAvailableThemes = array_keys($newThemes);
                    shuffle($newAvailableThemes);
                    $newSelectedThemes = array_slice($newAvailableThemes, 0, 5);
                    $session->set('selectedThemes', $newSelectedThemes);

                    $newCurrentTheme = $newSelectedThemes[0];
                    $usedThemes = [$newCurrentTheme];
                    $session->set('usedThemes', $usedThemes);
                    $session->set('currentTheme', $newCurrentTheme);

                    $newWords = $newThemes[$newCurrentTheme];
                    $newShuffledWords = array_keys($newWords);
                    $newShuffledSynonyms = array_values($newWords);
                    shuffle($newShuffledWords);
                    shuffle($newShuffledSynonyms);

                    $newTimeLeft = match ($nextDifficulty) {
                        1 => 60,
                        2 => 45,
                        3 => 30,
                        default => 60,
                    };

                    $session->set('timeLeft', $newTimeLeft);
                    $session->set('shuffledWords', $newShuffledWords);
                    $session->set('shuffledSynonyms', $newShuffledSynonyms);

                    $this->addFlash('success', "Félicitations ! Vous avez complété le niveau '" . $this->getLevelName($difficulty) . "' avec succès. Passage au niveau '" . $this->getLevelName((string)$nextDifficulty) . "' !");

                    return $this->json([
                        'status' => 'next_level',
                        'theme' => $newCurrentTheme,
                        'words' => $newShuffledWords,
                        'synonyms' => $newShuffledSynonyms,
                        'timeLeft' => $newTimeLeft,
                        'attemptsLeft' => 3,
                        'score' => $totalScore,
                        'message' => "Félicitations ! Passage au niveau '" . $this->getLevelName((string)$nextDifficulty) . "' !",
                        'gif' => '/images/fun.gif'
                    ]);
                }

                $gameService->saveLevelResult($childId, $gameId, $totalScore, $totalAttemptsUsed, $totalTimeSpent);
                return $this->json([
                    'status' => 'game_over',
                    'score' => $totalScore,
                    'message' => 'Jeu terminé ! Score final : ' . $totalScore,
                    'gif' => '/images/fun.gif'
                ]);
            } else {
                return $this->json([
                    'status' => 'continue',
                    'words' => $shuffledWords,
                    'synonyms' => $shuffledSynonyms,
                    'timeLeft' => $timeLeft - $timeSpent,
                    'attemptsLeft' => $attemptsLeft,
                    'score' => $totalScore,
                    'correctMatch' => true,
                    'message' => 'Bonne correspondance !',
                    'gif' => '/images/fun.gif'
                ]);
            }
        } else {
            $attemptsLeft--;
            $attemptsUsedInStage++;
            $timeLeft -= $timeSpent;

            if ($attemptsLeft <= 0 || $timeLeft <= 0) {
                if ($timeLeft <= 0) {
                    // Temps écoulé : rester dans le même stage
                    $this->addFlash('error', 'Temps écoulé ! Le stage recommence.');
                    $words = $themes[$currentTheme];
                    $shuffledWords = array_keys($words);
                    $shuffledSynonyms = array_values($words);
                    shuffle($shuffledWords);
                    shuffle($shuffledSynonyms);
                    $session->set('shuffledWords', $shuffledWords);
                    $session->set('shuffledSynonyms', $shuffledSynonyms);
                    $session->set('matchedWords', []); // Réinitialiser les paires associées
                    $timeLeft = match ($difficulty) {
                        '1' => 60,
                        '2' => 45,
                        '3' => 30,
                        default => 60,
                    }; // Réinitialiser le temps
                    return $this->json([
                        'status' => 'continue',
                        'words' => $shuffledWords,
                        'synonyms' => $shuffledSynonyms,
                        'timeLeft' => $timeLeft,
                        'attemptsLeft' => 3, // Réinitialiser les tentatives
                        'score' => $totalScore,
                        'correctMatch' => false,
                        'message' => 'Temps écoulé ! Le stage recommence.',
                        'gif' => '/images/no.gif'
                    ]);
                } elseif ($attemptsLeft <= 0) {
                    // Plus de tentatives : rester dans le même stage
                    $failedThemes[] = $currentTheme;
                    $session->set('failedThemes', $failedThemes);

                    $this->addFlash('error', 'Échec du stage : plus de tentatives. Le stage recommence.');
                    $words = $themes[$currentTheme];
                    $shuffledWords = array_keys($words);
                    $shuffledSynonyms = array_values($words);
                    shuffle($shuffledWords);
                    shuffle($shuffledSynonyms);
                    $session->set('shuffledWords', $shuffledWords);
                    $session->set('shuffledSynonyms', $shuffledSynonyms);
                    $session->set('matchedWords', []); // Réinitialiser les paires associées
                    $timeLeft = match ($difficulty) {
                        '1' => 60,
                        '2' => 45,
                        '3' => 30,
                        default => 60,
                    }; // Réinitialiser le temps
                    return $this->json([
                        'status' => 'continue',
                        'words' => $shuffledWords,
                        'synonyms' => $shuffledSynonyms,
                        'timeLeft' => $timeLeft,
                        'attemptsLeft' => 3, // Réinitialiser les tentatives
                        'score' => $totalScore,
                        'correctMatch' => false,
                        'message' => 'Échec du stage : plus de tentatives. Le stage recommence.',
                        'gif' => '/images/no.gif'
                    ]);
                }
            } else {
                return $this->json([
                    'status' => 'continue',
                    'words' => $shuffledWords,
                    'synonyms' => $shuffledSynonyms,
                    'timeLeft' => $timeLeft,
                    'attemptsLeft' => $attemptsLeft,
                    'score' => $totalScore,
                    'correctMatch' => false,
                    'message' => 'Mauvaise correspondance !',
                    'gif' => '/images/no.gif'
                ]);
            }
        }
        return $this->json(['status' => 'error', 'message' => 'Une erreur inattendue s\'est produite.']);
    }

    #[Route('/load-data', name: 'load_data')]
    public function loadData(EntityManagerInterface $entityManager): Response
    {
        $jsonFiles = [
            'Facile' => $this->getParameter('kernel.project_dir') . '/src/Controller/data/easy.json',
            'Moyen' => $this->getParameter('kernel.project_dir') . '/src/Controller/data/medium.json',
            'Difficile' => $this->getParameter('kernel.project_dir') . '/src/Controller/data/hard.json',
        ];

        $validLanguages = ['fr', 'en', 'de', 'es'];
        $validLevels = ['Facile', 'Moyen', 'Difficile'];

        foreach ($jsonFiles as $level => $jsonFile) {
            if (!file_exists($jsonFile)) {
                return $this->json(['error' => "Fichier JSON $jsonFile non trouvé."], 404);
            }

            $jsonData = file_get_contents($jsonFile);
            $data = json_decode($jsonData, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                return $this->json(['error' => "Erreur lors du décodage du JSON pour $jsonFile."], 500);
            }

            if (empty($data)) {
                return $this->json(['error' => "Aucune donnée trouvée dans $jsonFile."], 400);
            }

            foreach ($data as $language => $themes) {
                if (!in_array($language, $validLanguages)) {
                    continue; // Ignorer les langues non valides
                }

                if (!in_array($level, $validLevels)) {
                    continue; // Ignorer les niveaux non valides
                }

                $themesArray = array_keys($themes);
                shuffle($themesArray);
                $themesPerStage = array_chunk($themesArray, ceil(count($themesArray) / 5));

                foreach ($themesPerStage as $index => $stageThemes) {
                    $stage = $index + 1;
                    foreach ($stageThemes as $themeName) {
                        $theme = $entityManager->getRepository(Theme::class)->findOneBy([
                            'name' => $themeName,
                            'language' => $language,
                            'level' => $level,
                        ]);

                        if (!$theme) {
                            $theme = new Theme();
                            $theme->setName($themeName);
                            $theme->setLanguage($language);
                            $theme->setLevel($level);
                            $theme->setStage($stage);
                            $entityManager->persist($theme);
                        } else {
                            $theme->setStage($stage);
                        }

                        $words = $themes[$themeName];
                        foreach ($words as $wordData) {
                            if (!isset($wordData['mot']) || !isset($wordData['synonyme'])) {
                                continue; // Ignorer les entrées invalides
                            }

                            $word = $wordData['mot'];
                            $synonym = isset($wordData['synonyme']['ar']) ? $wordData['synonyme']['ar'] : (isset($wordData['synonyme']['Arabe']) ? $wordData['synonyme']['Arabe'] : null);

                            if (!$synonym) {
                                continue; // Ignorer si aucun synonyme valide
                            }

                            $existingWord = $entityManager->getRepository(Word::class)->findOneBy([
                                'word' => $word,
                                'synonym' => $synonym,
                                'theme' => $theme,
                            ]);

                            if (!$existingWord) {
                                $wordEntity = new Word();
                                $wordEntity->setWord($word);
                                $wordEntity->setSynonym($synonym);
                                $wordEntity->setTheme($theme);
                                $entityManager->persist($wordEntity);
                            }
                        }
                    }
                }
            }
        }

        $entityManager->flush();

        return $this->json(['message' => 'Données importées avec succès dans la base de données avec stages.']);
    }

    private function getLevelName(string $difficulty): string
    {
        return match ($difficulty) {
            '1' => 'Facile',
            '2' => 'Moyen',
            '3' => 'Difficile',
            default => 'Facile',
        };
    }
}