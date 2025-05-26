<?php

namespace App\Service;

use App\Entity\Dragdrop;
use App\Repository\DragdropRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class DragdropGameService
{
    private DragdropRepository $dragdropRepository;
    private LoggerInterface $logger;
    private ?Dragdrop $currentSentence = null;
    private int $level;
    private string $language;
    private array $shuffledWords = [];

    public function __construct(
        DragdropRepository $dragdropRepository,
        LoggerInterface $logger,
        private EntityManagerInterface $entityManager
    ) {
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

    public function setCurrentSentence(Dragdrop $sentence): void
    {
        $this->currentSentence = $sentence;
        $this->shuffleWords();
    }

    public function loadRandomSentence(array $usedSentences): void
    {
        $this->logger->debug('Loading sentence', ['language' => $this->language, 'level' => $this->level, 'usedSentences' => $usedSentences]);
        $this->currentSentence = $this->dragdropRepository->findRandomByLanguageAndLevel($this->language, $this->level, $usedSentences);
        if (!$this->currentSentence) {
            $this->logger->error('No sentences found', ['language' => $this->language, 'level' => $this->level]);
            throw new \Exception('No available sentences for language ' . $this->language . ' and level ' . $this->level);
        }
        $this->logger->info('Sentence loaded', ['phrase' => $this->currentSentence->getPhrase()]);
    }

    public function getShuffledWords(): array
    {
        return $this->shuffledWords;
    }

    public function isCorrect(string $userSentence): bool
    {
        // Normaliser les phrases : minuscules, suppression des espaces multiples, trim
        $expected = mb_strtolower(trim($this->getOriginalPhrase()));
        $actual = mb_strtolower(trim($userSentence));

        // Supprimer les espaces multiples internes
        $expected = preg_replace('/\s+/', ' ', $expected);
        $actual = preg_replace('/\s+/', ' ', $actual);

        return $expected === $actual;
    }

    public function getOriginalPhrase(): string
    {
        return $this->currentSentence ? $this->currentSentence->getPhrase() : '';
    }

    public function getArabicTranslation(): string
    {
        return $this->currentSentence ? ($this->currentSentence->getArabicTranslation() ?? '') : '';
    }

    public function getCorrectSentence(): ?Dragdrop
    {
        return $this->currentSentence;
    }

    public function getLevel(): int
    {
        return $this->level;
    }

    public function getAvailableSentenceCount(): int
    {
        return $this->dragdropRepository->countByLanguageAndLevel($this->language, $this->level);
    }

    private function shuffleWords(): void
    {
        if (!$this->currentSentence) {
            $this->shuffledWords = [];
            return;
        }

        $words = explode(' ', $this->currentSentence->getPhrase());
        shuffle($words);
        $this->shuffledWords = $words;
    }
}