<?php

declare(strict_types=1);

namespace App\Auth;

use App\Core\Session;
use App\Models\Role;
use App\Models\User;

/**
 * Who is signed in, kept in the session. The role is stored alongside the id
 * so guards can authorise without a database read (spec §4 Users & auth).
 */
final readonly class Auth
{
    private const string KEY = '_auth';

    public function __construct(private Session $session, private Csrf $csrf)
    {
    }

    /** Signs $user in under a new session id and CSRF token (prevents fixation). */
    public function login(User $user): void
    {
        $this->session->regenerate();
        $this->csrf->rotate();
        $this->session->set(self::KEY, ['id' => $user->id, 'name' => $user->name, 'role' => $user->role->value]);
    }

    public function logout(): void
    {
        $this->session->clear();
    }

    public function check(): bool
    {
        return $this->identity() !== null;
    }

    public function id(): ?int
    {
        return $this->identity()['id'] ?? null;
    }

    public function name(): ?string
    {
        return $this->identity()['name'] ?? null;
    }

    public function role(): ?Role
    {
        $role = $this->identity()['role'] ?? null;

        return $role === null ? null : Role::tryFrom($role);
    }

    public function isAdmin(): bool
    {
        return $this->role() === Role::Admin;
    }

    /** @return array{id: int, name: string, role: string}|null */
    private function identity(): ?array
    {
        $identity = $this->session->get(self::KEY);

        return is_array($identity) ? $identity : null;
    }
}
