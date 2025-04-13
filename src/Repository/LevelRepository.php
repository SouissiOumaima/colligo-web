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

    // Add custom methods as needed
}