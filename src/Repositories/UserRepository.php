<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Role;
use App\Models\User;

/** People who can sign in. Emails are unique (case-insensitively, by the column collation). */
interface UserRepository
{
    public function find(int $id): ?User;

    public function findByEmail(string $email): ?User;

    /** Throws DuplicateEmailException when the email is already registered. */
    public function create(string $name, string $email, string $passwordHash, Role $role = Role::User): User;

    /** @return bool false when the User doesn't exist */
    public function update(int $id, string $name, string $email): bool;

    /** @return bool false when the User doesn't exist */
    public function updatePassword(int $id, string $passwordHash): bool;

    /** Fails with a constraint violation if the User has Purchases (FK RESTRICT). */
    public function delete(int $id): bool;
}
