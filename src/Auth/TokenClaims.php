<?php

declare(strict_types=1);

namespace App\Auth;

use App\Models\Role;

/** Who a verified API token speaks for: its `sub` and `role` claims. */
final readonly class TokenClaims
{
    public function __construct(public int $userId, public Role $role)
    {
    }

    public function isAdmin(): bool
    {
        return $this->role === Role::Admin;
    }
}
