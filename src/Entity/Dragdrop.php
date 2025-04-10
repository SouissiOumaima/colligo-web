<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;


#[ORM\Entity]
class Dragdrop
{

    #[ORM\Id]
    #[ORM\Column(type: "integer")]
    private int $id;

    #[ORM\Column(type: "text")]
    private string $phrase;

    #[ORM\Column(type: "integer")]
    private int $niveau;

    #[ORM\Column(type: "string", length: 50)]
    private string $langue;

    public function getId()
    {
        return $this->id;
    }

    public function setId($value)
    {
        $this->id = $value;
    }

    public function getPhrase()
    {
        return $this->phrase;
    }

    public function setPhrase($value)
    {
        $this->phrase = $value;
    }

    public function getNiveau()
    {
        return $this->niveau;
    }

    public function setNiveau($value)
    {
        $this->niveau = $value;
    }

    public function getLangue()
    {
        return $this->langue;
    }

    public function setLangue($value)
    {
        $this->langue = $value;
    }
}
