<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\ArrayCollection;
use App\Entity\Child;

#[ORM\Entity]
#[ORM\Table(name: "parent")]
class Parents
{
    #[ORM\Id]
    #[ORM\Column(type: "integer", name: "parentId")]
    private int $parentId;

    #[ORM\Column(type: "string", length: 100)]
    private string $email;

    #[ORM\Column(type: "string", length: 255)]
    private string $password;

    #[ORM\Column(type: "string", length: 255)]
    private string $verification_code;

    #[ORM\Column(type: "string", length: 255)]
    private string $is_verified;

    #[ORM\OneToMany(mappedBy: "parentId", targetEntity: Child::class)]
    private Collection $childs;

    public function __construct()
    {
        $this->childs = new ArrayCollection();
    }

    public function getParentId(): int
    {
        return $this->parentId;
    }

    public function setParentId(int $value): self
    {
        $this->parentId = $value;
        return $this;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $value): self
    {
        $this->email = $value;
        return $this;
    }

    public function getPassword(): string
    {
        return $this->password;
    }

    public function setPassword(string $value): self
    {
        $this->password = $value;
        return $this;
    }

    public function getVerificationCode(): string
    {
        return $this->verification_code;
    }

    public function setVerificationCode(string $value): self
    {
        $this->verification_code = $value;
        return $this;
    }

    public function getIsVerified(): string
    {
        return $this->is_verified;
    }

    public function setIsVerified(string $value): self
    {
        $this->is_verified = $value;
        return $this;
    }

    public function getChilds(): Collection
    {
        return $this->childs;
    }

    public function addChild(Child $child): self
    {
        if (!$this->childs->contains($child)) {
            $this->childs[] = $child;
            $child->setParentId($this);
        }

        return $this;
    }

    public function removeChild(Child $child): self
    {
        if ($this->childs->removeElement($child)) {
            if ($child->getParentId() === $this) {
                $child->setParentId(null);
            }
        }

        return $this;
    }
}