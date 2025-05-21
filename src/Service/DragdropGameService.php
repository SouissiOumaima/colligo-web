<?php

namespace App\Service;

use App\Entity\Dragdrop;
use App\Repository\DragdropRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Psr\Log\LoggerInterface;

class DragdropGameService
{
    private ParameterBagInterface $params;
    private EntityManagerInterface $entityManager;
    private DragdropRepository $dragdropRepository;
    private LoggerInterface $logger;
    private ?Dragdrop $currentDragdrop = null;
    private int $level;
    private string $language;
    private array $usedSentences = [];

    public function __construct(
        ParameterBagInterface $params,
        EntityManagerInterface $entityManager,
        DragdropRepository $dragdropRepository,
        LoggerInterface $logger
    ) {
        $this->params = $params;
        $this->entityManager = $entityManager;
        $this->dragdropRepository = $dragdropRepository;
        $this->logger = $logger;
    }

    public function setLanguage(string $language): void
    {
        $this->language = $language;
        $this->logger->debug('Set language in DragdropGameService', ['language' => $this->language]);
    }

    public function setLevel(int $level): void
    {
        $this->level = $level;
        $this->logger->debug('Set level in DragdropGameService', ['level' => $this->level]);
    }

    public function loadRandomSentence(array $usedSentences): void
    {
        $this->usedSentences = $usedSentences;
        $this->logger->debug('Attempting to load random sentence', [
            'language' => $this->language,
            'level' => $this->level,
            'usedSentences' => $usedSentences,
        ]);

        $availableCount = $this->dragdropRepository->countByLanguageAndLevel($this->language, $this->level);
        $this->logger->debug('Available sentences count', ['count' => $availableCount]);

        if ($availableCount === 0) {
            $this->logger->error('No sentences found for given criteria', [
                'language' => $this->language,
                'level' => $this->level,
            ]);
            throw new \Exception(sprintf('No sentences found for language "%s" and level %d.', $this->language, $this->level));
        }

        $dragdrop = $this->dragdropRepository->findRandomByLanguageAndLevel($this->language, $this->level, $this->usedSentences);
        if (!$dragdrop) {
            $this->logger->error('Failed to load a sentence despite available count', [
                'language' => $this->language,
                'level' => $this->level,
                'availableCount' => $availableCount,
            ]);
            throw new \Exception(sprintf('Failed to load a sentence despite %d available sentences.', $availableCount));
        }

        $this->currentDragdrop = $dragdrop;
        $this->usedSentences[] = $dragdrop->getId();
        $this->logger->debug('Successfully loaded sentence', [
            'phrase' => $dragdrop->getPhrase(),
            'id' => $dragdrop->getId(),
            'arabicTranslation' => $dragdrop->getArabicTranslation(),
        ]);
    }

    public function getOriginalPhrase(): ?string
    {
        if (!$this->currentDragdrop) {
            $this->logger->error('No current dragdrop sentence to get original phrase');
            return null;
        }

        $phrase = $this->currentDragdrop->getPhrase();
        $this->logger->debug('Retrieved original phrase', ['phrase' => $phrase]);
        return $phrase;
    }

    public function getShuffledWords(): ?array
    {
        if (!$this->currentDragdrop) {
            $this->logger->error('No current dragdrop sentence to get shuffled words');
            return null;
        }

        $phrase = $this->currentDragdrop->getPhrase();
        $words = array_filter(explode(' ', trim($phrase)));
        shuffle($words);
        $this->logger->debug('Retrieved shuffled words', ['words' => $words]);
        return $words;
    }

    public function isCorrect(string $userSentence): bool
    {
        if (!$this->currentDragdrop) {
            return false;
        }
        $this->logger->debug('Checking user sentence', [
            'userSentence' => $userSentence,
            'correctPhrase' => $this->currentDragdrop->getPhrase(),
        ]);
        return strtolower(trim($userSentence)) === strtolower(trim($this->currentDragdrop->getPhrase()));
    }

    public function saveSentenceToDatabase(string $sentence): void
    {
        // Not needed for this use case
    }

    public function getLevel(): int
    {
        return $this->level;
    }

    public function getLanguage(): string
    {
        return $this->language;
    }

    public function getArabicTranslation(): ?string
    {
        return $this->currentDragdrop ? $this->currentDragdrop->getArabicTranslation() : null;
    }

    public function getCorrectSentence(): ?Dragdrop
    {
        return $this->currentDragdrop;
    }

    public function getAvailableSentenceCount(): int
    {
        return $this->dragdropRepository->countByLanguageAndLevel($this->language, $this->level);
    }
}