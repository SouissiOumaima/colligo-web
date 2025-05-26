<?php

namespace App\Controller;

use App\Entity\Parents;
use App\Repository\ChildRepository;
use App\Repository\ParentsRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class DashboardParentController extends AbstractController
{
    #[Route('/dashboard/{parentId}', name: 'app_dashboard_parent')]
    public function index(int $parentId, ParentsRepository $parentsRepository, ChildRepository $childRepository): Response
    {
        $parent = $parentsRepository->find($parentId);
        $children = [];

        if ($parent) {
            $children = $childRepository->findBy(['parentId' => $parent->getParentId()]);
        }

        return $this->render('dashboard_parent/index.html.twig', [
            'parent' => $parent,
            'children' => $children,
            'error' => $parent ? null : 'Parent with ID ' . $parentId . ' not found.',
        ]);
    }

}