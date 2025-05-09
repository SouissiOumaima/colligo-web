<?php

namespace App\Repository;

use App\Entity\Child;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class ChildRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Child::class);
    }

   /**
     * Delete a child by ID
     *
     * @param int $childId
     * @return void
     */
    public function deleteChild(int $childId): void
    {
        $child = $this->find($childId);
        if ($child) {
            $this->getEntityManager()->remove($child);
            $this->getEntityManager()->flush();
        }
    }
}