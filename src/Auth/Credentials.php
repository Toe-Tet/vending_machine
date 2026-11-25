<?php

declare(strict_types=1);

namespace App\Auth;

use App\Models\User;
use App\Repositories\UserRepository;

/** Checks an email and password, for the web sign-in form and the API login alike. */
final readonly class Credentials
{
    /** Hash of a random, discarded password at the default cost; nothing verifies against it. */
    private const string DUMMY_HASH = '$2y$12$l9Kk5j4X4yaISkM/WV9Kje9reeGWiKkei0j744KIHLIi64wNKeQrS';

    public function __construct(private UserRepository $users)
    {
    }

    /** Emails are compared trimmed and lower-cased; anything that isn't a string counts as empty. */
    public static function normaliseEmail(mixed $email): string
    {
        return strtolower(trim(is_string($email) ? $email : ''));
    }

    /** The User the email and password belong to, or null when either is wrong. */
    public function check(mixed $email, mixed $password): ?User
    {
        $email = self::normaliseEmail($email);
        $user = $email === '' ? null : $this->users->findByEmail($email);
        // Verify against a dummy hash for unknown emails so response time doesn't reveal which emails exist.
        $valid = password_verify(is_string($password) ? $password : '', $user->passwordHash ?? self::DUMMY_HASH);

        return $valid ? $user : null;
    }
}
