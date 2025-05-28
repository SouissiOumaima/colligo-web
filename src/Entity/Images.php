<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;


#[ORM\Entity]
class Images
{

    #[ORM\Id]
    #[ORM\Column(type: "integer")]
    private int $id;

    #[ORM\Column(type: "string", length: 255)]
    private string $word;

    #[ORM\Column(type: "string", length: 512)]
    private string $image_url;

    #[ORM\Column(type: "string", length: 45)]
    private string $french_translation;

    #[ORM\Column(type: "string", length: 45)]
    private string $spanish_translation;

    #[ORM\Column(type: "string", length: 45)]
    private string $german_translation;

    public function getId()
    {
        return $this->id;
    }

    public function setId($value)
    {
        $this->id = $value;
    }

    public function getWord()
    {
        return $this->word;
    }

    public function setWord($value)
    {
        $this->word = $value;
    }

    public function getImage_url()
    {
        return $this->image_url;
    }

    public function setImage_url($value)
    {
        $this->image_url = $value;
    }

    public function getFrench_translation()
    {
        return $this->french_translation;
    }

    public function setFrench_translation($value)
    {
        $this->french_translation = $value;
    }

    public function getSpanish_translation()
    {
        return $this->spanish_translation;
    }

    public function setSpanish_translation($value)
    {
        $this->spanish_translation = $value;
    }

    public function getGerman_translation()
    {
        return $this->german_translation;
    }

    public function setGerman_translation($value)
    {
        $this->german_translation = $value;
    }
}
