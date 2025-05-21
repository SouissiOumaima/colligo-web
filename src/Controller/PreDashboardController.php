<?php

namespace App\Controller;

use App\Entity\Child;
use App\Form\ChildType;
use App\Repository\ChildRepository;
use App\Repository\ParentsRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class PreDashboardController extends AbstractController
{
    #[Route('/pre-dashboard/{parentId}', name: 'pre_dashboard', methods: ['GET', 'POST'])]
    public function index(
        int $parentId,
        Request $request,
        ParentsRepository $parentsRepository,
        ChildRepository $childRepository,
        EntityManagerInterface $entityManager
    ): Response {
        $parent = $parentsRepository->find($parentId);
        if (!$parent) {
            throw $this->createNotFoundException('Parent avec ID ' . $parentId . ' non trouvé.');
        }

        $children = $childRepository->findBy(['parentId' => $parent]);

        $child = new Child();
        $child->setParentId($parent);
        $form = $this->createForm(ChildType::class, $child);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Assign a unique childId
            $maxId = $entityManager->getRepository(Child::class)
                ->createQueryBuilder('c')
                ->select('COALESCE(MAX(c.childId), 0)')
                ->getQuery()
                ->getSingleScalarResult();
            $child->setChildId($maxId + 1);

            $entityManager->persist($child);
            $entityManager->flush();

            $this->addFlash('success', 'Bonjour ' . $child->getName() . ' ! Le compte a été créé avec succès.');
            return $this->redirectToRoute('pre_dashboard', ['parentId' => $parentId]);
        }

        return $this->render('pre_dashboard/index.html.twig', [
            'parent' => $parent,
            'children' => $children,
            'form' => $form->createView(),
            'showModal' => $form->isSubmitted(),
            'getLanguageName' => [$this, 'getLanguageName'],
        ]);
    }

    #[Route('/child/{childId}', name: 'app_child_view')]
    public function showChildView(int $childId, ChildRepository $childRepository): Response
    {
        $child = $childRepository->find($childId);
        if (!$child) {
            throw $this->createNotFoundException('Enfant non trouvé.');
        }

        return $this->render('child/view.html.twig', [
            'child' => $child,
            'controller' => $this,
        ]);
    }

    public function getLanguageName(string $code): string
    {
        $languages = [
            'fr' => 'Français',
            'en' => 'Anglais',
            'es' => 'Espagnol',
            'de' => 'Allemand',
            'al' => 'Allemand', // Correction: 'al' should map to 'Allemand'
        ];
        return $languages[$code] ?? $code;
    }
}