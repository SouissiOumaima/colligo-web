<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use App\Repository\DragdropRepository;

#[ORM\Entity(repositoryClass: DragdropRepository::class)]
class Dragdrop
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: "integer")]
    private int $id;

    #[ORM\Column(type: "text")]
    private string $phrase;

    #[ORM\Column(name: "arabicTranslation", type: "text", nullable: true)] // Explicitly set name to match database
    private ?string $arabicTranslation = null;

    #[ORM\Column(type: "integer")]
    private int $niveau;

    #[ORM\Column(type: "string", length: 50)]
    private string $langue;

    public function getId(): int
    {
        return $this->id;
    }

    public function setId(int $value): self
    {
        $this->id = $value;
        return $this;
    }

    public function getPhrase(): string
    {
        return $this->phrase;
    }

    public function setPhrase(string $value): self
    {
        $this->phrase = $value;
        return $this;
    }

    public function getArabicTranslation(): ?string
    {
        return $this->arabicTranslation;
    }

    public function setArabicTranslation(?string $value): self
    {
        $this->arabicTranslation = $value;
        return $this;
    }

    public function getNiveau(): int
    {
        return $this->niveau;
    }

    public function setNiveau(int $value): self
    {
        $this->niveau = $value;
        return $this;
    }

    public function getLangue(): string
    {
        return $this->langue;
    }

    public function setLangue(string $value): self
    {
        $this->langue = $value;
        return $this;
    }
}