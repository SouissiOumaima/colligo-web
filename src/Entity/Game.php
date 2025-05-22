<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

use Doctrine\Common\Collections\Collection;
use App\Entity\Level;

#[ORM\Entity]
class Game
{

    #[ORM\Id]
    #[ORM\Column(type: "integer")]
    private int $id;

    #[ORM\Column(type: "string", length: 50)]
    private string $name;

    public function getId()
    {
        return $this->id;
    }

    public function setId($value)
    {
        $this->id = $value;
    }

    public function getName()
    {
        return $this->name;
    }

    public function setName($value)
    {
        $this->name = $value;
    }

    #[ORM\OneToMany(mappedBy: "gameId", targetEntity: Level::class)]
    private Collection $levels;

        public function getLevels(): Collection
        {
            return $this->levels;
        }
    
        public function addLevel(Level $level): self
        {
            if (!$this->levels->contains($level)) {
                $this->levels[] = $level;
                $level->setGameId($this);
            }
    
            return $this;
        }
    
        public function removeLevel(Level $level): self
        {
            if ($this->levels->removeElement($level)) {
                // set the owning side to null (unless already changed)
                if ($level->getGameId() === $this) {
                    $level->setGameId(null);
                }
            }
    
            return $this;
        }
}
