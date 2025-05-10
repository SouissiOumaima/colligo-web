<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;


#[ORM\Entity]
class Jeudedevinette
{

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: "string", length: 255)]
    private string $rightword;

    #[ORM\Column(type: "string", length: 255)]
    private string $wrongword;

    #[ORM\Column(type: "string", length: 50)]
    private string $language;

    #[ORM\Column(type: "string", length: 50)]
    private string $level;

    #[ORM\Column(type: "string", length: 255)]
    private string $thème;

    public function getId()
    {
        return $this->id;
    }

    public function setId($value)
    {
        $this->id = $value;
    }

    public function getRightword()
    {
        return $this->rightword;
    }

    public function setRightword($value)
    {
        $this->rightword = $value;
    }

    public function getWrongword()
    {
        return $this->wrongword;
    }

    public function setWrongword($value)
    {
        $this->wrongword = $value;
    }

    public function getLanguage()
    {
        return $this->language;
    }

    public function setLanguage($value)
    {
        $this->language = $value;
    }

    public function getLevel()
    {
        return $this->level;
    }

    public function setLevel($value)
    {
        $this->level = $value;
    }

    public function getThème()
    {
        return $this->thème;
    }

    public function setThème($value)
    {
        $this->thème = $value;
    }
}