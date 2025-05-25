<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: \App\Repository\WordEntryRepository::class)]
class WordEntry
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 255)]
    private ?string $rightWord = null;

    #[ORM\Column(type: 'string', length: 255)]
    private ?string $wrongWord = null;

    #[ORM\Column(type: 'string', length: 255)]
    private ?string $theme = null;

    #[ORM\Column(type: 'string', length: 50)]
    private ?string $language = null;

    #[ORM\Column(type: 'string', length: 50)]
    private ?string $level = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getRightWord(): ?string
    {
        return $this->rightWord;
    }

    public function setRightWord(string $rightWord): self
    {
        $this->rightWord = $rightWord;
        return $this;
    }

    public function getWrongWord(): ?string
    {
        return $this->wrongWord;
    }

    public function setWrongWord(string $wrongWord): self
    {
        $this->wrongWord = $wrongWord;
        return $this;
    }

    public function getTheme(): ?string
    {
        return $this->theme;
    }

    public function setTheme(string $theme): self
    {
        $this->theme = $theme;
        return $this;
    }

    public function getLanguage(): ?string
    {
        return $this->language;
    }

    public function setLanguage(string $language): self
    {
        $this->language = $language;
        return $this;
    }

    public function getLevel(): ?string
    {
        return $this->level;
    }

    public function setLevel(string $level): self
    {
        $this->level = $level;
        return $this;
    }
}