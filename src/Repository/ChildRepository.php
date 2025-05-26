<?php

namespace App\Repository;

use App\Entity\Child;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class ChildRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Child::class);
    }

    public function findOneByParentAndChildId(int $parentId, int $childId): ?Child
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.parentId = :parentId')
            ->andWhere('c.childId = :childId')
            ->setParameter('parentId', $parentId)
            ->setParameter('childId', $childId)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findGameProgress(int $childId): array
    {
        // Subquery to get the latest level (highest id) per gameId
        $subQuery = $this->createQueryBuilder('c2')
            ->select('MAX(l2.id)')
            ->leftJoin('c2.levels', 'l2')
            ->where('c2.childId = :childId')
            ->andWhere('l2.gameId = l.gameId')
            ->getDQL();

        $levels = $this->createQueryBuilder('c')
            ->select('IDENTITY(l.gameId) as gameId, l.id as level, l.score')
            ->leftJoin('c.levels', 'l')
            ->where('c.childId = :childId')
            ->andWhere("l.id IN ($subQuery)")
            ->setParameter('childId', $childId)
            ->orderBy('l.gameId', 'ASC')
            ->getQuery()
            ->getResult();

        // Initialize progress for games 1 to 5
        $gameProgress = [];
        for ($i = 1; $i <= 5; $i++) {
            $gameProgress[$i] = ['level' => 0, 'score' => 0];
        }

        // Populate with latest level data
        foreach ($levels as $level) {
            $gameProgress[$level['gameId']] = [
                'level' => $level['level'], // id is the level (1, 2, or 3)
                'score' => $level['score'],
            ];
        }

        return $gameProgress;
    }
}