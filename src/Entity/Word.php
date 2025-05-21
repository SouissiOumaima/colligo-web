<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'words')]
class Word
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 255)]
    private ?string $word = null;

    #[ORM\Column(type: 'string', length: 255)]
    private ?string $synonym = null;

    #[ORM\ManyToOne(targetEntity: Theme::class, inversedBy: 'words')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Theme $theme = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getWord(): ?string
    {
        return $this->word;
    }

    public function setWord(string $word): self
    {
        $this->word = $word;
        return $this;
    }

    public function getSynonym(): ?string
    {
        return $this->synonym;
    }

    public function setSynonym(string $synonym): self
    {
        $this->synonym = $synonym;
        return $this;
    }

    public function getTheme(): ?Theme
    {
        return $this->theme;
    }

    public function setTheme(?Theme $theme): self
    {
        $this->theme = $theme;
        return $this;
    }
}