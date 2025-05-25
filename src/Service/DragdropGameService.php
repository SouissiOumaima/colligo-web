<?php

namespace App\Service;

use App\Entity\Dragdrop;
use App\Repository\DragdropRepository;
use Psr\Log\LoggerInterface;

class DragdropGameService
{
    private DragdropRepository $dragdropRepository;
    private LoggerInterface $logger;
    private ?Dragdrop $currentDragdrop = null;
    private int $level;
    private string $language;
    private string $originalPhrase;

    public function __construct(DragdropRepository $dragdropRepository, LoggerInterface $logger)
    {
        $this->dragdropRepository = $dragdropRepository;
        $this->logger = $logger;

    }

    public function setLanguage(string $language): void
    {
        $this->language = $language;
    }

    public function setLevel(int $level): void
    {
        $this->level = $level;
    }

    public function loadRandomSentence(array $usedSentences): void
    {
        $this->logger->debug('Loading sentence', ['language' => $this->language, 'level' => $this->level, 'usedSentences' => $usedSentences]);
        $this->currentDragdrop = $this->dragdropRepository->findRandomByLanguageAndLevel($this->language, $this->level, $usedSentences);
        if (!$this->currentDragdrop) {
            $this->logger->error('No sentences found', ['language' => $this->language, 'level' => $this->level]);
            throw new \Exception('No available sentences for language ' . $this->language . ' and level ' . $this->level);
        }
        $this->logger->info('Sentence loaded', ['phrase' => $this->currentDragdrop->getPhrase()]);
    }

    public function getShuffledWords(): array
    {
        if (!$this->currentDragdrop) {
            return [];
        }
        $words = array_filter(explode(' ', trim($this->currentDragdrop->getPhrase())));
        shuffle($words);
        return $words;
    }

    public function isCorrect(string $userSentence): bool
    {
        // Normaliser les phrases : minuscules, suppression des espaces multiples, trim
        $expected = mb_strtolower(trim($this->originalPhrase));
        $actual = mb_strtolower(trim($userSentence));

        // Supprimer les espaces multiples internes
        $expected = preg_replace('/\s+/', ' ', $expected);
        $actual = preg_replace('/\s+/', ' ', $actual);

        return $expected === $actual;
    }

    public function getOriginalPhrase(): string
    {
        return $this->currentDragdrop ? $this->currentDragdrop->getPhrase() : '';
    }

    public function getArabicTranslation(): ?string
    {
        return $this->currentDragdrop ? $this->currentDragdrop->getArabicTranslation() : null;
    }

    public function getCorrectSentence(): ?Dragdrop
    {
        return $this->currentDragdrop;
    }

    public function getLevel(): int
    {
        return $this->level;
    }

    public function getAvailableSentenceCount(): int
    {
        return $this->dragdropRepository->countByLanguageAndLevel($this->language, $this->level);
    }
}