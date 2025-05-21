<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;

#[ORM\Entity]
#[ORM\Table(name: 'admin')]
class Admin implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\Column(type: 'integer', name: 'adminId')]
    private int $adminId;

    #[ORM\Column(type: 'string', length: 100, unique: true)]
    private string $email;

    #[ORM\Column(type: 'string', length: 255)]
    private string $password;

    public function getAdminId(): int
    {
        return $this->adminId;
    }

    public function setAdminId(int $adminId): self
    {
        $this->adminId = $adminId;
        return $this;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): self
    {
        $this->email = $email;
        return $this;
    }

    public function getPassword(): string
    {
        return $this->password;
    }

    public function setPassword(string $password): self
    {
        $this->password = $password;
        return $this;
    }

    public function getRoles(): array
    {
        return ['ROLE_ADMIN']; // Default role for admin
    }

    public function eraseCredentials(): void
    {
        // If you store sensitive data (e.g., plain-text password), clear it here
    }

    public function getUserIdentifier(): string
    {
        return $this->email;
    }

    public function getSalt(): ?string
    {
        return null; // Not needed if using bcrypt or similar
    }

    public function getUsername(): string
    {
        return $this->getUserIdentifier(); // Alias for compatibility with older Symfony versions
    }
}