<?php

namespace App\Controller;

use App\Entity\Level;
use App\Repository\LevelRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class LevelController extends AbstractController
{
    private LevelRepository $repository;

    public function __construct(LevelRepository $repository)
    {
        $this->repository = $repository;
    }

    #[Route('/Level/{id}', name: 'Level_show', methods: ['GET'])]
    public function show(int $id): Response
    {
        $Level = $this->repository->find($id);

        if (!$Level) {
            throw $this->createNotFoundException('Level not found');
        }

        return $this->render('Level/show.html.twig', [
            'Level' => $Level,
        ]);
    }
}