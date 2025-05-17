<?php

namespace App\Controller;

use App\Entity\Images;
use App\Service\AdminService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Controller for admin-related actions, such as managing game images.
 */
class AdminController extends AbstractController
{
    #[Route('/admin', name: 'admin_manage_images')]
    #[IsGranted('ROLE_USER')]
    public function manageImages(AdminService $adminService, Request $request): Response
    {
        $images = $adminService->getAllImages();

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
                        $image = $adminService->getImageById($imageId);
                        if (!$image) {
                            $this->addFlash('error', 'L\'image n\'existe pas.');
                            return $this->redirectToRoute('admin_manage_images');
                        }
                        $imageUrl = $adminService->handleImageUpload($file, $image->getImage_url());
                    } else {
                        $image = new Images();
                        $image->setId($this->generateUniqueImageId($adminService));
                        $imageUrl = $adminService->handleImageUpload($file);
                    }

                    $image->setWord($word);
                    $image->setImage_url($imageUrl);
                    $image->setFrench_translation($french ?: $word);
                    $image->setSpanish_translation($spanish ?: $word);
                    $image->setGerman_translation($german ?: $word);

                    $adminService->saveImage($image);
                    $this->addFlash('success', 'L\'image a été enregistrée avec succès.');
                } catch (\Exception $e) {
                    $this->addFlash('error', $e->getMessage());
                }
                return $this->redirectToRoute('admin_manage_images');
            }
        }

        if ($request->query->has('delete')) {
            $imageId = $request->query->getInt('delete');
            $adminService->deleteImage($imageId);
            $this->addFlash('success', 'L\'image a été supprimée avec succès.');
            return $this->redirectToRoute('admin_manage_images');
        }

        return $this->render('game/manage_images.html.twig', [
            'images' => $images,
        ]);
    }

    private function generateUniqueImageId(AdminService $adminService): int
    {
        do {
            $id = random_int(1, 999999);
            $exists = $adminService->getImageById($id);
        } while ($exists);
        return $id;
    }
}