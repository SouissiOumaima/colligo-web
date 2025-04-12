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

    // Add custom methods as needed
}