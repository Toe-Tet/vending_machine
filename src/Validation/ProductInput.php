<?php

declare(strict_types=1);

namespace App\Validation;

/** A Product's editable fields after they passed ProductValidator. */
final readonly class ProductInput
{
    /** @param numeric-string $price normalised to exactly three decimals */
    public function __construct(
        public string $name,
        public string $price,
        public int $quantityAvailable,
    ) {
    }
}
