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
     * Displays progress data for a child and game, including scores, times, and tries.
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
        $maxScores = [];
        $targetMaxTimes = [];
        $scoreComparisons = [];
        $timeComparisons = [];

        if ($childId && $gameId) {
            if ($childId <= 0 || $gameId < 1 || $gameId > 5) {
                throw new BadRequestHttpException('Invalid childId or gameId.');
            }

            $wordGameService->setChildId($childId);
            $wordGameService->setGameId($gameId);

            $maxScorePerLevel = $wordGameService->getMaxScorePerLevel();
            $targetMaxTimePerLevel = 30; // Hardcoded to 30 seconds

            for ($level = 1; $level <= 3; $level++) {
                $maxScores[$level] = $maxScorePerLevel;
                $targetMaxTimes[$level] = $targetMaxTimePerLevel;
            }

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

                $maxScore = $maxScores[$levelNumber] ?? $maxScorePerLevel;
                $scoreComparisons[$levelNumber] = [
                    'achieved' => $scores[$levelNumber],
                    'max' => $maxScore,
                    'percentage' => $maxScore > 0 ? round(($scores[$levelNumber] / $maxScore) * 100, 1) : 0,
                ];

                $targetMaxTime = $targetMaxTimes[$levelNumber] ?? $targetMaxTimePerLevel;
                $actualTime = $times[$levelNumber];
                $timePercentage = $targetMaxTime > 0 ? (($targetMaxTime - $actualTime) / $targetMaxTime) * 100 : 0;
                $timeComparisons[$levelNumber] = [
                    'actual' => $actualTime,
                    'targetMax' => $targetMaxTime,
                    'percentage' => round($timePercentage, 2),
                ];
            }

            // Ensure all levels have data
            for ($level = 1; $level <= 3; $level++) {
                if (!isset($scores[$level])) {
                    $scores[$level] = 0;
                    $times[$level] = 0;
                    $tries[$level] = 0;
                    $maxScores[$level] = $maxScorePerLevel;
                    $targetMaxTimes[$level] = $targetMaxTimePerLevel;
                    $scoreComparisons[$level] = [
                        'achieved' => 0,
                        'max' => $maxScorePerLevel,
                        'percentage' => 0,
                    ];
                    $timeComparisons[$level] = [
                        'actual' => 0,
                        'targetMax' => $targetMaxTimePerLevel,
                        'percentage' => 0,
                    ];
                }
            }
        }

        $maxScore = !empty($maxScores) ? max($maxScores) : 0;
        $maxTime = !empty($targetMaxTimes) ? max($targetMaxTimes) : 0;
        $maxScoresValue = !empty($scores) ? max($scores) : 0;
        $maxTimesValue = !empty($times) ? max($times) : 0;
        $maxTriesValue = !empty($tries) ? max($tries) : 0;
        $yAxisMax = ceil(max($maxScore, $maxTime, $maxScoresValue, $maxTimesValue, $maxTriesValue) * 1.1);

        if ($request->isXmlHttpRequest()) {
            return new JsonResponse([
                'scores' => $scores,
                'times' => $times,
                'tries' => $tries,
                'maxScores' => $maxScores,
                'targetMaxTimes' => $targetMaxTimes,
                'scoreComparisons' => $scoreComparisons,
                'timeComparisons' => $timeComparisons,
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
            'targetMaxTimes' => $targetMaxTimes,
            'scoreComparisons' => $scoreComparisons,
            'timeComparisons' => $timeComparisons,
            'yAxisMax' => $yAxisMax,
            'highestLevel' => $wordGameService->getHighestLevelReached(),
            'childId' => $childId,
            'gameId' => $gameId,
            'games' => $games,
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