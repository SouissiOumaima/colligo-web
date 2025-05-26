<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;


#[ORM\Entity]
class Matching_game
{

    #[ORM\Id]
    #[ORM\Column(type: "integer")]
    private int $id;

    #[ORM\Column(type: "string", length: 50)]
    private string $langue;

    #[ORM\Column(type: "string", length: 20)]
    private string $niveau;

    #[ORM\Column(type: "text")]
    private string $words;

    public function getId()
    {
        return $this->id;
    }

    public function setId($value)
    {
        $this->id = $value;
    }

    public function getLangue()
    {
        return $this->langue;
    }

    public function setLangue($value)
    {
        $this->langue = $value;
    }

    public function getNiveau()
    {
        return $this->niveau;
    }

    public function setNiveau($value)
    {
        $this->niveau = $value;
    }

    public function getWords()
    {
        return $this->words;
    }

    public function setWords($value)
    {
        $this->words = $value;
    }
}
