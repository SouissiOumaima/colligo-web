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

        $session->set('shuffledWords', $shuffledWords);
        $session->set('shuffledSynonyms', $shuffledSynonyms);

        return $this->render('game/game.html.twig', [
            'language' => $language,
            'difficulty' => $difficulty,
            'theme' => $currentTheme,
            'words' => $shuffledWords,
            'synonyms' => $shuffledSynonyms,
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
        $usedThemes = $session->get('usedThemes', []);
        $successfulThemes = $session->get('successfulThemes', []);
        $failedThemes = $session->get('failedThemes', []);
        $selectedThemes = $session->get('selectedThemes', []);
        $attemptsLeft = (int)$request->request->get('attemptsLeft', 3);
        $attemptsUsedInStage = (int)$request->request->get('attemptsUsedInStage', 0);

        $shuffledWords = $session->get('shuffledWords', []);
        $shuffledSynonyms = $session->get('shuffledSynonyms', []);

        if ($action === 'next') {
            $failedThemes[] = $currentTheme;
            $session->set('failedThemes', $failedThemes);
            $totalAttemptsUsed += $attemptsUsedInStage;
            $stagesCompleted++;
            $session->set('stagesCompleted', $stagesCompleted);
            $session->set('totalAttemptsUsed', $totalAttemptsUsed);

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
                        'attemptsLeft' => 3,
                        'score' => $totalScore,
                        'correctMatch' => false
                    ]);
                }
            }

            $this->saveLevelResult($entityManager, $childId, $gameId, $totalScore, $totalAttemptsUsed);
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

        $firstWord = $request->request->get('firstWord');
        $secondWord = $request->request->get('secondWord');
        $isWord = filter_var($request->request->get('isWord'), FILTER_VALIDATE_BOOLEAN);

        $matchedWords = $session->get('matchedWords', []);
        $totalAttemptsUsed += $attemptsUsedInStage;
        $session->set('totalAttemptsUsed', $totalAttemptsUsed);

        if (!$firstWord || !$secondWord) {
            if ($attemptsLeft <= 0) {
                $failedThemes[] = $currentTheme;
                $session->set('failedThemes', $failedThemes);
                $stagesCompleted++;
                $session->set('stagesCompleted', $stagesCompleted);

                $this->saveStageResult($entityManager, $childId, $gameId, 0, $attemptsUsedInStage, $currentTheme, false);

                return $this->json([
                    'status' => 'game_over',
                    'message' => 'لقد نفدت المحاولات! حاول مرة أخرى.',
                    'score' => $totalScore,
                    'attemptsLeft' => 0,
                    'showNextButton' => true
                ]);
            }

            return $this->json([
                'status' => 'continue',
                'message' => 'يرجى اختيار كلمة ومرادف!',
                'score' => $totalScore,
                'attemptsLeft' => $attemptsLeft,
                'words' => $shuffledWords,
                'synonyms' => $shuffledSynonyms,
                'correctMatch' => false
            ]);
        }

        $correctMatch = false;
        $themeWords = $themes[$currentTheme] ?? [];
        if ($isWord) {
            $correctMatch = isset($themeWords[$firstWord]) && $themeWords[$firstWord] === $secondWord;
        } else {
            $correctMatch = array_search($firstWord, $themeWords) === $secondWord;
        }

        if ($correctMatch) {
            $score = $gameService->calculateScore($attemptsUsedInStage);
            $totalScore += $score;
            $session->set('totalScore', $totalScore);
            $matchedWords[] = $isWord ? $firstWord : $secondWord;
            $session->set('matchedWords', $matchedWords);

            $this->saveStageResult($entityManager, $childId, $gameId, $score, $attemptsUsedInStage, $currentTheme, true);

            if (count($matchedWords) >= count($themeWords)) {
                $stagesCompleted++;
                $successfulStagesCompleted++;
                $session->set('stagesCompleted', $stagesCompleted);
                $session->set('successfulStagesCompleted', $successfulStagesCompleted);
                $successfulThemes[] = $currentTheme;
                $session->set('successfulThemes', $successfulThemes);

                if ($successfulStagesCompleted >= 3 && $difficulty !== '3') {
                    $newDifficulty = (int)$difficulty + 1;
                    $session->set('difficulty', $newDifficulty);
                    $this->saveLevelResult($entityManager, $childId, $gameId, $totalScore, $totalAttemptsUsed);
                    return $this->json([
                        'status' => 'next_level',
                        'message' => 'تهانينا! لقد انتقلت إلى المستوى التالي!',
                        'score' => $totalScore,
                        'attemptsLeft' => $attemptsLeft
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
                            'attemptsLeft' => 3,
                            'score' => $totalScore,
                            'correctMatch' => true,
                            'flashMessages' => ['success' => 'مطابقة صحيحة!']
                        ]);
                    }
                }

                $this->saveLevelResult($entityManager, $childId, $gameId, $totalScore, $totalAttemptsUsed);
                return $this->json([
                    'status' => 'game_over',
                    'score' => $totalScore,
                    'message' => 'اللعبة انتهت! النتيجة النهائية: ' . $totalScore,
                    'gif' => '/images/fun.gif'
                ]);
            }

            return $this->json([
                'status' => 'continue',
                'message' => 'مطابقة صحيحة!',
                'score' => $totalScore,
                'attemptsLeft' => $attemptsLeft,
                'words' => $shuffledWords,
                'synonyms' => $shuffledSynonyms,
                'correctMatch' => true,
                'flashMessages' => ['success' => 'مطابقة صحيحة!']
            ]);
        }

        $attemptsLeft--;
        $attemptsUsedInStage++;
        $totalAttemptsUsed++;
        $session->set('totalAttemptsUsed', $totalAttemptsUsed);

        $this->saveStageResult($entityManager, $childId, $gameId, 0, $attemptsUsedInStage, $currentTheme, false);

        if ($attemptsLeft <= 0) {
            $failedThemes[] = $currentTheme;
            $session->set('failedThemes', $failedThemes);
            $stagesCompleted++;
            $session->set('stagesCompleted', $stagesCompleted);

            return $this->json([
                'status' => 'game_over',
                'message' => 'لقد نفدت المحاولات! حاول مرة أخرى.',
                'score' => $totalScore,
                'attemptsLeft' => 0,
                'showNextButton' => true,
                'flashMessages' => ['error' => 'محاولة غير صحيحة!']
            ]);
        }

        return $this->json([
            'status' => 'continue',
            'message' => 'محاولة غير صحيحة، حاول مرة أخرى!',
            'score' => $totalScore,
            'attemptsLeft' => $attemptsLeft,
            'words' => $shuffledWords,
            'synonyms' => $shuffledSynonyms,
            'correctMatch' => false,
            'flashMessages' => ['error' => 'محاولة غير صحيحة!']
        ]);
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
        $child = $entityManager->getRepository(Child::class)->find($childId);
        $game = $entityManager->getRepository(Game::class)->find($gameId);

        if (!$child || !$game) {
            throw new \RuntimeException('Child or Game not found');
        }

        // Générer un ID unique pour la combinaison childId/gameId
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
    }

    private function saveLevelResult(EntityManagerInterface $entityManager, int $childId, int $gameId, int $totalScore, int $totalAttemptsUsed): void
    {
        $child = $entityManager->getRepository(Child::class)->find($childId);
        $game = $entityManager->getRepository(Game::class)->find($gameId);

        if (!$child || !$game) {
            throw new \RuntimeException('Child or Game not found');
        }

        // Générer un ID unique pour la combinaison childId/gameId
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
        $level->setScore($totalScore);
        $level->setNbtries($totalAttemptsUsed);

        $entityManager->persist($level);
        $entityManager->flush();
    }
}