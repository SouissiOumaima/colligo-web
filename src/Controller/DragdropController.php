<?php

namespace App\Controller;

use App\Entity\Dragdrop;
use App\Repository\DragdropRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class DragdropController extends AbstractController
{
    private DragdropRepository $repository;

    public function __construct(DragdropRepository $repository)
    {
        $this->repository = $repository;
    }

    #[Route('/Dragdrop/{id}', name: 'Dragdrop_show', methods: ['GET'])]
    public function show(int $id): Response
    {
        $Dragdrop = $this->repository->find($id);

        if (!$Dragdrop) {
            throw $this->createNotFoundException('Dragdrop not found');
        }

        return $this->render('Dragdrop/show.html.twig', [
            'Dragdrop' => $Dragdrop,
        ]);
    }
}