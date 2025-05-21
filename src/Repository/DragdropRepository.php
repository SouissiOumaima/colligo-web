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
        try {
            $result = $this->createQueryBuilder('d')
                ->select('d.id')
                ->setMaxResults(1)
                ->getQuery()
                ->getResult();

            $this->logger->info('Database connection test successful', ['count' => count($result)]);
            return $result;
        } catch (\Exception $e) {
            $this->logger->error('Database connection test failed', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    public function countByLanguageAndLevel(string $language, int $level): int
    {
        try {
            $qb = $this->createQueryBuilder('d')
                ->select('COUNT(d.id)')
                ->where('d.langue = :language')
                ->andWhere('d.niveau = :level')
                ->setParameter('language', trim($language))
                ->setParameter('level', $level);

            $count = (int) $qb->getQuery()->getSingleScalarResult();
            $this->logger->debug('Counted sentences', ['language' => $language, 'level' => $level, 'count' => $count]);
            return $count;
        } catch (\Exception $e) {
            $this->logger->error('Error counting sentences', [
                'language' => $language,
                'level' => $level,
                'error' => $e->getMessage()
            ]);
            return 0;
        }
    }

    public function findRandomByLanguageAndLevel(string $language, int $level, array $usedSentences): ?Dragdrop
    {
        try {
            $conn = $this->getEntityManager()->getConnection();
            $platform = $conn->getDatabasePlatform()->getName();
            $randomFunction = stripos($platform, 'mysql') !== false ? 'RAND()' : 'RANDOM()';

            $sql = 'SELECT id FROM dragdrop WHERE langue = :language AND niveau = :level';
            if (!empty($usedSentences)) {
                $sql .= ' AND id NOT IN (:usedSentences)';
            }
            $sql .= ' ORDER BY ' . $randomFunction . ' LIMIT 1';

            $params = [
                'language' => trim($language),
                'level' => $level,
            ];
            if (!empty($usedSentences)) {
                $params['usedSentences'] = $usedSentences;
            }

            $this->logger->debug('Executing random sentence query', [
                'sql' => $sql,
                'params' => $params,
            ]);

            $result = $conn->executeQuery($sql, $params)->fetchAssociative();

            if (!$result) {
                $this->logger->warning('No random sentence found', [
                    'language' => $language,
                    'level' => $level,
                    'usedSentences' => $usedSentences,
                ]);
                return null;
            }

            $dragdrop = $this->find($result['id']);
            $this->logger->debug('Found random sentence', [
                'language' => $language,
                'level' => $level,
                'usedSentences' => $usedSentences,
                'result' => $dragdrop ? $dragdrop->getId() . ': ' . $dragdrop->getPhrase() : 'null',
            ]);

            return $dragdrop;
        } catch (\Exception $e) {
            $this->logger->error('Error finding random sentence', [
                'language' => $language,
                'level' => $level,
                'usedSentences' => $usedSentences,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    public function findByLanguageAndLevel(string $language, int $level): array
    {
        try {
            return $this->createQueryBuilder('d')
                ->where('d.langue = :language')
                ->andWhere('d.niveau = :level')
                ->setParameter('language', trim($language))
                ->setParameter('level', $level)
                ->getQuery()
                ->getResult();
        } catch (\Exception $e) {
            $this->logger->error('Error finding sentences by language and level', [
                'language' => $language,
                'level' => $level,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }
}