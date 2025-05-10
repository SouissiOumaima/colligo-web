<?php

namespace App\Controller;

use App\Entity\Child;
use App\Entity\Images;
use App\Service\WordGameService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
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
        $this->addFlash('success', 'تم إعادة تعيين قاعدة البيانات بنجاح.');
        return $this->redirectToRoute('main_menu');
    }

    #[Route('/api/children', name: 'api_children', methods: ['GET'])]
    public function getChildren(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $parentId = $request->query->getInt('parentId');

        if (!$parentId) {
            return $this->json(['error' => 'معرف الوالد غير صالح أو مفقود'], 400);
        }

        // Fetch children for the given parentId
        $children = $em->getRepository(Child::class)->findBy(['parentId' => $parentId]);

        // Map children to a JSON-friendly format
        $data = array_map(function (Child $child) {
            return [
                'id' => $child->getChildId(),
                'name' => $child->getName() ?? 'طفل ' . $child->getChildId(),
            ];
        }, $children);

        return $this->json($data);
    }

    #[Route('/admin', name: 'parent_manage_images')]
    #[IsGranted('ROLE_USER')] // Assuming parents have ROLE_USER
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
                $this->addFlash('error', 'الكلمة مطلوبة.');
            } elseif (!$imageId && !$file) {
                $this->addFlash('error', 'ملف الصورة مطلوب للصور الجديدة.');
            } else {
                try {
                    if ($imageId) {
                        // Update existing image
                        $image = $wordGameService->getImageById($imageId);
                        if (!$image) {
                            $this->addFlash('error', 'الصورة غير موجودة.');
                            return $this->redirectToRoute('parent_manage_images');
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
                    $this->addFlash('success', 'تم حفظ الصورة بنجاح.');
                } catch (\Exception $e) {
                    $this->addFlash('error', $e->getMessage());
                }
                return $this->redirectToRoute('parent_manage_images');
            }
        }

        // Handle image deletion
        if ($request->query->has('delete')) {
            $imageId = $request->query->getInt('delete');
            $wordGameService->deleteImage($imageId);
            $this->addFlash('success', 'تم حذف الصورة بنجاح.');
            return $this->redirectToRoute('parent_manage_images');
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