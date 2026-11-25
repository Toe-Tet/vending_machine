<?php

declare(strict_types=1);

namespace App\Services;

use RuntimeException;

/** More units were asked for than the Product has; $available is 0 when it is Sold Out. */
final class InsufficientStockException extends RuntimeException
{
    public function __construct(public readonly int $available)
    {
        parent::__construct($available === 0 ? 'Sold out.' : "Only {$available} available.");
    }
}
