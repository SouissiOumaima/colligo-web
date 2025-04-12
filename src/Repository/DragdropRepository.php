<?php

namespace App\Repository;

use App\Entity\Dragdrop;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class DragdropRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Dragdrop::class);
    }

    // Add custom methods as needed
}