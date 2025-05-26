<?php

namespace App\Repository;

use App\Entity\Dragdrop;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Psr\Log\LoggerInterface;

class DragdropRepository extends ServiceEntityRepository
{
   private LoggerInterface $logger;

    public function __construct(ManagerRegistry $registry, LoggerInterface $logger)
    {
        parent::__construct($registry, Dragdrop::class);
        $this->logger = $logger;
    }

 public function findRandomByLanguageAndLevel(string $language, int $level, array $usedSentences): ?Dragdrop
{
    $results = $this->createQueryBuilder('d')
        ->where('d.langue = :language')
        ->andWhere('d.niveau = :level')
        ->setParameter('language', $language)
        ->setParameter('level', $level);

    if (!empty($usedSentences)) {
        $results->andWhere('d.id NOT IN (:used)')
                ->setParameter('used', $usedSentences);
    }

    $sentences = $results->getQuery()->getResult();
    if (empty($sentences)) {
        return null;
    }

    return $sentences[array_rand($sentences)];
}

}