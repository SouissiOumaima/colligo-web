<?php

namespace App\Controller;

use App\Entity\Fill_in_the_blank;
use App\Repository\Fill_in_the_blankRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class Fill_in_the_blankController extends AbstractController
{
    private Fill_in_the_blankRepository $repository;

    public function __construct(Fill_in_the_blankRepository $repository)
    {
        $this->repository = $repository;
    }

    #[Route('/Fill_in_the_blank/{id}', name: 'Fill_in_the_blank_show', methods: ['GET'])]
    public function show(int $id): Response
    {
        $Fill_in_the_blank = $this->repository->find($id);

        if (!$Fill_in_the_blank) {
            throw $this->createNotFoundException('Fill_in_the_blank not found');
        }

        return $this->render('Fill_in_the_blank/show.html.twig', [
            'Fill_in_the_blank' => $Fill_in_the_blank,
        ]);
    }
}