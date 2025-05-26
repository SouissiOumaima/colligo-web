<?php

namespace App\Controller;

use App\Entity\Parents;
use App\Entity\Admin;
use App\Form\VerifyFormType;
use App\Service\AuthService;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Exception\AuthenticationException;

class AuthController extends AbstractController
{
    private $authService;
    private $logger;

    public function __construct(AuthService $authService, LoggerInterface $logger)
    {
        $this->authService = $authService;
        $this->logger = $logger;
    }

    private function isValidPassword(string $password): bool
    {
        $isValid = strlen($password) >= 6 &&
            preg_match('/[A-Z]/', $password) &&
            preg_match('/[!@#$%^&*(),.?":{}|<>]/', $password);

        if (!$isValid) {
            $this->logger->debug('Password validation failed', [
                'password' => $password,
                'length' => strlen($password),
                'uppercase' => preg_match('/[A-Z]/', $password),
                'special_char' => preg_match('/[!@#$%^&*(),.?":{}|<>]/', $password),
            ]);
        } else {
            $this->logger->debug('Password validation passed', ['password' => $password]);
        }

        return $isValid;
    }
#[Route('/', name: 'app_root', methods: ['GET', 'POST'])]
    #[Route('/login', name: 'app_login', methods: ['GET', 'POST'])] 
    public function login(Request $request): Response
    {
        $this->logger->info('Processing login request', ['method' => $request->getMethod()]);

        if ($request->isMethod('POST')) {
            $email = $request->request->get('email');
            $password = $request->request->get('password');

            $this->logger->debug('Login form data', ['email' => $email]);

            try {
                // Vérifier si l'email existe dans la table Admin et si le mot de passe correspond
                $admin = $this->authService->verifyAdminCredentials($email, $password);
                if ($admin) {
                    $this->logger->info('Admin credentials verified, redirecting to admin dashboard', ['email' => $email]);
                    $this->addFlash('success', 'Connexion admin réussie !');
                    return $this->redirectToRoute('admin_dashboard', ['adminId' => $admin->getAdminId()]);
                }

                // Si pas admin, tenter la connexion parent
                $user = $this->authService->login($email, $password);
                $this->logger->info('Login successful, redirecting to parent dashboard', ['email' => $email]);
                $this->addFlash('success', 'Connexion réussie !');
                return $this->redirectToRoute('pre_dashboard', ['parentId' => $user->getParentId()]);
            } catch (AuthenticationException $e) {
                $this->logger->error('Login failed: {error}', ['error' => $e->getMessage(), 'email' => $email]);
                $this->addFlash('error', $e->getMessage());
                return $this->redirectToRoute('app_login');
            }
        }

        $this->logger->info('Rendering login page');
        return $this->render('auth/login.html.twig');
    }

    #[Route('/register', name: 'app_register', methods: ['GET', 'POST'])]
    public function register(Request $request): Response
    {
        $this->logger->info('Processing register request', ['method' => $request->getMethod()]);

        $passwordError = null;
        $email = $request->request->get('email');
        $password = $request->request->get('password');
        $confirmPassword = $request->request->get('confirm_password');

        if ($request->isMethod('POST')) {
            $this->logger->debug('Form data extracted', [
                'email' => $email,
                'password' => $password ? 'provided' : 'missing',
                'confirm_password' => $confirmPassword ? 'provided' : 'missing',
            ]);

            if (empty($email) || empty($password) || empty($confirmPassword)) {
                $this->addFlash('error', 'Veuillez remplir tous les champs du formulaire.');
            } elseif ($password !== $confirmPassword) {
                $this->addFlash('error', 'Les mots de passe ne correspondent pas.');
            } elseif (!$this->isValidPassword($password)) {
                $passwordError = 'Le mot de passe doit contenir :';
                $passwordError .= '<ul>';
                $passwordError .= strlen($password) < 6 ? '<li>Au moins 6 caractères</li>' : '';
                $passwordError .= !preg_match('/[A-Z]/', $password) ? '<li>Au moins une majuscule</li>' : '';
                $passwordError .= !preg_match('/[!@#$%^&*(),.?":{}|<>]/', $password) ? '<li>Au moins un caractère spécial</li>' : '';
                $passwordError .= '</ul>';
                $this->addFlash('error', $passwordError);
            } else {
                try {
                    // Vérifier si l'email existe dans la table Admin et si le mot de passe correspond
                    $admin = $this->authService->verifyAdminCredentials($email, $password);
                    if ($admin) {
                        $this->logger->info('Admin credentials verified, redirecting to admin dashboard', ['email' => $email]);
                        $this->addFlash('success', 'Connexion admin réussie !');
                        return $this->redirectToRoute('admin_dashboard', ['adminId' => $admin->getAdminId()]);
                    }

                    // Si pas admin, continuer avec la logique de signup des parents
                    $result = $this->authService->signup($email, $password);
                    $signupToken = $result['signup_token'];
                    $request->getSession()->set('pending_verification_email', $email);

                    $this->addFlash('success', 'Inscription réussie. Veuillez vérifier votre compte.');
                    return $this->redirectToRoute('verify_account', ['token' => $signupToken]);
                } catch (\Exception $e) {
                    $this->logger->error('Signup failed: {error}', [
                        'error' => $e->getMessage(),
                        'email' => $email,
                        'exception' => $e->getTraceAsString(),
                    ]);
                    $this->addFlash('error', $e->getMessage());
                }
            }

            // Rediriger vers la page d'inscription pour afficher les erreurs
            return $this->redirectToRoute('app_register');
        }

        // Rendre la page pour GET sans messages flash
        $this->logger->info('Rendering register page');
        return $this->render('/auth/register.html.twig', [
            'password_error' => $passwordError,
            'email' => $email ?? '',
            'password' => null,
            'verify_password' => null,
        ]);
    }

    #[Route('/verify-account', name: 'verify_account', methods: ['GET', 'POST'])]
    public function verify(Request $request): Response
    {
        $this->logger->info('Processing verify request', ['method' => $request->getMethod()]);

        $email = $request->getSession()->get('pending_verification_email');
        $token = $request->query->get('token');

        if (!$email) {
            $this->logger->error('Verification failed: No email found in session');
            $this->addFlash('error', 'Aucun email trouvé. Veuillez vous inscrire à nouveau.');
            return $this->redirectToRoute('app_register');
        }

        $form = $this->createForm(VerifyFormType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            $code = $data['code'];

            $this->logger->debug('Verify form data', [
                'email' => $email,
                'code' => $code,
                'token' => $token,
            ]);

            try {
                $this->logger->info('Calling verifyAccount for email: {email}', ['email' => $email]);
                $user = $this->authService->verifyAccount($email, $code);
                $this->logger->info('Verification successful, clearing session email and redirecting to app_login', ['email' => $email]);

                $request->getSession()->remove('pending_verification_email');

                $this->addFlash('success', 'Compte vérifié avec succès.');
                return $this->redirectToRoute('app_login');
            } catch (\Exception $e) {
                $this->logger->error('Verification failed: {error}', [
                    'error' => $e->getMessage(),
                    'email' => $email,
                    'code' => $code
                ]);
                $this->addFlash('error', $e->getMessage());
            }
        } else {
            $this->logger->debug('Form submission status', [
                'is_submitted' => $form->isSubmitted(),
                'errors' => $form->isSubmitted() ? $form->getErrors(true) : 'Form not submitted yet'
            ]);
        }

        $this->logger->info('Rendering verify page after processing');
        return $this->render('auth/verify.html.twig', [
            'form' => $form->createView(),
            'token' => $token,
        ]);
    }

    #[Route('/forgot-password-request', name: 'forgot_password_request', methods: ['GET', 'POST'])]
    public function forgotPasswordRequest(Request $request): Response
    {
        $this->logger->info('Processing forgot password request');

        if ($request->isMethod('POST')) {
            $email = $request->request->get('email');

            $this->logger->debug('Forgot password form data', ['email' => $email]);

            try {
                $result = $this->authService->forgotPassword($email);
                $resetPasswordToken = $result['reset_password_token'];
                $this->logger->info('Forgot password request successful, redirecting to forgot_password with token', [
                    'email' => $email,
                    'reset_password_token' => $resetPasswordToken
                ]);
                $this->addFlash('success', 'Un code de réinitialisation a été envoyé à votre email.');
                return $this->redirectToRoute('forgot_password', ['token' => $resetPasswordToken]);
            } catch (\Exception $e) {
                $this->logger->error('Forgot password request failed: {error}', ['error' => $e->getMessage(), 'email' => $email]);
                $this->addFlash('error', $e->getMessage());
            }
        }

        $this->logger->info('Rendering forgot password request page');
        return $this->render('auth/forgot_password_request.html.twig');
    }

    #[Route('/forgot-password', name: 'forgot_password', methods: ['GET', 'POST'])]
    public function forgotPassword(Request $request): Response
    {
        $this->logger->info('Processing forgot password reset');

        $token = $request->query->get('token') ?? $request->request->get('token');

        if (!$token && $request->isMethod('GET')) {
            $this->logger->info('No token provided, redirecting to forgot_password_request');
            return $this->redirectToRoute('forgot_password_request');
        }

        $passwordError = null;
        $formData = [
            'token' => $token,
            'code' => $request->request->get('code'),
            'new_password' => $request->request->get('new_password'),
            'confirm_password' => $request->request->get('confirm_password')
        ];

        if ($request->isMethod('POST')) {
            $this->logger->debug('Processing password reset form submission', ['token' => $token]);

            try {
                if (!$this->authService->isResetPasswordTokenValid($token)) {
                    $this->logger->error('Invalid reset token', ['token' => $token]);
                    $this->addFlash('error_forgot_password', 'Le lien de réinitialisation est invalide ou a expiré.');
                    return $this->redirectToRoute('forgot_password_request');
                }
            } catch (\Exception $e) {
                $this->logger->error('Token validation error', ['error' => $e->getMessage()]);
                $this->addFlash('error_forgot_password', 'Une erreur est survenue lors de la validation du lien.');
                return $this->redirectToRoute('forgot_password_request');
            }

            if ($formData['new_password'] !== $formData['confirm_password']) {
                $this->logger->error('Password mismatch');
                $this->addFlash('error_forgot_password', 'Les mots de passe ne correspondent pas.');
            } elseif (!$this->isValidPassword($formData['new_password'])) {
                $this->logger->error('Invalid password format');
                $passwordError = $this->getPasswordRequirementsError($formData['new_password']);
            } else {
                try {
                    $this->authService->resetPassword($token, $formData['new_password']);
                    $this->logger->info('Password reset successful');
                    $this->addFlash('success_forgot_password', 'Votre mot de passe a été réinitialisé avec succès. Vous pouvez maintenant vous connecter.');
                    return $this->render('auth/forgot_password.html.twig', [
                        'token' => $token,
                        'password_error' => $passwordError,
                        'code' => $formData['code'] ?? null,
                        'new_password' => $formData['new_password'] ?? null,
                        'confirm_password' => $formData['confirm_password'] ?? null
                    ]);
                } catch (\Exception $e) {
                    $this->logger->error('Password reset failed', ['error' => $e->getMessage()]);
                    $this->addFlash('error_forgot_password', 'Échec de la réinitialisation : ' . $e->getMessage());
                }
            }
        }

        return $this->render('auth/forgot_password.html.twig', [
            'token' => $token,
            'password_error' => $passwordError,
            'code' => $formData['code'] ?? null,
            'new_password' => $formData['new_password'] ?? null,
            'confirm_password' => $formData['confirm_password'] ?? null
        ]);
    }

    private function getPasswordRequirementsError(string $password): string
    {
        $error = 'Le mot de passe doit contenir :<ul>';
        $error .= strlen($password) < 6 ? '<li>Au moins 6 caractères</li>' : '';
        $error .= !preg_match('/[A-Z]/', $password) ? '<li>Au moins une majuscule</li>' : '';
        $error .= !preg_match('/[!@#$%^&*(),.?":{}|<>]/', $password) ? '<li>Au moins un caractère spécial</li>' : '';
        $error .= '</ul>';
        return $error;
    }

    private function getAndClearFlash(string $type): ?string
    {
        $flashBag = $this->container->get('request_stack')->getSession()->getFlashBag();
        return $flashBag->has($type) ? $flashBag->get($type)[0] : null;
    }
}