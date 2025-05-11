<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'game_results')]
class GameResult
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'integer')]
    private ?int $childId = null;

    #[ORM\Column(type: 'integer')]
    private ?int $gameId = null;

    #[ORM\Column(type: 'integer')]
    private ?int $totalScore = null;

    #[ORM\Column(type: 'integer')]
    private ?int $totalAttemptsUsed = null;

    #[ORM\Column(type: 'integer')]
    private ?int $totalTimeSpent = null;

    #[ORM\Column(type: 'string', length: 10)]
    private ?string $language = null;

    #[ORM\Column(type: 'string', length: 50)]
    private ?string $level = null;

    #[ORM\Column(type: 'datetime')]
    private ?\DateTimeInterface $playedAt = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getChildId(): ?int
    {
        return $this->childId;
    }

    public function setChildId(int $childId): self
    {
        $this->childId = $childId;
        return $this;
    }

    public function getGameId(): ?int
    {
        return $this->gameId;
    }

    public function setGameId(int $gameId): self
    {
        $this->gameId = $gameId;
        return $this;
    }

    public function getTotalScore(): ?int
    {
        return $this->totalScore;
    }

    public function setTotalScore(int $totalScore): self
    {
        $this->totalScore = $totalScore;
        return $this;
    }

    public function getTotalAttemptsUsed(): ?int
    {
        return $this->totalAttemptsUsed;
    }

    public function setTotalAttemptsUsed(int $totalAttemptsUsed): self
    {
        $this->totalAttemptsUsed = $totalAttemptsUsed;
        return $this;
    }

    public function getTotalTimeSpent(): ?int
    {
        return $this->totalTimeSpent;
    }

    public function setTotalTimeSpent(int $totalTimeSpent): self
    {
        $this->totalTimeSpent = $totalTimeSpent;
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

    public function getPlayedAt(): ?\DateTimeInterface
    {
        return $this->playedAt;
    }

    public function setPlayedAt(\DateTimeInterface $playedAt): self
    {
        $this->playedAt = $playedAt;
        return $this;
    }
}