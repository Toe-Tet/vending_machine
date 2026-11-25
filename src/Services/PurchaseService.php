<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Purchase;

/**
 * Buys Products: all-or-nothing, so a failed Purchase changes neither the
 * Quantity Available nor the Purchase history (spec §4 Purchase).
 */
interface PurchaseService
{
    /**
     * Takes $quantity units of the Product out of stock and records the Purchase
     * at the current Unit Price.
     *
     * @param positive-int $quantity
     * @throws ProductNotFoundException when the Product is missing or archived
     * @throws InsufficientStockException when $quantity exceeds the Quantity Available (incl. Sold Out)
     */
    public function purchase(int $userId, int $productId, int $quantity): Purchase;
}
