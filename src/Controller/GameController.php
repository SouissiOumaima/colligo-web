<?php

namespace App\Controller;

use App\Service\GameService;
use App\Service\ProgressService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;

/**
 * Controller for handling game-related actions such as starting, checking answers, and progressing.
 */
class GameController extends AbstractController
{
    #[Route('/', name: 'home')]
    public function home(): Response
    {
        return $this->redirectToRoute('select_child');
    }

    #[Route('/select-child', name: 'select_child')]
    public function selectChild(Request $request, ProgressService $progressService): Response
    {
        $form = $this->createFormBuilder()
            ->add('childId', IntegerType::class, [
                'label' => 'Child ID',
                'attr' => ['min' => 1],
                'required' => true,
            ])
            ->add('submit', SubmitType::class, ['label' => 'Start Game'])
            ->getForm();

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            $childId = $data['childId'];

            if ($childId <= 0) {
                $this->addFlash('error', 'Child ID must be a positive integer.');
                return $this->render('game/select_child.html.twig', [
                    'form' => $form->createView(),
                ]);
            }

            // Fetch parentId from database
            $childDetails = $progressService->fetchChildDetails($childId);
            if (!$childDetails || !isset($childDetails['parentId']) || $childDetails['parentId'] <= 0) {
                $this->addFlash('error', 'Invalid Child ID or no associated Parent ID found.');
                return $this->render('game/select_child.html.twig', [
                    'form' => $form->createView(),
                ]);
            }

            $parentId = $childDetails['parentId'];

            return $this->redirectToRoute('main_menu', [
                'childId' => $childId,
                'parentId' => $parentId,
            ]);
        }

        return $this->render('game/select_child.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/main/{childId}/{parentId}', name: 'main_menu', requirements: ['childId' => '\d+', 'parentId' => '\d+'])]
    public function mainMenu(int $childId, int $parentId, GameService $gameService, ProgressService $progressService): Response
    {
        if ($childId <= 0 || $parentId <= 0) {
            throw new BadRequestHttpException('Invalid childId or parentId.');
        }

        $gameId = 3;
        $childDetails = $progressService->fetchChildDetails($childId);
        if (!$childDetails || $childDetails['parentId'] != $parentId) {
            return $this->redirectToRoute('select_child');
        }

        $progressService->ensureChildExists($childId, $parentId, $childDetails['age']);
        $progressService->ensureGamesExist($gameId);
        $gameService->setChildId($childId);
        $gameService->setGameId($gameId);

        return $this->render('game/main_menu.html.twig', [
            'highestLevel' => $gameService->getHighestLevelReached(),
            'childId' => $childId,
            'parentId' => $parentId,
            'gameId' => $gameId,
            'selectedLevel' => $gameService->getHighestLevelReached(),
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
            return $this->redirectToRoute('select_child');
        }

        $progressService->ensureChildExists($childId, $parentId, $childDetails['age']);
        $progressService->ensureGamesExist($gameId);
        $gameService->setChildId($childId);
        $gameService->setGameId($gameId);
        $gameService->startGame($level);

        return $this->renderGameScreen($gameService, $childId, $parentId, $gameId);
    }

    #[Route('/check/{childId}/{parentId}', name: 'check_answer', methods: ['POST'], requirements: ['childId' => '\d+', 'parentId' => '\d+'])]
    public function checkAnswer(int $childId, int $parentId, Request $request, GameService $gameService, ProgressService $progressService): Response
    {
        $gameId = $request->request->getInt('game_id', 3);
        $selectedImageUrl = $request->request->get('image_url');
        $timeout = $request->request->getBoolean('timeout');

        error_log("checkAnswer: childId=$childId, parentId=$parentId, gameId=$gameId, timeout=$timeout");

        if ($childId <= 0 || $parentId <= 0 || $gameId < 1 || $gameId > 5) {
            throw new BadRequestHttpException('Invalid childId, parentId, or gameId.');
        }

        $childDetails = $progressService->fetchChildDetails($childId);
        if (!$childDetails || $childDetails['parentId'] != $parentId) {
            return $this->redirectToRoute('select_child');
        }

        $progressService->ensureChildExists($childId, $parentId, $childDetails['age']);
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
            'parentId' => $parentId,
            'gameId' => $gameId,
        ]));
    }

    #[Route('/proceed/{childId}/{parentId}', name: 'proceed_or_retry', methods: ['POST'], requirements: ['childId' => '\d+', 'parentId' => '\d+'])]
    public function proceedOrRetry(int $childId, int $parentId, Request $request, GameService $gameService, ProgressService $progressService): Response
    {
        $gameId = $request->request->getInt('game_id', 3);
        $isCorrect = $request->request->getBoolean('is_correct');

        error_log("proceedOrRetry: childId=$childId, parentId=$parentId, gameId=$gameId, isCorrect=$isCorrect");

        if ($childId <= 0 || $parentId <= 0 || $gameId < 1 || $gameId > 5) {
            throw new BadRequestHttpException('Invalid childId, parentId, or gameId.');
        }

        $childDetails = $progressService->fetchChildDetails($childId);
        if (!$childDetails || $childDetails['parentId'] != $parentId) {
            return $this->redirectToRoute('select_child');
        }

        $progressService->ensureChildExists($childId, $parentId, $childDetails['age']);
        $progressService->ensureGamesExist($gameId);
        $gameService->setChildId($childId);
        $gameService->setGameId($gameId);
        $completed = $gameService->proceedOrRetry($isCorrect);

        if ($completed) {
            return $this->redirectToRoute('main_menu', [
                'childId' => $childId,
                'parentId' => $parentId,
            ]);
        }

        return $this->renderGameScreen($gameService, $childId, $parentId, $gameId);
    }

    private function renderGameScreen(GameService $gameService, int $childId, int $parentId, int $gameId): Response
    {
        return $this->render('game/game_screen.html.twig', [
            'currentImages' => $gameService->getCurrentImages(),
            'correctWord' => $gameService->getCorrectWord(),
            'currentLevel' => $gameService->getCurrentLevel(),
            'currentStage' => $gameService->getCurrentStage(),
            'currentScore' => $gameService->getCurrentLevelPoints(),
            'childId' => $childId,
            'parentId' => $parentId,
            'gameId' => $gameId,
            'isCorrect' => null,
        ]);
    }
}