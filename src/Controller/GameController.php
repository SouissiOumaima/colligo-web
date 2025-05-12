<?php

namespace App\Controller;

use App\Service\WordGameService;
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
    /**
     * Displays the main menu with game options.
     *
     * @param WordGameService $wordGameService Word game service
     * @return Response Rendered main menu page
     */
    #[Route('/main', name: 'main_menu')]
    public function mainMenu(WordGameService $wordGameService): Response
{
    $childId = 5; // Default or dynamic childId, adjust as needed
    $gameId = 3; // Default gameId for "Picture Game", adjust as needed
    $wordGameService->setChildId($childId);
    $wordGameService->setGameId($gameId);

    return $this->render('game/main_menu.html.twig', [
        'highestLevel' => $wordGameService->getHighestLevelReached(),
        'childId' => $childId,
        'gameId' => $gameId,
        'selectedLevel' => $wordGameService->getHighestLevelReached(), // Use highest level reached
    ]);
}

    /**
     * Starts a new game at the specified level.
     *
     * @param int $level Level to start
     * @param WordGameService $wordGameService Word game service
     * @param Request $request HTTP request
     * @return Response Rendered game screen
     * @throws BadRequestHttpException If childId or gameId is invalid
     */
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

    /**
     * Checks the player's answer and updates the game state.
     *
     * @param Request $request HTTP request
     * @param WordGameService $wordGameService Word game service
     * @return Response Rendered game screen with answer result
     * @throws BadRequestHttpException If parameters are invalid
     */
    #[Route('/check', name: 'check_answer', methods: ['POST'])]
    public function checkAnswer(Request $request, WordGameService $wordGameService): Response
    {
        $childId = $request->request->getInt('child_id', 1);
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

    /**
     * Proceeds to the next stage or level, or retries if incorrect.
     *
     * @param Request $request HTTP request
     * @param WordGameService $wordGameService Word game service
     * @return Response Rendered game screen or redirect to main menu
     * @throws BadRequestHttpException If parameters are invalid
     */
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

    /**
     * Renders the game screen with current game state.
     *
     * @param WordGameService $wordGameService Word game service
     * @param int $childId Child ID
     * @param int $gameId Game ID
     * @return Response Rendered game screen
     */
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