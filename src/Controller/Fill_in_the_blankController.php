<?php

namespace App\Controller;

use App\Entity\Fill_in_the_blank;
use App\Entity\Level;
use App\Entity\Child;
use App\Entity\Game;
use App\Repository\Fill_in_the_blankRepository;
use App\Repository\LevelRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

class Fill_in_the_blankController extends AbstractController
{
    private Fill_in_the_blankRepository $fillInTheBlankRepository;
    private LevelRepository $levelRepository;
    private EntityManagerInterface $entityManager;
    private RequestStack $requestStack;

    public function __construct(
        Fill_in_the_blankRepository $fillInTheBlankRepository,
        LevelRepository $levelRepository,
        EntityManagerInterface $entityManager,
        RequestStack $requestStack
    ) {
        $this->fillInTheBlankRepository = $fillInTheBlankRepository;
        $this->levelRepository = $levelRepository;
        $this->entityManager = $entityManager;
        $this->requestStack = $requestStack;
    }

    #[Route('/fill-in-the-blank/cover/{parentId}/{childId}', name: 'fill_in_the_blank_cover', methods: ['GET'])]
    public function cover(int $parentId, int $childId): Response
    {
        $session = $this->requestStack->getSession();
        $session->remove('game_state'); // Reset game state for a fresh start

        $child = $this->entityManager->getRepository(Child::class)->find($childId);
        if (!$child) {
            throw $this->createNotFoundException('Child not found');
        }

        $language = $child->getLanguage() ?? 'Français';
        $gameId = 2;

        $game = $this->entityManager->getRepository(Game::class)->find($gameId);
        if (!$game) {
            throw $this->createNotFoundException('Game not found');
        }

        $level = $this->entityManager->createQueryBuilder()
            ->select('l')
            ->from(Level::class, 'l')
            ->where('l.childId = :child')
            ->andWhere('l.gameId = :game')
            ->setParameter('child', $child)
            ->setParameter('game', $game)
            ->orderBy('l.id', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        if (!$level) {
            $level = new Level();
            $level->setId(1);
            $level->setChildId($child);
            $level->setGameId($game);
            $level->setScore(0);
            $level->setNbtries(0);
            $level->setTime(0);
            $this->entityManager->persist($level);
            $this->entityManager->flush();
        }

        return $this->render('fill_in_the_blank/cover_page_FillInTheBlank.html.twig', [
            'parent_id' => $parentId,
            'child_id' => $childId,
            'child_name' => $child->getName(),
            'child_avatar' => $child->getAvatar() ?? 'avatar1.png',
            'level' => $level->getId(),
            'language' => $language,
        ]);
    }

    #[Route('/fill-in-the-blank/{parentId}/{childId}', name: 'fill_in_the_blank_play', methods: ['GET', 'POST'])]
    public function play(int $parentId, int $childId, Request $request): Response
    {
        $session = $this->requestStack->getSession();

        $child = $this->entityManager->getRepository(Child::class)->find($childId);
        if (!$child) {
            throw $this->createNotFoundException('Child not found');
        }

        $language = $child->getLanguage() ?? 'Français';
        $gameId = 2;

        $game = $this->entityManager->getRepository(Game::class)->find($gameId);
        if (!$game) {
            throw $this->createNotFoundException('Game not found');
        }

        $level = $this->entityManager->createQueryBuilder()
            ->select('l')
            ->from(Level::class, 'l')
            ->where('l.childId = :child')
            ->andWhere('l.gameId = :game')
            ->setParameter('child', $child)
            ->setParameter('game', $game)
            ->orderBy('l.id', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        if (!$level) {
            $level = new Level();
            $level->setId(1);
            $level->setChildId($child);
            $level->setGameId($game);
            $level->setScore(0);
            $level->setNbtries(0);
            $level->setTime(0);
            $this->entityManager->persist($level);
            $this->entityManager->flush();
        }

        $gameState = $session->get('game_state', [
            'current_question_index' => 0,
            'score' => $level->getScore(),
            'attempt_count' => 0,
            'start_time' => time(),
            'questions' => [],
            'total_questions' => 0,
            'feedback' => null,
            'show_feedback' => false,
            'lives' => 3,
        ]);

        if (!isset($gameState['lives'])) {
            $gameState['lives'] = 3;
        }

        // Load questions if no questions are loaded
        if (empty($gameState['questions'])) {
            $questions = $this->fillInTheBlankRepository->findByLevelAndLanguage($level->getId(), $language);
            if (empty($questions)) {
                throw $this->createNotFoundException('No questions found for this level and language');
            }

            shuffle($questions);
            $limitedQuestions = array_slice($questions, 0, min(10, count($questions)));

            $gameState['questions'] = array_map(function ($question) {
                return [
                    'id' => $question->getId(),
                    'question_text' => $question->getQuestionText(),
                    'correct_answer' => $question->getCorrectAnswer(),
                    'all_answers' => $question->getAllAnswers(),
                ];
            }, $limitedQuestions);

            $gameState['total_questions'] = count($limitedQuestions);
            $gameState['current_question_index'] = 0;
            $gameState['start_time'] = time();
            $gameState['show_feedback'] = false;
            $session->set('game_state', $gameState);
        }

        $feedback = null;
        if (isset($gameState['show_feedback']) && $gameState['show_feedback']) {
            $feedback = $gameState['feedback'];
            $gameState['show_feedback'] = false;
            $gameState['feedback'] = null;
            $session->set('game_state', $gameState);
        }

        if ($request->isMethod('POST')) {
            $selectedAnswer = $request->request->get('answer');
            $currentQuestion = $gameState['questions'][$gameState['current_question_index']];
            $correctAnswer = $currentQuestion['correct_answer'];

            $gameState['attempt_count']++;

            if ($selectedAnswer === $correctAnswer) {
                $points = 0;
                switch ($gameState['attempt_count']) {
                    case 1:
                        $points = 5;
                        break;
                    case 2:
                        $points = 3;
                        break;
                    case 3:
                        $points = 1;
                        break;
                }

                $gameState['score'] += $points;
                $level->setScore($gameState['score']);
                $gameState['feedback'] = ['type' => 'correct', 'points' => $points];
                $gameState['show_feedback'] = true;

                $gameState['current_question_index']++;
                $gameState['attempt_count'] = 0;

                if ($gameState['current_question_index'] >= $gameState['total_questions']) {
                    $this->completeLevel($level, $childId, $gameId);
                    $gameState = $this->resetGameState($level, $language);
                }
            } else {
                $level->setNbtries($level->getNbtries() + 1);
                $gameState['lives'] = max(0, $gameState['lives'] - 1);
                $gameState['feedback'] = ['type' => 'incorrect', 'points' => 0];
                $gameState['show_feedback'] = true;

                if ($gameState['attempt_count'] >= 3 || $gameState['lives'] <= 0) {
                    $gameState['current_question_index']++;
                    $gameState['attempt_count'] = 0;

                    if ($gameState['current_question_index'] >= $gameState['total_questions'] || $gameState['lives'] <= 0) {
                        $this->completeLevel($level, $childId, $gameId);
                        $gameState = $this->resetGameState($level, $language);
                    }
                }
            }

            $this->entityManager->flush();
            $session->set('game_state', $gameState);

            return $this->redirectToRoute('fill_in_the_blank_play', ['parentId' => $parentId, 'childId' => $childId]);
        }

        $currentQuestionIndex = $gameState['current_question_index'];
        $questions = $gameState['questions'];

        if ($currentQuestionIndex >= $gameState['total_questions'] || $gameState['lives'] <= 0) {
            return $this->render('fill_in_the_blank/fill_in_the_blank.html.twig', [
                'parent_id' => $parentId,
                'child_id' => $childId,
                'child_name' => $child->getName(),
                'child_avatar' => $child->getAvatar() ?? 'avatar1.png',
                'level' => $level->getId(),
                'score' => $gameState['score'],
                'lives' => $gameState['lives'],
                'questions' => $questions,
                'completed' => true,
                'feedback' => null,
            ]);
        }

        $currentQuestion = $gameState['questions'][$currentQuestionIndex];
        $answers = $currentQuestion['all_answers'];
        shuffle($answers);

        return $this->render('fill_in_the_blank/fill_in_the_blank.html.twig', [
            'parent_id' => $parentId,
            'child_id' => $childId,
            'child_name' => $child->getName(),
            'child_avatar' => $child->getAvatar() ?? 'avatar1.png',
            'level' => $level->getId(),
            'score' => $gameState['score'],
            'lives' => $gameState['lives'],
            'question' => $currentQuestion,
            'answers' => $answers,
            'questions' => $questions,
            'completed' => false,
            'feedback' => $feedback,
        ]);
    }

    private function completeLevel(Level $level, int $childId, int $gameId): void
    {
        $session = $this->requestStack->getSession();
        $gameState = $session->get('game_state');
        $endTime = time();
        $startTime = $gameState['start_time'];
        $timeTakenInSeconds = $endTime - $startTime;
        $timeTakenInMinutes = (int) ($timeTakenInSeconds / 60);

        $level->setTime($timeTakenInMinutes);
        $this->entityManager->flush();

        if ($level->getId() < 3) {
            $child = $this->entityManager->getRepository(Child::class)->find($childId);
            $game = $this->entityManager->getRepository(Game::class)->find($gameId);

            $newLevel = new Level();
            $newLevel->setId($level->getId() + 1);
            $newLevel->setChildId($child);
            $newLevel->setGameId($game);
            $newLevel->setScore(0);
            $newLevel->setNbtries(0);
            $newLevel->setTime(0);

            $this->entityManager->persist($newLevel);
            $this->entityManager->flush();
        }
    }

    private function resetGameState(Level $level, string $language): array
    {
        $session = $this->requestStack->getSession();
        $questions = $this->fillInTheBlankRepository->findByLevelAndLanguage($level->getId(), $language);
        shuffle($questions);

        $limitedQuestions = array_slice($questions, 0, min(10, count($questions)));

        $gameState = [
            'current_question_index' => 0,
            'score' => $level->getScore(),
            'attempt_count' => 0,
            'start_time' => time(),
            'questions' => array_map(function ($question) {
                return [
                    'id' => $question->getId(),
                    'question_text' => $question->getQuestionText(),
                    'correct_answer' => $question->getCorrectAnswer(),
                    'all_answers' => $question->getAllAnswers(),
                ];
            }, $limitedQuestions),
            'total_questions' => count($limitedQuestions),
            'feedback' => null,
            'show_feedback' => false,
            'lives' => 3,
        ];

        $session->set('game_state', $gameState);
        return $gameState;
    }
}