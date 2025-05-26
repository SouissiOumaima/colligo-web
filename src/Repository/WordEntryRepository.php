<?php

namespace App\Repository;

use App\Entity\WordEntry;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class WordEntryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, WordEntry::class);
    }

    public function findWordsForAdmin(string $language, int $level): array
    {
        return $this->createQueryBuilder('w')
            ->where('w.language = :language')
            ->andWhere('w.level = :level')
            ->setParameter('language', $language)
            ->setParameter('level', $level)
            ->getQuery()
            ->getResult();
    }

    public function addLot(string $rightWords, string $wrongWord, string $theme, string $language, int $level): void
    {
        $entityManager = $this->getEntityManager();
        $wordEntry = new WordEntry();
        $wordEntry->setRightWord($rightWords);
        $wordEntry->setWrongWord($wrongWord);
        $wordEntry->setTheme($theme);
        $wordEntry->setLanguage($language);
        $wordEntry->setLevel($level);
        $entityManager->persist($wordEntry);
        $entityManager->flush();
    }

    public function deleteLot(string $rightWord, string $wrongWord, string $theme): void
    {
        $entityManager = $this->getEntityManager();
        $wordEntry = $this->createQueryBuilder('w')
            ->where('w.rightWord = :rightWord')
            ->andWhere('w.wrongWord = :wrongWord')
            ->andWhere('w.theme = :theme')
            ->setParameter('rightWord', $rightWord)
            ->setParameter('wrongWord', $wrongWord)
            ->setParameter('theme', $theme)
            ->getQuery()
            ->getOneOrNullResult();

        if ($wordEntry) {
            $entityManager->remove($wordEntry);
            $entityManager->flush();
        }
    }
}