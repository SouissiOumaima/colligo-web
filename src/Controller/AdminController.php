<?php

namespace App\Controller;

use App\Entity\Admin;
use App\Entity\Fill_in_the_blank;
use App\Form\AddAdminType;
use App\Repository\AdminRepository;
use App\Repository\ParentsRepository;
use App\Repository\ChildRepository;
use App\Repository\Fill_in_the_blankRepository;
use App\Service\AIService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;

class AdminController extends AbstractController
{
    private AdminRepository $adminRepository;
    private Fill_in_the_blankRepository $fillInTheBlankRepository;
    private AIService $aiService;
    private ParentsRepository $parentsRepository;
    private ChildRepository $childRepository;
    private EntityManagerInterface $entityManager;

    public function __construct(
        AdminRepository $adminRepository,
        Fill_in_the_blankRepository $fillInTheBlankRepository,
        ParentsRepository $parentsRepository,
        ChildRepository $childRepository,
        AIService $aiService,
        EntityManagerInterface $entityManager
    ) {
        $this->adminRepository = $adminRepository;
        $this->fillInTheBlankRepository = $fillInTheBlankRepository;
        $this->parentsRepository = $parentsRepository;
        $this->childRepository = $childRepository;
        $this->aiService = $aiService;
        $this->entityManager = $entityManager;
    }

    #[Route('/admin/dashboard', name: 'admin_dashboard')]
    public function dashboard(): Response
    {
        $connection = $this->entityManager->getConnection();

        // Fetch total counts
        $totalAdmins = $connection->fetchOne('SELECT COUNT(*) FROM admin');
        $totalParents = $connection->fetchOne('SELECT COUNT(*) FROM parents');
        $totalChildren = $connection->fetchOne('SELECT COUNT(*) FROM child');

        // Fetch admins using raw SQL
        $stmt = $connection->prepare('SELECT adminId, email, password FROM admin');
        $result = $stmt->executeQuery();
        $adminRows = $result->fetchAllAssociative();

        $admins = [];
        foreach ($adminRows as $row) {
            $admin = new \App\Entity\Admin();
            $reflection = new \ReflectionClass($admin);
            $adminIdProperty = $reflection->getProperty('adminId');
            $adminIdProperty->setAccessible(true);
            $adminIdProperty->setValue($admin, (int) $row['adminId']);
            $admin->setEmail((string) $row['email']);
            $admin->setPassword((string) $row['password']);
            $admins[] = $admin;
        }

        // Fetch parents using raw SQL
        $stmt = $connection->prepare('SELECT parentId, email, password FROM parents');
        $result = $stmt->executeQuery();
        $parentRows = $result->fetchAllAssociative();

        $parents = [];
        $parentChildren = [];
        foreach ($parentRows as $row) {
            $parent = new \App\Entity\Parents();
            $reflection = new \ReflectionClass($parent);
            $parentIdProperty = $reflection->getProperty('parentId');
            $parentIdProperty->setAccessible(true);
            $parentIdProperty->setValue($parent, (int) $row['parentId']);
            $parent->setEmail((string) $row['email']);
            $parent->setPassword((string) $row['password']);

            // Fetch children for this parent
            $stmt = $connection->prepare(
                'SELECT childId, age, language, avatar, name FROM child WHERE parentId = :parentId'
            );
            $result = $stmt->executeQuery(['parentId' => $parent->getParentId()]);
            $childRows = $result->fetchAllAssociative();

            $children = [];
            foreach ($childRows as $childRow) {
                $child = new \App\Entity\Child();
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

        // Fetch children by language
        $childrenFrench = $connection->fetchOne('SELECT COUNT(*) FROM child WHERE language = "Français"');
        $childrenEnglish = $connection->fetchOne('SELECT COUNT(*) FROM child WHERE language = "Anglais"');
        $childrenSpanish = $connection->fetchOne('SELECT COUNT(*) FROM child WHERE language = "Espagnol"');
        $childrenGerman = $connection->fetchOne('SELECT COUNT(*) FROM child WHERE language = "German"');

        // Fetch children's age distribution (histogram)
        $ageData = $connection->fetchAllAssociative('SELECT age FROM child');
        $ageHistogram = array_fill(0, 4, 0); // Bins: 4-5, 6-7, 8-9, 10+
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

    #[Route('/admin/fill-in-the-blank-dashboard', name: 'admin_fill_in_the_blank_dashboard')]
    public function fillInTheBlankDashboard(): Response
    {
        return $this->render('admin/fill_in_the_blank_dashboard.html.twig');
    }

    #[Route('/admin/generate-questions', name: 'admin_generate_questions')]
    public function generateQuestions(
        Request $request,
        SessionInterface $session
    ): Response {
        // Define choices for the form
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

        // Create the form with default values
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

        // Handle form submission for generating questions
        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            $theme = $data['theme'];
            $level = $data['level'];
            $language = $data['language'];

            // Validate form data
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

            // Generate questions using AIService
            $rawResponse = $this->aiService->generateMultipleFillInTheBlank($theme, $level, $language);

            if (count($rawResponse) === 1 && str_starts_with($rawResponse[0], 'Error')) {
                $this->addFlash('error', 'Error: ' . $rawResponse[0]);
            } else {
                $rawText = $rawResponse[0];
                $questions = $this->parseApiResponse($rawText, $theme, $level, $language);
                $session->set('generated_questions', $questions);
            }
        }

        // Handle the "Sauvegarder" (Save) button
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

            // Remove saved questions from the session
            $remainingQuestions = array_filter($questions, function ($question) use ($selected) {
                return !in_array($question->getQuestionText(), $selected);
            });
            $session->set('generated_questions', array_values($remainingQuestions));
            $questions = $session->get('generated_questions', []);

            $this->addFlash('success', 'Les questions sélectionnées ont été sauvegardées avec succès.');
        }

        // Handle the "Rafraîchir" (Refresh) button
        if ($request->request->has('refresh')) {
            $theme = $request->request->get('theme', 'Animaux');
            $level = (int) $request->request->get('level', 1);
            $language = $request->request->get('language', 'Français');

            // Validate refresh data
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

    #[Route('/admin/add-question', name: 'admin_add_question')]
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

            // Validate form inputs
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

            // Validate query parameters
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

    #[Route('/admin/fill-in-the-blank-database', name: 'admin_fill_in_the_blank_database')]
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

    #[Route('/admin/update-question', name: 'admin_update_question', methods: ['POST'])]
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

    #[Route('/admin/user/management', name: 'admin_user_management')]
    public function userManagement(
        Request $request,
        UserPasswordHasherInterface $passwordHasher
    ): Response {
        // Log the request query parameters for debugging
        error_log('Query Parameters: ' . json_encode($request->query->all()));

        // Fetch raw admins using raw SQL to avoid mapping issues
        $connection = $this->entityManager->getConnection();
        $stmt = $connection->prepare('SELECT adminId, email, password FROM admin');
        $result = $stmt->executeQuery();
        $adminRows = $result->fetchAllAssociative();

        // Fetch parents using raw SQL to avoid parent_id vs parentId mismatch
        $stmt = $connection->prepare('SELECT parentId, email, password FROM parents');
        $result = $stmt->executeQuery();
        $parentRows = $result->fetchAllAssociative();

        // Debug: Log the raw data
        error_log('Raw Admins: ' . json_encode($adminRows));
        error_log('Raw Parents: ' . json_encode($parentRows));

        // Construct Admins entities from raw data
        $admins = [];
        foreach ($adminRows as $row) {
            $admin = new \App\Entity\Admin();
            $reflection = new \ReflectionClass($admin);
            $adminIdProperty = $reflection->getProperty('adminId');
            $adminIdProperty->setAccessible(true);
            $adminIdProperty->setValue($admin, (int) $row['adminId']);

            $admin->setEmail((string) $row['email']);
            $admin->setPassword((string) $row['password']);
            $admins[] = $admin;
        }

        // Construct Parents entities from raw data
        $parents = [];
        $parentChildren = []; // Array to store children for each parent
        foreach ($parentRows as $row) {
            $parent = new \App\Entity\Parents();
            $reflection = new \ReflectionClass($parent);
            $parentIdProperty = $reflection->getProperty('parentId');
            $parentIdProperty->setAccessible(true);
            $parentIdProperty->setValue($parent, (int) $row['parentId']);

            $parent->setEmail((string) $row['email']);
            $parent->setPassword((string) $row['password']);

            // Fetch children using raw SQL to avoid child_id vs childId mismatch
            $stmt = $connection->prepare(
                'SELECT childId, age, language, avatar, name ' .
                    'FROM child WHERE parentId = :parentId'
            );
            $result = $stmt->executeQuery(['parentId' => $parent->getParentId()]);
            $childRows = $result->fetchAllAssociative();

            $children = [];
            foreach ($childRows as $childRow) {
                $child = new \App\Entity\Child();
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

        // Debug: Log the filtered data
        error_log('Filtered Admins: ' . json_encode($admins));
        error_log('Filtered Parents: ' . json_encode($parents));
        // Handle delete action
        if ($request->query->has('delete')) {
            $id = $request->query->getInt('delete');
            error_log("Delete request received: ID=$id");

            try {
                // Check if the ID belongs to an admin
                $stmt = $connection->prepare('SELECT adminId, email FROM admin WHERE adminId = :id');
                $result = $stmt->executeQuery(['id' => $id]);
                $adminRow = $result->fetchAssociative();

                if ($adminRow) {
                    error_log("Deleting admin with ID: $id");
                    $affectedRows = $connection->executeQuery('DELETE FROM admin WHERE adminId = :id', ['id' => $id]);
                    error_log("Affected rows after admin delete: " . $affectedRows->rowCount());
                    $this->addFlash('success', 'Administrateur supprimé avec succès.');
                    return $this->redirectToRoute('admin_user_management');
                }

                // Check if the ID belongs to a parent
                $stmt = $connection->prepare('SELECT parentId, email FROM parents WHERE parentId = :id');
                $result = $stmt->executeQuery(['id' => $id]);
                $parentRow = $result->fetchAssociative();

                if ($parentRow) {
                    error_log("Deleting parent with ID: $id");
                    // Delete associated children first due to foreign key constraint
                    $connection->executeQuery('DELETE FROM child WHERE parentId = :id', ['id' => $id]);
                    $affectedRows = $connection->executeQuery('DELETE FROM parents WHERE parentId = :id', ['id' => $id]);
                    error_log("Affected rows after parent delete: " . $affectedRows->rowCount());
                    $this->addFlash('success', 'Parent supprimé avec succès.');
                    return $this->redirectToRoute('admin_user_management');
                }

                // Check if the ID belongs to a child
                $stmt = $connection->prepare('SELECT childId, name FROM child WHERE childId = :id');
                $result = $stmt->executeQuery(['id' => $id]);
                $childRow = $result->fetchAssociative();

                if ($childRow) {
                    error_log("Deleting child with ID: $id");
                    $affectedRows = $connection->executeQuery('DELETE FROM child WHERE childId = :id', ['id' => $id]);
                    error_log("Affected rows after child delete: " . $affectedRows->rowCount());
                    $this->addFlash('success', 'Enfant supprimé avec succès.');
                    return $this->redirectToRoute('admin_user_management');
                }

                // If ID doesn't match any user type
                error_log("No user found with ID: $id");
                $this->addFlash('error', 'Utilisateur non trouvé.');
            } catch (\Exception $e) {
                error_log("General error during deletion: " . $e->getMessage());
                $this->addFlash('error', 'Erreur générale lors de la suppression : ' . $e->getMessage());
            }

            return $this->redirectToRoute('admin_user_management');
        }

        // Render the template with validated data
        return $this->render('admin/user_management.html.twig', [
            'admins' => $admins,
            'parents' => $parents,
            'parentChildren' => $parentChildren
        ]);
    }

    #[Route('/admin/add-admin', name: 'admin_add_admin', methods: ['GET', 'POST'])]
    public function addAdmin(Request $request, UserPasswordHasherInterface $passwordHasher): Response
    {
        $form = $this->createForm(AddAdminType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            $email = $data['email'];
            $plainPassword = $data['password'];

            // Check if email already exists
            $existingAdmin = $this->adminRepository->findOneBy(['email' => $email]);
            if ($existingAdmin) {
                $this->addFlash('error', 'Un administrateur avec cet email existe déjà.');
                return $this->render('admin/add_admin.html.twig', [
                    'form' => $form->createView(),
                ]);
            }

            // Create new Admin entity
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
    #[Route('/admin/user/statistics', name: 'admin_user_statistics')]
    public function userStatistics(): Response
    {
        $connection = $this->entityManager->getConnection();

        // Fetch total counts
        $totalAdmins = $connection->fetchOne('SELECT COUNT(*) FROM admin');
        $totalParents = $connection->fetchOne('SELECT COUNT(*) FROM parents');
        $totalChildren = $connection->fetchOne('SELECT COUNT(*) FROM child');

        // Fetch children by level
        $childrenLevel1 = $connection->fetchOne('SELECT COUNT(DISTINCT l.childId) FROM level l WHERE l.id = 1');
        $childrenLevel2 = $connection->fetchOne('SELECT COUNT(DISTINCT l.childId) FROM level l WHERE l.id = 2');
        $childrenLevel3 = $connection->fetchOne('SELECT COUNT(DISTINCT l.childId) FROM level l WHERE l.id = 3');

        // Fetch children by language
        $childrenFrench = $connection->fetchOne('SELECT COUNT(*) FROM child WHERE language = "Français"');
        $childrenEnglish = $connection->fetchOne('SELECT COUNT(*) FROM child WHERE language = "Anglais"');
        $childrenSpanish = $connection->fetchOne('SELECT COUNT(*) FROM child WHERE language = "Espagnol"');
        $childrenGerman = $connection->fetchOne('SELECT COUNT(*) FROM child WHERE language = "German"');

        // Fetch number of children per parent (for histogram)
        $childrenPerParentData = $connection->fetchAllAssociative(
            'SELECT p.parentId, COUNT(c.childId) as child_count 
             FROM parents p 
             LEFT JOIN child c ON c.parentId = p.parentId 
             GROUP BY p.parentId'
        );
        $childrenPerParentHistogram = array_fill(0, 6, 0); // For 0 to 5+ children
        foreach ($childrenPerParentData as $row) {
            $count = (int)$row['child_count'];
            $index = min($count, 5); // Cap at 5+ for the histogram
            $childrenPerParentHistogram[$index]++;
        }

        // Fetch levels by game from the level table
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

        // Fetch children's age distribution (histogram)
        $ageData = $connection->fetchAllAssociative('SELECT age FROM child');
        $ageHistogram = array_fill(0, 4, 0); // Bins: 4-5, 6-7, 8-9, 10+
        foreach ($ageData as $row) {
            $age = (int)$row['age'];
            if ($age <= 5) { // Include ages 4 and below in the 4-5 bin
                $ageHistogram[0]++;
            } elseif ($age <= 7) {
                $ageHistogram[1]++;
            } elseif ($age <= 9) {
                $ageHistogram[2]++;
            } else { // 10+
                $ageHistogram[3]++;
            }
        }

        // Fetch average attempts per level
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

        // Fetch average time spent per level
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

        // Fetch score distribution per game (histogram)
        $scoreData = $connection->fetchAllAssociative(
            'SELECT gameId, score 
             FROM level'
        );
        $scoreHistogram = [];
        $scoreBins = [0, 20, 40, 60, 80, 100]; // Bins: 0-20, 20-40, ..., 80-100
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
}
