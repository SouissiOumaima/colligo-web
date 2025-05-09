<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
#[ORM\Table(name: 'Admin')]
class Admin implements PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: "adminId", type: "integer")]
    private int $adminId;

    #[ORM\Column(type: "string", length: 100, unique: true)]
    #[Assert\Email(message: "L'email '{{ value }}' n'est pas valide.")]
    #[Assert\NotBlank(message: "L'email ne peut pas être vide.")]
    private string $email;

    #[ORM\Column(type: "string", length: 255)]
    #[Assert\NotBlank(message: "Le mot de passe ne peut pas être vide.")]
    #[Assert\Length(min: 8, minMessage: "Le mot de passe doit contenir au moins {{ limit }} caractères.")]
    private string $password;

    public function getAdminId()
    {
        return $this->adminId;
    }

    public function setAdminId($value)
    {
        $this->adminId = $value;
    }

    public function getEmail()
    {
        return $this->email;
    }

    public function setEmail($value)
    {
        $this->email = $value;
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(string $password): self
    {
        $this->password = $password;
        return $this;
    }
}
