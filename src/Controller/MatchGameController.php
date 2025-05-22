<?php

namespace App\Controller;

use App\Entity\Game;
use App\Entity\Level;
use App\Entity\Theme;
use App\Entity\Word;
use App\Entity\Child;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Annotation\Route;
use Psr\Log\LoggerInterface;

#[Route('/game')]
class MatchGameController extends AbstractController
{
    private const MAX_TRIES = 3;

    private const LEVEL_MAP = [
        1 => 'Facile',
        2 => 'Moyen',
        3 => 'Difficile',
    ];

    #[Route('/match/{childId}', name: 'game_match', methods: ['GET'], requirements: ['childId' => '\d+'])]
    public function index(int $childId, EntityManagerInterface $entityManager, SessionInterface $session, LoggerInterface $logger): Response
    {
        $logger->info('Starting game/match for childId: ' . $childId . ' at ' . date('Y-m-d H:i:s'));

        // Vérifier si l'enfant existe
        $child = $entityManager->getRepository(Child::class)->find($childId);
        if (!$child) {
            $logger->error('Child not found for ID: ' . $childId);
            $this->addFlash('error', 'Enfant non trouvé.');
            return $this->render('game/match.html.twig', [
                'theme' => null,
                'words' => [],
                'language' => null,
                'current_level' => null,
                'game_id' => null,
                'child_id' => $childId,
            ]);
        }
        $logger->info('Child found: ' . $child->getName() . ', language: ' . ($child->getLanguage() ?? 'null'));

        // Valider la langue
        $language = $child->getLanguage();
        $validLanguages = ['fr', 'en', 'de', 'es'];
        if (!$language || !in_array($language, $validLanguages)) {
            $logger->error('Unsupported or missing language for child ID: ' . $childId . ', Language: ' . ($language ?? 'null'));
            $this->addFlash('error', 'Langue non supportée pour cet utilisateur: ' . ($language ?? 'aucune langue définie'));
            return $this->render('game/match.html.twig', [
                'theme' => null,
                'words' => [],
                'language' => null,
                'current_level' => null,
                'game_id' => null,
                'child_id' => $childId,
            ]);
        }
        $logger->info('Language validated: ' . $language);

        // Initialiser la session
        $session->set('game_language', $language);
        $session->set('current_level', 1);
        $session->set('themes_completed', 0);
        $session->set('completed_theme_ids', []);
        $session->set('nb_tries', 0);
        $session->set('start_time', time());
        $session->set('current_theme_id', null);
        $session->set('child_id', $childId);
        $logger->info('Session initialized: level=1, completed_theme_ids=[]');

        // Utiliser le mappage pour obtenir la valeur de niveau dans la base de données
        $currentLevel = $session->get('current_level');
        $dbLevel = self::LEVEL_MAP[$currentLevel] ?? self::LEVEL_MAP[1];
        $logger->info('Current level: ' . $currentLevel . ', DB level: ' . $dbLevel);

        // Journaliser tous les thèmes pour débogage
        $allThemes = $entityManager->getRepository(Theme::class)->findAll();
        $logger->info('All themes in database: ' . json_encode(array_map(fn($t) => [
            'id' => $t->getId(),
            'name' => $t->getName(),
            'language' => $t->getLanguage(),
            'level' => $t->getLevel(),
            'is_validated' => $t->isValidated(),
            'stage' => $t->getStage(),
        ], $allThemes)));

        // Vérifier les données brutes de la base pour language, level, et is_validated
        $rawThemes = $entityManager->getConnection()->executeQuery(
            "SELECT DISTINCT language, level, is_validated FROM themes WHERE language = ? AND level = ? AND is_validated = ?",
            [$language, $dbLevel, 1]
        )->fetchAllAssociative();
        $logger->info('Raw themes from DB with language=' . $language . ', level=' . $dbLevel . ': ' . json_encode($rawThemes));

        // Récupérer les thèmes avec le niveau correct (avec comparaison insensible à la casse)
        $completedThemeIds = $session->get('completed_theme_ids', []);
        $logger->info('Querying themes with parameters: language=' . $language . ', level=' . $dbLevel . ', completedThemeIds=' . json_encode($completedThemeIds));

        try {
            $query = $entityManager->getRepository(Theme::class)
                ->createQueryBuilder('t')
                ->where('t.isValidated = :isValidated')
                ->andWhere('t.language = :language')
                ->andWhere('LOWER(t.level) = LOWER(:level)')
                ->andWhere('t.id NOT IN (:completedThemeIds)')
                ->setParameter('isValidated', true)
                ->setParameter('language', $language)
                ->setParameter('level', $dbLevel)
                ->setParameter('completedThemeIds', $completedThemeIds ?: [-1]);
            $sql = $query->getQuery()->getSQL();
            $logger->info('Generated SQL query: ' . $sql);
            $themes = $query->getQuery()->getResult();
            $logger->info('Query returned ' . count($themes) . ' themes: ' . json_encode(array_map(fn($t) => [
                'id' => $t->getId(),
                'name' => $t->getName(),
                'language' => $t->getLanguage(),
                'level' => $t->getLevel(),
                'is_validated' => $t->isValidated(),
                'stage' => $t->getStage(),
            ], $themes)));
        } catch (\Exception $e) {
            $logger->error('Theme query failed: ' . $e->getMessage());
            $this->addFlash('error', 'Erreur lors de la récupération des thèmes: ' . $e->getMessage());
            return $this->render('game/match.html.twig', [
                'theme' => null,
                'words' => [],
                'language' => $language,
                'current_level' => $currentLevel,
                'game_id' => null,
                'child_id' => $childId,
            ]);
        }

        // Requête de secours si aucun thème n'est trouvé
        if (empty($themes)) {
            $logger->warning('No themes found, attempting fallback query');
            try {
                $query = $entityManager->getRepository(Theme::class)
                    ->createQueryBuilder('t')
                    ->where('t.isValidated = :isValidated')
                    ->andWhere('t.language = :language')
                    ->setParameter('isValidated', true)
                    ->setParameter('language', $language);
                $sql = $query->getQuery()->getSQL();
                $logger->info('Fallback SQL query: ' . $sql);
                $themes = $query->getQuery()->getResult();
                $logger->info('Fallback query returned ' . count($themes) . ' themes: ' . json_encode(array_map(fn($t) => [
                    'id' => $t->getId(),
                    'name' => $t->getName(),
                    'language' => $t->getLanguage(),
                    'level' => $t->getLevel(),
                    'is_validated' => $t->isValidated(),
                    'stage' => $t->getStage(),
                ], $themes)));
            } catch (\Exception $e) {
                $logger->error('Fallback theme query failed: ' . $e->getMessage());
                $this->addFlash('error', 'Erreur lors de la récupération des thèmes (fallback): ' . $e->getMessage());
                return $this->render('game/match.html.twig', [
                    'theme' => null,
                    'words' => [],
                    'language' => $language,
                    'current_level' => $currentLevel,
                    'game_id' => null,
                    'child_id' => $childId,
                ]);
            }
        }

        // Gérer l'absence de thèmes
        if (empty($themes)) {
            $logger->error('No themes available even after fallback for language: ' . $language . ', level: ' . $dbLevel);
            $this->addFlash('error', 'Aucun thème validé disponible pour ce niveau.');
            return $this->render('game/match.html.twig', [
                'theme' => null,
                'words' => [],
                'language' => $language,
                'current_level' => $currentLevel,
                'game_id' => null,
                'child_id' => $childId,
            ]);
        }

        // Sélectionner un thème aléatoire
        $theme = $themes[array_rand($themes)];
        $session->set('current_theme_id', $theme->getId());
        $words = $theme->getWords()->toArray();
        $logger->info('Selected theme: ' . $theme->getId() . ' (' . $theme->getName() . ')');

        // Préparer les données des mots avec word et synonym maintenus ensemble
        $wordData = array_map(function ($word) {
            return [
                'id' => $word->getId(),
                'word' => $word->getWord(),
                'synonym' => $word->getSynonym(),
            ];
        }, $words);

        // Mélanger l'ordre des paires word-synonym
        shuffle($wordData);
        $logger->info('Prepared and shuffled word data: ' . json_encode($wordData));

        // S'assurer que l'entité Game existe
        try {
            $game = $entityManager->getRepository(Game::class)->find(4);
            if (!$game) {
                $game = new Game();
                $game->setId(4);
                $game->setName('Match Game');
                $entityManager->persist($game);
                $entityManager->flush();
                $logger->info('New Game created with ID: 4');
            } else {
                $logger->info('Existing Game found with ID: 4');
            }
        } catch (\Exception $e) {
            $logger->error('Game creation/retrieval failed: ' . $e->getMessage());
            $this->addFlash('error', 'Erreur lors de la création ou récupération du jeu: ' . $e->getMessage());
            return $this->render('game/match.html.twig', [
                'theme' => null,
                'words' => [],
                'language' => $language,
                'current_level' => $currentLevel,
                'game_id' => null,
                'child_id' => $childId,
            ]);
        }

        // Rendre le template
        $logger->info('Rendering template: game/match.html.twig');
        return $this->render('game/match.html.twig', [
            'theme' => $theme,
            'words' => $wordData,
            'language' => $language,
            'current_level' => $currentLevel,
            'game_id' => $game->getId(),
            'child_id' => $childId,
        ]);
    }

    #[Route('/match/check/{childId}', name: 'game_match_check', methods: ['POST'], requirements: ['childId' => '\d+'])]
    public function checkMatch(int $childId, Request $request, EntityManagerInterface $entityManager, SessionInterface $session, LoggerInterface $logger): JsonResponse
    {
        $logger->info('Checking match for childId: ' . $childId);

        $child = $entityManager->getRepository(Child::class)->find($childId);
        if (!$child) {
            $logger->error('Child not found for ID: ' . $childId);
            return new JsonResponse([
                'success' => false,
                'message' => 'Enfant non trouvé.',
            ], Response::HTTP_BAD_REQUEST);
        }

        $wordId = $request->request->getInt('wordId');
        $synonymId = $request->request->getInt('synonymId');

        $word = $entityManager->getRepository(Word::class)->find($wordId);
        $synonym = $entityManager->getRepository(Word::class)->find($synonymId);

        if (!$word || !$synonym || $word->getTheme() !== $synonym->getTheme()) {
            $logger->warning('Invalid selection or words from different themes');
            return new JsonResponse([
                'success' => false,
                'message' => 'Sélection invalide ou mots de thèmes différents.',
                'tries_left' => self::MAX_TRIES - $session->get('nb_tries', 0),
            ], Response::HTTP_BAD_REQUEST);
        }

        $nbTries = $session->get('nb_tries', 0) + 1;
        $session->set('nb_tries', $nbTries);

        $isCorrect = $word->getId() === $synonymId;

        if ($nbTries >= self::MAX_TRIES && !$isCorrect) {
            $logger->info('Max tries reached, repeating theme');
            return new JsonResponse([
                'success' => false,
                'message' => 'Échec ! Nombre maximum d\'essais atteint. Réessayez ce thème.',
                'tries_left' => 0,
                'repeat' => true,
            ]);
        }

        $logger->info('Check result: ' . ($isCorrect ? 'Correct' : 'Incorrect'));
        return new JsonResponse([
            'success' => $isCorrect,
            'message' => $isCorrect ? 'Succès ! Bonne correspondance.' : 'Échec ! Ce n\'est pas le bon synonyme.',
            'tries_left' => self::MAX_TRIES - $nbTries,
        ]);
    }

    #[Route('/match/complete/{childId}', name: 'game_match_complete', methods: ['POST'], requirements: ['childId' => '\d+'])]
    public function completeTheme(int $childId, Request $request, EntityManagerInterface $entityManager, SessionInterface $session, LoggerInterface $logger): JsonResponse
    {
        $logger->info('Completing theme for childId: ' . $childId);

        $child = $entityManager->getRepository(Child::class)->find($childId);
        if (!$child) {
            $logger->error('Child not found for ID: ' . $childId);
            return new JsonResponse([
                'success' => false,
                'message' => 'Enfant non trouvé.',
            ], Response::HTTP_BAD_REQUEST);
        }

        $themeId = $request->request->getInt('themeId');
        $gameId = $request->request->getInt('gameId');
        $nbTries = $request->request->getInt('nbTries');
        $time = $request->request->getInt('time');
        $allCorrect = $request->request->getBoolean('allCorrect');

        $theme = $entityManager->getRepository(Theme::class)->find($themeId);
        $game = $entityManager->getRepository(Game::class)->find($gameId);

        if (!$theme || !$game) {
            $logger->error('Invalid theme or game: themeId=' . $themeId . ', gameId=' . $gameId);
            return new JsonResponse([
                'success' => false,
                'message' => 'Thème ou jeu invalide.',
            ], Response::HTTP_BAD_REQUEST);
        }

        if (!$allCorrect || $nbTries > self::MAX_TRIES) {
            $session->set('nb_tries', 0);
            $session->set('start_time', time());
            $logger->info('Theme not completed successfully, repeating');
            return new JsonResponse([
                'success' => false,
                'message' => 'Échec ! Réessayez ce thème.',
                'repeat' => true,
                'themeId' => $themeId,
            ]);
        }

        $score = match ($nbTries) {
            1 => 1,
            2 => 3,
            3 => 5,
            default => 0,
        };

        try {
            $level = new Level();
            $level->setId($themeId);
            $level->setChildId($child);
            $level->setGameId($game);
            $level->setScore($score);
            $level->setNbtries($nbTries);
            $level->setTime($time);
            $entityManager->persist($level);
            $entityManager->flush();
            $logger->info('Level recorded: themeId=' . $themeId);
        } catch (\Exception $e) {
            $logger->error('Level creation failed: ' . $e->getMessage());
            return new JsonResponse([
                'success' => false,
                'message' => 'Erreur lors de l\'enregistrement du niveau: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        $themesCompleted = $session->get('themes_completed', 0) + 1;
        $currentLevel = $session->get('current_level', 1);
        $completedThemeIds = $session->get('completed_theme_ids', []);
        $completedThemeIds[] = $themeId;

        $session->set('themes_completed', $themesCompleted);
        $session->set('completed_theme_ids', $completedThemeIds);
        $session->set('current_theme_id', null);

        $nextLevel = null;
        if ($themesCompleted >= 10) {
            if ($currentLevel === 1) {
                $nextLevel = 2;
            } elseif ($currentLevel === 2) {
                $nextLevel = 3;
            }
            if ($nextLevel) {
                $session->set('current_level', $nextLevel);
                $session->set('themes_completed', 0);
                $session->set('completed_theme_ids', []);
            }
        }

        $dbLevel = self::LEVEL_MAP[$currentLevel] ?? self::LEVEL_MAP[1];

        $themes = $entityManager->getRepository(Theme::class)
            ->createQueryBuilder('t')
            ->where('t.isValidated = :isValidated')
            ->andWhere('t.language = :language')
            ->andWhere('t.level = :level')
            ->andWhere('t.id NOT IN (:completedThemeIds)')
            ->setParameter('isValidated', 1)
            ->setParameter('language', $session->get('game_language'))
            ->setParameter('level', $dbLevel)
            ->setParameter('completedThemeIds', $completedThemeIds ?: [-1])
            ->getQuery()
            ->getResult();

        $nextThemeId = !empty($themes) ? $themes[array_rand($themes)]->getId() : null;

        $logger->info('Theme completed: nextThemeId=' . ($nextThemeId ?? 'none') . ', nextLevel=' . ($nextLevel ?? 'none'));
        return new JsonResponse([
            'success' => true,
            'message' => $nextLevel ? "Félicitations ! Vous passez au niveau $nextLevel !" : 'Thème terminé !',
            'nextThemeId' => $nextThemeId,
            'nextLevel' => $nextLevel,
            'childId' => $childId,
        ]);
    }
}