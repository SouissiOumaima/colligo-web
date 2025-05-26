<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'completed_sentences')]
class CompletedSentence
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Child::class)]
    #[ORM\JoinColumn(name: 'child_id', referencedColumnName: 'childId', nullable: false)]
    private Child $child;

    #[ORM\ManyToOne(targetEntity: Dragdrop::class)]
    #[ORM\JoinColumn(name: 'sentence_id', referencedColumnName: 'id', nullable: false)]
    private Dragdrop $sentence;

    #[ORM\Column(type: 'datetime')]
    private \DateTime $completedAt;

    public function __construct()
    {
        $this->completedAt = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getChild(): Child
    {
        return $this->child;
    }

    public function setChild(Child $child): self
    {
        $this->child = $child;
        return $this;
    }

    public function getSentence(): Dragdrop
    {
        return $this->sentence;
    }

    public function setSentence(Dragdrop $sentence): self
    {
        $this->sentence = $sentence;
        return $this;
    }

    public function getCompletedAt(): \DateTime
    {
        return $this->completedAt;
    }
} 