<?php

namespace App\Service;

use App\Entity\Parents;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use App\Entity\Admin;
class AuthService
{
    private $entityManager;
    private $passwordHasher;
    private $mailer;
    private $logger;
    private $fromEmail = 'appcolligo@gmail.com';

    public function __construct(
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher,
        MailerInterface $mailer,
        LoggerInterface $logger
    ) {
        $this->entityManager = $entityManager;
        $this->passwordHasher = $passwordHasher;
        $this->mailer = $mailer;
        $this->logger = $logger;
    }
    public function verifyAdminCredentials(string $email, string $password): ?Admin
    {
        $this->logger->debug('Checking admin credentials', ['email' => $email]);
        $admin = $this->entityManager->getRepository(Admin::class)->findOneBy(['email' => $email]);
        if ($admin && $password === $admin->getPassword()) {
            $this->logger->debug('Admin password verified', ['email' => $email]);
            return $admin;
        }
        $this->logger->debug('Admin credentials invalid', ['email' => $email, 'admin_found' => $admin !== null]);
        return null;
    }
    /**
     * Handles user signup: creates a new user, hashes the password, generates a verification code and signup token, and sends email.
     */
    public function signup(string $email, string $password): array
    {
        $this->logger->info('Attempting signup for email: {email}', ['email' => $email]);

        // Check if email already exists
        $existingUser = $this->entityManager->getRepository(Parents::class)->findOneBy(['email' => $email]);
        if ($existingUser) {
            $this->logger->error('Signup failed: Email already exists', ['email' => $email]);
            throw new \Exception('Cet email est déjà utilisé.');
        }

        // Create new Parents entity
        $user = new Parents();
        $user->setEmail($email);
        $user->setPassword($this->passwordHasher->hashPassword($user, $password));
        $user->setIsverified(false);

        // Generate a verification code
        $verificationCode = sprintf("%06d", mt_rand(0, 999999));
        $user->setVerificationcode($verificationCode);

        // Generate a signup token
        $signupToken = bin2hex(random_bytes(32));
        $user->setSignupToken($signupToken);

        // Persist the user to the database
        try {
            $this->entityManager->persist($user);
            $this->entityManager->flush();
            $this->logger->info('User persisted to database successfully', ['email' => $email, 'parentId' => $user->getParentId()]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to persist user to database: {error}', [
                'error' => $e->getMessage(),
                'email' => $email,
                'exception' => $e->getTraceAsString(),
            ]);
            throw new \Exception('Erreur lors de la création du compte dans la base de données.');
        }

        // Send verification email
        try {
            $emailMessage = (new Email())
                ->from($this->fromEmail)
                ->to($email)
                ->subject('Colligo - Vérification de Compte')
                ->text("Colligo - Vérification de Compte\n\nVérifiez Votre Compte\n\nVous avez créé un compte sur Colligo. Utilisez le code ci-dessous pour vérifier votre compte :\n\n$verificationCode\n\nSi vous n'avez pas créé ce compte, veuillez ignorer cet email.")
                ->html("<h2>Colligo - Vérification de Compte</h2><h3>Vérifiez Votre Compte</h3><p>Vous avez créé un compte sur Colligo. Utilisez le code ci-dessous pour vérifier votre compte :</p><p style='font-size: 18px; font-weight: bold;'>$verificationCode</p><p>Si vous n'avez pas créé ce compte, veuillez ignorer cet email.</p>");

            $this->mailer->send($emailMessage);
            $this->logger->info('Verification email sent successfully', ['email' => $email, 'verification_code' => $verificationCode]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to send verification email: {error}', [
                'error' => $e->getMessage(),
                'email' => $email,
                'exception' => $e->getTraceAsString(),
            ]);
            throw new \Exception('Compte créé, mais échec de l\'envoi de l\'email de vérification.');
        }

        return [
            'user' => $user,
            'signup_token' => $signupToken,
        ];
    }
    /**
     * Handles user login: verifies email and password.
     */
    public function login(string $email, string $password): Parents
    {
        $this->logger->info('Attempting login for email: {email}', ['email' => $email]);

        $user = $this->entityManager->getRepository(Parents::class)->findOneBy(['email' => $email]);

        if (!$user) {
            $this->logger->error('Login failed: User not found', ['email' => $email]);
            throw new AuthenticationException('Utilisateur non trouvé.');
        }

        if (!$user->getIsVerified()) {
            $this->logger->error('Login failed: Account not verified', ['email' => $email]);
            throw new AuthenticationException('Veuillez vérifier votre compte avant de vous connecter.');
        }

        if (!$this->passwordHasher->isPasswordValid($user, $password)) {
            $this->logger->error('Login failed: Invalid password', ['email' => $email]);
            throw new AuthenticationException('Mot de passe incorrect.');
        }

        $this->logger->info('Login successful', ['email' => $email]);
        return $user;
    }

    /**
     * Verifies the user's account using the verification code.
     */
    public function verifyAccount(string $email, string $code): Parents
    {
        $this->logger->info('Attempting account verification for email: {email}', ['email' => $email, 'code' => $code]);

        $user = $this->entityManager->getRepository(Parents::class)->findOneBy(['email' => $email]);

        if (!$user) {
            $this->logger->error('Verification failed: User not found', ['email' => $email]);
            throw new \Exception('Utilisateur non trouvé.');
        }

        $this->logger->debug('User found', [
            'email' => $email,
            'is_verified' => $user->getIsVerified(),
            'stored_code' => $user->getVerificationCode()
        ]);

        if ($user->getIsVerified()) {
            $this->logger->error('Verification failed: Account already verified', ['email' => $email]);
            throw new \Exception('Compte déjà vérifié.');
        }

        // Trim both codes to avoid whitespace issues
        $submittedCode = trim($code);
        $storedCode = trim($user->getVerificationCode());

        $this->logger->debug('Comparing codes', [
            'submitted_code' => $submittedCode,
            'stored_code' => $storedCode
        ]);

        if ($storedCode !== $submittedCode) {
            $this->logger->error('Verification failed: Invalid verification code', [
                'email' => $email,
                'submitted_code' => $submittedCode,
                'stored_code' => $storedCode
            ]);
            throw new \Exception('Code de vérification incorrect.');
        }

        // Mark the account as verified and clear the verification code
        $user->setIsVerified(true);
        $user->setVerificationCode(null);
        $user->setSignupToken(null); // Clear the signup token after verification

        try {
            $this->entityManager->flush();
            $this->logger->info('Account verified and database updated successfully', ['email' => $email]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to update database during verification: {error}', [
                'error' => $e->getMessage(),
                'email' => $email
            ]);
            throw new \Exception('Erreur lors de la mise à jour du compte.');
        }

        $this->logger->info('Account verified successfully, sending confirmation email', ['email' => $email]);

        // Send confirmation email
        try {
            $emailMessage = (new Email())
                ->from($this->fromEmail)
                ->to($email)
                ->subject('Compte vérifié')
                ->text('Votre compte a été vérifié avec succès.')
                ->html('<p>Votre compte a été vérifié avec succès.</p>');

            $this->mailer->send($emailMessage);
            $this->logger->info('Confirmation email sent successfully', ['email' => $email]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to send confirmation email: {error}', ['error' => $e->getMessage(), 'email' => $email]);
        }

        return $user;
    }

    /**
     * Handles forgot password: generates a reset code and token, and sends it via email.
     */
    public function forgotPassword(string $email): array
    {
        $this->logger->info('Attempting forgot password request for email: {email}', ['email' => $email]);

        $user = $this->entityManager->getRepository(Parents::class)->findOneBy(['email' => $email]);

        if (!$user) {
            $this->logger->error('Forgot password failed: User not found', ['email' => $email]);
            throw new \Exception('Utilisateur non trouvé.');
        }

        // Generate a reset code (e.g., a random 6-digit code)
        $resetCode = sprintf("%06d", mt_rand(0, 999999));
        $user->setResetCode($resetCode);

        // Generate a reset password token
        $resetPasswordToken = bin2hex(random_bytes(32));
        $user->setResetPasswordToken($resetPasswordToken);

        $this->logger->debug('Generated reset password token', ['token' => $resetPasswordToken, 'email' => $email]);

        $this->entityManager->flush();

        $this->logger->info('Reset code and token generated, sending reset email', ['email' => $email, 'reset_code' => $resetCode, 'reset_password_token' => $resetPasswordToken]);

        // Send reset password email with the new format in French
        try {
            $emailMessage = (new Email())
                ->from($this->fromEmail)
                ->to($email)
                ->subject('Colligo - Réinitialisation de Mot de Passe')
                ->text(
                    "Colligo - Réinitialisation de Mot de Passe\n\n" .
                    "Réinitialisez Votre Mot de Passe\n\n" .
                    "Vous avez demandé à réinitialiser votre mot de passe pour Colligo. Utilisez le code ci-dessous pour réinitialiser votre mot de passe :\n\n" .
                    "$resetCode\n\n" .
                    "Si vous n'avez pas fait cette demande, veuillez ignorer cet email."
                )
                ->html(
                    "<h2>Colligo - Réinitialisation de Mot de Passe</h2>" .
                    "<h3>Réinitialisez Votre Mot de Passe</h3>" .
                    "<p>Vous avez demandé à réinitialiser votre mot de passe pour Colligo. Utilisez le code ci-dessous pour réinitialiser votre mot de passe :</p>" .
                    "<p style='font-size: 18px; font-weight: bold;'>$resetCode</p>" .
                    "<p>Si vous n'avez pas fait cette demande, veuillez ignorer cet email.</p>"
                );

            $this->mailer->send($emailMessage);
            $this->logger->info('Reset password email sent successfully', ['email' => $email]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to send reset password email: {error}', [
                'error' => $e->getMessage(),
                'email' => $email
            ]);
            throw new \Exception('Échec de l\'envoi de l\'email de réinitialisation.');
        }

        return [
            'user' => $user,
            'reset_password_token' => $resetPasswordToken
        ];
    }

    /**
     * Resets the user's password using the reset password token.
     */
    public function resetPassword(string $token, string $newPassword): Parents
    {
        $this->logger->info('Attempting password reset with token: {token}', ['token' => $token]);

        $user = $this->entityManager->getRepository(Parents::class)->findOneBy(['reset_password_token' => $token]);

        if (!$user) {
            $this->logger->error('Password reset failed: Invalid token', ['token' => $token]);
            throw new \Exception('Token de réinitialisation invalide.');
        }

        // Update the password and clear the reset code and token
        $user->setPassword($this->passwordHasher->hashPassword($user, $newPassword));
        $user->setResetCode(null);
        $user->setResetPasswordToken(null);

        $this->entityManager->flush();

        $this->logger->info('Password reset successfully', ['email' => $user->getEmail()]);

        // Send confirmation email
        try {
            $emailMessage = (new Email())
                ->from($this->fromEmail)
                ->to($user->getEmail())
                ->subject('Mot de passe réinitialisé')
                ->text('Votre mot de passe a été réinitialisé avec succès.')
                ->html('<p>Votre mot de passe a été réinitialisé avec succès.</p>');

            $this->mailer->send($emailMessage);
            $this->logger->info('Password reset confirmation email sent successfully', ['email' => $user->getEmail()]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to send password reset confirmation email: {error}', ['error' => $e->getMessage(), 'email' => $user->getEmail()]);
        }

        return $user;
    }

    /**
     * Checks if a reset password token is valid.
     */
    public function isResetPasswordTokenValid(string $token): bool
    {
        $this->logger->info('Checking validity of reset password token: {token}', ['token' => $token]);

        $user = $this->entityManager->getRepository(Parents::class)->findOneBy(['reset_password_token' => $token]);

        if (!$user) {
            $this->logger->error('Token validation failed: Invalid token', ['token' => $token]);
            return false;
        }

        $this->logger->info('Token is valid', ['token' => $token, 'email' => $user->getEmail()]);
        return true;
    }
}