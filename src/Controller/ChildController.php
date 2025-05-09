<?php

namespace App\Controller;

use App\Entity\Child;
use App\Repository\ChildRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class ChildController extends AbstractController
{
    private ChildRepository $repository;

    public function __construct(ChildRepository $repository)
    {
        $this->repository = $repository;
    }

    #[Route('/Child/{id}', name: 'Child_show', methods: ['GET'])]
    public function show(int $id): Response
    {
        $Child = $this->repository->find($id);

        if (!$Child) {
            throw $this->createNotFoundException('Child not found');
        }

        return $this->render('Child/show.html.twig', [
            'Child' => $Child,
        ]);
    }
}