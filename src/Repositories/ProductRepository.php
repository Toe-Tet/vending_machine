<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Product;

/**
 * Products for sale. Archived Products are invisible to every read: they are
 * excluded from lists and find() returns null for them (ADR-0003).
 */
interface ProductRepository
{
    /** @return list<Product> active Products ordered by name */
    public function all(): array;

    public function find(int $id): ?Product;

    /** @param numeric-string $price */
    public function create(string $name, string $price, int $quantityAvailable): Product;

    /**
     * @param numeric-string $price
     * @return bool false when the Product doesn't exist or is archived
     */
    public function update(int $id, string $name, string $price, int $quantityAvailable): bool;

    /** @return bool false when the Product doesn't exist or is already archived */
    public function archive(int $id): bool;
}
