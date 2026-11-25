<?php

declare(strict_types=1);

namespace App\Auth;

use App\Models\Role;
use App\Models\User;
use Closure;
use DateTimeImmutable;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Throwable;

/**
 * Bearer tokens for the JSON API (spec §6): HS256 JWTs with claims `sub`
 * (user id), `role`, `iat` and `exp`, valid for one hour. No refresh tokens.
 *
 * The signing key is SHA-256 of JWT_SECRET: php-jwt refuses HS256 keys shorter
 * than 32 bytes, while the spec allows any non-empty secret outside production.
 */
final readonly class JwtTokens
{
    public const int TTL_SECONDS = 3600;
    private const string ALGORITHM = 'HS256';

    private string $key;

    /** @var Closure(): int */
    private Closure $clock;

    /** @param (Closure(): int)|null $clock current Unix time; defaults to time() */
    public function __construct(#[\SensitiveParameter] string $secret, ?Closure $clock = null)
    {
        $this->key = hash('sha256', $secret, true);
        $this->clock = $clock ?? time(...);
    }

    public function issue(User $user): IssuedToken
    {
        $now = ($this->clock)();
        $expires = $now + self::TTL_SECONDS;
        $token = JWT::encode(
            ['sub' => (string) $user->id, 'role' => $user->role->value, 'iat' => $now, 'exp' => $expires],
            $this->key,
            self::ALGORITHM,
        );

        return new IssuedToken($token, new DateTimeImmutable("@{$expires}"));
    }

    /** The token's claims, or null when it is malformed, tampered with, expired or not ours. */
    public function verify(string $token): ?TokenClaims
    {
        // php-jwt reads the time from a static; set it for this call only.
        $previous = JWT::$timestamp;
        JWT::$timestamp = ($this->clock)();
        try {
            $payload = JWT::decode($token, new Key($this->key, self::ALGORITHM));
        } catch (Throwable) {
            return null;
        } finally {
            JWT::$timestamp = $previous;
        }

        $sub = $payload->sub ?? null;
        $role = $payload->role ?? null;
        if (!isset($payload->exp)
            || !is_string($sub) || !preg_match('/^[1-9][0-9]{0,9}$/', $sub)
            || !is_string($role) || ($role = Role::tryFrom($role)) === null
        ) {
            return null;
        }

        return new TokenClaims((int) $sub, $role);
    }
}
