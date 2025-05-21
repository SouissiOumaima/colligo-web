<?php

namespace App\Controller;

use App\Service\GameService;
use App\Service\ProgressService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Session\SessionInterface; // Add this import

/**
 * Controller for handling game-related actions such as starting, checking answers, and progressing.
 */


/**
 * Controller for handling game-related actions such as starting, checking answers, and progressing.
 */
class GameController extends AbstractController
{
    private $session; // Add this property

    public function __construct(SessionInterface $session) // Add this constructor
    {
        $this->session = $session;
    }
    #[Route('/', name: 'home')]
    public function home(Request $request): Response
    {
        $childId = $request->query->getInt('childId', 0);
        $parentId = $request->query->getInt('parentId', 0);
        if ($childId <= 0 || $parentId <= 0) {
            throw new BadRequestHttpException('Child ID and Parent ID must be provided in the URL, e.g., /?childId=1&parentId=1');
        }
        return $this->redirectToRoute('main_menu', [
            'childId' => $childId,
            'parentId' => $parentId,
        ]);
    }

    #[Route('/main/{childId}/{parentId}', name: 'main_menu', requirements: ['childId' => '\d+', 'parentId' => '\d+'])]
    #[Route('/main/{childId}/{parentId}', name: 'main_menu', requirements: ['childId' => '\d+', 'parentId' => '\d+'])]
    public function mainMenu(int $childId, int $parentId, GameService $gameService, ProgressService $progressService): Response
    {
        if ($childId <= 0 || $parentId <= 0) {
            throw new BadRequestHttpException('Invalid childId or parentId.');
        }

        $gameId = 3;
        $childDetails = $progressService->fetchChildDetails($childId);
        if (!$childDetails) {
            $progressService->ensureChildExists($childId, $parentId, 8);
            $childDetails = $progressService->fetchChildDetails($childId);
        }

        if ($childDetails['parentId'] != $parentId) {
            throw new BadRequestHttpException('Parent ID mismatch for Child ID.');
        }

        $progressService->ensureChildExists($childId, $parentId, $childDetails['age']);
        $progressService->ensureGamesExist($gameId);
        $gameService->setChildId($childId);
        $gameService->setGameId($gameId);

        $state = $gameService->getGameState();
        $currentLevel = $state['currentLevel'] ?? 1;
        error_log("mainMenu: childId=$childId, parentId=$parentId, currentLevel=$currentLevel, currentStage={$state['currentStage']}, highestLevel={$gameService->getHighestLevelReached()}");

        return $this->render('game/main_menu.html.twig', [
            'highestLevel' => $gameService->getHighestLevelReached(),
            'childId' => $childId,
            'parentId' => $parentId,
            'gameId' => $gameId,
            'selectedLevel' => $currentLevel,
            'isGameComplete' => $gameService->isGameComplete(),
        ]);
    }

    #[Route('/start/{childId}/{parentId}/{level}', name: 'start_game', requirements: ['childId' => '\d+', 'parentId' => '\d+', 'level' => '\d+'])]
    public function startGame(int $childId, int $parentId, int $level, GameService $gameService, ProgressService $progressService): Response
    {
        error_log("startGame: childId=$childId, parentId=$parentId, level=$level");

        if ($childId <= 0 || $parentId <= 0) {
            throw new BadRequestHttpException('Invalid childId or parentId.');
        }

        $gameId = 3;
        $childDetails = $progressService->fetchChildDetails($childId);
        if (!$childDetails || $childDetails['parentId'] != $parentId) {
            throw new BadRequestHttpException('Invalid Child ID or Parent ID mismatch.');
        }

        $progressService->ensureChildExists($childId, $parentId, $childDetails['age']);
        $progressService->ensureGamesExist($gameId);
        $gameService->setChildId($childId);
        $gameService->setGameId($gameId);

        // Check if the game is complete
        if ($gameService->isGameComplete()) {
            $this->addFlash('error', 'لقد أكملت اللعبة بالفعل! لا يمكن بدء لعبة جديدة.');
            return $this->redirectToRoute('main_menu', [
                'childId' => $childId,
                'parentId' => $parentId,
            ]);
        }

        $gameService->startGame($level);

        return $this->renderGameScreen($gameService, $childId, $parentId, $gameId);
    }

    #[Route('/check/{childId}/{parentId}', name: 'check_answer', methods: ['POST'], requirements: ['childId' => '\d+', 'parentId' => '\d+'])]
    #[Route('/check/{childId}/{parentId}', name: 'check_answer', methods: ['POST'], requirements: ['childId' => '\d+', 'parentId' => '\d+'])]
    public function checkAnswer(int $childId, int $parentId, Request $request, GameService $gameService, ProgressService $progressService): Response
    {
        $gameId = $request->request->getInt('game_id', 3);
        $selectedImageUrl = $request->request->get('image_url');

        error_log("checkAnswer: childId=$childId, parentId=$parentId, gameId=$gameId");

        if ($childId <= 0 || $parentId <= 0 || $gameId < 1 || $gameId > 5) {
            throw new BadRequestHttpException('Invalid childId, parentId, or gameId.');
        }

        $childDetails = $progressService->fetchChildDetails($childId);
        if (!$childDetails || $childDetails['parentId'] != $parentId) {
            throw new BadRequestHttpException('Invalid Child ID or Parent ID mismatch.');
        }

        $progressService->ensureChildExists($childId, $parentId, $childDetails['age']);
        $progressService->ensureGamesExist($gameId);
        $gameService->setChildId($childId);
        $gameService->setGameId($gameId);

        // Handle exit request
        if ($request->request->get('exit', false)) {
            $state = $gameService->getGameState();
            $this->session->save();
            error_log("Exiting to main menu: childId=$childId, currentLevel={$state['currentLevel']}, currentStage={$state['currentStage']}");
            return $this->redirectToRoute('main_menu', [
                'childId' => $childId,
                'parentId' => $parentId,
            ]);
        }

        // Validate selectedImageUrl
        if (!$selectedImageUrl) {
            throw new BadRequestHttpException('Missing selectedImageUrl parameter.');
        }

        $result = $gameService->checkAnswer($selectedImageUrl);
        $state = $gameService->getGameState();

        // Force session save
        $this->session->save();
        error_log("checkAnswer: childId=$childId, isCorrect={$result['isCorrect']}, maxTriesReached={$result['maxTriesReached']}, currentStage={$state['currentStage']}");

        // Proceed or retry
        $gameEnded = $gameService->proceedOrRetry($result['isCorrect'], $result['maxTriesReached']);

        // Force session save
        $this->session->save();
        $state = $gameService->getGameState();
        error_log("After proceedOrRetry: childId=$childId, currentLevel={$state['currentLevel']}, currentStage={$state['currentStage']}");

        // Handle game completion
        if ($gameEnded) {
            return $this->render('game/complete.html.twig', [
                'childId' => $childId,
                'parentId' => $parentId,
                'gameId' => $gameId,
                'finalScore' => $state['currentLevelPoints'],
            ]);
        }

        return $this->render('game/game_screen.html.twig', array_merge($result, [
            'currentImages' => $gameService->getCurrentImages(),
            'correctWord' => $gameService->getCorrectWord(),
            'currentLevel' => $gameService->getCurrentLevel(),
            'currentStage' => $gameService->getCurrentStage(),
            'currentScore' => $gameService->getCurrentLevelPoints(),
            'currentStageTries' => $gameService->getCurrentStageTries(),
            'maxTriesPerStage' => $gameService->getMaxTriesPerStage(),
            'childId' => $childId,
            'parentId' => $parentId,
            'gameId' => $gameId,
        ]));
    }
    #[Route('/proceed/{childId}/{parentId}', name: 'proceed_or_retry', methods: ['POST'], requirements: ['childId' => '\d+', 'parentId' => '\d+'])]
    public function proceedOrRetry(int $childId, int $parentId, Request $request, GameService $gameService, ProgressService $progressService): Response
    {
        $gameId = $request->request->getInt('game_id', 3);

        error_log("proceedOrRetry: childId=$childId, parentId=$parentId, gameId=$gameId");

        if ($childId <= 0 || $parentId <= 0 || $gameId < 1 || $gameId > 5) {
            throw new BadRequestHttpException('Invalid childId, parentId, or gameId.');
        }

        $childDetails = $progressService->fetchChildDetails($childId);
        if (!$childDetails || $childDetails['parentId'] != $parentId) {
            throw new BadRequestHttpException('Invalid Child ID or Parent ID mismatch.');
        }

        $progressService->ensureChildExists($childId, $parentId, $childDetails['age']);
        $progressService->ensureGamesExist($gameId);
        $gameService->setChildId($childId);
        $gameService->setGameId($gameId);

        $state = $gameService->getGameState();
        // Check if the game is complete (e.g., level 3 completed)
        if ($state['currentLevel'] > 3 || ($state['currentLevel'] === 3 && $state['currentStage'] > $gameService->getStagesPerLevel())) {
            return $this->redirectToRoute('main_menu', [
                'childId' => $childId,
                'parentId' => $parentId,
            ]);
        }

        return $this->renderGameScreen($gameService, $childId, $parentId, $gameId);
    }

    private function renderGameScreen(GameService $gameService, int $childId, int $parentId, int $gameId): Response
    {
        $state = $gameService->getGameState();
        return $this->render('game/game_screen.html.twig', [
            'currentImages' => $gameService->getCurrentImages(),
            'correctWord' => $gameService->getCorrectWord(),
            'currentLevel' => $gameService->getCurrentLevel(),
            'currentStage' => $gameService->getCurrentStage(),
            'currentScore' => $gameService->getCurrentLevelPoints(),
            'currentStageTries' => $gameService->getCurrentStageTries(),
            'maxTriesPerStage' => $gameService->getMaxTriesPerStage(),
            'childId' => $childId,
            'parentId' => $parentId,
            'gameId' => $gameId,
            'isCorrect' => null,
        ]);
    }
}