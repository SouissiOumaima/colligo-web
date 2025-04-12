<?php

namespace App\Controller;

use App\Entity\Admin;
use App\Repository\AdminRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class AdminController extends AbstractController
{
    private AdminRepository $repository;

    public function __construct(AdminRepository $repository)
    {
        $this->repository = $repository;
    }

    #[Route('/admin/{id}', name: 'admin_show', methods: ['GET'])]
    public function show(int $id): Response
    {
        $admin = $this->repository->find($id);

        if (!$admin) {
            throw $this->createNotFoundException('Admin not found');
        }

        return $this->render('admin/show.html.twig', [
            'admin' => $admin,
        ]);
    }
}