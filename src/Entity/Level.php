<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class Level
{
    #[ORM\Id]
    #[ORM\Column(type: "integer")]
    private int $id;

    #[ORM\Id]
    #[ORM\ManyToOne(targetEntity: Child::class, inversedBy: "levels")]
    #[ORM\JoinColumn(name: 'childId', referencedColumnName: 'childId', onDelete: 'CASCADE')]
    private Child $childId;

    #[ORM\Id]
    #[ORM\ManyToOne(targetEntity: Game::class, inversedBy: "levels")]
    #[ORM\JoinColumn(name: 'gameId', referencedColumnName: 'id', onDelete: 'CASCADE')]
    private Game $gameId;

    #[ORM\Column(type: "integer")]
    private int $score;

    #[ORM\Column(type: "integer")]
    private int $nbtries;

    #[ORM\Column(type: "integer")]
    private int $time;

    public function getId(): int
    {
        return $this->id;
    }

    public function setId(int $value): self
    {
        $this->id = $value;
        return $this;
    }

    public function getChildId(): Child
    {
        return $this->childId;
    }

    public function setChildId(Child $value): self
    {
        $this->childId = $value;
        return $this;
    }

    public function getGameId(): Game
    {
        return $this->gameId;
    }

    public function setGameId(Game $value): self
    {
        $this->gameId = $value;
        return $this;
    }

    public function getScore(): int
    {
        return $this->score;
    }

    public function setScore(int $value): self
    {
        $this->score = $value;
        return $this;
    }

    public function getNbtries(): int
    {
        return $this->nbtries;
    }

    public function setNbtries(int $value): self
    {
        $this->nbtries = $value;
        return $this;
    }

    public function getTime(): int
    {
        return $this->time;
    }

    public function setTime(int $value): self
    {
        $this->time = $value;
        return $this;
    }
}