<?php

declare(strict_types=1);

namespace App\Auth;

use DateTimeImmutable;

/** A freshly signed API token and the moment it stops being accepted. */
final readonly class IssuedToken
{
    public function __construct(public string $token, public DateTimeImmutable $expiresAt)
    {
    }
}
