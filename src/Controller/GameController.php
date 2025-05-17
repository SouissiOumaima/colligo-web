<?php

namespace App\Controller;

use App\Service\GameService;
use App\Service\ProgressService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Controller for handling game-related actions such as starting, checking answers, and progressing.
 */
class GameController extends AbstractController
{
    #[Route('/main', name: 'main_menu')]
    public function mainMenu(GameService $gameService, ProgressService $progressService): Response
    {
        $childId = 1;
        $gameId = 3;
        $childDetails = $progressService->fetchChildDetails($childId);
        $progressService->ensureChildExists($childId, $childDetails['parentId'], $childDetails['age']);
        $progressService->ensureGamesExist($gameId);
        $gameService->setChildId($childId);
        $gameService->setGameId($gameId);

        return $this->render('game/main_menu.html.twig', [
            'highestLevel' => $gameService->getHighestLevelReached(),
            'childId' => $childId,
            'gameId' => $gameId,
            'selectedLevel' => $gameService->getHighestLevelReached(),
        ]);
    }

    #[Route('/start/{level}', name: 'start_game', requirements: ['level' => '\d+'])]
    public function startGame(int $level, GameService $gameService, ProgressService $progressService, Request $request): Response
    {
        $childId = $request->query->getInt('childId', 1);
        $gameId = $request->query->getInt('gameId', 3);

        error_log("startGame: childId=$childId, gameId=$gameId, level=$level");

        if ($childId <= 0 || $gameId < 1 || $gameId > 5) {
            throw new BadRequestHttpException('Invalid childId or gameId.');
        }

        $childDetails = $progressService->fetchChildDetails($childId);
        $progressService->ensureChildExists($childId, $childDetails['parentId'], $childDetails['age']);
        $progressService->ensureGamesExist($gameId);
        $gameService->setChildId($childId);
        $gameService->setGameId($gameId);
        $gameService->startGame($level);

        return $this->renderGameScreen($gameService, $childId, $gameId);
    }

    #[Route('/check', name: 'check_answer', methods: ['POST'])]
    public function checkAnswer(Request $request, GameService $gameService, ProgressService $progressService): Response
    {
        $childId = $request->request->getInt('child_id', 1);
        $gameId = $request->request->getInt('game_id', 3);
        $selectedImageUrl = $request->request->get('image_url');
        $timeout = $request->request->getBoolean('timeout');

        error_log("checkAnswer: childId=$childId, gameId=$gameId, timeout=$timeout");

        if ($childId <= 0 || $gameId < 1 || $gameId > 5) {
            throw new BadRequestHttpException('Invalid childId or gameId.');
        }

        $childDetails = $progressService->fetchChildDetails($childId);
        $progressService->ensureChildExists($childId, $childDetails['parentId'], $childDetails['age']);
        $progressService->ensureGamesExist($gameId);
        $gameService->setChildId($childId);
        $gameService->setGameId($gameId);

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
            $result = $gameService->checkAnswer($selectedImageUrl);
        }

        return $this->render('game/game_screen.html.twig', array_merge($result, [
            'currentImages' => $gameService->getCurrentImages(),
            'correctWord' => $gameService->getCorrectWord(),
            'currentLevel' => $gameService->getCurrentLevel(),
            'currentStage' => $gameService->getCurrentStage(),
            'currentScore' => $gameService->getCurrentLevelPoints(),
            'childId' => $childId,
            'gameId' => $gameId,
        ]));
    }

    #[Route('/proceed', name: 'proceed_or_retry', methods: ['POST'])]
    public function proceedOrRetry(Request $request, GameService $gameService, ProgressService $progressService): Response
    {
        $childId = $request->request->getInt('child_id', 1);
        $gameId = $request->request->getInt('game_id', 3);
        $isCorrect = $request->request->getBoolean('is_correct');

        error_log("proceedOrRetry: childId=$childId, gameId=$gameId, isCorrect=$isCorrect");

        if ($childId <= 0 || $gameId < 1 || $gameId > 5) {
            throw new BadRequestHttpException('Invalid childId or gameId.');
        }

        $childDetails = $progressService->fetchChildDetails($childId);
        $progressService->ensureChildExists($childId, $childDetails['parentId'], $childDetails['age']);
        $progressService->ensureGamesExist($gameId);
        $gameService->setChildId($childId);
        $gameService->setGameId($gameId);
        $completed = $gameService->proceedOrRetry($isCorrect);

        if ($completed) {
            return $this->redirectToRoute('main_menu');
        }

        return $this->renderGameScreen($gameService, $childId, $gameId);
    }

    private function renderGameScreen(GameService $gameService, int $childId, int $gameId): Response
    {
        return $this->render('game/game_screen.html.twig', [
            'currentImages' => $gameService->getCurrentImages(),
            'correctWord' => $gameService->getCorrectWord(),
            'currentLevel' => $gameService->getCurrentLevel(),
            'currentStage' => $gameService->getCurrentStage(),
            'currentScore' => $gameService->getCurrentLevelPoints(),
            'childId' => $childId,
            'gameId' => $gameId,
            'isCorrect' => null,
        ]);
    }
}