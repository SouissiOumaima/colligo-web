<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;


#[ORM\Entity]
class Fill_in_the_blank
{

    #[ORM\Id]
    #[ORM\Column(type: "integer")]
    private int $id;

    #[ORM\Column(type: "text")]
    private string $questionText;

    #[ORM\Column(type: "string", length: 255)]
    private string $correctAnswer;

    #[ORM\Column(type: "integer")]
    private int $level;

    #[ORM\Column(type: "string", length: 50)]
    private string $language;

    #[ORM\Column(type: "string", length: 50)]
    private string $theme;

    #[ORM\Column(type: "string")]
    private string $allAnswers;

    public function getId()
    {
        return $this->id;
    }

    public function setId($value)
    {
        $this->id = $value;
    }

    public function getQuestionText()
    {
        return $this->questionText;
    }

    public function setQuestionText($value)
    {
        $this->questionText = $value;
    }

    public function getCorrectAnswer()
    {
        return $this->correctAnswer;
    }

    public function setCorrectAnswer($value)
    {
        $this->correctAnswer = $value;
    }

    public function getLevel()
    {
        return $this->level;
    }

    public function setLevel($value)
    {
        $this->level = $value;
    }

    public function getLanguage()
    {
        return $this->language;
    }

    public function setLanguage($value)
    {
        $this->language = $value;
    }

    public function getTheme()
    {
        return $this->theme;
    }

    public function setTheme($value)
    {
        $this->theme = $value;
    }

    public function getAllAnswers()
    {
        return $this->allAnswers;
    }

    public function setAllAnswers($value)
    {
        $this->allAnswers = $value;
    }
}
