<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

#[ORM\Entity]
#[ORM\Table(name: 'themes')]
class Theme
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 255)]
    private ?string $name = null;

    #[ORM\Column(type: 'string', length: 10)]
    private ?string $language = null;

    #[ORM\Column(type: 'string', length: 50)]
    private ?string $level = null;

    #[ORM\Column(type: 'smallint', options: ['unsigned' => true, 'default' => 1])]
    private ?int $stage = 1;

    #[ORM\OneToMany(mappedBy: 'theme', targetEntity: Word::class, cascade: ['persist', 'remove'])]
    private Collection $words;

    public function __construct()
    {
        $this->words = new ArrayCollection();
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
        if ($stage < 1 || $stage > 5) {
            throw new \InvalidArgumentException('Le stage doit être compris entre 1 et 5.');
        }
        $this->stage = $stage;
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
            if ($word->getTheme() === $this) {
                $word->setTheme(null);
            }
        }
        return $this;
    }
}