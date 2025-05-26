<?php

namespace App\Repository;

use App\Entity\Matching_game;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class Matching_gameRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Matching_game::class);
    }

    // Add custom methods as needed
}