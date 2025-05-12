<?php

namespace App\Repository;

use App\Entity\Dragdrop;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Psr\Log\LoggerInterface;

/**
 * @extends ServiceEntityRepository<Dragdrop>
 */
class DragdropRepository extends ServiceEntityRepository
{
    private LoggerInterface $logger;

    public function __construct(ManagerRegistry $registry, LoggerInterface $logger)
    {
        parent::__construct($registry, Dragdrop::class);
        $this->logger = $logger;
    }

    public function testConnection(): array
    {
        $this->logger->debug('Testing database connection');
        $query = $this->createQueryBuilder('d')
            ->select('d.id, d.phrase')
            ->setMaxResults(1)
            ->getQuery();
        $this->logger->debug('Executing test connection query', ['sql' => $query->getSQL(), 'params' => $query->getParameters()]);
        $result = $query->getResult();
        $this->logger->debug('Test connection result', ['result' => $result]);
        return $result;
    }

    public function countByLanguageAndLevel(string $language, int $level): int
    {
        $this->logger->debug('Counting sentences by language and level', ['language' => $language, 'level' => $level]);
        $query = $this->createQueryBuilder('d')
            ->select('COUNT(d.id)')
            ->where('LOWER(d.langue) = LOWER(:language)')
            ->andWhere('d.niveau = :level')
            ->setParameter('language', $language)
            ->setParameter('level', $level)
            ->getQuery();
        $this->logger->debug('Executing count query', ['sql' => $query->getSQL(), 'params' => $query->getParameters()]);
        $count = $query->getSingleScalarResult();
        $this->logger->debug('Count result', ['count' => $count]);
        return $count;
    }

    public function findRandomByLanguageAndLevel(string $language, int $level, array $usedSentences): ?Dragdrop
    {
        $this->logger->debug('Finding random sentence by language and level', [
            'language' => $language,
            'level' => $level,
            'usedSentences' => $usedSentences,
        ]);

        $query = $this->createQueryBuilder('d')
            ->where('LOWER(d.langue) = LOWER(:language)')
            ->andWhere('d.niveau = :level')
            ->setParameter('language', $language)
            ->setParameter('level', $level);

        if (!empty($usedSentences)) {
            $query->andWhere('d.id NOT IN (:usedSentences)')
                ->setParameter('usedSentences', $usedSentences);
        }

        $this->logger->debug('Executing random sentence query', ['sql' => $query->getQuery()->getSQL(), 'params' => $query->getParameters()]);
        $results = $query->getQuery()->getResult();

        $this->logger->debug('Raw query results', ['results' => array_map(function($d) { return ['id' => $d->getId(), 'phrase' => $d->getPhrase()]; }, $results)]);

        $availableResults = array_filter($results, function($d) use ($usedSentences) {
            return empty($usedSentences) || !in_array($d->getId(), $usedSentences);
        });

        $this->logger->debug('Available results after filtering', ['count' => count($availableResults)]);

        if (empty($availableResults)) {
            return null;
        }

        $randomIndex = array_rand($availableResults);
        $result = $availableResults[$randomIndex];

        $this->logger->debug('Random sentence query result', [
            'result' => ['id' => $result->getId(), 'phrase' => $result->getPhrase()],
        ]);

        return $result;
    }
}