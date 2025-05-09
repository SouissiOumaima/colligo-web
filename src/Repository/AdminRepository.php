<?php

  namespace App\Repository;

  use App\Entity\Admin;
  use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
  use Doctrine\Persistence\ManagerRegistry;

  class AdminRepository extends ServiceEntityRepository
  {
      public function __construct(ManagerRegistry $registry)
      {
          parent::__construct($registry, Admin::class);
      }

      /**
       * Delete an admin by ID
       *
       * @param int $adminId
       * @return void
       * @throws \Exception
       */
      public function deleteAdmin(int $adminId): void
      {
          $admin = $this->find($adminId);
          if ($admin) {
              try {
                  $this->getEntityManager()->remove($admin);
                  $this->getEntityManager()->flush();
              } catch (\Exception $e) {
                  error_log("Failed to delete admin with ID $adminId: " . $e->getMessage()); // Detailed debugging
                  throw new \Exception("Failed to delete admin with ID $adminId: " . $e->getMessage());
              }
          } else {
              throw new \Exception("Admin with ID $adminId not found.");
          }
      }
  }