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