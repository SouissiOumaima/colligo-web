<?php

namespace App\Repository;

use App\Entity\Fill_in_the_blank;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class Fill_in_the_blankRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Fill_in_the_blank::class);
    }

    /**
     * Find all questions by theme and language
     *
     * @param string $theme
     * @param string $language
     * @return Fill_in_the_blank[]
     */
    public function findByThemeAndLanguage(string $theme, string $language): array
    {
        return $this->createQueryBuilder('q')
            ->andWhere('q.theme = :theme')
            ->andWhere('q.language = :language')
            ->setParameter('theme', $theme)
            ->setParameter('language', $language)
            ->getQuery()
            ->getResult();
    }

    /**
     * Find questions by level and language.
     *
     * @param int $level
     * @param string $language
     * @return Fill_in_the_blank[]
     */
    public function findByLevelAndLanguage(int $level, string $language): array
    {
        return $this->createQueryBuilder('q')
            ->andWhere('q.level = :level')
            ->andWhere('q.language = :language')
            ->setParameter('level', $level)
            ->setParameter('language', $language)
            ->getQuery()
            ->getResult();
    }
}
