<?php

namespace App\Controller;

use App\Entity\Images;
use App\Repository\ImagesRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class ImagesController extends AbstractController
{
    private ImagesRepository $repository;

    public function __construct(ImagesRepository $repository)
    {
        $this->repository = $repository;
    }

    #[Route('/Images/{id}', name: 'Images_show', methods: ['GET'])]
    public function show(int $id): Response
    {
        $Images = $this->repository->find($id);

        if (!$Images) {
            throw $this->createNotFoundException('Images not found');
        }

        return $this->render('Images/show.html.twig', [
            'Images' => $Images,
        ]);
    }
}