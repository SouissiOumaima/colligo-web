<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class StaticAuthController extends AbstractController
{
    #[Route('/auth', name: 'app_static_auth')]
    public function index(): Response
    {
        return $this->render('static_auth/index.html.twig');
    }

    #[Route('/signup', name: 'app_static_signup')]
    public function signup(): Response
    {
        return $this->render('static_auth/index.html.twig', [
            'is_signup' => true,
        ]);
    }
}