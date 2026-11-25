<?php

declare(strict_types=1);

namespace App\Repositories;

use RuntimeException;

/** Raised when creating a User whose email is already registered. */
final class DuplicateEmailException extends RuntimeException
{
}
