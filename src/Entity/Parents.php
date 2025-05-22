<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;

#[ORM\Entity]
class Parents implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer', name: "parentId")]
    private ?int $parentId = null;

    #[ORM\Column(type: 'string', length: 100, unique: true)]
    private ?string $email = null;

    #[ORM\Column(type: 'string', length: 255)]
    private ?string $password = null;

    #[ORM\Column(type: 'string', length: 6, nullable: true)]
    private ?string $verification_code = null;

    #[ORM\Column(type: 'boolean')]
    private bool $is_verified = false;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $signup_token = null;

    #[ORM\Column(type: 'string', length: 6, nullable: true)]
    private ?string $reset_code = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $reset_password_token = null;

    #[ORM\OneToMany(mappedBy: 'parentId', targetEntity: Child::class)]
    private Collection $childs;

    public function __construct()
    {
        $this->childs = new ArrayCollection();
    }

    public function getParentId(): ?int
    {
        return $this->parentId;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(?string $email): self
    {
        $this->email = $email;
        return $this;
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

   public function setPassword(?string $password): self
{
    $this->password = $password;
    return $this;
}


    public function getVerificationCode(): ?string
    {
        return $this->verification_code;
    }

    public function setVerificationCode(?string $verification_code): self
    {
        $this->verification_code = $verification_code;
        return $this;
    }

    public function getIsVerified(): bool
    {
        return $this->is_verified;
    }

    public function setIsVerified(bool $is_verified): self
    {
        $this->is_verified = $is_verified;
        return $this;
    }

    public function getSignupToken(): ?string
    {
        return $this->signup_token;
    }

    public function setSignupToken(?string $signup_token): self
    {
        $this->signup_token = $signup_token;
        return $this;
    }

    public function getResetCode(): ?string
    {
        return $this->reset_code;
    }

    public function setResetCode(?string $reset_code): self
    {
        $this->reset_code = $reset_code;
        return $this;
    }

    public function getResetPasswordToken(): ?string
    {
        return $this->reset_password_token;
    }

    public function setResetPasswordToken(?string $reset_password_token): self
    {
        $this->reset_password_token = $reset_password_token;
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

    public function getRoles(): array
    {
        return ['ROLE_USER'];
    }

    public function eraseCredentials(): void
    {
    }

    public function getUserIdentifier(): string
    {
        return $this->email;
    }

    public function getUsername(): string
    {
        return $this->email;
    }
}