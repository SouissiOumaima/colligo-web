<?php

namespace App\Controller;

use App\Service\WordGameService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Annotation\Route;

class GameController extends AbstractController
{
    #[Route('/main', name: 'main_menu')]
    public function mainMenu(WordGameService $wordGameService): Response
    {
        return $this->render('game/main_menu.html.twig', [
            'highestLevel' => $wordGameService->getHighestLevelReached(),
            'childId' => 3, // Default or dynamic childId
            'gameId' => 3,  // Default gameId for "لعبة الصور"
            'selectedLevel' => 1, // Default level
        ]);
    }

    #[Route('/start/{level}', name: 'start_game', requirements: ['level' => '\d+'])]
    public function startGame(int $level, WordGameService $wordGameService, Request $request): Response
    {
        $childId = $request->query->getInt('childId', 1);
        $gameId = $request->query->getInt('gameId', 3);

        error_log("startGame: childId=$childId, gameId=$gameId, level=$level");

        if ($childId <= 0 || $gameId < 1 || $gameId > 5) {
            throw new BadRequestHttpException('Invalid childId or gameId.');
        }

        $wordGameService->setChildId($childId);
        $wordGameService->setGameId($gameId);
        $wordGameService->startGame($level);

        return $this->renderGameScreen($wordGameService, $childId, $gameId);
    }

    #[Route('/check', name: 'check_answer', methods: ['POST'])]
    public function checkAnswer(Request $request, WordGameService $wordGameService): Response
    {
        $childId = $request->request->getInt('child_id', 1); // Changed from 1 to 3
        $gameId = $request->request->getInt('game_id', 3);
        $selectedImageUrl = $request->request->get('image_url');
        $timeout = $request->request->getBoolean('timeout');

        error_log("checkAnswer: childId=$childId, gameId=$gameId, timeout=$timeout");

        if ($childId <= 0 || $gameId < 1 || $gameId > 5) {
            throw new BadRequestHttpException('Invalid childId or gameId.');
        }

        $wordGameService->setChildId($childId);
        $wordGameService->setGameId($gameId);

        if ($timeout) {
            $result = [
                'isCorrect' => false,
                'points' => 0,
                'timeout' => true,
            ];
        } else {
            if (!$selectedImageUrl) {
                throw new BadRequestHttpException('Missing selectedImageUrl parameter.');
            }
            $result = $wordGameService->checkAnswer($selectedImageUrl);
        }

        return $this->render('game/game_screen.html.twig', array_merge($result, [
            'currentImages' => $wordGameService->getCurrentImages(),
            'correctWord' => $wordGameService->getCorrectWord(),
            'currentLevel' => $wordGameService->getCurrentLevel(),
            'currentStage' => $wordGameService->getCurrentStage(),
            'currentScore' => $wordGameService->getCurrentLevelPoints(),
            'childId' => $childId,
            'gameId' => $gameId,
        ]));
    }

    #[Route('/proceed', name: 'proceed_or_retry', methods: ['POST'])]
    public function proceedOrRetry(Request $request, WordGameService $wordGameService): Response
    {
        $childId = $request->request->getInt('child_id', 1);
        $gameId = $request->request->getInt('game_id', 3);
        $isCorrect = $request->request->getBoolean('is_correct');

        error_log("proceedOrRetry: childId=$childId, gameId=$gameId, isCorrect=$isCorrect");

        if ($childId <= 0 || $gameId < 1 || $gameId > 5) {
            throw new BadRequestHttpException('Invalid childId or gameId.');
        }

        $wordGameService->setChildId($childId);
        $wordGameService->setGameId($gameId);
        $completed = $wordGameService->proceedOrRetry($isCorrect);

        if ($completed) {
            return $this->redirectToRoute('main_menu');
        }

        return $this->renderGameScreen($wordGameService, $childId, $gameId);
    }

    private function renderGameScreen(WordGameService $wordGameService, int $childId, int $gameId): Response
    {
        return $this->render('game/game_screen.html.twig', [
            'currentImages' => $wordGameService->getCurrentImages(),
            'correctWord' => $wordGameService->getCorrectWord(),
            'currentLevel' => $wordGameService->getCurrentLevel(),
            'currentStage' => $wordGameService->getCurrentStage(),
            'currentScore' => $wordGameService->getCurrentLevelPoints(),
            'childId' => $childId,
            'gameId' => $gameId,
            'isCorrect' => null,
        ]);
    }
}