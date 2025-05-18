<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use App\Entity\Parents;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\ArrayCollection;
use App\Entity\Level;

#[ORM\Entity]
class Child
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: "integer")]
    private ?int $childId = null;

    #[ORM\ManyToOne(targetEntity: Parents::class, inversedBy: "childs")]
    #[ORM\JoinColumn(name: 'parentId', referencedColumnName: 'parentId', onDelete: 'CASCADE')]
    private ?Parents $parentId = null;

    #[ORM\Column(type: "integer")]
    private int $age;

    #[ORM\Column(type: "string", length: 50)]
    private string $language;

    #[ORM\Column(type: "string", length: 255)]
    private string $avatar;

    #[ORM\Column(type: "string", length: 100)]
    private string $name;

    #[ORM\OneToMany(mappedBy: "child", targetEntity: Level::class)]
    private Collection $levels;

    public function __construct()
    {
        $this->levels = new ArrayCollection();
    }

    public function getChildId(): ?int
    {
        return $this->childId;
    }

    public function getParentId(): ?Parents
    {
        return $this->parentId;
    }

    public function setParentId(?Parents $parentId): self
    {
        $this->parentId = $parentId;
        return $this;
    }

    public function getAge(): int
    {
        return $this->age;
    }

    public function setAge(int $age): self
    {
        $this->age = $age;
        return $this;
    }

    public function getLanguage(): string
    {
        return $this->language;
    }

    public function setLanguage(string $language): self
    {
        $this->language = $language;
        return $this;
    }

    public function getAvatar(): string
    {
        return $this->avatar;
    }

    public function setAvatar(string $avatar): self
    {
        $this->avatar = $avatar;
        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;
        return $this;
    }

    public function getLevels(): Collection
    {
        return $this->levels;
    }

    public function addLevel(Level $level): self
    {
        if (!$this->levels->contains($level)) {
            $this->levels[] = $level;
            $level->setChildId($this);
        }

        return $this;
    }

    public function removeLevel(Level $level): self
    {
        if ($this->levels->removeElement($level)) {
            if ($level->getChildId() === $this) {
                $level->setChildId(null);
            }
        }

        return $this;
    }
}