<?php

namespace App\Controller;

use App\Entity\Level;
use App\Service\WordGameService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Annotation\Route;

class ProgressController extends AbstractController
{
    #[Route('/progress', name: 'show_progress')]
    public function showProgress(Request $request, WordGameService $wordGameService, EntityManagerInterface $em): Response
    {
        $childId = $request->query->getInt('childId');
        $gameId = $request->query->getInt('gameId', 3);

        // Initialize arrays with default values for levels 1, 2, 3
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
            // Hardcode target max time to 30 seconds
            $targetMaxTimePerLevel = 30; // Override the value from WordGameService
            for ($level = 1; $level <= 3; $level++) {
                $maxScores[$level] = $maxScorePerLevel;
                $targetMaxTimes[$level] = $targetMaxTimePerLevel;
            }

            // Fetch Level records, ordered by id
            $levels = $em->getRepository(Level::class)->findBy(
                ['childId' => $childId, 'gameId' => $gameId],
                ['id' => 'ASC']
            );

            // Map the records to levels 1, 2, 3
            foreach ($levels as $index => $level) {
                // Assuming the first record is level 1, second is level 2, etc.
                $levelNumber = $index + 1;
                if ($levelNumber > 3) {
                    break; // Only process the first three levels
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
                    'percentage' => round($timePercentage, 2), // Round to 2 decimal places
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
}