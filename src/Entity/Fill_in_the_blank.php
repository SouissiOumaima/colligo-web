<?php

namespace App\Entity;

use App\Repository\Fill_in_the_blankRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: Fill_in_the_blankRepository::class)]
#[ORM\Table(name: 'fill_in_the_blank')]
class Fill_in_the_blank
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id')]
    private ?int $id = null;

    #[ORM\Column(name: 'questionText', length: 255)]
    private ?string $questionText = null;

    #[ORM\Column(name: 'correctAnswer', length: 100)]
    private ?string $correctAnswer = null;

    #[ORM\Column(name: 'allAnswers', type: 'json')]
    private array $allAnswers = [];

    #[ORM\Column(name: 'theme', length: 100)]
    private ?string $theme = null;

    #[ORM\Column(name: 'level')]
    private ?int $level = null;

    #[ORM\Column(name: 'language', length: 50)]
    private ?string $language = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getQuestionText(): ?string
    {
        return $this->questionText;
    }

    public function setQuestionText(string $questionText): static
    {
        $this->questionText = $questionText;
        return $this;
    }

    public function getCorrectAnswer(): ?string
    {
        return $this->correctAnswer;
    }

    public function setCorrectAnswer(string $correctAnswer): static
    {
        $this->correctAnswer = $correctAnswer;
        return $this;
    }

    public function getAllAnswers(): array
    {
        return $this->allAnswers;
    }

    public function setAllAnswers(array $allAnswers): static
    {
        $this->allAnswers = $allAnswers;
        return $this;
    }

    public function getTheme(): ?string
    {
        return $this->theme;
    }

    public function setTheme(string $theme): static
    {
        $this->theme = $theme;
        return $this;
    }

    public function getLevel(): ?int
    {
        return $this->level;
    }

    public function setLevel(int $level): static
    {
        $this->level = $level;
        return $this;
    }

    public function getLanguage(): ?string
    {
        return $this->language;
    }

    public function setLanguage(string $language): static
    {
        $this->language = $language;
        return $this;
    }
}