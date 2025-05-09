<?php

namespace App\Entity;

use App\Repository\ChildRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ChildRepository::class)]
class Child
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'childId')]
    private ?int $childId = null;

    #[ORM\ManyToOne(targetEntity: Parents::class, inversedBy: "childs")]
    #[ORM\JoinColumn(name: "parentId", referencedColumnName: "parentId")]
    private Parents $parentId;

    #[ORM\Column(nullable: true)]
    private ?int $age = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $language = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $avatar = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $name = null;

    public function getChildId(): ?int
    {
        return $this->childId;
    }

    public function getParentId(): ?Parents
    {
        return $this->parentId;
    }

    public function setParentId(?Parents $parentId): static
    {
        $this->parentId = $parentId;
        return $this;
    }

    public function getAge(): ?int
    {
        return $this->age;
    }

    public function setAge(?int $age): static
    {
        $this->age = $age;
        return $this;
    }

    public function getLanguage(): ?string
    {
        return $this->language;
    }

    public function setLanguage(?string $language): static
    {
        $this->language = $language;
        return $this;
    }

    public function getAvatar(): ?string
    {
        return $this->avatar;
    }

    public function setAvatar(?string $avatar): static
    {
        $this->avatar = $avatar;
        return $this;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): static
    {
        $this->name = $name;
        return $this;
    }
}