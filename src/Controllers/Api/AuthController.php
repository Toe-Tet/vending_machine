<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Api\Json;
use App\Auth\Credentials;
use App\Auth\JwtTokens;
use App\Core\Request;
use App\Core\Response;
use App\Core\Route;
use DateTimeInterface;

/** Exchanges an email and password for a one-hour Bearer token (spec §6). */
final readonly class AuthController
{
    public function __construct(private Credentials $credentials, private JwtTokens $tokens)
    {
    }

    #[Route('POST', '/api/auth/login')]
    public function login(Request $request): Response
    {
        $body = Json::body($request);
        if ($body instanceof Response) {
            return $body;
        }

        $email = $body['email'] ?? null;
        $password = $body['password'] ?? null;
        $errors = array_filter([
            'email' => Credentials::normaliseEmail($email) === '' ? 'Enter your email.' : null,
            'password' => !is_string($password) || $password === '' ? 'Enter your password.' : null,
        ]);
        if ($errors !== []) {
            return Json::invalid($errors);
        }

        $user = $this->credentials->check($email, $password);
        if ($user === null) {
            return Json::error(401, 'invalid_credentials', 'Incorrect email or password.');
        }

        $issued = $this->tokens->issue($user);

        return Response::json([
            'token' => $issued->token,
            'expires_at' => $issued->expiresAt->format(DateTimeInterface::ATOM),
            'user' => ['id' => $user->id, 'name' => $user->name, 'email' => $user->email, 'role' => $user->role->value],
        ]);
    }
}
