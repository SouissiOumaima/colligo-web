<?php

namespace App\Controller;

use App\Entity\Admin;
use App\Entity\Child;
use App\Entity\Dragdrop;
use App\Entity\Fill_in_the_blank;
use App\Entity\Images;
use App\Entity\Jeudedevinette;
use App\Entity\Parents;
use App\Entity\Theme;
use App\Entity\Word;
use App\Form\AddAdminType;
use App\Form\DragdropType;
use App\Repository\AdminRepository;
use App\Repository\ChildRepository;
use App\Repository\DragdropRepository;
use App\Repository\Fill_in_the_blankRepository;
use App\Repository\ParentsRepository;
use App\Service\AIService;
use App\Service\GameService;
use Symfony\Component\HttpClient\HttpClient;
use App\Service\GoogleAIUtilService;
use App\Service\GuessingGameService;
use App\Service\WordGameService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;

#[Route('/admin')]
class AdminController extends AbstractController
{
    private AdminRepository $adminRepository;
    private Fill_in_the_blankRepository $fillInTheBlankRepository;
    private ParentsRepository $parentsRepository;
    private ChildRepository $childRepository;
    private AIService $aiService;
    private EntityManagerInterface $entityManager;
    private GuessingGameService $gameService;
    private GoogleAIUtilService $googleAIUtil;
    private LoggerInterface $logger;
    private CsrfTokenManagerInterface $csrfTokenManager;
    private GameService $themeGameService;
    private DragdropRepository $dragdropRepository;
    private string $aiApiKey;

    public function __construct(
        AdminRepository $adminRepository,
        Fill_in_the_blankRepository $fillInTheBlankRepository,
        ParentsRepository $parentsRepository,
        ChildRepository $childRepository,
        AIService $aiService,
        EntityManagerInterface $entityManager,
        GuessingGameService $gameService,
        GoogleAIUtilService $googleAIUtil,
        LoggerInterface $logger,
        CsrfTokenManagerInterface $csrfTokenManager,
        GameService $themeGameService,
        DragdropRepository $dragdropRepository,
        string $aiApiKey
    ) {
        $this->adminRepository = $adminRepository;
        $this->fillInTheBlankRepository = $fillInTheBlankRepository;
        $this->parentsRepository = $parentsRepository;
        $this->childRepository = $childRepository;
        $this->aiService = $aiService;
        $this->entityManager = $entityManager;
        $this->gameService = $gameService;
        $this->googleAIUtil = $googleAIUtil;
        $this->logger = $logger;
        $this->csrfTokenManager = $csrfTokenManager;
        $this->themeGameService = $themeGameService;
        $this->dragdropRepository = $dragdropRepository;
        $this->aiApiKey = $aiApiKey;
    }

    #[Route('/dashboard', name: 'admin_dashboard')]
    public function dashboard(): Response
    {
        $connection = $this->entityManager->getConnection();

        $totalAdmins = $connection->fetchOne('SELECT COUNT(*) FROM admin');
        $totalParents = $connection->fetchOne('SELECT COUNT(*) FROM parents');
        $totalChildren = $connection->fetchOne('SELECT COUNT(*) FROM child');

        $stmt = $connection->prepare('SELECT adminId, email, password FROM admin');
        $result = $stmt->executeQuery();
        $adminRows = $result->fetchAllAssociative();

        $admins = [];
        foreach ($adminRows as $row) {
            $admin = new Admin();
            $reflection = new \ReflectionClass($admin);
            $adminIdProperty = $reflection->getProperty('adminId');
            $adminIdProperty->setAccessible(true);
            $adminIdProperty->setValue($admin, (int) $row['adminId']);
            $admin->setEmail((string) $row['email']);
            $admin->setPassword((string) $row['password']);
            $admins[] = $admin;
        }

        $stmt = $connection->prepare('SELECT parentId, email, password FROM parents');
        $result = $stmt->executeQuery();
        $parentRows = $result->fetchAllAssociative();

        $parents = [];
        $parentChildren = [];
        foreach ($parentRows as $row) {
            $parent = new Parents();
            $reflection = new \ReflectionClass($parent);
            $parentIdProperty = $reflection->getProperty('parentId');
            $parentIdProperty->setAccessible(true);
            $parentIdProperty->setValue($parent, (int) $row['parentId']);
            $parent->setEmail((string) $row['email']);
            $parent->setPassword((string) $row['password']);

            $stmt = $connection->prepare(
                'SELECT childId, age, language, avatar, name FROM child WHERE parentId = :parentId'
            );
            $result = $stmt->executeQuery(['parentId' => $parent->getParentId()]);
            $childRows = $result->fetchAllAssociative();

            $children = [];
            foreach ($childRows as $childRow) {
                $child = new Child();
                $reflection = new \ReflectionClass($child);
                $childIdProperty = $reflection->getProperty('childId');
                $childIdProperty->setAccessible(true);
                $childIdProperty->setValue($child, (int) $childRow['childId']);
                $child->setParentId($parent);
                $child->setAge(isset($childRow['age']) ? (int) $childRow['age'] : null);
                $child->setLanguage(isset($childRow['language']) ? (string) $childRow['language'] : null);
                $child->setAvatar(isset($childRow['avatar']) ? (string) $childRow['avatar'] : null);
                $child->setName(isset($childRow['name']) ? (string) $childRow['name'] : null);
                $children[] = $child;
            }

            $parentChildren[$parent->getParentId()] = $children;
            $parents[] = $parent;
        }

        $childrenFrench = $connection->fetchOne('SELECT COUNT(*) FROM child WHERE language = "Français"');
        $childrenEnglish = $connection->fetchOne('SELECT COUNT(*) FROM child WHERE language = "Anglais"');
        $childrenSpanish = $connection->fetchOne('SELECT COUNT(*) FROM child WHERE language = "Espagnol"');
        $childrenGerman = $connection->fetchOne('SELECT COUNT(*) FROM child WHERE language = "German"');

        $ageData = $connection->fetchAllAssociative('SELECT age FROM child');
        $ageHistogram = array_fill(0, 4, 0);
        foreach ($ageData as $row) {
            $age = (int)$row['age'];
            if ($age <= 5) {
                $ageHistogram[0]++;
            } elseif ($age <= 7) {
                $ageHistogram[1]++;
            } elseif ($age <= 9) {
                $ageHistogram[2]++;
            } else {
                $ageHistogram[3]++;
            }
        }

        return $this->render('admin/dashboard.html.twig', [
            'totalAdmins' => $totalAdmins,
            'totalParents' => $totalParents,
            'totalChildren' => $totalChildren,
            'admins' => $admins,
            'parents' => $parents,
            'parentChildren' => $parentChildren,
            'childrenFrench' => $childrenFrench,
            'childrenEnglish' => $childrenEnglish,
            'childrenSpanish' => $childrenSpanish,
            'childrenGerman' => $childrenGerman,
            'ageHistogram' => $ageHistogram,
        ]);
    }

    #[Route('/fill-in-the-blank-dashboard', name: 'admin_fill_in_the_blank_dashboard')]
    public function fillInTheBlankDashboard(): Response
    {
        return $this->render('admin/fill_in_the_blank_dashboard.html.twig');
    }

    #[Route('/generate-questions', name: 'admin_generate_questions')]
    public function generateQuestions(Request $request, SessionInterface $session): Response
    {
        $themes = [
            'Animaux',
            'Couleurs',
            'Nourriture',
            'Sports',
            'Vêtements',
            'Moyens de transport',
            'Parties du corps',
            'Famille',
            'Saison',
            'Jours de la semaine',
            'Formes géométriques',
            'Métiers'
        ];
        $levels = [1, 2, 3];
        $languages = ['Français', 'Anglais', 'Espagnol', 'German'];

        $form = $this->createFormBuilder()
            ->add('theme', ChoiceType::class, [
                'choices' => array_combine($themes, $themes),
                'placeholder' => 'Sélectionner un thème',
                'required' => true,
                'data' => 'Animaux',
            ])
            ->add('level', ChoiceType::class, [
                'choices' => array_combine($levels, $levels),
                'placeholder' => 'Sélectionner un niveau',
                'required' => true,
                'data' => 1,
            ])
            ->add('language', ChoiceType::class, [
                'choices' => array_combine($languages, $languages),
                'placeholder' => 'Sélectionner une langue',
                'required' => true,
                'data' => 'Français',
            ])
            ->add('generate', SubmitType::class, ['label' => 'Générer'])
            ->getForm();

        $form->handleRequest($request);
        $questions = $session->get('generated_questions', []);
        $selectedQuestions = [];

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            $theme = $data['theme'];
            $level = $data['level'];
            $language = $data['language'];

            if (!is_string($theme) || empty($theme)) {
                $this->addFlash('error', 'Le thème est requis.');
                return $this->render('admin/generate_questions.html.twig', [
                    'form' => $form->createView(),
                    'questions' => $questions,
                    'selected_questions' => $selectedQuestions,
                ]);
            }
            if (!is_int($level)) {
                $this->addFlash('error', 'Le niveau doit être un nombre.');
                return $this->render('admin/generate_questions.html.twig', [
                    'form' => $form->createView(),
                    'questions' => $questions,
                    'selected_questions' => $selectedQuestions,
                ]);
            }
            if (!is_string($language) || empty($language)) {
                $this->addFlash('error', 'La langue est requise.');
                return $this->render('admin/generate_questions.html.twig', [
                    'form' => $form->createView(),
                    'questions' => $questions,
                    'selected_questions' => $selectedQuestions,
                ]);
            }

            $rawResponse = $this->aiService->generateMultipleFillInTheBlank($theme, $level, $language);

            if (count($rawResponse) === 1 && str_starts_with($rawResponse[0], 'Error')) {
                $this->addFlash('error', 'Error: ' . $rawResponse[0]);
            } else {
                $rawText = $rawResponse[0];
                $questions = $this->parseApiResponse($rawText, $theme, $level, $language);
                $session->set('generated_questions', $questions);
            }
        }

        if ($request->request->has('save')) {
            $selected = $request->request->all()['selected_questions'] ?? [];
            $selected = is_array($selected) ? $selected : [];

            foreach ($questions as $question) {
                if (in_array($question->getQuestionText(), $selected)) {
                    $entity = new Fill_in_the_blank();
                    $entity->setQuestionText($question->getQuestionText())
                        ->setCorrectAnswer($question->getCorrectAnswer())
                        ->setAllAnswers($question->getAllAnswers())
                        ->setTheme($question->getTheme())
                        ->setLevel($question->getLevel())
                        ->setLanguage($question->getLanguage());
                    $this->entityManager->persist($entity);
                    $selectedQuestions[] = $question;
                }
            }
            $this->entityManager->flush();

            $remainingQuestions = array_filter($questions, function ($question) use ($selected) {
                return !in_array($question->getQuestionText(), $selected);
            });
            $session->set('generated_questions', array_values($remainingQuestions));
            $questions = $session->get('generated_questions', []);

            $this->addFlash('success', 'Les questions sélectionnées ont été sauvegardées avec succès.');
        }

        if ($request->request->has('refresh')) {
            $theme = $request->request->get('theme', 'Animaux');
            $level = (int) $request->request->get('level', 1);
            $language = $request->request->get('language', 'Français');

            if (!is_string($theme) || empty($theme)) {
                $this->addFlash('error', 'Le thème est requis pour rafraîchir.');
                return $this->render('admin/generate_questions.html.twig', [
                    'form' => $form->createView(),
                    'questions' => $questions,
                    'selected_questions' => $selectedQuestions,
                ]);
            }
            if (!is_int($level)) {
                $this->addFlash('error', 'Le niveau doit être un nombre pour rafraîchir.');
                return $this->render('admin/generate_questions.html.twig', [
                    'form' => $form->createView(),
                    'questions' => $questions,
                    'selected_questions' => $selectedQuestions,
                ]);
            }
            if (!is_string($language) || empty($language)) {
                $this->addFlash('error', 'La langue est requise pour rafraîchir.');
                return $this->render('admin/generate_questions.html.twig', [
                    'form' => $form->createView(),
                    'questions' => $questions,
                    'selected_questions' => $selectedQuestions,
                ]);
            }

            $rawResponse = $this->aiService->generateMultipleFillInTheBlank($theme, $level, $language);

            if (count($rawResponse) === 1 && str_starts_with($rawResponse[0], 'Error')) {
                $this->addFlash('error', 'Error: ' . $rawResponse[0]);
            } else {
                $rawText = $rawResponse[0];
                $questions = $this->parseApiResponse($rawText, $theme, $level, $language);
                $session->set('generated_questions', $questions);
            }
        }

        return $this->render('admin/generate_questions.html.twig', [
            'form' => $form->createView(),
            'questions' => $questions,
            'selected_questions' => $selectedQuestions,
        ]);
    }

    private function parseApiResponse(string $rawResponse, string $theme, int $level, string $language): array
    {
        $questions = [];
        $pattern = '/Question: (.*?)\s*:\s*\[(.*?)\]/';
        preg_match_all($pattern, $rawResponse, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            $questionText = trim($match[1]);
            $answersString = trim($match[2]);
            $correctAnswer = '';
            $allAnswers = [];

            $answerPattern = '/\*([^*]+)\*|([^*,\[\]]+)/';
            preg_match_all($answerPattern, $answersString, $answerMatches, PREG_SET_ORDER);

            foreach ($answerMatches as $answerMatch) {
                if (!empty($answerMatch[1])) {
                    $correctAnswer = trim($answerMatch[1]);
                    $allAnswers[] = $correctAnswer;
                } else {
                    $answer = trim($answerMatch[2]);
                    if (!empty($answer)) {
                        $allAnswers[] = $answer;
                    }
                }
            }

            if (empty($questionText) || strpos($questionText, '____') === false) {
                continue;
            }

            if (empty($correctAnswer) || count($allAnswers) !== 3) {
                continue;
            }

            $allAnswers = array_filter($allAnswers, fn($answer) => !empty($answer));
            if (count($allAnswers) !== 3) {
                continue;
            }

            $question = new Fill_in_the_blank();
            $question->setQuestionText($questionText)
                ->setCorrectAnswer($correctAnswer)
                ->setAllAnswers($allAnswers)
                ->setTheme($theme)
                ->setLevel($level)
                ->setLanguage($language);
            $questions[] = $question;
        }

        return $questions;
    }

    #[Route('/add-question', name: 'admin_add_question')]
    public function addQuestion(Request $request): Response
    {
        $theme = $request->query->get('theme', 'Animaux');
        $level = $request->query->getInt('level', 1);
        $language = $request->query->get('language', 'Français');

        $form = $this->createFormBuilder()
            ->add('questionText', TextType::class, [
                'label' => 'Question',
                'attr' => ['placeholder' => 'Entrez la question (ex: Le chat est un ____.)'],
                'required' => true,
            ])
            ->add('correctAnswer', TextType::class, [
                'label' => 'Réponse Correcte',
                'attr' => ['placeholder' => 'Réponse correcte'],
                'required' => true,
            ])
            ->add('incorrectAnswer1', TextType::class, [
                'label' => 'Mauvaise Réponse 1',
                'attr' => ['placeholder' => 'Mauvaise réponse 1'],
                'required' => true,
            ])
            ->add('incorrectAnswer2', TextType::class, [
                'label' => 'Mauvaise Réponse 2',
                'attr' => ['placeholder' => 'Mauvaise réponse 2'],
                'required' => true,
            ])
            ->add('save', SubmitType::class, ['label' => 'Enregistrer'])
            ->getForm();

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $formData = $form->getData();
            $questionText = $formData['questionText'];
            $correctAnswer = $formData['correctAnswer'];
            $incorrectAnswer1 = $formData['incorrectAnswer1'];
            $incorrectAnswer2 = $formData['incorrectAnswer2'];

            if (!is_string($questionText) || empty($questionText)) {
                $this->addFlash('error', 'La question est requise.');
                return $this->render('admin/add_question.html.twig', [
                    'form' => $form->createView(),
                    'theme' => $theme,
                    'level' => $level,
                    'language' => $language,
                ]);
            }
            if (!is_string($correctAnswer) || empty($correctAnswer)) {
                $this->addFlash('error', 'La réponse correcte est requise.');
                return $this->render('admin/add_question.html.twig', [
                    'form' => $form->createView(),
                    'theme' => $theme,
                    'level' => $level,
                    'language' => $language,
                ]);
            }
            if (!is_string($incorrectAnswer1) || empty($incorrectAnswer1)) {
                $this->addFlash('error', 'La mauvaise réponse 1 est requise.');
                return $this->render('admin/add_question.html.twig', [
                    'form' => $form->createView(),
                    'theme' => $theme,
                    'level' => $level,
                    'language' => $language,
                ]);
            }
            if (!is_string($incorrectAnswer2) || empty($incorrectAnswer2)) {
                $this->addFlash('error', 'La mauvaise réponse 2 est requise.');
                return $this->render('admin/add_question.html.twig', [
                    'form' => $form->createView(),
                    'theme' => $theme,
                    'level' => $level,
                    'language' => $language,
                ]);
            }

            if (!is_string($theme) || empty($theme)) {
                $this->addFlash('error', 'Le thème est requis.');
                return $this->render('admin/add_question.html.twig', [
                    'form' => $form->createView(),
                    'theme' => $theme,
                    'level' => $level,
                    'language' => $language,
                ]);
            }
            if (!is_int($level)) {
                $this->addFlash('error', 'Le niveau doit être un nombre.');
                return $this->render('admin/add_question.html.twig', [
                    'form' => $form->createView(),
                    'theme' => $theme,
                    'level' => $level,
                    'language' => $language,
                ]);
            }
            if (!is_string($language) || empty($language)) {
                $this->addFlash('error', 'La langue est requise.');
                return $this->render('admin/add_question.html.twig', [
                    'form' => $form->createView(),
                    'theme' => $theme,
                    'level' => $level,
                    'language' => $language,
                ]);
            }

            if (strpos($questionText, '____') === false) {
                $this->addFlash('error', 'La question doit contenir un espace vide marqué par "____".');
                return $this->render('admin/add_question.html.twig', [
                    'form' => $form->createView(),
                    'theme' => $theme,
                    'level' => $level,
                    'language' => $language,
                ]);
            }

            $allAnswers = [$correctAnswer, $incorrectAnswer1, $incorrectAnswer2];
            if (count(array_unique($allAnswers)) !== count($allAnswers)) {
                $this->addFlash('error', 'Les réponses ne doivent pas contenir de doublons.');
                return $this->render('admin/add_question.html.twig', [
                    'form' => $form->createView(),
                    'theme' => $theme,
                    'level' => $level,
                    'language' => $language,
                ]);
            }

            if (count($allAnswers) !== 3) {
                $this->addFlash('error', 'Il doit y avoir exactement trois réponses (une correcte et deux incorrectes).');
                return $this->render('admin/add_question.html.twig', [
                    'form' => $form->createView(),
                    'theme' => $theme,
                    'level' => $level,
                    'language' => $language,
                ]);
            }

            $newQuestion = new Fill_in_the_blank();
            $newQuestion->setQuestionText($questionText)
                ->setCorrectAnswer($correctAnswer)
                ->setAllAnswers($allAnswers)
                ->setTheme($theme)
                ->setLevel($level)
                ->setLanguage($language);

            $this->entityManager->persist($newQuestion);
            $this->entityManager->flush();

            $this->addFlash('success', 'Question ajoutée à la base de données avec succès.');

            return $this->redirectToRoute('admin_fill_in_the_blank_database');
        }

        return $this->render('admin/add_question.html.twig', [
            'form' => $form->createView(),
            'theme' => $theme,
            'level' => $level,
            'language' => $language,
        ]);
    }

    #[Route('/fill-in-the-blank-database', name: 'admin_fill_in_the_blank_database')]
    public function fillInTheBlankDatabase(Request $request): Response
    {
        $themes = [
            'Animaux',
            'Couleurs',
            'Nourriture',
            'Sports',
            'Vêtements',
            'Moyens de transport',
            'Parties du corps',
            'Famille',
            'Saison',
            'Jours de la semaine',
            'Formes géométriques',
            'Métiers'
        ];
        $languages = ['Français', 'Anglais', 'Espagnol', 'German'];

        $questions = $this->fillInTheBlankRepository->findAll();

        if ($request->query->has('delete')) {
            $questionId = $request->query->getInt('delete');
            $question = $this->fillInTheBlankRepository->find($questionId);

            if ($question) {
                $this->entityManager->remove($question);
                $this->entityManager->flush();
                $this->addFlash('success', 'Question supprimée avec succès.');
            } else {
                $this->addFlash('error', 'Question non trouvée.');
            }

            return $this->redirectToRoute('admin_fill_in_the_blank_database');
        }

        return $this->render('admin/fill_in_the_blank_database.html.twig', [
            'questions' => $questions,
            'themes' => $themes,
            'languages' => $languages,
        ]);
    }

    #[Route('/update-question', name: 'admin_update_question', methods: ['POST'])]
    public function updateQuestion(Request $request): JsonResponse
    {
        if (!$request->isXmlHttpRequest()) {
            return new JsonResponse(['success' => false, 'message' => 'Requête non autorisée.'], 403);
        }

        $data = json_decode($request->getContent(), true);

        if (!isset($data['id'], $data['questionText'], $data['correctAnswer'], $data['incorrectAnswer1'], $data['incorrectAnswer2'], $data['theme'], $data['level'], $data['language'])) {
            return new JsonResponse(['success' => false, 'message' => 'Données manquantes.'], 400);
        }

        $questionId = $data['id'];
        $questionText = $data['questionText'];
        $correctAnswer = $data['correctAnswer'];
        $incorrectAnswer1 = $data['incorrectAnswer1'];
        $incorrectAnswer2 = $data['incorrectAnswer2'];
        $theme = $data['theme'];
        $level = (int) $data['level'];
        $language = $data['language'];

        if (empty($questionText) || empty($correctAnswer) || empty($incorrectAnswer1) || empty($incorrectAnswer2)) {
            return new JsonResponse(['success' => false, 'message' => 'Veuillez remplir tous les champs.'], 400);
        }

        if (strpos($questionText, '____') === false) {
            return new JsonResponse(['success' => false, 'message' => 'La question doit contenir un espace vide marqué par "____".'], 400);
        }

        $allAnswers = [$correctAnswer, $incorrectAnswer1, $incorrectAnswer2];
        if (count(array_unique($allAnswers)) !== count($allAnswers)) {
            return new JsonResponse(['success' => false, 'message' => 'Les réponses ne doivent pas contenir de doublons.'], 400);
        }

        if (count($allAnswers) !== 3) {
            return new JsonResponse(['success' => false, 'message' => 'Il doit y avoir exactement trois réponses (une correcte et deux incorrectes).'], 400);
        }

        $question = $this->fillInTheBlankRepository->find($questionId);
        if (!$question) {
            return new JsonResponse(['success' => false, 'message' => 'Question non trouvée.'], 404);
        }

        $question->setQuestionText($questionText);
        $question->setCorrectAnswer($correctAnswer);
        $question->setAllAnswers($allAnswers);
        $question->setTheme($theme);
        $question->setLevel($level);
        $question->setLanguage($language);

        $this->entityManager->flush();

        return new JsonResponse(['success' => true, 'message' => 'Question modifiée avec succès.']);
    }

    #[Route('/user/management', name: 'admin_user_management')]
    public function userManagement(Request $request, UserPasswordHasherInterface $passwordHasher): Response
    {
        error_log('Query Parameters: ' . json_encode($request->query->all()));

        $connection = $this->entityManager->getConnection();
        $stmt = $connection->prepare('SELECT adminId, email, password FROM admin');
        $result = $stmt->executeQuery();
        $adminRows = $result->fetchAllAssociative();

        $stmt = $connection->prepare('SELECT parentId, email, password FROM parents');
        $result = $stmt->executeQuery();
        $parentRows = $result->fetchAllAssociative();

        error_log('Raw Admins: ' . json_encode($adminRows));
        error_log('Raw Parents: ' . json_encode($parentRows));

        $admins = [];
        foreach ($adminRows as $row) {
            $admin = new Admin();
            $reflection = new \ReflectionClass($admin);
            $adminIdProperty = $reflection->getProperty('adminId');
            $adminIdProperty->setAccessible(true);
            $adminIdProperty->setValue($admin, (int) $row['adminId']);
            $admin->setEmail((string) $row['email']);
            $admin->setPassword((string) $row['password']);
            $admins[] = $admin;
        }

        $parents = [];
        $parentChildren = [];
        foreach ($parentRows as $row) {
            $parent = new Parents();
            $reflection = new \ReflectionClass($parent);
            $parentIdProperty = $reflection->getProperty('parentId');
            $parentIdProperty->setAccessible(true);
            $parentIdProperty->setValue($parent, (int) $row['parentId']);
            $parent->setEmail((string) $row['email']);
            $parent->setPassword((string) $row['password']);

            $stmt = $connection->prepare(
                'SELECT childId, age, language, avatar, name FROM child WHERE parentId = :parentId'
            );
            $result = $stmt->executeQuery(['parentId' => $parent->getParentId()]);
            $childRows = $result->fetchAllAssociative();

            $children = [];
            foreach ($childRows as $childRow) {
                $child = new Child();
                $reflection = new \ReflectionClass($child);
                $childIdProperty = $reflection->getProperty('childId');
                $childIdProperty->setAccessible(true);
                $childIdProperty->setValue($child, (int) $childRow['childId']);
                $child->setParentId($parent);
                $child->setAge(isset($childRow['age']) ? (int) $childRow['age'] : null);
                $child->setLanguage(isset($childRow['language']) ? (string) $childRow['language'] : null);
                $child->setAvatar(isset($childRow['avatar']) ? (string) $childRow['avatar'] : null);
                $child->setName(isset($childRow['name']) ? (string) $childRow['name'] : null);
                $children[] = $child;
            }

            error_log('Parent ID ' . $parent->getParentId() . ' Children: ' . json_encode($children));
            $parentChildren[$parent->getParentId()] = $children;
            $parents[] = $parent;
        }

        error_log('Filtered Admins: ' . json_encode($admins));
        error_log('Filtered Parents: ' . json_encode($parents));

        if ($request->query->has('delete')) {
            $id = $request->query->getInt('delete');
            error_log("Delete request received: ID=$id");

            try {
                $stmt = $connection->prepare('SELECT adminId, email FROM admin WHERE adminId = :id');
                $result = $stmt->executeQuery(['id' => $id]);
                $adminRow = $result->fetchAssociative();

                if ($adminRow) {
                    error_log("Deleting admin with ID: $id");
                    $connection->executeQuery('DELETE FROM admin WHERE adminId = :id', ['id' => $id]);
                    $this->addFlash('success', 'Administrateur supprimé avec succès.');
                    return $this->redirectToRoute('admin_user_management');
                }

                $stmt = $connection->prepare('SELECT parentId, email FROM parents WHERE parentId = :id');
                $result = $stmt->executeQuery(['id' => $id]);
                $parentRow = $result->fetchAssociative();

                if ($parentRow) {
                    error_log("Deleting parent with ID: $id");
                    $connection->executeQuery('DELETE FROM child WHERE parentId = :id', ['id' => $id]);
                    $connection->executeQuery('DELETE FROM parents WHERE parentId = :id', ['id' => $id]);
                    $this->addFlash('success', 'Parent supprimé avec succès.');
                    return $this->redirectToRoute('admin_user_management');
                }

                $stmt = $connection->prepare('SELECT childId, name FROM child WHERE childId = :id');
                $result = $stmt->executeQuery(['id' => $id]);
                $childRow = $result->fetchAssociative();

                if ($childRow) {
                    error_log("Deleting child with ID: $id");
                    $connection->executeQuery('DELETE FROM child WHERE childId = :id', ['id' => $id]);
                    $this->addFlash('success', 'Enfant supprimé avec succès.');
                    return $this->redirectToRoute('admin_user_management');
                }

                error_log("No user found with ID: $id");
                $this->addFlash('error', 'Utilisateur non trouvé.');
            } catch (\Exception $e) {
                error_log("General error during deletion: " . $e->getMessage());
                $this->addFlash('error', 'Erreur générale lors de la suppression : ' . $e->getMessage());
            }

            return $this->redirectToRoute('admin_user_management');
        }

        return $this->render('admin/user_management.html.twig', [
            'admins' => $admins,
            'parents' => $parents,
            'parentChildren' => $parentChildren
        ]);
    }

    #[Route('/add-admin', name: 'admin_add_admin', methods: ['GET', 'POST'])]
    public function addAdmin(Request $request, UserPasswordHasherInterface $passwordHasher): Response
    {
        $form = $this->createForm(AddAdminType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            $email = $data['email'];
            $plainPassword = $data['password'];

            $existingAdmin = $this->adminRepository->findOneBy(['email' => $email]);
            if ($existingAdmin) {
                $this->addFlash('error', 'Un administrateur avec cet email existe déjà.');
                return $this->render('admin/add_admin.html.twig', [
                    'form' => $form->createView(),
                ]);
            }

            $admin = new Admin();
            $admin->setEmail($email);
            $hashedPassword = $passwordHasher->hashPassword($admin, $plainPassword);
            $admin->setPassword($hashedPassword);

            try {
                $this->entityManager->persist($admin);
                $this->entityManager->flush();
                $this->addFlash('success', 'Administrateur ajouté avec succès.');
                return $this->redirectToRoute('admin_user_management');
            } catch (\Exception $e) {
                $this->addFlash('error', 'Erreur lors de l\'ajout de l\'administrateur : ' . $e->getMessage());
            }
        }

        return $this->render('admin/add_admin.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/user/statistics', name: 'admin_user_statistics')]
    public function userStatistics(): Response
    {
        $connection = $this->entityManager->getConnection();

        $totalAdmins = $connection->fetchOne('SELECT COUNT(*) FROM admin');
        $totalParents = $connection->fetchOne('SELECT COUNT(*) FROM parents');
        $totalChildren = $connection->fetchOne('SELECT COUNT(*) FROM child');

        $childrenLevel1 = $connection->fetchOne('SELECT COUNT(DISTINCT l.childId) FROM level l WHERE l.id = 1');
        $childrenLevel2 = $connection->fetchOne('SELECT COUNT(DISTINCT l.childId) FROM level l WHERE l.id = 2');
        $childrenLevel3 = $connection->fetchOne('SELECT COUNT(DISTINCT l.childId) FROM level l WHERE l.id = 3');

        $childrenFrench = $connection->fetchOne('SELECT COUNT(*) FROM child WHERE language = "Français"');
        $childrenEnglish = $connection->fetchOne('SELECT COUNT(*) FROM child WHERE language = "Anglais"');
        $childrenSpanish = $connection->fetchOne('SELECT COUNT(*) FROM child WHERE language = "Espagnol"');
        $childrenGerman = $connection->fetchOne('SELECT COUNT(*) FROM child WHERE language = "German"');

        $childrenPerParentData = $connection->fetchAllAssociative(
            'SELECT p.parentId, COUNT(c.childId) as child_count 
             FROM parents p 
             LEFT JOIN child c ON c.parentId = p.parentId 
             GROUP BY p.parentId'
        );
        $childrenPerParentHistogram = array_fill(0, 6, 0);
        foreach ($childrenPerParentData as $row) {
            $count = (int)$row['child_count'];
            $index = min($count, 5);
            $childrenPerParentHistogram[$index]++;
        }

        $levelsByGameData = $connection->fetchAllAssociative(
            'SELECT gameId, id as level, COUNT(DISTINCT childId) as child_count 
             FROM level 
             GROUP BY gameId, id'
        );
        $games = array_unique(array_column($levelsByGameData, 'gameId'));
        $levelsByGame = [];
        foreach ($games as $game) {
            $levelsByGame[$game] = ['Level 1' => 0, 'Level 2' => 0, 'Level 3' => 0];
        }
        foreach ($levelsByGameData as $row) {
            $game = $row['gameId'];
            $level = (int)$row['level'];
            $levelsByGame[$game]["Level $level"] = (int)$row['child_count'];
        }

        $ageData = $connection->fetchAllAssociative('SELECT age FROM child');
        $ageHistogram = array_fill(0, 4, 0);
        foreach ($ageData as $row) {
            $age = (int)$row['age'];
            if ($age <= 5) {
                $ageHistogram[0]++;
            } elseif ($age <= 7) {
                $ageHistogram[1]++;
            } elseif ($age <= 9) {
                $ageHistogram[2]++;
            } else {
                $ageHistogram[3]++;
            }
        }

        $avgAttemptsPerLevel = [];
        for ($level = 1; $level <= 3; $level++) {
            $avg = $connection->fetchOne(
                'SELECT AVG(nbtries) 
                 FROM level 
                 WHERE id = :level',
                ['level' => $level]
            );
            $avgAttemptsPerLevel[$level] = $avg ? round((float)$avg, 2) : 0;
        }

        $avgTimePerLevel = [];
        for ($level = 1; $level <= 3; $level++) {
            $avg = $connection->fetchOne(
                'SELECT AVG(time) 
                 FROM level 
                 WHERE id = :level',
                ['level' => $level]
            );
            $avgTimePerLevel[$level] = $avg ? round((float)$avg, 2) : 0;
        }

        $scoreData = $connection->fetchAllAssociative(
            'SELECT gameId, score 
             FROM level'
        );
        $scoreHistogram = [];
        $scoreBins = [0, 20, 40, 60, 80, 100];
        foreach ($games as $game) {
            $scoreHistogram[$game] = array_fill(0, count($scoreBins), 0);
        }
        foreach ($scoreData as $row) {
            $game = $row['gameId'];
            $score = (int)$row['score'];
            for ($i = 0; $i < count($scoreBins); $i++) {
                if ($score <= $scoreBins[$i] || $i == count($scoreBins) - 1) {
                    $scoreHistogram[$game][$i]++;
                    break;
                }
            }
        }

        return $this->render('admin/user_statistics.html.twig', [
            'totalAdmins' => $totalAdmins,
            'totalParents' => $totalParents,
            'totalChildren' => $totalChildren,
            'childrenLevel1' => $childrenLevel1,
            'childrenLevel2' => $childrenLevel2,
            'childrenLevel3' => $childrenLevel3,
            'childrenFrench' => $childrenFrench,
            'childrenEnglish' => $childrenEnglish,
            'childrenSpanish' => $childrenSpanish,
            'childrenGerman' => $childrenGerman,
            'childrenPerParentHistogram' => $childrenPerParentHistogram,
            'levelsByGame' => $levelsByGame,
            'games' => $games,
            'ageHistogram' => $ageHistogram,
            'avgAttemptsPerLevel' => $avgAttemptsPerLevel,
            'avgTimePerLevel' => $avgTimePerLevel,
            'scoreHistogram' => $scoreHistogram,
            'scoreBins' => $scoreBins,
        ]);
    }

    #[Route('/load-words', name: 'admin_load_words', methods: ['GET'])]
    public function loadWords(Request $request): Response
    {
        $language = $request->query->get('language', 'français');
        $level = $request->query->get('level', '1');

        $words = $this->gameService->getWordsForAdmin($language, $level) ?? [];

        return $this->render('admin/adminJeuDevinette.html.twig', [
            'words' => $words,
            'language' => $language,
            'level' => $level,
        ]);
    }

    #[Route('/generate', name: 'admin_generate_words', methods: ['POST'])]
    public function generateWords(Request $request): Response
    {
        try {
            $language = $request->request->get('language', 'français');
            $level = $request->request->get('level', '1');

            $this->logger->info('Generating words', [
                'language' => $language,
                'level' => $level,
            ]);

            $words = $this->googleAIUtil->getWordsForLevelAndLanguage($level, $language);

            $this->addFlash('info', 'Mots générés avec succès.');

            return $this->redirectToRoute('admin_load_words', [
                'language' => $language,
                'level' => $level,
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('Erreur lors de la génération des mots', [
                'exception' => $e->getMessage(),
                'language' => $language,
                'level' => $level,
                'trace' => $e->getTraceAsString(),
            ]);
            $this->addFlash('error', 'Erreur lors de la génération : ' . $e->getMessage());

            return $this->redirectToRoute('admin_load_words', [
                'language' => $language,
                'level' => $level,
            ]);
        }
    }

    #[Route('/add', name: 'admin_add_words', methods: ['POST'])]
    public function addWords(Request $request): Response
    {
        $language = $request->request->get('language', 'français');
        $level = $request->request->get('level', '1');
        $rightWords = $request->request->get('rightWords');
        $wrongWord = $request->request->get('wrongWord');
        $theme = $request->request->get('theme');

        try {
            $this->logger->info('Adding words', [
                'rightWords' => $rightWords,
                'wrongWord' => $wrongWord,
                'theme' => $theme,
                'language' => $language,
                'level' => $level,
            ]);

            if (empty($rightWords) || empty($wrongWord) || empty($theme)) {
                $this->addFlash('error', 'Tous les champs doivent être remplis.');
                return $this->redirectToRoute('admin_load_words', [
                    'language' => $language,
                    'level' => $level,
                ]);
            }

            $this->gameService->addLot($rightWords, $wrongWord, $theme, $language, $level);

            $this->addFlash('success', 'Mots ajoutés avec succès.');

            return $this->redirectToRoute('admin_load_words', [
                'language' => $language,
                'level' => $level,
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('Erreur lors de l\'ajout des mots', [
                'exception' => $e->getMessage(),
                'language' => $language,
                'level' => $level,
                'trace' => $e->getTraceAsString(),
            ]);
            $this->addFlash('error', 'Erreur lors de l\'ajout : ' . $e->getMessage());

            return $this->redirectToRoute('admin_load_words', [
                'language' => $language,
                'level' => $level,
            ]);
        }
    }

    #[Route('/delete', name: 'admin_delete_words', methods: ['POST'])]
    public function deleteWords(Request $request): Response
    {
        $language = $request->request->get('language', 'français');
        $level = $request->request->get('level', '1');

        try {
            $requestData = $request->request->all();
            $selectedEntries = $requestData['selectedEntries'] ?? [];

            if (!is_array($selectedEntries) || empty($selectedEntries)) {
                $this->addFlash('error', 'Aucune entrée sélectionnée pour la suppression.');
                return $this->redirectToRoute('admin_load_words', [
                    'language' => $language,
                    'level' => $level,
                ]);
            }

            $deletedCount = 0;
            foreach ($selectedEntries as $entryData) {
                if (is_string($entryData)) {
                    $entryData = json_decode($entryData, true);
                }

                if (
                    !is_array($entryData) ||
                    !isset($entryData['rightWord'], $entryData['wrongWord'], $entryData['theme'])
                ) {
                    continue;
                }

                $this->gameService->deleteLot(
                    $entryData['rightWord'],
                    $entryData['wrongWord'],
                    $entryData['theme'],
                    $language,
                    $level,
                    $this->logger
                );
                $deletedCount++;
            }

            if ($deletedCount > 0) {
                $this->addFlash('success', "$deletedCount entrée(s) supprimée(s) avec succès.");
            } else {
                $this->addFlash('warning', 'Aucune entrée valide n\'a été trouvée pour suppression.');
            }

            return $this->redirectToRoute('admin_load_words', [
                'language' => $language,
                'level' => $level,
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('Erreur lors de la suppression des mots', [
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            $this->addFlash('error', 'Erreur lors de la suppression : ' . $e->getMessage());
            return $this->redirectToRoute('admin_load_words', [
                'language' => $language,
                'level' => $level,
            ]);
        }
    }

    #[Route('/themes', name: 'admin_themes', methods: ['GET'])]
    public function themes(EntityManagerInterface $entityManager, SessionInterface $session): Response
    {
        $language = $session->get('admin_filter_language', null);
        $level = $session->get('admin_filter_level', null);

        $queryBuilder = $entityManager->getRepository(Theme::class)->createQueryBuilder('t');
        if ($language) {
            $queryBuilder->andWhere('t.language = :language')->setParameter('language', $language);
        }
        if ($level) {
            $queryBuilder->andWhere('t.level = :level')->setParameter('level', $level);
        }

        $themes = $queryBuilder->getQuery()->getResult();

        return $this->render('admin/themes.html.twig', [
            'themes' => $themes,
            'filter_language' => $language,
            'filter_level' => $level,
        ]);
    }

    #[Route('/generate-themes', name: 'admin_generate_themes', methods: ['GET', 'POST'])]
    public function generateThemes(Request $request, SessionInterface $session, EntityManagerInterface $entityManager): Response
    {
        $validLanguages = ['fr', 'en', 'de', 'es'];
        $validLevels = ['Facile', 'Moyen', 'Difficile'];

        if ($request->isMethod('POST')) {
            $language = $request->request->get('language');
            $level = $request->request->get('level');

            if (!in_array($language, $validLanguages) || !in_array($level, $validLevels)) {
                $this->addFlash('error', 'Langue ou niveau invalide.');
                return $this->redirectToRoute('admin_themes');
            }

            $themeCount = 5; // Default value
            $wordsPerTheme = 5; // Default value
            $themes = $this->themeGameService->generateThemes($language, $level, $themeCount, $wordsPerTheme);
            $session->set('admin_filter_language', $language);
            $session->set('admin_filter_level', $level);
            $this->addFlash('success', 'Thèmes générés avec succès. Veuillez les valider.');
            return $this->redirectToRoute('admin_themes');
        }

        $language = $session->get('admin_filter_language', null);
        $level = $session->get('admin_filter_level', null);

        $queryBuilder = $entityManager->getRepository(Theme::class)->createQueryBuilder('t');
        if ($language) {
            $queryBuilder->andWhere('t.language = :language')->setParameter('language', $language);
        }
        if ($level) {
            $queryBuilder->andWhere('t.level = :level')->setParameter('level', $level);
        }

        $themes = $queryBuilder->getQuery()->getResult();

        return $this->render('admin/themes.html.twig', [
            'themes' => $themes,
            'filter_language' => $language,
            'filter_level' => $level,
            'validLanguages' => $validLanguages,
            'validLevels' => $validLevels,
        ]);
    }

    #[Route('/add-theme', name: 'admin_add_theme', methods: ['GET'])]
    public function addTheme(): Response
    {
        return $this->render('admin/add_theme.html.twig');
    }

    #[Route('/save-theme', name: 'admin_save_theme', methods: ['POST'])]
    public function saveTheme(Request $request, EntityManagerInterface $entityManager): Response
    {
        $token = new CsrfToken('save_theme', $request->request->get('_csrf_token'));
        if (!$this->csrfTokenManager->isTokenValid($token)) {
            $this->addFlash('error', 'Jeton CSRF invalide.');
            return $this->redirectToRoute('admin_add_theme');
        }

        $name = $request->request->get('name');
        $language = $request->request->get('language');
        $level = $request->request->get('level');
        $stage = $request->request->get('stage', 1);
        $allParams = $request->request->all();
        $wordsData = isset($allParams['words']) && is_array($allParams['words']) ? $allParams['words'] : [];

        $validLanguages = ['fr', 'en', 'de', 'es'];
        $validLevels = ['Facile', 'Moyen', 'Difficile'];

        if (empty($name) || !in_array($language, $validLanguages) || !in_array($level, $validLevels)) {
            $this->addFlash('error', 'Données invalides. Vérifiez le nom, la langue ou le niveau.');
            return $this->redirectToRoute('admin_add_theme');
        }

        if (empty($wordsData)) {
            $this->addFlash('error', 'Vous devez ajouter au moins un mot.');
            return $this->redirectToRoute('admin_add_theme');
        }

        $theme = new Theme();
        $theme->setName($name);
        $theme->setLanguage($language);
        $theme->setLevel($level);
        $theme->setStage((int)$stage);
        $theme->setIsValidated(false);

        foreach ($wordsData as $wordData) {
            if (is_array($wordData) && !empty($wordData['word']) && !empty($wordData['synonym'])) {
                $word = new Word();
                $word->setWord($wordData['word']);
                $word->setSynonym($wordData['synonym']);
                $word->setTheme($theme);
                $theme->addWord($word);
                $entityManager->persist($word);
            }
        }

        $entityManager->persist($theme);
        $entityManager->flush();

        $this->addFlash('success', 'Thème ajouté avec succès.');
        return $this->redirectToRoute('admin_themes');
    }

    #[Route('/validate-theme/{id}', name: 'admin_validate_theme', methods: ['POST'])]
    #[ParamConverter('theme', class: 'App\Entity\Theme')]
    public function validateTheme(Theme $theme, EntityManagerInterface $entityManager, Request $request): Response
    {
        $token = new CsrfToken('validate_theme', $request->request->get('_csrf_token'));
        if (!$this->csrfTokenManager->isTokenValid($token)) {
            $this->addFlash('error', 'Jeton CSRF invalide.');
            return $this->redirectToRoute('admin_themes');
        }

        $theme->setIsValidated(!$theme->isValidated());
        $entityManager->flush();
        $this->addFlash('success', sprintf(
            'Le thème "%s" a été %s.',
            $theme->getName(),
            $theme->isValidated() ? 'validé' : 'dévalidé'
        ));
        return $this->redirectToRoute('admin_themes');
    }

    #[Route('/delete-theme/{id}', name: 'admin_delete_theme', methods: ['POST'])]
    #[ParamConverter('theme', class: 'App\Entity\Theme')]
    public function deleteTheme(Theme $theme, EntityManagerInterface $entityManager, Request $request): Response
    {
        $token = new CsrfToken('delete_theme', $request->request->get('_csrf_token'));
        if (!$this->csrfTokenManager->isTokenValid($token)) {
            $this->addFlash('error', 'Jeton CSRF invalide.');
            return $this->redirectToRoute('admin_themes');
        }

        $entityManager->remove($theme);
        $entityManager->flush();
        $this->addFlash('success', 'Thème supprimé avec succès.');
        return $this->redirectToRoute('admin_themes');
    }

    #[Route('/clear-filter', name: 'admin_clear_filter', methods: ['GET'])]
    public function clearFilter(SessionInterface $session): Response
    {
        $session->remove('admin_filter_language');
        $session->remove('admin_filter_level');
        return $this->redirectToRoute('admin_themes');
    }



    
   #[Route('/dragdrop', name: 'admin_dragdrop_content', methods: ['GET', 'POST'])]
    public function manageContent(Request $request, DragdropRepository $repository, EntityManagerInterface $entityManager): Response
    {
        // Define valid languages at the method level
        $validLanguages = ['Français', 'English', 'Espagnol', 'Allemand'];

        // Manual Creation Form
        $dragdrop = new Dragdrop();
        $form = $this->createForm(DragdropType::class, $dragdrop);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($dragdrop);
            $entityManager->flush();
            $this->addFlash('success', 'Phrase ajoutée avec succès !');
            return $this->redirectToRoute('admin_dragdrop_content');
        }

        // AI Generation
        $language = $request->request->get('language'); // Required, no default
        $level = (int) $request->request->get('level', 1); // Default to 1 (Niveau 1)
        $generatedSentences = [];

        if ($request->isMethod('POST') && $request->request->has('generate')) {
            if (!$language || !in_array($language, $validLanguages)) {
                $this->addFlash('error', 'Veuillez sélectionner une langue valide.');
                $language = 'Français';
            }
            $prompt = $this->buildPrompt($language, $level);
            $result = $this->callApi($prompt);
            $generatedSentences = $result ? $this->parseDualLanguageResponse($result, $level) : $this->getFallbackSentences($language, $level);
        }

        // Save Generated Sentences
        $selectedSentences = $request->request->all('sentences');
        $saveLanguage = $request->request->get('save_language', $language ?? 'Français');

        if ($request->isMethod('POST') && $request->request->has('save_generated') && is_array($selectedSentences) && !empty($selectedSentences)) {
            foreach ($selectedSentences as $sentenceText) {
                if (is_string($sentenceText) && trim($sentenceText) !== '') {
                    $sentenceParts = explode(' | ', $sentenceText, 2);
                    $originalSentence = trim($sentenceParts[0]);
                    $arabicTranslation = isset($sentenceParts[1]) ? trim($sentenceParts[1]) : 'ترجمة افتراضية';
                    $dragdropEntity = new Dragdrop();
                    $dragdropEntity->setPhrase($originalSentence);
                    $dragdropEntity->setArabicTranslation($arabicTranslation);
                    $dragdropEntity->setNiveau($level);
                    $saveLanguage = $saveLanguage ?? 'Français';
                    if (!in_array($saveLanguage, $validLanguages)) {
                        $saveLanguage = 'Français';
                    }
                    $dragdropEntity->setLangue($saveLanguage);
                    $entityManager->persist($dragdropEntity);
                }
            }
            $entityManager->flush();
            $this->addFlash('success', 'Phrases enregistrées avec succès !');
            return $this->redirectToRoute('admin_dragdrop_content');
        } elseif ($request->request->has('save_generated') && (!is_array($selectedSentences) || empty($selectedSentences))) {
            $this->addFlash('error', 'Veuillez sélectionner au moins une phrase à enregistrer.');
        }

        $dragdrops = $repository->findAll();

        return $this->render('admin/dragdrop_content.html.twig', [
            'form' => $form->createView(),
            'dragdrops' => $dragdrops,
            'language' => $language ?? 'Français',
            'level' => $level,
            'generatedSentences' => $generatedSentences,
        ]);
    }

    #[Route('/dragdrop/add', name: 'admin_dragdrop_add', methods: ['GET', 'POST'])]
    public function addContent(Request $request, EntityManagerInterface $entityManager): Response
    {
        $dragdrop = new Dragdrop();
        $form = $this->createForm(DragdropType::class, $dragdrop);

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($dragdrop);
            $entityManager->flush();
            $this->addFlash('success', 'Phrase ajoutée avec succès !');
            return $this->redirectToRoute('admin_dragdrop_content');
        }

        return $this->render('admin/add_dragdrop.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/dragdrop/delete/{id}', name: 'admin_dragdrop_delete', methods: ['POST'])]
    public function deleteContent(int $id, DragdropRepository $repository, EntityManagerInterface $entityManager): Response
    {
        $dragdrop = $repository->find($id);

        if (!$dragdrop) {
            $this->addFlash('error', 'Phrase non trouvée.');
            return $this->redirectToRoute('admin_dragdrop_content');
        }

        try {
            $entityManager->remove($dragdrop);
            $entityManager->flush();
            $this->addFlash('success', 'Phrase supprimée avec succès !');
        } catch (\Exception $e) {
            $this->addFlash('error', 'Erreur lors de la suppression de la phrase : ' . $e->getMessage());
        }

        return $this->redirectToRoute('admin_dragdrop_content');
    }

    private function buildPrompt(string $language, int $level): string
    {
        $langCode = match (strtolower($language)) {
            'français' => 'fr',
            'english' => 'en',
            'espagnol' => 'es',
            'allemand' => 'de',
            default => 'fr',
        };
        return match ($level) {
            1 => "Generate exactly 10 unique, simple, child-friendly sentences in the language code '$langCode'. Each sentence must have 3 to 5 words and be easy to drag-and-drop for learning. For each sentence, provide an accurate translation in Arabic ('ar'). Return the result as a JSON array of objects, each with 'sentence' and 'arabicTranslation' keys (e.g., [{\"sentence\": \"I like to play\", \"arabicTranslation\": \"أحب اللعب\"}]). Do not include any additional text, markdown, or formatting outside the JSON array.",
            2 => "Generate exactly 10 unique, slightly more complex but still child-friendly sentences in the language code '$langCode'. Each sentence must have 4 to 6 words suitable for drag-and-drop learning. For each sentence, provide an accurate translation in Arabic ('ar'). Return the result as a JSON array of objects, each with 'sentence' and 'arabicTranslation' keys (e.g., [{\"sentence\": \"The dog runs in the park\", \"arabicTranslation\": \"الكلب يركض في الحديقة\"}]). Do not include any additional text, markdown, or formatting outside the JSON array.",
            3 => "Generate exactly 10 unique, moderately complex child-friendly sentences in the language code '$langCode'. Each sentence must have 5 to 7 words suitable for advanced drag-and-drop learning. For each sentence, provide an accurate translation in Arabic ('ar'). Return the result as a JSON array of objects, each with 'sentence' and 'arabicTranslation' keys (e.g., [{\"sentence\": \"The girl reads a book quietly\", \"arabicTranslation\": \"الفتاة تقرأ كتابًا بهدوء\"}]). Do not include any additional text, markdown, or formatting outside the JSON array.",
            default => "Generate exactly 10 unique, simple, child-friendly sentences in the language code '$langCode'. Each sentence must have 3 to 5 words and be easy to drag-and-drop for learning. For each sentence, provide an accurate translation in Arabic ('ar'). Return the result as a JSON array of objects, each with 'sentence' and 'arabicTranslation' keys (e.g., [{\"sentence\": \"I like to play\", \"arabicTranslation\": \"أحب اللعب\"}]). Do not include any additional text, markdown, or formatting outside the JSON array.",
        };
    }

    private function callApi(string $prompt): ?string
    {
        $apiUrl = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key=' . $this->aiApiKey;
        $client = HttpClient::create();

        try {
            $response = $client->request('POST', $apiUrl, [
                'headers' => ['Content-Type' => 'application/json'],
                'json' => [
                    'contents' => [['parts' => [['text' => $prompt]]]],
                    'generationConfig' => [
                        'temperature' => 0.7,
                        'topK' => 40,
                        'topP' => 0.95,
                        'maxOutputTokens' => 500,
                        'stopSequences' => [],
                    ],
                ],
            ]);

            $statusCode = $response->getStatusCode();
            $rawContent = $response->getContent(false);
            $this->logger->debug('API Gemini Raw Response', ['status' => $statusCode, 'content' => $rawContent]);

            if ($statusCode !== 200) {
                throw new \Exception('API request failed with status code: ' . $statusCode . ', content: ' . $rawContent);
            }

            $data = $response->toArray();
            $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? '{}';
            $this->logger->debug('API Gemini Parsed Response', ['response' => $data, 'prompt' => $prompt]);
            return trim($text);
        } catch (\Exception $e) {
            $this->logger->error('Erreur API Gemini', ['exception' => $e->getMessage(), 'prompt' => $prompt, 'raw_content' => $rawContent ?? 'N/A']);
            return null;
        }
    }

    private function parseDualLanguageResponse(string $response, int $level): array
    {
        $cleanedResponse = trim($response);
        $cleanedResponse = preg_replace('/^```json\s*|\s*```$/m', '', $cleanedResponse);
        $cleanedResponse = trim($cleanedResponse);

        $result = json_decode($cleanedResponse, true);
        $sentences = [];

        if (json_last_error() === JSON_ERROR_NONE && is_array($result) && !empty($result)) {
            foreach ($result as $item) {
                if (is_string($item)) {
                    $subItem = json_decode($item, true);
                    if (json_last_error() === JSON_ERROR_NONE && isset($subItem['sentence']) && isset($subItem['arabicTranslation'])) {
                        $sentences[] = $subItem['sentence'] . ' | ' . $subItem['arabicTranslation'];
                    }
                } elseif (is_array($item) && count($item) >= 2 && isset($item[0]) && $item[0] === 'json') {
                    $translation = isset($item[1]) ? trim($item[1]) : 'ترجمة افتراضية';
                    $sentences[] = 'Generated sentence | ' . $translation;
                } elseif (isset($item['sentence']) && isset($item['arabicTranslation'])) {
                    $sentences[] = $item['sentence'] . ' | ' . $item['arabicTranslation'];
                }
            }
        }

        if (empty($sentences)) {
            $lines = array_filter(explode("\n", $cleanedResponse));
            foreach ($lines as $line) {
                $parts = explode(' | ', trim($line), 2);
                if (count($parts) === 2) {
                    $sentences[] = $line;
                } elseif (count($parts) === 1) {
                    $sentences[] = $parts[0] . ' | ترجمة افتراضية';
                }
            }
        }

        while (count($sentences) < 10) {
            $sentences[] = "Fallback sentence " . (count($sentences) + 1) . " | ترجمة افتراضية";
        }

        return array_slice($sentences, 0, 10);
    }

    private function getFallbackSentences(string $language, int $level): array
    {
        $fallbackSentences = [
            1 => [
                'I like to play | أحب اللعب',
                'J’aime jouer | أحب اللعب',
                'Me gusta jugar | أحب اللعب',
                'Ich mag spielen | أحب اللعب',
                'The cat runs fast | القط يركض بسرعة',
                'Nous lisons un livre | نقرأ كتابًا',
                'Leemos un libro | نقرأ كتابًا',
                'Wir lesen ein Buch | نقرأ كتابًا',
                'The bird sings | الطائر يغني',
                'I draw a house | أرسم منزلًا',
            ],
            2 => [
                'The dog runs in the park | الكلب يركض في الحديقة',
                'Je lis un livre tranquillement | أقرأ كتابًا بهدوء',
                'Leo un libro tranquilo | أقرأ كتابًا بهدوء',
                'Ich lese ein Buch leise | أقرأ كتابًا بهدوء',
                'The cat climbs the tree | القط يتسلق الشجرة',
                'Nous jouons avec nos jouets | نلعب مع ألعابنا',
                'Jugamos con nuestros juguetes | نلعب مع ألعابنا',
                'Wir spielen mit unseren Spielsachen | نلعب مع ألعابنا',
                'The bird flies over water | الطائر يطير فوق الماء',
                'I draw a big house | أرسم منزلًا كبيرًا',
            ],
            3 => [
                'The girl reads a book quietly | الفتاة تقرأ كتابًا بهدوء',
                'La fille lit un livre tranquillement | الفتاة تقرأ كتابًا بهدوء',
                'La niña lee un libro tranquilo | الفتاة تقرأ كتابًا بهدوء',
                'Das Mädchen liest ein Buch leise | الفتاة تقرأ كتابًا بهدوء',
                'The dog runs quickly in the garden | الكلب يركض بسرعة في الحديقة',
                'Nous jouons avec des amis chaque après-midi | نلعب مع الأصدقاء كل بعد ظهر',
                'Jugamos con amigos cada tarde | نلعب مع الأصدقاء كل بعد ظهر',
                'Wir spielen mit Freunden jeden Nachmittag | نلعب مع الأصدقاء كل بعد ظهر',
                'The bird flies high above the trees | الطائر يطير عاليًا فوق الأشجار',
                'I draw a house with a red roof | أرسم منزلًا بسيطًا بلون أحمر',
            ],
        ];

        return $fallbackSentences[$level] ?? $fallbackSentences[1];
    }
}