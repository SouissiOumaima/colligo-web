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
     * @param string $level
     * @return array
     */
    public function findByLanguageAndLevel(string $language, string $level): array
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
     * @param string $level
     */
    public function addLot(string $rightWord, string $wrongWord, string $theme, string $language, string $level): void
    {
        $entityManager = $this->getEntityManager();
        $jeuDevinette = new Jeudedevinette();
        $jeuDevinette->setRightword($rightWord);
        $jeuDevinette->setWrongword($wrongWord);
        $jeuDevinette->setThème($theme);
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
            ->where('j.rightword = :rightWord')
            ->andWhere('j.wrongword = :wrongWord')
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

    /**
     * Save multiple word lots.
     *
     * @param array $lotData
     * @param string $language
     * @param string $level
     */
    public function saveLots(array $lotData, string $language, string $level): void
    {
        try {
            $entityManager = $this->getEntityManager();
            foreach ($lotData as $lot) {
                $entity = new Jeudedevinette();
                $entity->setRightword($lot['rightWord']);
                $entity->setWrongword($lot['wrongWord']);
                $entity->setThème($lot['theme']);
                $entity->setLanguage($language);
                $entity->setLevel($level);

                $entityManager->persist($entity);
            }
            $entityManager->flush();
        } catch (\Throwable $e) {
            throw new \RuntimeException('Erreur lors de l\'enregistrement des lots : ' . $e->getMessage());
        }
    }
}