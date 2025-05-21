<?php

namespace App\Controller;

use App\Entity\Parents;
use App\Repository\ParentsRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class ParentsController extends AbstractController
{
    private ParentsRepository $repository;

    public function __construct(ParentsRepository $repository)
    {
        $this->repository = $repository;
    }



    #[Route('/dashboard', name: 'app_dashboard')]
    public function index(): Response
    {
        $parent = $this->getUser();
        if (!$parent instanceof Parents) {
            // Mock the user directly for testing
            $parent = new Parents();
            $parent->setEmail('parent@example.com');
        }

        return $this->render('parents/dashboard.html.twig', [
            'parent' => $parent,
        ]);
    }


    #[Route('/Parents/{id}', name: 'Parents_show', methods: ['GET'])]
    public function show(int $id): Response
    {
        $Parents = $this->repository->find($id);

        if (!$Parents) {
            throw $this->createNotFoundException('Parents not found');
        }

        return $this->render('Parents/show.html.twig', [
            'Parents' => $Parents,
        ]);
    }

}