<?php

namespace App\Controller;

use App\Entity\Game;
use App\Repository\GameRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class GameController extends AbstractController
{
    private GameRepository $repository;

    public function __construct(GameRepository $repository)
    {
        $this->repository = $repository;
    }

    #[Route('/Game/{id}', name: 'Game_show', methods: ['GET'])]
    public function show(int $id): Response
    {
        $Game = $this->repository->find($id);

        if (!$Game) {
            throw $this->createNotFoundException('Game not found');
        }

        return $this->render('Game/show.html.twig', [
            'Game' => $Game,
        ]);
    }
}