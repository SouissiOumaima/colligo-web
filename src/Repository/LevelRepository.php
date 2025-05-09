<?php

namespace App\Repository;

use App\Entity\Level;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Level>
 */
class LevelRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Level::class);
    }

    public function findLatestByGameAndChild(int $gameId, int $childId): ?Level
    {
        return $this->createQueryBuilder('l')
            ->andWhere('l.game = :gameId')
            ->andWhere('l.child = :childId')
            ->setParameter('gameId', $gameId)
            ->setParameter('childId', $childId)
            ->orderBy('l.id', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findMaxIdForGameAndChild(int $gameId, int $childId): ?int
    {
        $result = $this->createQueryBuilder('l')
            ->select('MAX(l.id)')
            ->andWhere('l.game = :gameId')
            ->andWhere('l.child = :childId')
            ->setParameter('gameId', $gameId)
            ->setParameter('childId', $childId)
            ->getQuery()
            ->getSingleScalarResult();

        return $result ? (int) $result : 0;
    }
}