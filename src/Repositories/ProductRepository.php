<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Product;
use App\Models\ProductSort;
use App\Models\SortDirection;

/**
 * Products for sale. Archived Products are invisible to every read: they are
 * excluded from lists and find() returns null for them (ADR-0003).
 */
interface ProductRepository
{
    /** Number of active Products. */
    public function count(): int;

    /**
     * One page of active Products, ordered by $sort then id (so ties keep a stable order).
     *
     * @return list<Product>
     */
    public function page(ProductSort $sort, SortDirection $dir, int $limit, int $offset): array;

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
