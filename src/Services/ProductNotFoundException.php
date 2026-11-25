<?php

declare(strict_types=1);

namespace App\Services;

use RuntimeException;

/** The Product to buy doesn't exist or is archived. */
final class ProductNotFoundException extends RuntimeException
{
    public function __construct(public readonly int $productId)
    {
        parent::__construct("Product {$productId} not found");
    }
}
