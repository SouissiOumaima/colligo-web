<?php

namespace App\Service;

use App\Entity\Images;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Service for managing administrative tasks, such as image handling.
 */
class AdminService
{
    private EntityManagerInterface $em;
    private string $uploadDir;

    public function __construct(EntityManagerInterface $em, string $uploadDir)
    {
        $this->em = $em;
        $this->uploadDir = $uploadDir;

        // Create the upload directory if it doesn't exist
        if (!is_dir($this->uploadDir)) {
            mkdir($this->uploadDir, 0777, true);
        }
    }

    public function getAllImages(): array
    {
        return $this->em->getRepository(Images::class)->findAll();
    }

    public function getImageById(int $id): ?Images
    {
        return $this->em->getRepository(Images::class)->find($id);
    }

    public function saveImage(Images $image): void
    {
        $this->em->persist($image);
        $this->em->flush();
    }

    public function deleteImage(int $id): void
    {
        $image = $this->getImageById($id);
        if ($image) {
            $filePath = $this->uploadDir . '/' . basename($image->getImage_url());
            if (file_exists($filePath)) {
                unlink($filePath);
            }
            $this->em->remove($image);
            $this->em->flush();
        }
    }

    public function handleImageUpload(?UploadedFile $file, ?string $existingUrl = null): string
    {
        if ($file) {
            $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
            if (!in_array($file->getMimeType(), $allowedTypes)) {
                throw new \Exception('Invalid file type. Only JPEG, PNG, and GIF are allowed.');
            }

            if ($file->getSize() > 5 * 1024 * 1024) {
                throw new \Exception('File size exceeds 5MB limit.');
            }

            // Check image dimensions
            $imageInfo = getimagesize($file->getPathname());
            if ($imageInfo === false) {
                throw new \Exception('Unable to determine image dimensions.');
            }

            $width = $imageInfo[0];
            $height = $imageInfo[1];
            if ($width !== 1024 || $height !== 1024) {
                throw new \Exception('Image must be exactly 1024x1024 pixels. Current dimensions are ' . $width . 'x' . $height . ' pixels.');
            }

            $filename = md5(uniqid()) . '.' . $file->guessExtension();
            $file->move($this->uploadDir, $filename);

            return '/uploads/images/' . $filename;
        }

        if ($existingUrl) {
            return $existingUrl;
        }

        throw new \Exception('An image file is required.');
    }
}