<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

use App\Entity\Parents;
use Doctrine\Common\Collections\Collection;
use App\Entity\Level;

#[ORM\Entity]
class Child
{

    #[ORM\Id]
    #[ORM\Column(type: "integer")]
    private int $childId;

    #[ORM\ManyToOne(targetEntity: Parents::class, inversedBy: "childs")]
    #[ORM\JoinColumn(name: 'parentId', referencedColumnName: 'parentId', onDelete: 'CASCADE')]
    private Parents $parentId;

    #[ORM\Column(type: "integer")]
    private int $age;

    #[ORM\Column(type: "string", length: 50)]
    private string $language;

    #[ORM\Column(type: "string", length: 255)]
    private string $avatar;

    #[ORM\Column(type: "string", length: 100)]
    private string $name;

    public function getChildId()
    {
        return $this->childId;
    }

    public function setChildId($value)
    {
        $this->childId = $value;
    }

    public function getParentId()
    {
        return $this->parentId;
    }

    public function setParentId($value)
    {
        $this->parentId = $value;
    }

    public function getAge()
    {
        return $this->age;
    }

    public function setAge($value)
    {
        $this->age = $value;
    }

    public function getLanguage()
    {
        return $this->language;
    }

    public function setLanguage($value)
    {
        $this->language = $value;
    }

    public function getAvatar()
    {
        return $this->avatar;
    }

    public function setAvatar($value)
    {
        $this->avatar = $value;
    }

    public function getName()
    {
        return $this->name;
    }

    public function setName($value)
    {
        $this->name = $value;
    }

    #[ORM\OneToMany(mappedBy: "childId", targetEntity: Level::class)]
    private Collection $levels;

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
            // set the owning side to null (unless already changed)
            if ($level->getChildId() === $this) {
                $level->setChildId(null);
            }
        }

        return $this;
    }
}
