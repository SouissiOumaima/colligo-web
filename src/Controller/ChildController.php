<?php

namespace App\Controller;

use App\Entity\Child;
use App\Repository\ChildRepository;
use App\Repository\LevelRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Annotation\Route;

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
}
