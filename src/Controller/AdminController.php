<?php

namespace App\Controller;

use App\Entity\Jeudedevinette;
use App\Service\GoogleAIUtilService;
use App\Service\GuessingGameService;
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
    public function loadWords(Request $request): Response
    {
        $language = $request->query->get('language', 'en');
        $level = $request->query->getInt('level', 1);

        $words = $this->gameService->getWordsForAdmin($language, $level);

        return $this->render('Admin/dashboardAdmin.html.twig', [
            'words' => $words,
            'language' => $language,
            'level' => $level,
        ]);
    }

    #[Route('/generate', name: 'admin_generate_words', methods: ['POST'])]
    public function generateWords(Request $request): Response
    {
        $language = $request->request->get('language', 'en');
        $level = $request->request->get('level', '1');

        $words = $this->googleAIUtil->getWordsForLevelAndLanguage($level, $language);

        $this->addFlash('info', 'Generated words: ' . $words);

        return $this->redirectToRoute('admin_load_words', [
            'language' => $language,
            'level' => $level,
        ]);
    }

    #[Route('/add', name: 'admin_add_words', methods: ['POST'])]
    public function addWords(Request $request): Response
    {
        $rightWords = $request->request->get('rightWords');
        $wrongWord = $request->request->get('wrongWord');
        $theme = $request->request->get('theme');
        $language = $request->request->get('language', 'en');
        $level = $request->request->get('level', '1');

        $this->gameService->addLot($rightWords, $wrongWord, $theme, $language, $level);

        $this->addFlash('success', 'Words added successfully.');

        return $this->redirectToRoute('admin_load_words', [
            'language' => $language,
            'level' => $level,
        ]);
    }

    #[Route('/delete', name: 'admin_delete_words', methods: ['POST'])]
    public function deleteWords(Request $request): Response
    {
        $selectedEntries = $request->request->get('selectedEntries', []);
        $language = $request->request->get('language', 'en');
        $level = $request->request->get('level', '1');

        foreach ($selectedEntries as $entryData) {
            $this->gameService->deleteLot(
                $entryData['rightWord'],
                $entryData['wrongWord'],
                $entryData['theme']
            );
        }

        $this->addFlash('success', 'Selected words deleted successfully.');

        return $this->redirectToRoute('admin_load_words', [
            'language' => $language,
            'level' => $level,
        ]);
    }
}