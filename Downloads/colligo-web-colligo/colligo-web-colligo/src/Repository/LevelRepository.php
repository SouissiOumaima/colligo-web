<?php

namespace App\Repository;

use App\Entity\Level;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class LevelRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Level::class);
    }
    // src/Repository/LevelRepository.php

    public function findLastLevelByChild(Child $child): ?Level
    {
        return $this->createQueryBuilder('l')
            ->where('l.child = :child')
            ->setParameter('child', $child)
            ->orderBy('l.id', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findMaxIdForGameAndChild(int $gameId, int $childId): ?int
    {
        $result = $this->createQueryBuilder('l')
            ->select('MAX(l.id)')
            ->andWhere('l.gameId = :gameId')
            ->andWhere('l.childId = :childId')
            ->setParameter('gameId', $gameId)
            ->setParameter('childId', $childId)
            ->getQuery()
            ->getSingleScalarResult();

        return $result ? (int) $result : 1;
    }
}