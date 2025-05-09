<?php

namespace App\Entity;

use App\Repository\ParentsRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ParentsRepository::class)]
#[ORM\Table(name: 'Parents')]
class Parents
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'parentId')]
    private ?int $parentId = null;

    #[ORM\Column(name: 'email', length: 100, unique: true)]
    private ?string $email = null;

    #[ORM\Column(name: 'password', length: 255)]
    private ?string $password = null;

    /**
     * @var Collection<int, Child>
     */
    #[ORM\OneToMany(targetEntity: Child::class, mappedBy: 'parentId')]
    private Collection $children;

    public function __construct()
    {
        $this->children = new ArrayCollection();
    }

    public function getParentId(): ?int
    {
        return $this->parentId;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;

        return $this;
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;

        return $this;
    }

    /**
     * @return Collection<int, Child>
     */
    public function getChildren(): Collection
    {
        return $this->children;
    }

    public function addChild(Child $child): static
    {
        if (!$this->children->contains($child)) {
            $this->children->add($child);
            $child->setParentId($this);
        }

        return $this;
    }

    public function removeChild(Child $child): static
    {
        if ($this->children->removeElement($child)) {
            // set the owning side to null (unless already changed)
            if ($child->getParentId() === $this) {
                $child->setParentId(null);
            }
        }

        return $this;
    }
}
