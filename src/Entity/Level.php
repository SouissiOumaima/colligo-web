<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

use App\Entity\Game;

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

    public function getId()
    {
        return $this->id;
    }

    public function setId($value)
    {
        $this->id = $value;
    }

    public function getChildId()
    {
        return $this->childId;
    }

    public function setChildId($value)
    {
        $this->childId = $value;
    }

    public function getGameId()
    {
        return $this->gameId;
    }

    public function setGameId($value)
    {
        $this->gameId = $value;
    }

    public function getScore()
    {
        return $this->score;
    }

    public function setScore($value)
    {
        $this->score = $value;
    }

    public function getNbtries()
    {
        return $this->nbtries;
    }

    public function setNbtries($value)
    {
        $this->nbtries = $value;
    }

    public function getTime()
    {
        return $this->time;
    }

    public function setTime($value)
    {
        $this->time = $value;
    }
}