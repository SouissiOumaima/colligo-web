<?php

  namespace App\Repository;

  use App\Entity\Parents;
  use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
  use Doctrine\Persistence\ManagerRegistry;

  /**
   * @extends ServiceEntityRepository<Parents>
   */
  class ParentsRepository extends ServiceEntityRepository
  {
      public function __construct(ManagerRegistry $registry)
      {
          parent::__construct($registry, Parents::class);
      }

      /**
       * Delete a parent by ID
       *
       * @param int $parentId
       * @return void
       * @throws \Exception
       */
      public function deleteParent(int $parentId): void
      {
          $parent = $this->find($parentId);
          if ($parent) {
              try {
                  $this->getEntityManager()->remove($parent);
                  $this->getEntityManager()->flush();
              } catch (\Exception $e) {
                  error_log("Failed to delete parent with ID $parentId: " . $e->getMessage()); // Detailed debugging
                  throw new \Exception("Failed to delete parent with ID $parentId: " . $e->getMessage());
              }
          } else {
              throw new \Exception("Parent with ID $parentId not found.");
          }
      }
  }