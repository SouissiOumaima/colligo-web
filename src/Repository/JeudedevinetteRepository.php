<?php

namespace App\Repository;

use App\Entity\Jeudedevinette;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Jeudedevinette>
 */
class JeudedevinetteRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Jeudedevinette::class);
    }

    /**
     * Fetch word lots for a given language and level.
     *
     * @param string $language
     * @param int $level
     * @return array
     */
    public function findByLanguageAndLevel(string $language, int $level): array
    {
        return $this->createQueryBuilder('j')
            ->where('j.language = :language')
            ->andWhere('j.level = :level')
            ->setParameter('language', $language)
            ->setParameter('level', $level)
            ->getQuery()
            ->getResult();
    }
}