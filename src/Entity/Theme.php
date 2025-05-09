<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: "themes")]
class Theme
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: "integer")]
    private $id;

    #[ORM\Column(type: "string", length: 255)]
    private $name;

    #[ORM\Column(type: "string", length: 2)]
    private $language;

    #[ORM\Column(type: "string", length: 50)]
    private $level;

    #[ORM\Column(type: "integer")]
    private $stage;

    #[ORM\Column(type: "boolean")]
    private $isValidated;

    #[ORM\OneToMany(mappedBy: "theme", targetEntity: Word::class, cascade: ["persist", "remove"])]
    private $words;

    public function __construct()
    {
        $this->words = new ArrayCollection();
        $this->isValidated = false; // Default to false as per GameService logic
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;
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

    public function getStage(): ?int
    {
        return $this->stage;
    }

    public function setStage(int $stage): self
    {
        $this->stage = $stage;
        return $this;
    }

    public function isValidated(): bool
    {
        return $this->isValidated;
    }

    public function setIsValidated(bool $isValidated): self
    {
        $this->isValidated = $isValidated;
        return $this;
    }

    /**
     * @return Collection|Word[]
     */
    public function getWords(): Collection
    {
        return $this->words;
    }

    public function addWord(Word $word): self
    {
        if (!$this->words->contains($word)) {
            $this->words[] = $word;
            $word->setTheme($this);
        }
        return $this;
    }

    public function removeWord(Word $word): self
    {
        if ($this->words->removeElement($word)) {
            // set the owning side to null (unless already changed)
            if ($word->getTheme() === $this) {
                $word->setTheme(null);
            }
        }
        return $this;
    }
}