<?php

namespace App\Controller;

use App\Entity\Child;
use App\Entity\Parents; // Add the Parent entity (adjust the namespace as needed)
use App\Service\ProgressService;
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
    #[Route('/progress/{parentId}', name: 'show_progress', requirements: ['parentId' => '\d+'])]
    public function showProgress(int $parentId, Request $request, ProgressService $progressService, EntityManagerInterface $em): Response
    {
        $childId = $request->query->getInt('childId');
        $gameId = $request->query->getInt('gameId', 3);

        if ($parentId <= 0) {
            throw new BadRequestHttpException('Invalid parentId.');
        }

        // Fetch the parent entity
        $parent = $em->getRepository(Parents::class)->find($parentId);
        if (!$parent) {
            throw $this->createNotFoundException('Parent not found for ID ' . $parentId);
        }

        $scores = [1 => 0, 2 => 0, 3 => 0];
        $times = [1 => 0, 2 => 0, 3 => 0];
        $tries = [1 => 0, 2 => 0, 3 => 0];
        $maxScores = [1 => 0, 2 => 0, 3 => 0];
        $scoreComparisons = [
            1 => ['achieved' => 0, 'max' => 0, 'percentage' => 0],
            2 => ['achieved' => 0, 'max' => 0, 'percentage' => 0],
            3 => ['achieved' => 0, 'max' => 0, 'percentage' => 0]
        ];
        $yAxisMax = 0;

        if ($childId && $gameId) {
            if ($childId <= 0 || $gameId < 1 || $gameId > 5) {
                throw new BadRequestHttpException('Invalid childId or gameId.');
            }

            $progressService->setChildId($childId);
            $progressService->setGameId($gameId);
            $progressData = $progressService->getProgressData($childId, $gameId);

            $scores = $progressData['scores'];
            $times = $progressData['times'];
            $tries = $progressData['tries'];
            $maxScores = $progressData['maxScores'];
            $scoreComparisons = $progressData['scoreComparisons'];
            $yAxisMax = $progressData['yAxisMax'];
        }

        // 🧠 Remplacement de GameService::getHighestLevelReached()
        $highestLevel = 1;
        if ($childId && $gameId) {
            $qb = $em->createQueryBuilder();
            $qb->select('MAX(l.id)')
                ->from('App\Entity\Level', 'l')
                ->where('l.childId = :childId')
                ->andWhere('l.gameId = :gameId')
                ->setParameter('childId', $childId)
                ->setParameter('gameId', $gameId);

            $highestLevelResult = $qb->getQuery()->getSingleScalarResult();
            $highestLevel = $highestLevelResult ? (int) $highestLevelResult : 1;
        }

        if ($request->isXmlHttpRequest()) {
            return new JsonResponse([
                'scores' => $scores,
                'times' => $times,
                'tries' => $tries,
                'maxScores' => $maxScores,
                'scoreComparisons' => $scoreComparisons,
                'yAxisMax' => $yAxisMax,
                'highestLevel' => $highestLevel,
            ]);
        }

        $games = [
            ['id' => 1, 'image' => 'game1.jpg'],
            ['id' => 2, 'image' => 'game2.jpg'],
            ['id' => 3, 'image' => 'game3.jpg'],
            ['id' => 4, 'image' => 'game4.jpg'],
            ['id' => 5, 'image' => 'game5.jpg'],
        ];

        return $this->render('Child/progress.html.twig', [
            'scores' => $scores,
            'times' => $times,
            'tries' => $tries,
            'maxScores' => $maxScores,
            'scoreComparisons' => $scoreComparisons,
            'yAxisMax' => $yAxisMax,
            'highestLevel' => $highestLevel,
            'childId' => $childId,
            'gameId' => $gameId,
            'games' => $games,
            'parentId' => $parentId,
            'parent' => $parent, // Pass the parent entity to the template
        ]);
    }

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