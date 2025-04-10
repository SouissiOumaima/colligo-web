<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

use Doctrine\Common\Collections\Collection;
use App\Entity\Child;

#[ORM\Entity]
class Parent
{

    #[ORM\Id]
    #[ORM\Column(type: "integer")]
    private int $parentId;

    #[ORM\Column(type: "string", length: 100)]
    private string $email;

    #[ORM\Column(type: "string", length: 255)]
    private string $password;

    #[ORM\Column(type: "string", length: 255)]
    private string $verification_code;

    #[ORM\Column(type: "string", length: 255)]
    private string $is_verified;

    public function getParentId()
    {
        return $this->parentId;
    }

    public function setParentId($value)
    {
        $this->parentId = $value;
    }

    public function getEmail()
    {
        return $this->email;
    }

    public function setEmail($value)
    {
        $this->email = $value;
    }

    public function getPassword()
    {
        return $this->password;
    }

    public function setPassword($value)
    {
        $this->password = $value;
    }

    public function getVerification_code()
    {
        return $this->verification_code;
    }

    public function setVerification_code($value)
    {
        $this->verification_code = $value;
    }

    public function getIs_verified()
    {
        return $this->is_verified;
    }

    public function setIs_verified($value)
    {
        $this->is_verified = $value;
    }

    #[ORM\OneToMany(mappedBy: "parentId", targetEntity: Child::class)]
    private Collection $childs;

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
                // set the owning side to null (unless already changed)
                if ($child->getParentId() === $this) {
                    $child->setParentId(null);
                }
            }
    
            return $this;
        }
}
