<?php

namespace App\Controller;

use App\Entity\Child;
use App\Entity\Game;
use App\Entity\Level;
use App\Entity\Theme;
use App\Service\GameService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Normalizer;

class GameController extends AbstractController
{
    #[Route('/language-selection', name: 'language_selection')]
    public function languageSelection(Request $request, SessionInterface $session): Response
    {
        if ($request->isMethod('POST')) {
            $language = $request->request->get('language');
            $validLanguages = ['fr', 'en', 'de', 'es', 'ar'];
            if (!$language || !in_array($language, $validLanguages)) {
                $this->addFlash('error', 'Langue invalide ou non sélectionnée.');
                return $this->render('game/language_selection.html.twig');
            }

            $session->set('language', $language);
            $session->set('difficulty', '1');

            return $this->redirectToRoute('start_game');
        }

        return $this->render('game/language_selection.html.twig');
    }

  #[Route('/start-game', name: 'start_game', methods: ['GET', 'POST'])]
public function startGame(Request $request, SessionInterface $session, GameService $gameService, EntityManagerInterface $entityManager): Response
{
    try {
        $difficulty = $session->get('difficulty', '1');
        $language = $session->get('language');
        $validLanguages = ['fr', 'en', 'de', 'es', 'ar'];
        if (!$language || !in_array($language, $validLanguages)) {
            $this->addFlash('error', 'Langue invalide ou non sélectionnée.');
            return $this->redirectToRoute('language_selection');
        }

        // Reset session completely to avoid stale data
        $session->clear();
        $session->set('language', $language);
        $session->set('difficulty', $difficulty);
        $session->set('childId', 1);
        $session->set('gameId', 4);
        $session->set('stagesCompleted', 0);
        $session->set('successfulStagesCompleted', 0);
        $session->set('totalScore', 0);
        $session->set('totalAttemptsUsed', 0);
        $session->set('usedThemes', []);
        $session->set('successfulThemes', []);
        $session->set('failedThemes', []);
        $session->set('selectedThemes', []);
        $session->set('attemptsUsedInStage', 0);

        $levelName = $this->getLevelName($difficulty);

        $queryBuilder = $entityManager->getRepository(Theme::class)->createQueryBuilder('t')
            ->where('t.language = :language')
            ->andWhere('t.level = :level')
            ->andWhere('t.isValidated = :isValidated')
            ->setParameter('language', $language)
            ->setParameter('level', $levelName)
            ->setParameter('isValidated', true)
            ->leftJoin('t.words', 'w')
            ->addSelect('w');
        $themesEntities = $queryBuilder->getQuery()->getResult();

        $themes = [];
        foreach ($themesEntities as $theme) {
            $words = [];
            foreach ($theme->getWords() as $word) {
                $words[$word->getWord()] = $word->getSynonym();
            }
            $themes[$theme->getName()] = $words;
        }

        $availableThemes = array_keys($themes);
        $successfulThemes = $session->get('successfulThemes', []);
        $availableThemes = array_diff($availableThemes, $successfulThemes);

        if (count($availableThemes) < 5) {
            $this->addFlash('error', "Pas assez de thèmes validés non complétés pour le niveau '$levelName' en langue '$language'. Veuillez demander à l'admin de valider des thèmes.");
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
        error_log("Words for theme '$currentTheme': " . json_encode($words));

        $shuffledWords = array_keys($words);
        $shuffledSynonyms = array_values($words);
        shuffle($shuffledWords);
        shuffle($shuffledSynonyms);

        error_log("Shuffled words (should be French): " . json_encode($shuffledWords));
        error_log("Shuffled synonyms (should be Arabic): " . json_encode($shuffledSynonyms));

        $session->set('shuffledWords', $shuffledWords);
        $session->set('shuffledSynonyms', $shuffledSynonyms);

        // Log the session data being sent to the front-end
        error_log("Session data set - shuffledWords: " . json_encode($shuffledWords));
        error_log("Session data set - shuffledSynonyms: " . json_encode($shuffledSynonyms));

        return $this->render('game/game.html.twig', [
            'language' => $language,
            'difficulty' => $difficulty,
            'theme' => $currentTheme,
            'words' => $shuffledWords,
            'synonyms' => $shuffledSynonyms,
            'attemptsLeft' => 3,
            'score' => $session->get('totalScore', 0),
        ]);
    } catch (\Exception $e) {
        error_log("Exception in startGame: " . $e->getMessage() . "\nStack trace: " . $e->getTraceAsString());
        $this->addFlash('error', 'Erreur lors du démarrage du jeu : ' . $e->getMessage());
        return $this->redirectToRoute('language_selection');
    }
}

    #[Route('/game-action', name: 'game_action', methods: ['POST'])]
    public function gameAction(Request $request, SessionInterface $session, GameService $gameService, EntityManagerInterface $entityManager): JsonResponse
    {
        error_log("gameAction called with action: " . ($request->request->get('action') ?? 'null'));
        error_log("Full request data: " . json_encode($request->request->all()));
        error_log("Session data: " . json_encode($session->all()));

        try {
            $action = $request->request->get('action');
            $language = $session->get('language');
            $difficulty = $session->get('difficulty');
            $currentTheme = $session->get('currentTheme');
            $childId = $session->get('childId');
            $gameId = $session->get('gameId');

            error_log("Session vars - language: $language, difficulty: $difficulty, currentTheme: $currentTheme, childId: $childId, gameId: $gameId");

            if (!$language || !$difficulty || !$currentTheme || !$childId || !$gameId) {
                error_log("Missing session data - language: $language, difficulty: $difficulty, currentTheme: $currentTheme, childId: $childId, gameId: $gameId");
                return $this->json([
                    'status' => 'error',
                    'message' => 'Données de session manquantes.',
                    'score' => 0,
                    'attemptsLeft' => 3,
                    'correctMatch' => false,
                    'flashMessages' => ['error' => 'Session invalide!']
                ], 400);
            }

            $stagesCompleted = $session->get('stagesCompleted', 0);
            $successfulStagesCompleted = $session->get('successfulStagesCompleted', 0);
            $totalScore = $session->get('totalScore', 0);
            $totalAttemptsUsed = $session->get('totalAttemptsUsed', 0);
            $usedThemes = $session->get('usedThemes', []);
            $successfulThemes = $session->get('successfulThemes', []);
            $failedThemes = $session->get('failedThemes', []);
            $selectedThemes = $session->get('selectedThemes', []);
            $attemptsLeft = (int)$request->request->get('attemptsLeft', 3);
            $attemptsUsedInStage = (int)$session->get('attemptsUsedInStage', 0);
            $shuffledWords = $session->get('shuffledWords', []);
            $shuffledSynonyms = $session->get('shuffledSynonyms', []);

            error_log("Action: $action, attemptsLeft: $attemptsLeft, attemptsUsedInStage: $attemptsUsedInStage");
            error_log("Shuffled words: " . json_encode($shuffledWords));
            error_log("Shuffled synonyms: " . json_encode($shuffledSynonyms));

            if ($action === 'next') {
                error_log("Processing 'next' action for theme: $currentTheme");
                $failedThemes[] = $currentTheme;
                $session->set('failedThemes', $failedThemes);
                $totalAttemptsUsed += $attemptsUsedInStage;
                $stagesCompleted++;
                $session->set('stagesCompleted', $stagesCompleted);
                $session->set('totalAttemptsUsed', $totalAttemptsUsed);
                $session->set('attemptsUsedInStage', 0);

                $this->saveStageResult($entityManager, $childId, $gameId, 0, $attemptsUsedInStage, $currentTheme, false);

                if ($stagesCompleted < count($selectedThemes)) {
                    $nextTheme = null;
                    foreach ($selectedThemes as $theme) {
                        if (!in_array($theme, $successfulThemes) && !in_array($theme, $usedThemes)) {
                            $nextTheme = $theme;
                            break;
                        }
                    }

                    if ($nextTheme) {
                        error_log("Loading next theme: $nextTheme");
                        $usedThemes[] = $nextTheme;
                        $session->set('usedThemes', $usedThemes);
                        $session->set('currentTheme', $nextTheme);
                        $session->set('matchedWords', []);
                        $session->set('attemptsUsedInStage', 0);

                        $queryBuilder = $entityManager->getRepository(Theme::class)->createQueryBuilder('t')
                            ->where('t.language = :language')
                            ->andWhere('t.level = :level')
                            ->andWhere('t.isValidated = :isValidated')
                            ->andWhere('t.name = :name')
                            ->setParameter('language', $language)
                            ->setParameter('level', $this->getLevelName($difficulty))
                            ->setParameter('isValidated', true)
                            ->setParameter('name', $nextTheme)
                            ->leftJoin('t.words', 'w')
                            ->addSelect('w');
                        $themeEntity = $queryBuilder->getQuery()->getOneOrNullResult();

                        if (!$themeEntity) {
                            error_log("Theme not found or not validated: $nextTheme");
                            return $this->json([
                                'status' => 'error',
                                'message' => 'Thème non trouvé ou non validé.',
                                'score' => $totalScore,
                                'attemptsLeft' => $attemptsLeft,
                                'correctMatch' => false,
                                'flashMessages' => ['error' => 'Thème introuvable!']
                            ], 500);
                        }

                        $nextWords = [];
                        foreach ($themeEntity->getWords() as $word) {
                            $nextWords[$word->getWord()] = $word->getSynonym();
                        }

                        $newShuffledWords = array_keys($nextWords);
                        $newShuffledSynonyms = array_values($nextWords);
                        shuffle($newShuffledWords);
                        shuffle($newShuffledSynonyms);

                        $session->set('shuffledWords', $newShuffledWords);
                        $session->set('shuffledSynonyms', $newShuffledSynonyms);

                        error_log("Next theme loaded - words: " . json_encode($newShuffledWords) . ", synonyms: " . json_encode($newShuffledSynonyms));

                        return $this->json([
                            'status' => 'next_theme',
                            'theme' => $nextTheme,
                            'words' => $newShuffledWords,
                            'synonyms' => $newShuffledSynonyms,
                            'attemptsLeft' => 3,
                            'attemptsUsedInStage' => 0,
                            'score' => $totalScore,
                            'correctMatch' => false,
                            'flashMessages' => ['success' => "Nouveau thème : $nextTheme"]
                        ]);
                    }
                }

                $this->saveLevelResult($entityManager, $childId, $gameId, $totalScore, $totalAttemptsUsed);
                error_log("Game over - final score: $totalScore");
                return $this->json([
                    'status' => 'game_over',
                    'score' => $totalScore,
                    'message' => 'اللعبة انتهت! النتيجة النهائية: ' . $totalScore,
                    'gif' => '/images/fun.gif',
                    'flashMessages' => ['success' => 'اللعبة انتهت!']
                ]);
            }

            if ($action !== 'match') {
                error_log("Invalid action: $action");
                return $this->json(['status' => 'error', 'message' => 'Action invalide'], 400);
            }

            error_log("Processing 'match' action");
            $queryBuilder = $entityManager->getRepository(Theme::class)->createQueryBuilder('t')
                ->where('t.language = :language')
                ->andWhere('t.level = :level')
                ->andWhere('t.isValidated = :isValidated')
                ->setParameter('language', $language)
                ->setParameter('level', $this->getLevelName($difficulty))
                ->setParameter('isValidated', true)
                ->leftJoin('t.words', 'w')
                ->addSelect('w');
            $themesEntities = $queryBuilder->getQuery()->getResult();

            $themes = [];
            foreach ($themesEntities as $theme) {
                $words = [];
                foreach ($theme->getWords() as $word) {
                    $words[$word->getWord()] = $word->getSynonym();
                }
                $themes[$theme->getName()] = $words;
            }

            error_log("Loaded themes: " . json_encode(array_keys($themes)));

            $firstWord = trim($request->request->get('firstWord'));
            $secondWord = trim($request->request->get('secondWord'));
            $isWord = filter_var($request->request->get('isWord'), FILTER_VALIDATE_BOOLEAN);

            error_log("Before normalization - firstWord: '$firstWord', secondWord: '$secondWord', isWord: " . ($isWord ? 'true' : 'false'));

            if (!$firstWord || !$secondWord) {
                error_log("Error: firstWord or secondWord is missing - firstWord: '$firstWord', secondWord: '$secondWord'");
                return $this->json([
                    'status' => 'error',
                    'message' => 'يرجى اختيار كلمة ومرادف!',
                    'score' => $totalScore,
                    'attemptsLeft' => $attemptsLeft,
                    'attemptsUsedInStage' => $attemptsUsedInStage,
                    'words' => $shuffledWords,
                    'synonyms' => $shuffledSynonyms,
                    'correctMatch' => false,
                    'flashMessages' => ['error' => 'يرجى اختيار كلمة ومرادف!']
                ], 400);
            }

            try {
                $firstWord = Normalizer::normalize($firstWord, Normalizer::FORM_C);
                $secondWord = Normalizer::normalize($secondWord, Normalizer::FORM_C);
            } catch (\Exception $e) {
                error_log("Normalization error: " . $e->getMessage());
                return $this->json([
                    'status' => 'error',
                    'message' => 'Erreur lors de la normalisation des mots.',
                    'score' => $totalScore,
                    'attemptsLeft' => $attemptsLeft,
                    'attemptsUsedInStage' => $attemptsUsedInStage,
                    'words' => $shuffledWords,
                    'synonyms' => $shuffledSynonyms,
                    'correctMatch' => false,
                    'flashMessages' => ['error' => 'Erreur de normalisation!']
                ], 400);
            }

            error_log("After normalization - firstWord: '$firstWord', secondWord: '$secondWord'");

            $themeWords = $themes[$currentTheme] ?? [];
            if (empty($themeWords)) {
                error_log("Error: No words found for theme: $currentTheme");
                return $this->json([
                    'status' => 'error',
                    'message' => 'Aucun mot trouvé pour ce thème.',
                    'score' => $totalScore,
                    'attemptsLeft' => $attemptsLeft,
                    'attemptsUsedInStage' => $attemptsUsedInStage,
                    'words' => $shuffledWords,
                    'synonyms' => $shuffledSynonyms,
                    'correctMatch' => false,
                    'flashMessages' => ['error' => 'Thème invalide!']
                ], 500);
            }

            error_log("Theme Words: " . json_encode($themeWords));

            // Validation: Check if words are in the correct lists
            $wordFound = false;
            $synonymFound = false;

            if ($isWord) {
                $wordFound = in_array($secondWord, $shuffledWords);
                $synonymFound = in_array($firstWord, $shuffledSynonyms);
                if (!$synonymFound || !$wordFound) {
                    error_log("Validation failed (isWord=true) - firstWord '$firstWord' not in shuffledSynonyms or secondWord '$secondWord' not in shuffledWords");
                    error_log("shuffledSynonyms: " . json_encode($shuffledSynonyms));
                    error_log("shuffledWords: " . json_encode($shuffledWords));
                    return $this->json([
                        'status' => 'error',
                        'message' => 'Mot ou synonyme non valide dans la liste actuelle.',
                        'score' => $totalScore,
                        'attemptsLeft' => $attemptsLeft,
                        'attemptsUsedInStage' => $attemptsUsedInStage,
                        'words' => $shuffledWords,
                        'synonyms' => $shuffledSynonyms,
                        'correctMatch' => false,
                        'flashMessages' => ['error' => 'Mot ou synonyme non valide!']
                    ], 400);
                }
            } else {
                $wordFound = in_array($firstWord, $shuffledWords);
                $synonymFound = in_array($secondWord, $shuffledSynonyms);
                if (!$wordFound || !$synonymFound) {
                    error_log("Validation failed (isWord=false) - firstWord '$firstWord' not in shuffledWords or secondWord '$secondWord' not in shuffledSynonyms");
                    error_log("shuffledWords: " . json_encode($shuffledWords));
                    error_log("shuffledSynonyms: " . json_encode($shuffledSynonyms));
                    return $this->json([
                        'status' => 'error',
                        'message' => 'Mot ou synonyme non valide dans la liste actuelle.',
                        'score' => $totalScore,
                        'attemptsLeft' => $attemptsLeft,
                        'attemptsUsedInStage' => $attemptsUsedInStage,
                        'words' => $shuffledWords,
                        'synonyms' => $shuffledSynonyms,
                        'correctMatch' => false,
                        'flashMessages' => ['error' => 'Mot ou synonyme non valide!']
                    ], 400);
                }
            }

            $wordExists = false;
            if ($isWord) {
                error_log("Checking if '$firstWord' is a synonym in themeWords: " . json_encode(array_values($themeWords)));
                $wordExists = in_array($firstWord, array_values($themeWords));
            } else {
                error_log("Checking if '$firstWord' is a word in themeWords: " . json_encode(array_keys($themeWords)));
                $wordExists = array_key_exists($firstWord, $themeWords);
            }

            if (!$wordExists) {
                error_log("Error: firstWord '$firstWord' not found in theme words");
                return $this->json([
                    'status' => 'error',
                    'message' => 'Mot ou synonyme invalide.',
                    'score' => $totalScore,
                    'attemptsLeft' => $attemptsLeft,
                    'attemptsUsedInStage' => $attemptsUsedInStage,
                    'words' => $shuffledWords,
                    'synonyms' => $shuffledSynonyms,
                    'correctMatch' => false,
                    'flashMessages' => ['error' => 'Mot ou synonyme invalide!']
                ], 400);
            }

            $correctMatch = false;
            if ($isWord) {
                $wordKey = array_search($firstWord, array_values($themeWords));
                $correctMatch = $wordKey !== false && array_keys($themeWords)[$wordKey] === $secondWord;
            } else {
                $correctMatch = isset($themeWords[$firstWord]) && $themeWords[$firstWord] === $secondWord;
            }

            error_log("Correct Match: " . ($correctMatch ? 'true' : 'false'));

            if ($correctMatch) {
                error_log("Correct match detected, calculating score");
                $score = $gameService->calculateScore($attemptsUsedInStage);
                $totalScore += $score;
                $session->set('totalScore', $totalScore);
                $matchedWords = $session->get('matchedWords', []);
                $matchedWords[] = $isWord ? $firstWord : $secondWord;
                $session->set('matchedWords', $matchedWords);

                error_log("Saving stage result - score: $score, attemptsUsedInStage: $attemptsUsedInStage");
                $this->saveStageResult($entityManager, $childId, $gameId, $score, $attemptsUsedInStage, $currentTheme, true);

                if (count($matchedWords) >= count($themeWords)) {
                    $stagesCompleted++;
                    $successfulStagesCompleted++;
                    $session->set('stagesCompleted', $stagesCompleted);
                    $session->set('successfulStagesCompleted', $successfulStagesCompleted);
                    $successfulThemes[] = $currentTheme;
                    $session->set('successfulThemes', $successfulThemes);
                    $session->set('attemptsUsedInStage', 0);

                    if ($successfulStagesCompleted >= 3 && $difficulty !== '3') {
                        $newDifficulty = (int)$difficulty + 1;
                        $session->set('difficulty', $newDifficulty);
                        $this->saveLevelResult($entityManager, $childId, $gameId, $totalScore, $totalAttemptsUsed);
                        error_log("Advancing to next level: $newDifficulty");
                        return $this->json([
                            'status' => 'next_level',
                            'message' => 'تهانينا! لقد انتقلت إلى المستوى التالي!',
                            'score' => $totalScore,
                            'attemptsLeft' => $attemptsLeft,
                            'attemptsUsedInStage' => 0,
                            'flashMessages' => ['success' => 'تهانينا! لقد انتقلت إلى المستوى التالي!']
                        ]);
                    }

                    if ($stagesCompleted < count($selectedThemes)) {
                        $nextTheme = null;
                        foreach ($selectedThemes as $theme) {
                            if (!in_array($theme, $successfulThemes) && !in_array($theme, $usedThemes)) {
                                $nextTheme = $theme;
                                break;
                            }
                        }

                        if ($nextTheme) {
                            $usedThemes[] = $nextTheme;
                            $session->set('usedThemes', $usedThemes);
                            $session->set('currentTheme', $nextTheme);
                            $session->set('matchedWords', []);
                            $session->set('attemptsUsedInStage', 0);

                            $queryBuilder = $entityManager->getRepository(Theme::class)->createQueryBuilder('t')
                                ->where('t.language = :language')
                                ->andWhere('t.level = :level')
                                ->andWhere('t.isValidated = :isValidated')
                                ->andWhere('t.name = :name')
                                ->setParameter('language', $language)
                                ->setParameter('level', $this->getLevelName($difficulty))
                                ->setParameter('isValidated', true)
                                ->setParameter('name', $nextTheme)
                                ->leftJoin('t.words', 'w')
                                ->addSelect('w');
                            $themeEntity = $queryBuilder->getQuery()->getOneOrNullResult();

                            $nextWords = [];
                            foreach ($themeEntity->getWords() as $word) {
                                $nextWords[$word->getWord()] = $word->getSynonym();
                            }

                            $newShuffledWords = array_keys($nextWords);
                            $newShuffledSynonyms = array_values($nextWords);
                            shuffle($newShuffledWords);
                            shuffle($newShuffledSynonyms);

                            $session->set('shuffledWords', $newShuffledWords);
                            $session->set('shuffledSynonyms', $newShuffledSynonyms);

                            error_log("Next theme loaded after completion - theme: $nextTheme, words: " . json_encode($newShuffledWords));
                            return $this->json([
                                'status' => 'next_theme',
                                'theme' => $nextTheme,
                                'words' => $newShuffledWords,
                                'synonyms' => $newShuffledSynonyms,
                                'attemptsLeft' => 3,
                                'attemptsUsedInStage' => 0,
                                'score' => $totalScore,
                                'correctMatch' => true,
                                'flashMessages' => ['success' => 'مطابقة صحيحة! Nouveau thème : ' . $nextTheme]
                            ]);
                        }
                    }

                    $this->saveLevelResult($entityManager, $childId, $gameId, $totalScore, $totalAttemptsUsed);
                    error_log("Game over after theme completion - score: $totalScore");
                    return $this->json([
                        'status' => 'game_over',
                        'score' => $totalScore,
                        'message' => 'اللعبة انتهت! النتيجة النهائية: ' . $totalScore,
                        'gif' => '/images/fun.gif',
                        'flashMessages' => ['success' => 'اللعبة انتهت!']
                    ]);
                }

                error_log("Continuing game - score: $totalScore, attemptsLeft: $attemptsLeft");
                return $this->json([
                    'status' => 'continue',
                    'message' => 'مطابقة صحيحة!',
                    'score' => $totalScore,
                    'attemptsLeft' => $attemptsLeft,
                    'attemptsUsedInStage' => $attemptsUsedInStage,
                    'words' => $shuffledWords,
                    'synonyms' => $shuffledSynonyms,
                    'correctMatch' => true,
                    'flashMessages' => ['success' => 'مطابقة صحيحة!']
                ]);
            }

            error_log("Incorrect match, decrementing attempts");
            $attemptsLeft--;
            $attemptsUsedInStage++;
            $totalAttemptsUsed++;
            $session->set('totalAttemptsUsed', $totalAttemptsUsed);
            $session->set('attemptsUsedInStage', $attemptsUsedInStage);

            error_log("Saving stage result for incorrect match - attemptsUsedInStage: $attemptsUsedInStage");
            $this->saveStageResult($entityManager, $childId, $gameId, 0, $attemptsUsedInStage, $currentTheme, false);

            if ($attemptsLeft <= 0) {
                $failedThemes[] = $currentTheme;
                $session->set('failedThemes', $failedThemes);
                $stagesCompleted++;
                $session->set('stagesCompleted', $stagesCompleted);
                $session->set('attemptsUsedInStage', 0);

                error_log("No attempts left, showing next button");
                return $this->json([
                    'status' => 'game_over',
                    'message' => 'لقد نفدت المحاولات! حاول مرة أخرى.',
                    'score' => $totalScore,
                    'attemptsLeft' => 0,
                    'attemptsUsedInStage' => 0,
                    'showNextButton' => true,
                    'flashMessages' => ['error' => 'لقد نفدت المحاولات!']
                ]);
            }

            error_log("Continuing after incorrect match - attemptsLeft: $attemptsLeft");
            return $this->json([
                'status' => 'continue',
                'message' => 'محاولة غير صحيحة، حاول مرة أخرى!',
                'score' => $totalScore,
                'attemptsLeft' => $attemptsLeft,
                'attemptsUsedInStage' => $attemptsUsedInStage,
                'words' => $shuffledWords,
                'synonyms' => $shuffledSynonyms,
                'correctMatch' => false,
                'flashMessages' => ['error' => 'محاولة غير صحيحة!']
            ]);
        } catch (\Exception $e) {
            error_log("Exception in gameAction: " . $e->getMessage() . "\nStack trace: " . $e->getTraceAsString());
            return $this->json([
                'status' => 'error',
                'message' => 'Erreur serveur: ' . $e->getMessage(),
                'score' => $session->get('totalScore', 0),
                'attemptsLeft' => $attemptsLeft,
                'attemptsUsedInStage' => $attemptsUsedInStage,
                'words' => $shuffledWords,
                'synonyms' => $shuffledSynonyms,
                'correctMatch' => false,
                'flashMessages' => ['error' => 'Erreur interne: ' . $e->getMessage()]
            ], 500);
        }
    }

    #[Route('/favicon.ico', name: 'favicon', methods: ['GET'])]
    public function favicon(): Response
    {
        return new Response('', 204);
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

    private function saveStageResult(EntityManagerInterface $entityManager, int $childId, int $gameId, int $score, int $attemptsUsed, string $theme, bool $success): void
    {
        try {
            $child = $entityManager->getRepository(Child::class)->find($childId);
            $game = $entityManager->getRepository(Game::class)->find($gameId);

            if (!$child || !$game) {
                error_log("Child or Game not found - childId: $childId, gameId: $gameId");
                throw new \RuntimeException('Child or Game not found');
            }

            $qb = $entityManager->createQueryBuilder();
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
            $level->setScore($score);
            $level->setNbtries($attemptsUsed);

            $entityManager->persist($level);
            $entityManager->flush();
        } catch (\Exception $e) {
            error_log("Exception in saveStageResult: " . $e->getMessage() . "\nStack trace: " . $e->getTraceAsString());
            throw new \RuntimeException("Failed to save stage result: " . $e->getMessage());
        }
    }

    private function saveLevelResult(EntityManagerInterface $entityManager, int $childId, int $gameId, int $totalScore, int $totalAttemptsUsed): void
    {
        try {
            error_log("Saving level result - childId: $childId, gameId: $gameId, totalScore: $totalScore, totalAttemptsUsed: $totalAttemptsUsed");

            $connection = $entityManager->getConnection();

            $childCheck = $connection->fetchAssociative('SELECT childId FROM child WHERE childId = :childId', ['childId' => $childId]);
            if (!$childCheck) {
                error_log("Error: Child not found with ID: $childId");
                throw new \RuntimeException("Child with ID $childId not found");
            }

            $gameCheck = $connection->fetchAssociative('SELECT id FROM game WHERE id = :gameId', ['gameId' => $gameId]);
            if (!$gameCheck) {
                error_log("Error: Game not found with ID: $gameId");
                throw new \RuntimeException("Game with ID $gameId not found");
            }

            $nextIdResult = $connection->fetchAssociative('SELECT COALESCE(MAX(id), 0) + 1 as nextId FROM level WHERE childId = :childId AND gameId = :gameId', [
                'childId' => $childId,
                'gameId' => $gameId
            ]);
            $nextId = $nextIdResult['nextId'] ?? 1;

            $currentTime = time();
            $data = [
                'id' => $nextId,
                'childId' => $childId,
                'gameId' => $gameId,
                'score' => $totalScore,
                'nbtries' => $totalAttemptsUsed,
                'time' => $currentTime,
            ];

            $sql = 'INSERT INTO level (id, childId, gameId, score, nbtries, time) VALUES (:id, :childId, :gameId, :score, :nbtries, :time)';
            error_log("Executing SQL: $sql with data: " . json_encode($data));

            $connection->executeStatement($sql, $data);
            error_log("Level result saved successfully - childId: $childId, gameId: $gameId, id: $nextId");
        } catch (\Exception $e) {
            error_log("Exception in saveLevelResult: " . $e->getMessage() . "\nStack trace: " . $e->getTraceAsString());
            throw new \RuntimeException("Failed to save level result: " . $e->getMessage());
        }
    }
}