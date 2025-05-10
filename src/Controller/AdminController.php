<?php

namespace App\Controller;

use App\Entity\Images;
use App\Service\WordGameService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class AdminController extends AbstractController
{
    #[Route('/reset', name: 'reset_database', methods: ['POST'])]
    public function resetDatabase(WordGameService $wordGameService): Response
    {
        $wordGameService->resetDatabase();
        $this->addFlash('success', 'La base de données a été réinitialisée avec succès.');
        return $this->redirectToRoute('main_menu');
    }

    #[Route('/admin', name: 'admin_manage_images')]
    #[IsGranted('ROLE_USER')] 
    public function manageImages(WordGameService $wordGameService, Request $request): Response
    {
        // Get all images
        $images = $wordGameService->getAllImages();

        // Handle form submission for adding/editing images
        if ($request->isMethod('POST')) {
            $imageId = $request->request->getInt('image_id');
            $word = $request->request->get('word');
            $french = $request->request->get('french_translation');
            $spanish = $request->request->get('spanish_translation');
            $german = $request->request->get('german_translation');
            $file = $request->files->get('image_file');

            if (!$word) {
                $this->addFlash('error', 'Le mot est requis.');
            } elseif (!$imageId && !$file) {
                $this->addFlash('error', 'Le fichier image est requis pour les nouvelles images.');
            } else {
                try {
                    if ($imageId) {
                        // Update existing image
                        $image = $wordGameService->getImageById($imageId);
                        if (!$image) {
                            $this->addFlash('error', 'L\'image n\'existe pas.');
                            return $this->redirectToRoute('admin_manage_images');
                        }
                        // Update image only if a new file is provided
                        $imageUrl = $wordGameService->handleImageUpload($file, $image->getImage_url());
                    } else {
                        // Create new image
                        $image = new Images();
                        $image->setId($this->generateUniqueImageId($wordGameService));
                        $imageUrl = $wordGameService->handleImageUpload($file);
                    }

                    $image->setWord($word);
                    $image->setImage_url($imageUrl);
                    $image->setFrench_translation($french ?: $word);
                    $image->setSpanish_translation($spanish ?: $word);
                    $image->setGerman_translation($german ?: $word);

                    $wordGameService->saveImage($image);
                    $this->addFlash('success', 'L\'image a été enregistrée avec succès.');
                } catch (\Exception $e) {
                    $this->addFlash('error', $e->getMessage());
                }
                return $this->redirectToRoute('admin_manage_images');
            }
        }

        // Handle image deletion
        if ($request->query->has('delete')) {
            $imageId = $request->query->getInt('delete');
            $wordGameService->deleteImage($imageId);
            $this->addFlash('success', 'L\'image a été supprimée avec succès.');
            return $this->redirectToRoute('admin_manage_images');
        }

        return $this->render('game/manage_images.html.twig', [
            'images' => $images,
        ]);
    }

    private function generateUniqueImageId(WordGameService $wordGameService): int
    {
        do {
            $id = random_int(1, 999999);
            $exists = $wordGameService->getImageById($id);
        } while ($exists);
        return $id;
    }
}