<?php

namespace App\Controller;

use App\Entity\GameResult;
use App\Entity\Theme;
use App\Service\GameService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Annotation\Route;

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
            $session->set('difficulty', '1');

            return $this->redirectToRoute('start_game');
        }

        return $this->render('game/language_selection.html.twig');
    }

    #[Route('/start-game', name: 'start_game', methods: ['GET', 'POST'])]
    public function startGame(Request $request, SessionInterface $session, GameService $gameService, EntityManagerInterface $entityManager): Response
    {
        $difficulty = $session->get('difficulty', '1');
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
        $session->set('successfulThemes', []);
        $session->set('failedThemes', []);
        $session->set('selectedThemes', []);

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
    public function gameAction(Request $request, SessionInterface $session, GameService $gameService, EntityManagerInterface $entityManager): Response
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
        $successfulThemes = $session->get('successfulThemes', []);
        $failedThemes = $session->get('failedThemes', []);
        $selectedThemes = $session->get('selectedThemes', []);
        $attemptsLeft = (int)$request->request->get('attemptsLeft', 3);
        $attemptsUsedInStage = (int)$request->request->get('attemptsUsedInStage', 0);

        $timeLeft = $session->get('timeLeft', 60);
        $shuffledWords = $session->get('shuffledWords', []);
        $shuffledSynonyms = $session->get('shuffledSynonyms', []);

        if ($action === 'next') {
            $timeSpent = (int)$request->request->get('timeSpent', 0);
            $failedThemes[] = $currentTheme;
            $session->set('failedThemes', $failedThemes);
            $totalAttemptsUsed += $attemptsUsedInStage;
            $totalTimeSpent += $timeSpent;
            $stagesCompleted++;
            $session->set('stagesCompleted', $stagesCompleted);
            $session->set('totalAttemptsUsed', $totalAttemptsUsed);
            $session->set('totalTimeSpent', $totalTimeSpent);

            $this->saveStageResult($entityManager, $childId, $gameId, 0, $attemptsUsedInStage, $timeSpent, $language, $this->getLevelName($difficulty), $currentTheme, false);

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

                    return $this->json([
                        'status' => 'next_theme',
                        'theme' => $nextTheme,
                        'words' => $newShuffledWords,
                        'synonyms' => $newShuffledSynonyms,
                        'timeLeft' => $timeLeft,
                        'attemptsLeft' => 3,
                        'score' => $totalScore,
                        'correctMatch' => false
                    ]);
                }
            }

            $this->saveLevelResult($entityManager, $childId, $gameId, $totalScore, $totalAttemptsUsed, $totalTimeSpent, $language, $this->getLevelName($difficulty));
            return $this->json([
                'status' => 'game_over',
                'score' => $totalScore,
                'message' => 'اللعبة انتهت! النتيجة النهائية: ' . $totalScore,
                'gif' => '/images/fun.gif'
            ]);
        }

        if ($action !== 'match') {
            return $this->json(['error' => 'Invalid action']);
        }

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

            if (count($matchedWords) >= count($words)) {
                $score = $this->calculateStageScore($attemptsUsedInStage);
                $totalScore += $score;
                $totalAttemptsUsed += $attemptsUsedInStage;
                $totalTimeSpent += $timeSpent;

                $session->set('totalScore', $totalScore);
                $session->set('totalAttemptsUsed', $totalAttemptsUsed);
                $session->set('totalTimeSpent', $totalTimeSpent);

                $successfulThemes[] = $currentTheme;
                $session->set('successfulThemes', $successfulThemes);
                $successfulStagesCompleted++;
                $session->set('successfulStagesCompleted', $successfulStagesCompleted);
                $stagesCompleted++;
                $session->set('stagesCompleted', $stagesCompleted);

                $this->saveStageResult($entityManager, $childId, $gameId, $score, $attemptsUsedInStage, $timeSpent, $language, $this->getLevelName($difficulty), $currentTheme, true);

                if ($stagesCompleted < count($selectedThemes)) {
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
                            'correctMatch' => true,
                            'message' => 'تم اجتياز المرحلة بنجاح!',
                            'gif' => '/images/fun.gif',
                            'audio' => '/audio/correct.mp3'
                        ]);
                    }
                }

                if ($successfulStagesCompleted === count($selectedThemes) && (int)$difficulty < 3) {
                    $nextDifficulty = (int)$difficulty + 1;
                    $session->set('difficulty', $nextDifficulty);
                    $session->set('stagesCompleted', 0);
                    $session->set('successfulStagesCompleted', 0);
                    $session->set('usedThemes', []);
                    $session->set('successfulThemes', $successfulThemes);
                    $session->set('failedThemes', []);
                    $session->set('selectedThemes', []);

                    $newLevelName = $this->getLevelName((string)$nextDifficulty);

                    $queryBuilder = $entityManager->getRepository(Theme::class)->createQueryBuilder('t')
                        ->where('t.language = :language')
                        ->andWhere('t.level = :level')
                        ->andWhere('t.isValidated = :isValidated')
                        ->setParameter('language', $language)
                        ->setParameter('level', $newLevelName)
                        ->setParameter('isValidated', true)
                        ->leftJoin('t.words', 'w')
                        ->addSelect('w');
                    $newThemesEntities = $queryBuilder->getQuery()->getResult();

                    $newThemes = [];
                    foreach ($newThemesEntities as $theme) {
                        $words = [];
                        foreach ($theme->getWords() as $word) {
                            $words[$word->getWord()] = $word->getSynonym();
                        }
                        $newThemes[$theme->getName()] = $words;
                    }

                    $newAvailableThemes = array_keys($newThemes);
                    $newAvailableThemes = array_diff($newAvailableThemes, $successfulThemes);
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
                        '1' => 60,
                        '2' => 45,
                        '3' => 30,
                        default => 60,
                    };

                    $session->set('timeLeft', $newTimeLeft);
                    $session->set('shuffledWords', $newShuffledWords);
                    $session->set('shuffledSynonyms', $newShuffledSynonyms);

                    $this->saveLevelResult($entityManager, $childId, $gameId, $totalScore, $totalAttemptsUsed, $totalTimeSpent, $language, $this->getLevelName($difficulty));

                    return $this->json([
                        'status' => 'next_level',
                        'theme' => $newCurrentTheme,
                        'words' => $newShuffledWords,
                        'synonyms' => $newShuffledSynonyms,
                        'timeLeft' => $newTimeLeft,
                        'attemptsLeft' => 3,
                        'score' => $totalScore,
                        'message' => 'المستوى ' . $difficulty . ' --> المستوى ' . $nextDifficulty,
                        'gif' => '/images/fun.gif',
                        'audio' => '/audio/correct.mp3'
                    ]);
                }

                $this->saveLevelResult($entityManager, $childId, $gameId, $totalScore, $totalAttemptsUsed, $totalTimeSpent, $language, $this->getLevelName($difficulty));
                return $this->json([
                    'status' => 'game_over',
                    'score' => $totalScore,
                    'message' => 'اللعبة انتهت! النتيجة النهائية: ' . $totalScore,
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
                    'message' => 'تطابق جيد!',
                    'gif' => '/images/fun.gif',
                    'audio' => '/audio/correct.mp3'
                ]);
            }
        } else {
            $attemptsLeft--;
            $attemptsUsedInStage++;
            $timeLeft -= $timeSpent;

            if ($attemptsLeft <= 0 || $timeLeft <= 0) {
                if ($timeLeft <= 0) {
                    $this->addFlash('error', 'انتهى الوقت! المرحلة ستعاد.');
                    $words = $themes[$currentTheme];
                    $shuffledWords = array_keys($words);
                    $shuffledSynonyms = array_values($words);
                    shuffle($shuffledWords);
                    shuffle($shuffledSynonyms);
                    $session->set('shuffledWords', $shuffledWords);
                    $session->set('shuffledSynonyms', $shuffledSynonyms);
                    $session->set('matchedWords', []);
                    $timeLeft = match ($difficulty) {
                        '1' => 60,
                        '2' => 45,
                        '3' => 30,
                        default => 60,
                    };
                    return $this->json([
                        'status' => 'continue',
                        'words' => $shuffledWords,
                        'synonyms' => $shuffledSynonyms,
                        'timeLeft' => $timeLeft,
                        'attemptsLeft' => 3,
                        'showNextButton' => true,
                        'correctMatch' => false,
                        'message' => 'انتهى الوقت! يمكنك إعادة المحاولة أو الانتقال إلى المرحلة التالية.',
                        'gif' => '/images/no.gif',
                        'audio' => '/audio/incorrect.mp3'
                    ]);
                } elseif ($attemptsLeft <= 0) {
                    $failedThemes[] = $currentTheme;
                    $session->set('failedThemes', $failedThemes);

                    $this->addFlash('error', 'فشل المرحلة: لا توجد محاولات متبقية. المرحلة ستعاد.');
                    $words = $themes[$currentTheme];
                    $shuffledWords = array_keys($words);
                    $shuffledSynonyms = array_values($words);
                    shuffle($shuffledWords);
                    shuffle($shuffledSynonyms);
                    $session->set('shuffledWords', $shuffledWords);
                    $session->set('shuffledSynonyms', $shuffledSynonyms);
                    $session->set('matchedWords', []);
                    $timeLeft = match ($difficulty) {
                        '1' => 60,
                        '2' => 45,
                        '3' => 30,
                        default => 60,
                    };
                    return $this->json([
                        'status' => 'continue',
                        'words' => $shuffledWords,
                        'synonyms' => $shuffledSynonyms,
                        'timeLeft' => $timeLeft,
                        'attemptsLeft' => 3,
                        'showNextButton' => true,
                        'correctMatch' => false,
                        'message' => 'لا توجد محاولات متبقية! يمكنك إعادة المحاولة أو الانتقال إلى المرحلة التالية.',
                        'gif' => '/images/no.gif',
                        'audio' => '/audio/incorrect.mp3'
                    ]);
                }
            } else {
                return $this->json([
                    'status' => 'continue',
                    'words' => $shuffledWords,
                    'synonyms' => $shuffledSynonyms,
                    'timeLeft' => $timeLeft,
                    'score' => $totalScore,
                    'attemptsLeft' => $attemptsLeft,
                    'correctMatch' => false,
                    'message' => 'تطابق خاطئ!',
                    'gif' => '/images/no.gif',
                    'audio' => '/audio/incorrect.mp3'
                ]);
            }
        }
        return $this->json(['status' => 'error', 'message' => 'حدث خطأ غير متوقع.']);
    }

    #[Route('/generate-themes', name: 'generate_themes', methods: ['GET', 'POST'])]
    public function generateThemes(Request $request, GameService $gameService): Response
    {
        $language = $request->query->get('language', $request->request->get('language', 'fr'));
        $level = $request->query->get('level', $request->request->get('level', 'Facile'));
        $themeCount = (int) $request->query->get('themeCount', $request->request->get('themeCount', 5));
        $wordsPerTheme = (int) $request->query->get('wordsPerTheme', $request->request->get('wordsPerTheme', 5));

        $validLanguages = ['fr', 'en', 'de', 'es'];
        $validLevels = ['Facile', 'Moyen', 'Difficile'];
        if (!in_array($language, $validLanguages)) {
            return $this->json(['error' => 'Langue invalide'], Response::HTTP_BAD_REQUEST);
        }
        if (!in_array($level, $validLevels)) {
            return $this->json(['error' => 'Niveau invalide'], Response::HTTP_BAD_REQUEST);
        }
        if ($themeCount < 1 || $wordsPerTheme < 1) {
            return $this->json(['error' => 'Nombre de thèmes ou de mots par thème invalide'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $generatedThemes = $gameService->generateThemes($language, $level, $themeCount, $wordsPerTheme);

            $themesData = [];
            foreach ($generatedThemes as $theme) {
                $themesData[] = [
                    'name' => $theme->getName(),
                    'language' => $theme->getLanguage(),
                    'level' => $theme->getLevel(),
                    'stage' => $theme->getStage(),
                    'isValidated' => $theme->isValidated(),
                    'words' => array_map(fn($word) => [
                        'word' => $word->getWord(),
                        'synonym' => $word->getSynonym(),
                    ], $theme->getWords()->toArray()),
                ];
            }

            return $this->json([
                'message' => 'Thèmes générés avec succès',
                'themes' => $themesData,
            ], Response::HTTP_CREATED);
        } catch (\Exception $e) {
            return $this->json([
                'error' => 'Erreur lors de la génération des thèmes : ' . $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
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

    private function calculateStageScore(int $attemptsUsed): int
    {
        return match ($attemptsUsed) {
            0 => 5,
            1 => 3,
            2 => 1,
            default => 1,
        };
    }

    private function saveStageResult(EntityManagerInterface $entityManager, int $childId, int $gameId, int $score, int $attemptsUsed, int $timeSpent, string $language, string $level, string $theme, bool $success): void
    {
        $gameResult = new GameResult();
        $gameResult->setChildId($childId);
        $gameResult->setGameId($gameId);
        $gameResult->setTotalScore($score);
        $gameResult->setTotalAttemptsUsed($attemptsUsed);
        $gameResult->setTotalTimeSpent($timeSpent);
        $gameResult->setLanguage($language);
        $gameResult->setLevel($level);
        $gameResult->setPlayedAt(new \DateTime());

        $entityManager->persist($gameResult);
        $entityManager->flush();
    }

    private function saveLevelResult(EntityManagerInterface $entityManager, int $childId, int $gameId, int $totalScore, int $totalAttemptsUsed, int $totalTimeSpent, string $language, string $level): void
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

        $entityManager->persist($gameResult);
        $entityManager->flush();
    }
}