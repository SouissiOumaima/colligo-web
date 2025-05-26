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

    /**
     * Convertit la langue de l'enfant (ex. "Français", "Anglais") en code de langue (ex. "francais", "anglais").
     *
     * @param string|null $childLanguage La langue de l'enfant
     * @return string|null Le code de langue correspondant ou null si non supporté
     */
    private function convertChildLanguageToCode(?string $childLanguage): ?string
    {
        if (!$childLanguage) {
            return null;
        }

        $languageMap = [
            'francais' => 'francais',
            'anglais' => 'anglais',
            'allemand' => 'allemand',
            'espagnol' => 'espagnol',
        ];

        $childLanguageLower = strtolower(iconv('UTF-8', 'ASCII//TRANSLIT', $childLanguage));
        return $languageMap[$childLanguageLower] ?? null;
    }

    /**
     * Normalise les codes de langue pour la base de données (ex. "francais" -> "fr") ou retourne le code tel quel si déjà un code court.
     * Retourne le code court utilisé dans la base de données ou null si non supporté.
     *
     * @param string|null $appLanguage Le code de langue de l'application (ex. "francais")
     * @return string|null Le code court de la base de données (ex. "fr") ou null si non supporté
     */
    private function normalizeDatabaseLanguage(?string $appLanguage): ?string
    {
        if (!$appLanguage) {
            return null;
        }

        $languageMap = [
            'francais' => 'fr',
            'anglais' => 'en',
            'allemand' => 'de',
            'espagnol' => 'es',
        ];

        $appLanguageLower = strtolower($appLanguage);
        return $languageMap[$appLanguageLower] ?? null;
    }

    #[Route('/match/{childId}', name: 'game_match', methods: ['GET'], requirements: ['childId' => '\d+'])]
    public function index(int $childId, EntityManagerInterface $entityManager, SessionInterface $session, LoggerInterface $logger): Response
    {
        $logger->info('Starting Game/match for childId: ' . $childId . ' at ' . date('H:i:s'));
    
        try {
            // Vérifier si l'enfant existe
            $child = $entityManager->getRepository(Child::class)->find($childId);
            if (!$child) {
                $logger->error('Child not found for ID: ' . $childId);
                $this->addFlash('error', 'Enfant non trouvé.');
                return $this->render('Game/match.html.twig', [
                    'theme' => null,
                    'words' => [],
                    'synonyms' => [],
                    'language' => null,
                    'current_level' => null,
                    'game_id' => null,
                    'child_id' => $childId,
                ]);
            }
            $logger->info('Child found: ' . $child->getName() . ', raw language: ' . ($child->getLanguage() ?? 'null'));
    
            // Convertir la langue de l'enfant en code de langue
            $language = $this->convertChildLanguageToCode($child->getLanguage());
            $validLanguages = ['francais', 'anglais', 'allemand', 'espagnol'];
            if (!$language || !in_array($language, $validLanguages)) {
                $logger->error('Unsupported or missing language for child ID: ' . $childId . ', Language: ' . ($language ?? 'null'));
                $this->addFlash('error', 'Langue non supportée pour cet utilisateur: ' . ($child->getLanguage() ?? 'aucune langue définie'));
                return $this->render('Game/match.html.twig', [
                    'theme' => null,
                    'words' => [],
                    'synonyms' => [],
                    'language' => null,
                    'current_level' => null,
                    'game_id' => null,
                    'child_id' => $childId,
                ]);
            }
            $logger->info('Language validated: ' . $language);
    
            // Initialiser la session
            $session->set('game_language', $language);
            $session->set('current_level', $session->get('current_level', 1));
            $session->set('themes_completed', $session->get('themes_completed', 0));
            $session->set('completed_theme_ids', $session->get('completed_theme_ids', []));
            $session->set('nb_tries', 0);
            $session->set('start_time', time());
            $session->set('current_theme_id', null);
            $session->set('child_id', $childId);
            $session->set('cumulative_score', $session->get('cumulative_score', 0));
            $logger->info('Session initialized: level=' . $session->get('current_level') . ', themes_completed=' . $session->get('themes_completed') . ', cumulative_score=' . $session->get('cumulative_score'));
    
            // Utiliser le mappage pour obtenir la valeur de niveau dans la base de données
            $currentLevel = $session->get('current_level');
            $dbLevel = self::LEVEL_MAP[$currentLevel] ?? self::LEVEL_MAP[1];
            $logger->info('Current level: ' . $currentLevel . ', DB level: ' . $dbLevel);
    
            // Normaliser la langue pour la requête
            $dbLanguage = $this->normalizeDatabaseLanguage($language);
            if (!$dbLanguage) {
                $logger->error('Failed to normalize language: ' . $language);
                $this->addFlash('error', 'Erreur de normalisation de la langue.');
                return $this->render('Game/match.html.twig', [
                    'theme' => null,
                    'words' => [],
                    'synonyms' => [],
                    'language' => $language,
                    'current_level' => $currentLevel,
                    'game_id' => null,
                    'child_id' => $childId,
                ]);
            }
            $logger->info('Normalized database language: ' . $dbLanguage);
    
            // Récupérer les thèmes
            $completedThemeIds = $session->get('completed_theme_ids', []);
            $logger->info('Querying themes with parameters: language=' . $dbLanguage . ', level=' . $dbLevel . ', completedThemeIds=' . json_encode($completedThemeIds));
    
            $themes = $entityManager->getRepository(Theme::class)
                ->createQueryBuilder('t')
                ->where('t.isValidated = :isValidated')
                ->andWhere('t.language = :language')
                ->andWhere('LOWER(t.level) = LOWER(:level)')
                ->andWhere('t.id NOT IN (:completedThemeIds)')
                ->setParameter('isValidated', true)
                ->setParameter('language', $dbLanguage)
                ->setParameter('level', $dbLevel)
                ->setParameter('completedThemeIds', $completedThemeIds ?: [-1])
                ->getQuery()
                ->getResult();
    
            if (empty($themes)) {
                $logger->warning('No themes found, attempting fallback query');
                $themes = $entityManager->getRepository(Theme::class)
                    ->createQueryBuilder('t')
                    ->where('t.isValidated = :isValidated')
                    ->andWhere('t.language = :language')
                    ->setParameter('isValidated', true)
                    ->setParameter('language', $dbLanguage)
                    ->getQuery()
                    ->getResult();
            }
    
            if (empty($themes)) {
                $logger->error('No themes available even after fallback for language: ' . $dbLanguage . ', level: ' . $dbLevel);
                $this->addFlash('error', 'Aucun thème validé disponible pour ce niveau.');
                return $this->render('Game/match.html.twig', [
                    'theme' => null,
                    'words' => [],
                    'synonyms' => [],
                    'language' => $language,
                    'current_level' => $currentLevel,
                    'game_id' => null,
                    'child_id' => $childId,
                ]);
            }
    
            $theme = $themes[array_rand($themes)];
            $session->set('current_theme_id', $theme->getId());
            $words = $theme->getWords()->toArray();
            $logger->info('Selected theme: ' . $theme->getId() . ' (' . $theme->getName() . ')');
    
            // Prepare separate arrays for words and synonyms
            $wordData = array_map(function ($word) {
                return [
                    'id' => $word->getId(),
                    'word' => $word->getWord(),
                ];
            }, $words);
            $synonymData = array_map(function ($word) {
                return [
                    'id' => $word->getId(),
                    'synonym' => $word->getSynonym(),
                ];
            }, $words);
    
            // Shuffle words and synonyms independently
            shuffle($wordData);
            shuffle($synonymData);
            $logger->info('Prepared and shuffled word data: ' . json_encode($wordData) . ', synonym data: ' . json_encode($synonymData));
    
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
                return $this->render('Game/match.html.twig', [
                    'theme' => null,
                    'words' => [],
                    'synonyms' => [],
                    'language' => $language,
                    'current_level' => $currentLevel,
                    'game_id' => null,
                    'child_id' => $childId,
                ]);
            }
    
            return $this->render('Game/match.html.twig', [
                'theme' => $theme,
                'words' => $wordData,
                'synonyms' => $synonymData,
                'language' => $language,
                'current_level' => $currentLevel,
                'game_id' => $game->getId(),
                'child_id' => $childId,
                'cumulative_score' => $session->get('cumulative_score', 0),
            ]);
        } catch (\Exception $e) {
            $logger->error('Exception in index action: ' . $e->getMessage());
            $this->addFlash('error', 'Une erreur est survenue: ' . $e->getMessage());
            return $this->render('Game/match.html.twig', [
                'theme' => null,
                'words' => [],
                'synonyms' => [],
                'language' => null,
                'current_level' => null,
                'game_id' => null,
                'child_id' => $childId,
            ]);
        }
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

        $nbTries = $session->get('nb_tries', 0);
        $isCorrect = $word->getId() === $synonymId;

        if (!$isCorrect) {
            $nbTries++;
            $session->set('nb_tries', $nbTries);
            $logger->info('Incorrect match, nbTries incremented to: ' . $nbTries);
        } else {
            $logger->info('Correct match, nbTries remains: ' . $nbTries);
        }

        if ($nbTries > self::MAX_TRIES) {
            $logger->info('Max tries exceeded, repeating theme');
            $session->set('nb_tries', 0);
            return new JsonResponse([
                'success' => false,
                'message' => 'Échec ! Nombre maximum d\'essais dépassé.',
                'tries_left' => 0,
                'repeat' => true,
            ]);
        }

        $logger->info('Check result: ' . ($isCorrect ? 'Correct' : 'Incorrect') . ', tries_left: ' . (self::MAX_TRIES - $nbTries));
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

        // Vérifier si le nombre d'essais dépasse la limite
        if ($nbTries > self::MAX_TRIES) {
            $session->set('nb_tries', 0);
            $session->set('start_time', time());
            $logger->info('Theme not completed successfully, repeating due to max tries exceeded');
            return new JsonResponse([
                'success' => false,
                'message' => 'Échec ! Trop d\'erreurs.',
                'repeat' => true,
                'themeId' => $themeId,
            ]);
        }

        // Si toutes les paires ne sont pas correctes, demander de réessayer
        if (!$allCorrect) {
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

        $mistakes = $nbTries;
        $stageScore = match (true) {
            $mistakes === 0 => 5,
            $mistakes === 1 => 3,
            $mistakes >= 2 => 1,
            default => 0,
        };

        // Add new stage score to cumulative score
        $cumulativeScore = $session->get('cumulative_score', 0) + $stageScore;
        $session->set('cumulative_score', $cumulativeScore);

        try {
            // Check if a record already exists for this themeId and gameId
            $existingLevel = $entityManager->getRepository(Level::class)
                ->findOneBy([
                    'id' => $themeId,
                    'gameId' => $game,
                ]);

            if ($existingLevel) {
                // Update the existing record
                $existingLevel->setScore($cumulativeScore);
                $existingLevel->setNbtries($nbTries);
                $existingLevel->setTime($time);
                $existingLevel->setChildId($child);
                $entityManager->flush();
                $logger->info('Level updated: themeId=' . $themeId . ', gameId=' . $gameId . ', childId=' . $childId . ', cumulative_score=' . $cumulativeScore);
            } else {
                // Insert a new record
                $level = new Level();
                $level->setId($themeId);
                $level->setChildId($child);
                $level->setGameId($game);
                $level->setScore($cumulativeScore);
                $level->setNbtries($nbTries);
                $level->setTime($time);
                $entityManager->persist($level);
                $entityManager->flush();
                $logger->info('Level created: themeId=' . $themeId . ', gameId=' . $gameId . ', childId=' . $childId . ', cumulative_score=' . $cumulativeScore);
            }
        } catch (\Exception $e) {
            $logger->error('Level creation/update failed: ' . $e->getMessage());
            return new JsonResponse([
                'success' => false,
                'message' => 'Erreur lors de l\'enregistrement du niveau: ' . $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        $themesCompleted = $session->get('themes_completed', 0) + 1;
        $currentLevel = $session->get('current_level', 1);
        $completedThemeIds = $session->get('completed_theme_ids', []);
        $completedThemeIds[] = $themeId;

        $session->set('themes_completed', $themesCompleted);
        $session->set('completed_theme_ids', $completedThemeIds);
        $session->set('current_theme_id', null);
        $session->set('nb_tries', 0);

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
                $session->set('cumulative_score', 0); // Reset cumulative score when level changes
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
            ->setParameter('language', $this->normalizeDatabaseLanguage($session->get('game_language')))
            ->setParameter('level', $dbLevel)
            ->setParameter('completedThemeIds', $completedThemeIds ?: [-1])
            ->getQuery()
            ->getResult();

        $nextThemeId = !empty($themes) ? $themes[array_rand($themes)]->getId() : null;

        $logger->info('Theme completed: nextThemeId=' . ($nextThemeId ?? 'none') . ', nextLevel=' . ($nextLevel ?? 'none') . ', cumulative_score=' . $cumulativeScore);
        return new JsonResponse([
            'success' => true,
            'message' => $nextLevel ? "Félicitations ! Vous passez au niveau $nextLevel !" : 'Thème terminé avec succès !',
            'nextThemeId' => $nextThemeId,
            'nextLevel' => $nextLevel,
            'childId' => $childId,
            'score' => $cumulativeScore,
        ]);
    }
}