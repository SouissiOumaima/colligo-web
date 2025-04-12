<?php

namespace App\Controller;

use App\Entity\Jeudedevinette;
use App\Repository\JeudedevinetteRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class JeudedevinetteController extends AbstractController
{
    private JeudedevinetteRepository $repository;

    public function __construct(JeudedevinetteRepository $repository)
    {
        $this->repository = $repository;
    }

    #[Route('/Jeudedevinette/{id}', name: 'Jeudedevinette_show', methods: ['GET'])]
    public function show(int $id): Response
    {
        $Jeudedevinette = $this->repository->find($id);

        if (!$Jeudedevinette) {
            throw $this->createNotFoundException('Jeudedevinette not found');
        }

        return $this->render('Jeudedevinette/show.html.twig', [
            'Jeudedevinette' => $Jeudedevinette,
        ]);
    }
}