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
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Security\Csrf\CsrfToken;

#[Route('/admin')]
class AdminController_match extends AbstractController
{
    #[Route('/themes', name: 'admin_themes', methods: ['GET'])]
    public function themes(EntityManagerInterface $entityManager, SessionInterface $session): Response
    {
        $language = $session->get('admin_filter_language');
        $level = $session->get('admin_filter_level');

        $queryBuilder = $entityManager->getRepository(Theme::class)->createQueryBuilder('t');
        if ($language) {
            $queryBuilder->andWhere('t.language = :language')->setParameter('language', $language);
        }
        if ($level) {
            $queryBuilder->andWhere('t.level = :level')->setParameter('level', $level);
        }

        $themes = $queryBuilder->getQuery()->getResult();

        return $this->render('admin/themes.html.twig', [
            'themes' => $themes,
            'filter_language' => $language,
            'filter_level' => $level,
        ]);
    }

    #[Route('/generate-themes', name: 'admin_generate_themes', methods: ['GET', 'POST'])]
    public function generateThemes(Request $request, GameService $gameService, SessionInterface $session): Response
    {
        $language = $request->request->get('language');
        $level = $request->request->get('level');
        $validLanguages = ['fr', 'en', 'de', 'es'];
        $validLevels = ['Facile', 'Moyen', 'Difficile'];

        if ($request->isMethod('POST')) {
            if (!in_array($language, $validLanguages) || !in_array($level, $validLevels)) {
                $this->addFlash('error', 'Langue ou niveau invalide.');
                return $this->redirectToRoute('admin_themes');
            }

            $themeCount = 5; // Default value
            $wordsPerTheme = 5; // Default value
            $themes = $gameService->generateThemes($language, $level, $themeCount, $wordsPerTheme);
            $session->set('admin_filter_language', $language);
            $session->set('admin_filter_level', $level);
            $this->addFlash('success', 'Thèmes générés avec succès. Veuillez les valider.');
            return $this->redirectToRoute('admin_themes');
        }

        return $this->render('admin/generate_themes.html.twig', [
            'validLanguages' => $validLanguages,
            'validLevels' => $validLevels,
        ]);
    }

    #[Route('/add-theme', name: 'admin_add_theme', methods: ['GET'])]
    public function addTheme(): Response
    {
        return $this->render('admin/add_theme.html.twig');
    }

    #[Route('/save-theme', name: 'admin_save_theme', methods: ['POST'])]
    public function saveTheme(Request $request, EntityManagerInterface $entityManager, CsrfTokenManagerInterface $csrfTokenManager): Response
    {
        // Validate CSRF token
        $token = new CsrfToken('save_theme', $request->request->get('_csrf_token'));
        if (!$csrfTokenManager->isTokenValid($token)) {
            $this->addFlash('error', 'Jeton CSRF invalide.');
            return $this->redirectToRoute('admin_add_theme');
        }

        $name = $request->request->get('name');
        $language = $request->request->get('language');
        $level = $request->request->get('level');
        $stage = $request->request->get('stage', 1);
        // Retrieve the 'words' parameter as an array
        $allParams = $request->request->all();
        $wordsData = isset($allParams['words']) && is_array($allParams['words']) ? $allParams['words'] : [];

        $validLanguages = ['fr', 'en', 'de', 'es'];
        $validLevels = ['Facile', 'Moyen', 'Difficile'];

        // Validation
        if (empty($name) || !in_array($language, $validLanguages) || !in_array($level, $validLevels)) {
            $this->addFlash('error', 'Données invalides. Vérifiez le nom, la langue ou le niveau.');
            return $this->redirectToRoute('admin_add_theme');
        }

        if (empty($wordsData)) {
            $this->addFlash('error', 'Vous devez ajouter au moins un mot.');
            return $this->redirectToRoute('admin_add_theme');
        }

        // Create new Theme entity
        $theme = new Theme();
        $theme->setName($name);
        $theme->setLanguage($language);
        $theme->setLevel($level);
        $theme->setStage((int)$stage);
        $theme->setIsValidated(false);

        // Process words
        foreach ($wordsData as $wordData) {
            // Ensure $wordData is an array with 'word' and 'synonym' keys
            if (is_array($wordData) && !empty($wordData['word']) && !empty($wordData['synonym'])) {
                $word = new Word();
                $word->setWord($wordData['word']);
                $word->setSynonym($wordData['synonym']);
                $word->setTheme($theme);
                $theme->addWord($word);
                $entityManager->persist($word);
            }
        }

        $entityManager->persist($theme);
        $entityManager->flush();

        $this->addFlash('success', 'Thème ajouté avec succès.');
        return $this->redirectToRoute('admin_themes');
    }

    #[Route('/validate-theme/{id}', name: 'admin_validate_theme', methods: ['POST'])]
    public function validateTheme(Theme $theme, EntityManagerInterface $entityManager, CsrfTokenManagerInterface $csrfTokenManager, Request $request): Response
    {
        $token = new CsrfToken('validate_theme', $request->request->get('_csrf_token'));
        if (!$csrfTokenManager->isTokenValid($token)) {
            $this->addFlash('error', 'Jeton CSRF invalide.');
            return $this->redirectToRoute('admin_themes');
        }

        $theme->setIsValidated(!$theme->isValidated());
        $entityManager->flush();
        $this->addFlash('success', sprintf('Le thème "%s" a été %s.', 
            $theme->getName(), 
            $theme->isValidated() ? 'validé' : 'dévalidé'
        ));
        return $this->redirectToRoute('admin_themes');
    }

    #[Route('/delete-theme/{id}', name: 'admin_delete_theme', methods: ['POST'])]
    public function deleteTheme(Theme $theme, EntityManagerInterface $entityManager, CsrfTokenManagerInterface $csrfTokenManager, Request $request): Response
    {
        $token = new CsrfToken('delete_theme', $request->request->get('_csrf_token'));
        if (!$csrfTokenManager->isTokenValid($token)) {
            $this->addFlash('error', 'Jeton CSRF invalide.');
            return $this->redirectToRoute('admin_themes');
        }

        $entityManager->remove($theme);
        $entityManager->flush();
        $this->addFlash('success', 'Thème supprimé avec succès.');
        return $this->redirectToRoute('admin_themes');
    }

    #[Route('/clear-filter', name: 'admin_clear_filter', methods: ['GET'])]
    public function clearFilter(SessionInterface $session): Response
    {
        $session->remove('admin_filter_language');
        $session->remove('admin_filter_level');
        return $this->redirectToRoute('admin_themes');
    }
}