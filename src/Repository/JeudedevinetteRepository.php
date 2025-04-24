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

    /**
     * Add a new word lot.
     *
     * @param string $rightWord
     * @param string $wrongWord
     * @param string $theme
     * @param string $language
     * @param int $level
     */
    public function addLot(string $rightWord, string $wrongWord, string $theme, string $language, int $level): void
    {
        $entityManager = $this->getEntityManager();
        $jeuDevinette = new Jeudedevinette();
        $jeuDevinette->setRightWord($rightWord);
        $jeuDevinette->setWrongWord($wrongWord);
        $jeuDevinette->setTheme($theme);
        $jeuDevinette->setLanguage($language);
        $jeuDevinette->setLevel($level);
        $entityManager->persist($jeuDevinette);
        $entityManager->flush();
    }

    /**
     * Delete a word lot by rightWord, wrongWord, and theme.
     *
     * @param string $rightWord
     * @param string $wrongWord
     * @param string $theme
     */
    public function deleteLot(string $rightWord, string $wrongWord, string $theme): void
    {
        $entityManager = $this->getEntityManager();
        $jeuDevinette = $this->createQueryBuilder('j')
            ->where('j.rightWord = :rightWord')
            ->andWhere('j.wrongWord = :wrongWord')
            ->andWhere('j.theme = :theme')
            ->setParameter('rightWord', $rightWord)
            ->setParameter('wrongWord', $wrongWord)
            ->setParameter('theme', $theme)
            ->getQuery()
            ->getOneOrNullResult();

        if ($jeuDevinette) {
            $entityManager->remove($jeuDevinette);
            $entityManager->flush();
        }
    }
}