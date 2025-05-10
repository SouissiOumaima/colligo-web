<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;


#[ORM\Entity]
class Admin
{

    #[ORM\Id]
    #[ORM\Column(type: "integer")]
    private int $adminId;

    #[ORM\Column(type: "string", length: 100)]
    private string $email;

    #[ORM\Column(type: "string", length: 255)]
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

    public function getPassword()
    {
        return $this->password;
    }

    public function setPassword($value)
    {
        $this->password = $value;
    }
}