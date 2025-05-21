<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use App\Entity\Parents;
use App\Entity\Level;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\ArrayCollection;

#[ORM\Entity]
#[ORM\Table(name: "child")]
class Child
{
    #[ORM\Id]
    #[ORM\Column(type: "integer", name: "childId")]
    private int $childId;

    #[ORM\ManyToOne(targetEntity: Parents::class, inversedBy: "childs")]
    #[ORM\JoinColumn(name: "parentId", referencedColumnName: "parentId", onDelete: "CASCADE")]
    private Parents $parentId;

    #[ORM\Column(type: "integer")]
    private int $age;

    #[ORM\Column(type: "string", length: 50)]
    private string $language;

    #[ORM\Column(type: "string", length: 255)]
    private string $avatar;

    #[ORM\Column(type: "string", length: 100)]
    private string $name;

    #[ORM\OneToMany(mappedBy: "childId", targetEntity: Level::class)]
    private Collection $levels;

    public function __construct()
    {
        $this->levels = new ArrayCollection();
    }

    public function getChildId(): int
    {
        return $this->childId;
    }

    public function setChildId(int $value): self
    {
        $this->childId = $value;
        return $this;
    }

    public function getParentId(): Parents
    {
        return $this->parentId;
    }

    public function setParentId(Parents $value): self
    {
        $this->parentId = $value;
        return $this;
    }

    public function getAge(): int
    {
        return $this->age;
    }

    public function setAge(int $value): self
    {
        $this->age = $value;
        return $this;
    }

    public function getLanguage(): string
    {
        return $this->language;
    }

    public function setLanguage(string $value): self
    {
        $this->language = $value;
        return $this;
    }

    public function getAvatar(): string
    {
        return $this->avatar;
    }

    public function setAvatar(string $value): self
    {
        $this->avatar = $value;
        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $value): self
    {
        $this->name = $value;
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