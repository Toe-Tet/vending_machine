<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Auth\Auth;
use App\Auth\Credentials;
use App\Auth\Guard;
use App\Core\Request;
use App\Core\Response;
use App\Core\Route;
use App\Core\View;
use App\Models\Role;
use App\Repositories\DuplicateEmailException;
use App\Repositories\UserRepository;

/** Sign in, sign out and sign up for the web UI (spec §4 Users & auth). */
final readonly class AuthController
{
    private const string BAD_CREDENTIALS = 'Incorrect email or password.';
    private const string EMAIL_TAKEN = 'That email is already registered.';

    public function __construct(
        private UserRepository $users,
        private Credentials $credentials,
        private Auth $auth,
        private Guard $guard,
        private View $view,
    ) {
    }

    #[Route('GET', '/login')]
    public function showLogin(): Response
    {
        return $this->guard->guest() ?? $this->loginForm();
    }

    #[Route('POST', '/login')]
    public function login(Request $request): Response
    {
        if ($redirect = $this->guard->guest()) {
            return $redirect;
        }

        $user = $this->credentials->check($request->input('email'), $request->input('password'));
        if ($user === null) {
            return $this->loginForm(['email' => Credentials::normaliseEmail($request->input('email'))], self::BAD_CREDENTIALS, 422);
        }

        $this->auth->login($user);

        return Response::redirect('/products');
    }

    #[Route('POST', '/logout')]
    public function logout(): Response
    {
        if ($denied = $this->guard->signedIn()) {
            return $denied;
        }

        $this->auth->logout();

        return Response::redirect('/products');
    }

    #[Route('GET', '/register')]
    public function showRegister(): Response
    {
        return $this->guard->guest() ?? $this->registerForm();
    }

    /** Sign-ups always get the User role; Admins exist only via the seed. */
    #[Route('POST', '/register')]
    public function register(Request $request): Response
    {
        if ($redirect = $this->guard->guest()) {
            return $redirect;
        }

        $name = trim(self::string($request->input('name')));
        $email = Credentials::normaliseEmail($request->input('email'));
        $password = self::string($request->input('password'));
        $old = ['name' => $name, 'email' => $email];

        $errors = self::registrationErrors($name, $email, $password, self::string($request->input('password_confirmation')));
        if (!isset($errors['email']) && $this->users->findByEmail($email) !== null) {
            $errors['email'] = self::EMAIL_TAKEN;
        }
        if ($errors !== []) {
            return $this->registerForm($old, $errors, 422);
        }

        try {
            $user = $this->users->create($name, $email, password_hash($password, PASSWORD_DEFAULT), Role::User);
        } catch (DuplicateEmailException) {
            // Someone registered the same email between our check and the insert.
            return $this->registerForm($old, ['email' => self::EMAIL_TAKEN], 422);
        }
        $this->auth->login($user);

        return Response::redirect('/products');
    }

    /** @return array<string, string> field => message, empty when everything is valid */
    private static function registrationErrors(string $name, string $email, string $password, string $confirmation): array
    {
        $errors = [];

        if ($name === '') {
            $errors['name'] = 'Enter your name.';
        } elseif (mb_strlen($name) > 100) {
            $errors['name'] = 'Name must be 100 characters or fewer.';
        }

        if (strlen($email) > 255 || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $errors['email'] = 'Enter a valid email address.';
        }

        if (mb_strlen($password) < 8) {
            $errors['password'] = 'Password must be at least 8 characters.';
        }
        if (!hash_equals($password, $confirmation)) {
            $errors['password_confirmation'] = 'Passwords don’t match.';
        }

        return $errors;
    }

    /**
     * @param array<string, string> $old
     * @param array<string, string> $errors field => message
     */
    private function registerForm(array $old = [], array $errors = [], int $status = 200): Response
    {
        return $this->view->response('auth/register', ['title' => 'Sign up', 'old' => $old, 'errors' => $errors], $status);
    }

    /** @param array<string, string> $old */
    private function loginForm(array $old = [], ?string $error = null, int $status = 200): Response
    {
        return $this->view->response('auth/login', ['title' => 'Sign in', 'old' => $old, 'error' => $error], $status);
    }

    /** Form fields arrive as strings, but a crafted request can send arrays (name[]=x). */
    private static function string(mixed $value): string
    {
        return is_string($value) ? $value : '';
    }
}
