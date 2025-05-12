<?php

namespace App\Controller;

use App\Entity\Child;
use App\Entity\Level;
use App\Service\WordGameService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Controller for displaying and managing game progress data.
 */
class ProgressController extends AbstractController
{
    /**
     * Displays progress data for a child and game, including scores, times, tries, and max scores.
     *
     * @param Request $request HTTP request
     * @param WordGameService $wordGameService Word game service
     * @param EntityManagerInterface $em Doctrine entity manager
     * @return Response Rendered progress chart or JSON response for AJAX
     * @throws BadRequestHttpException If childId or gameId is invalid
     */
    #[Route('/progress', name: 'show_progress')]
    public function showProgress(Request $request, WordGameService $wordGameService, EntityManagerInterface $em): Response
    {
        $childId = $request->query->getInt('childId');
        $gameId = $request->query->getInt('gameId', 3);

        // Initialize arrays for levels 1, 2, 3
        $scores = [1 => 0, 2 => 0, 3 => 0];
        $times = [1 => 0, 2 => 0, 3 => 0];
        $tries = [1 => 0, 2 => 0, 3 => 0];
        $maxScores = [1 => 0, 2 => 0, 3 => 0];
        $scoreComparisons = [
            1 => ['achieved' => 0, 'max' => 0, 'percentage' => 0],
            2 => ['achieved' => 0, 'max' => 0, 'percentage' => 0],
            3 => ['achieved' => 0, 'max' => 0, 'percentage' => 0]
        ];

        if ($childId && $gameId) {
            if ($childId <= 0 || $gameId < 1 || $gameId > 5) {
                throw new BadRequestHttpException('Invalid childId or gameId.');
            }

            $wordGameService->setChildId($childId);
            $wordGameService->setGameId($gameId);

            // Fetch level records ordered by ID
            $levels = $em->getRepository(Level::class)->findBy(
                ['childId' => $childId, 'gameId' => $gameId],
                ['id' => 'ASC']
            );

            // Map records to levels 1, 2, 3
            foreach ($levels as $index => $level) {
                $levelNumber = $index + 1;
                if ($levelNumber > 3) {
                    break; // Only process first three levels
                }

                $scores[$levelNumber] = $level->getScore() ?? 0;
                $times[$levelNumber] = $level->getTime() ?? 0;
                $tries[$levelNumber] = $level->getNbtries() ?? 0;
            }

            // Set max scores and score comparisons
            $maxScorePerLevel = $wordGameService->getMaxScorePerLevel();
            for ($level = 1; $level <= 3; $level++) {
                $maxScores[$level] = $maxScorePerLevel;
                $scoreComparisons[$level] = [
                    'achieved' => $scores[$level],
                    'max' => $maxScores[$level],
                    'percentage' => $maxScores[$level] > 0 ? round(($scores[$level] / $maxScores[$level]) * 100) : 0
                ];
            }
        }

        // Calculate yAxisMax for chart scaling
        $maxScoresValue = !empty($scores) ? max($scores) : 0;
        $maxTimesValue = !empty($times) ? max($times) : 0;
        $maxTriesValue = !empty($tries) ? max($tries) : 0;
        $maxMaxScoresValue = !empty($maxScores) ? max($maxScores) : 0;
        $yAxisMax = ceil(max($maxScoresValue, $maxTimesValue, $maxTriesValue, $maxMaxScoresValue) * 1.1);

        if ($request->isXmlHttpRequest()) {
            return new JsonResponse([
                'scores' => $scores,
                'times' => $times,
                'tries' => $tries,
                'maxScores' => $maxScores,
                'scoreComparisons' => $scoreComparisons,
                'yAxisMax' => $yAxisMax,
            ]);
        }

        $games = [
            ['id' => 1, 'image' => 'game1.jpg'],
            ['id' => 2, 'image' => 'game2.jpg'],
            ['id' => 3, 'image' => 'game3.jpg'],
            ['id' => 4, 'image' => 'game4.jpg'],
            ['id' => 5, 'image' => 'game5.jpg'],
        ];

        return $this->render('game/progress_chart.html.twig', [
            'scores' => $scores,
            'times' => $times,
            'tries' => $tries,
            'maxScores' => $maxScores,
            'scoreComparisons' => $scoreComparisons,
            'yAxisMax' => $yAxisMax,
            'highestLevel' => $wordGameService->getHighestLevelReached(),
            'childId' => $childId,
            'gameId' => $gameId,
            'games' => $games,
            'parentId' => $request->query->getInt('parentId', 1), // Default parentId
        ]);
    }

    /**
     * API endpoint to fetch children for a given parent.
     *
     * @param Request $request HTTP request
     * @param EntityManagerInterface $em Doctrine entity manager
     * @return JsonResponse List of children
     */
    #[Route('/api/children', name: 'api_children', methods: ['GET'])]
    public function getChildren(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $parentId = $request->query->getInt('parentId');

        if (!$parentId) {
            return $this->json(['error' => 'معرف الوالد غير صالح أو مفقود'], 400);
        }

        $children = $em->getRepository(Child::class)->findBy(['parentId' => $parentId]);
        $data = array_map(function (Child $child) {
            return [
                'id' => $child->getChildId(),
                'name' => $child->getName() ?? 'طفل ' . $child->getChildId(),
            ];
        }, $children);

        return $this->json($data);
    }
}