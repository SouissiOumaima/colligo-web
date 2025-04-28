<?php

namespace App\Controller;

use App\Entity\Jeudedevinette;
use App\Service\GoogleAIUtilService;
use App\Service\GuessingGameService;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/admin')]
class AdminController extends AbstractController
{
    private GuessingGameService $gameService;
    private GoogleAIUtilService $googleAIUtil;

    public function __construct(GuessingGameService $gameService, GoogleAIUtilService $googleAIUtil)
    {
        $this->gameService = $gameService;
        $this->googleAIUtil = $googleAIUtil;
    }

    #[Route('/words', name: 'admin_load_words', methods: ['GET'])]
    public function loadWords(Request $request, LoggerInterface $logger): Response
    {
        $language = $request->query->get('language', 'français');
        $level = $request->query->get('level', '1');

        $logger->info('Loading words for admin', [
            'language' => $language,
            'level' => $level,
        ]);

        $words = $this->gameService->getWordsForAdmin($language, $level);

        return $this->render('Admin/dashboardAdmin.html.twig', [
            'words' => $words,
            'language' => $language,
            'level' => $level,
        ]);
    }

    #[Route('/generate', name: 'admin_generate_words', methods: ['POST'])]
    public function generateWords(Request $request, LoggerInterface $logger): Response
    {
        try {
            $language = $request->request->get('language', 'français');
            $level = $request->request->get('level', '1');

            $logger->info('Generating words', [
                'language' => $language,
                'level' => $level,
            ]);

            $words = $this->googleAIUtil->getWordsForLevelAndLanguage($level, $language);

            $this->addFlash('info', 'Mots générés avec succès.');

            return $this->redirectToRoute('admin_load_words', [
                'language' => $language,
                'level' => $level,
            ]);
        } catch (\Throwable $e) {
            $logger->error('Erreur lors de la génération des mots', [
                'exception' => $e->getMessage(),
                'language' => $language,
                'level' => $level,
                'trace' => $e->getTraceAsString(),
            ]);
            $this->addFlash('error', 'Erreur lors de la génération : ' . $e->getMessage());

            return $this->redirectToRoute('admin_load_words', [
                'language' => $language,
                'level' => $level,
            ]);
        }
    }

    #[Route('/add', name: 'admin_add_words', methods: ['POST'])]
    public function addWords(Request $request, LoggerInterface $logger): Response
    {
        try {
            $rightWords = $request->request->get('rightWords');
            $wrongWord = $request->request->get('wrongWord');
            $theme = $request->request->get('theme');
            $language = $request->request->get('language', 'français');
            $level = $request->request->get('level', '1');

            $logger->info('Adding words', [
                'rightWords' => $rightWords,
                'wrongWord' => $wrongWord,
                'theme' => $theme,
                'language' => $language,
                'level' => $level,
            ]);

            if (empty($rightWords) || empty($wrongWord) || empty($theme)) {
                $this->addFlash('error', 'Tous les champs doivent être remplis.');
                return $this->redirectToRoute('admin_load_words', [
                    'language' => $language,
                    'level' => $level,
                ]);
            }

            $this->gameService->addLot($rightWords, $wrongWord, $theme, $language, $level);

            $this->addFlash('success', 'Mots ajoutés avec succès.');

            return $this->redirectToRoute('admin_load_words', [
                'language' => $language,
                'level' => $level,
            ]);
        } catch (\Throwable $e) {
            $logger->error('Erreur lors de l\'ajout des mots', [
                'exception' => $e->getMessage(),
                'language' => $language,
                'level' => $level,
                'trace' => $e->getTraceAsString(),
            ]);
            $this->addFlash('error', 'Erreur lors de l\'ajout : ' . $e->getMessage());

            return $this->redirectToRoute('admin_load_words', [
                'language' => $language,
                'level' => $level,
            ]);
        }
    }

    // src/Controller/AdminController.php

    #[Route('/delete', name: 'admin_delete_words', methods: ['POST'])]
    public function deleteWords(Request $request, LoggerInterface $logger): Response
    {
        $language = $request->request->get('language', 'français');
        $level = $request->request->get('level', '1');

        try {
            $requestData = $request->request->all();
            $selectedEntries = $requestData['selectedEntries'] ?? [];

            if (!is_array($selectedEntries) || empty($selectedEntries)) {
                $this->addFlash('error', 'Aucune entrée sélectionnée pour la suppression.');
                return $this->redirectToRoute('admin_load_words', [
                    'language' => $language,
                    'level' => $level,
                ]);
            }

            $deletedCount = 0;
            foreach ($selectedEntries as $entryData) {
                if (is_string($entryData)) {
                    $entryData = json_decode($entryData, true);
                }

                if (
                    !is_array($entryData) ||
                    !isset($entryData['rightWord'], $entryData['wrongWord'], $entryData['theme'])
                ) {
                    continue;
                }

                $this->gameService->deleteLot(
                    $entryData['rightWord'],
                    $entryData['wrongWord'],
                    $entryData['theme'],
                    $language,
                    $level,
                    $logger
                );
                $deletedCount++;
            }

            if ($deletedCount > 0) {
                $this->addFlash('success', "$deletedCount entrée(s) supprimée(s) avec succès.");
            } else {
                $this->addFlash('warning', 'Aucune entrée valide n\'a été trouvée pour suppression.');
            }

            return $this->redirectToRoute('admin_load_words', [
                'language' => $language,
                'level' => $level,
            ]);
        } catch (\Throwable $e) {
            $logger->error('Erreur lors de la suppression des mots', [
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            $this->addFlash('error', 'Erreur lors de la suppression : ' . $e->getMessage());
            return $this->redirectToRoute('admin_load_words', [
                'language' => $language,
                'level' => $level,
            ]);
        }
    }
}