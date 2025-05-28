<?php

namespace App\Controller;

use App\Entity\Matching_game;
use App\Repository\Matching_gameRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class Matching_gameController extends AbstractController
{
    private Matching_gameRepository $repository;

    public function __construct(Matching_gameRepository $repository)
    {
        $this->repository = $repository;
    }

    #[Route('/Matching_game/{id}', name: 'Matching_game_show', methods: ['GET'])]
    public function show(int $id): Response
    {
        $Matching_game = $this->repository->find($id);

        if (!$Matching_game) {
            throw $this->createNotFoundException('Matching_game not found');
        }

        return $this->render('Matching_game/show.html.twig', [
            'Matching_game' => $Matching_game,
        ]);
    }
}