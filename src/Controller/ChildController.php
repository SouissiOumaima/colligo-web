<?php

namespace App\Controller;

use App\Entity\Child;
use App\Repository\ChildRepository;
use App\Repository\LevelRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Annotation\Route;
use App\Entity\Parents;
use App\Entity\Level;
use App\Repository\ParentsRepository;
use Symfony\Component\HttpFoundation\Request;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use App\Form\ChildType;

class ChildController extends AbstractController
{
    private ChildRepository $childRepository;
    private LevelRepository $levelRepository;

    public function __construct(ChildRepository $childRepository, LevelRepository $levelRepository)
    {
        $this->childRepository = $childRepository;
        $this->levelRepository = $levelRepository;
    }

    #[Route('/child/{parentId}/{childId}', name: 'child_dashboard', methods: ['GET'])]
    public function dashboard(int $parentId, int $childId): Response
    {
        $child = $this->childRepository->findOneBy([
            'parentId' => $parentId,
            'childId' => $childId
        ]);

        if (!$child) {
            throw $this->createNotFoundException('Child not found');
        }



        $gameProgress = $this->childRepository->findGameProgress($childId);
        $score = array_reduce($gameProgress, fn($carry, $item) => $carry + $item['score'], 0);

        // Utilise gameId = 1 pour l’exemple
        $level = $this->levelRepository->findMaxIdForGameAndChild(1, $childId) ?? 1;

        return $this->render('child/dashboard.html.twig', [
            'child' => $child,
            'username' => $child->getName(),
            'level' => $level,
            'score' => $score,
            'language' => $this->mapLanguageCodeToName($child->getLanguage()),
            'gameProgress' => $gameProgress,
        ]);
    }

    #[Route('/child/{parentId}/{childId}/game/{gameIndex}', name: 'child_game', methods: ['GET'], requirements: ['gameIndex' => '\d+'])]
    public function showGameDetailView(int $parentId, int $childId, int $gameIndex, SessionInterface $session): Response
    {
        $child = $this->childRepository->findOneBy([
            'parentId' => $parentId,
            'childId' => $childId
        ]);

        if (!$child) {
            throw $this->createNotFoundException('Child not found');
        }

        if ($gameIndex === 1) {
            $level = $this->levelRepository->findMaxIdForGameAndChild(1, $childId) ?? 1;

            return $this->render('game/main_guessing_game.html.twig', [
                'child' => $child,
                'level' => $level,
                'language' => $this->mapLanguageCodeToName($child->getLanguage()),
            ]);
        }

        $this->addFlash('error', 'Unknown game index: ' . $gameIndex);
        return $this->redirectToRoute('child_dashboard', [
            'parentId' => $parentId,
            'childId' => $childId
        ]);
    }

    private function mapLanguageCodeToName(string $code): string
    {
        return match (strtolower($code)) {
            'fr' => 'Français',
            'en' => 'Anglais',
            'de' => 'Allemand',
            'es' => 'Espagnol',
            'ar' => 'العربية',
            'français' => 'Français',
            'anglais' => 'Anglais',
            'allemand' => 'Allemand',
            'espagnol' => 'Espagnol',
            default => 'Français',
        };
    }


    #[Route('/parent/{parentId}/manage-children', name: 'app_manage_children', methods: ['GET'])]
    public function manageChildren(int $parentId, ChildRepository $childRepository, ParentsRepository $parentsRepository): Response
    {
        $parent = $parentsRepository->find($parentId);

        if (!$parent) {
            throw $this->createNotFoundException('Parent non trouvé avec l\'ID ' . $parentId);
        }

        $children = $childRepository->findBy(['parentId' => $parent]);

        if (empty($children)) {
            $this->addFlash('info', 'Aucun enfant trouvé pour ce parent.');
        }

        return $this->render('child/manage-children.html.twig', [
            'parent' => $parent,
            'children' => $children,
        ]);
    }

    #[Route('/child/{childId}/edit/{parentId?}', name: 'app_edit_child', methods: ['GET', 'POST'], requirements: ['childId' => '\d+', 'parentId' => '\d+'])]
    public function editChild(Request $request, int $childId, EntityManagerInterface $entityManager, LoggerInterface $logger, ?int $parentId = null): Response
    {
        $childRepository = $entityManager->getRepository(Child::class);
        $child = $childRepository->find($childId);

        if (!$child) {
            $logger->error('Enfant non trouvé', ['childId' => $childId]);
            $this->addFlash('error', 'Enfant non trouvé avec l\'ID ' . $childId);
            return $this->redirectToRoute('app_manage_children', ['parentId' => $parentId ?? $request->query->get('parentId', 0)]);
        }

        $parent = $child->getParentId();
        if (!$parent) {
            $logger->error('Parent non trouvé pour l\'enfant', ['childId' => $childId]);
            $this->addFlash('error', 'Parent associé non trouvé.');
            return $this->redirectToRoute('app_manage_children', ['parentId' => $parentId ?? $request->query->get('parentId', 0)]);
        }

        $parentId = $parentId ?? $parent->getParentId();

        $form = $this->createForm(ChildType::class, $child);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            $logger->debug('Form submitted', [
                'childId' => $childId,
                'form_data' => $request->request->all(),
                'session_id' => $request->getSession()->getId(),
            ]);

            if ($form->isValid()) {
                $logger->info('Formulaire de modification soumis', [
                    'childId' => $child->getChildId(),
                    'data' => [
                        'name' => $form->get('name')->getData(),
                        'age' => $form->get('age')->getData(),
                        'language' => $form->get('language')->getData(),
                        'avatar' => $form->get('avatar')->getData(),
                    ],
                ]);

                try {
                    $entityManager->flush();
                    $logger->info('Enfant mis à jour', ['childId' => $child->getChildId()]);
                    $this->addFlash('success', 'L\'enfant a été modifié avec succès.');
                    return $this->redirectToRoute('app_manage_children', ['parentId' => $parent->getParentId()]);
                } catch (\Exception $e) {
                    $logger->error('Erreur lors de la mise à jour', ['childId' => $child->getChildId(), 'error' => $e->getMessage()]);
                    $this->addFlash('error', 'Erreur lors de la mise à jour : ' . $e->getMessage());
                }
            } else {
                $logger->warning('Erreurs de validation du formulaire', [
                    'childId' => $childId,
                    'errors' => $form->getErrors(true),
                    'form_data' => $request->request->all(),
                ]);
                $this->addFlash('error', 'Erreur dans le formulaire. Vérifiez les champs.');
            }
        }

        return $this->render('child/edit.html.twig', [
            'child' => $child,
            'parentId' => $parentId,
            'form' => $form->createView(),
        ]);
    }



    #[Route('/child/{childId}/delete', name: 'app_delete_child', methods: ['POST'])]
    public function deleteChild(int $childId, Request $request, ChildRepository $childRepository, EntityManagerInterface $entityManager, LoggerInterface $logger): Response
    {
        $child = $childRepository->find($childId);

        if (!$child) {
            $this->addFlash('error', 'Enfant non trouvé avec l\'ID ' . $childId);
            return $this->redirectToRoute('app_manage_children', ['parentId' => $request->request->get('parentId', 0)]);
        }

        $parent = $child->getParentId();

        if (!$parent) {
            $this->addFlash('error', 'Parent associé non trouvé pour l\'enfant avec l\'ID ' . $childId);
            return $this->redirectToRoute('app_manage_children', ['parentId' => $request->request->get('parentId', 0)]);
        }

        if ($this->isCsrfTokenValid('delete' . $childId, $request->request->get('_token'))) {
            $logger->info('Suppression de l\'enfant', ['childId' => $childId]);

            // Explicitly remove associated levels (optional if ON DELETE CASCADE is set in DB)
            foreach ($child->getLevels() as $level) {
                $entityManager->remove($level);
            }

            $entityManager->remove($child);
            $entityManager->flush();

            $this->addFlash('success', 'L\'enfant a été supprimé avec succès.');
        } else {
            $logger->error('Échec de la validation CSRF pour la suppression', ['childId' => $childId]);
            $this->addFlash('error', 'Échec de la validation du jeton CSRF.');
        }

        return $this->redirectToRoute('app_manage_children', ['parentId' => $parent->getParentId()]);
    }

    #[Route('/parent/{parentId}/add-child', name: 'app_add_child', methods: ['GET', 'POST'])]
    public function addChild(int $parentId, Request $request, ParentsRepository $parentsRepository, EntityManagerInterface $entityManager, LoggerInterface $logger): Response
    {
        $parent = $parentsRepository->find($parentId);

        if (!$parent) {
            $this->addFlash('error', 'Parent non trouvé avec l\'ID ' . $parentId);
            return $this->redirectToRoute('pre_dashboard', ['parentId' => $parentId]);
        }

        $child = new Child();
        $child->setParentId($parent);
        $form = $this->createForm(ChildType::class, $child);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            $logger->info('Formulaire d\'ajout soumis', [
                'data' => [
                    'name' => $form->get('name')->getData(),
                    'age' => $form->get('age')->getData(),
                    'language' => $form->get('language')->getData(),
                    'avatar' => $form->get('avatar')->getData(),
                ],
                'errors' => $form->getErrors(true),
            ]);

            if ($form->isValid()) {
                $child->setLanguage(($form->get('language')->getData()));
                $child->setChildId($this->generateUniqueChildId());
                $entityManager->persist($child);
                $entityManager->flush();

                $this->addFlash('success', 'L\'enfant a été ajouté avec succès.');
                return $this->redirectToRoute('app_manage_children', ['parentId' => $parentId]);
            } else {
                $this->addFlash('error', 'Erreur dans le formulaire. Vérifiez les champs.');
            }
        }

        return $this->render('pre_dashboard/index.html.twig', [
            'children' => $this->childRepository->findBy(['parentId' => $parent]),
            'form' => $form->createView(),
            'parent' => $parent,
            'showModal' => $form->isSubmitted() && !$form->isValid(),
        ]);
    }

    private function generateUniqueChildId(): int
    {
        $maxAttempts = 10;
        for ($i = 0; $i < $maxAttempts; $i++) {
            $id = random_int(1, 999999);
            if (!$this->childRepository->find($id)) {
                return $id;
            }
        }
        throw new \RuntimeException('Impossible de générer un ID unique pour l\'enfant.');
    }
}
