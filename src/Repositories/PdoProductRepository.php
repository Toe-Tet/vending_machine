<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use App\Models\Product;
use App\Models\ProductSort;
use App\Models\SortDirection;
use RuntimeException;

final readonly class PdoProductRepository implements ProductRepository
{
    private const string COLUMNS = 'id, name, price, quantity_available, archived_at';

    public function __construct(private Database $db)
    {
    }

    public function count(): int
    {
        return (int) $this->db->query('SELECT COUNT(*) FROM products WHERE archived_at IS NULL')->fetchColumn();
    }

    public function page(ProductSort $sort, SortDirection $dir, int $limit, int $offset): array
    {
        // Every interpolated piece is an enum-derived keyword or an int, never raw input.
        $column = match ($sort) {
            ProductSort::Name => 'name',
            ProductSort::Price => 'price',
            ProductSort::Quantity => 'quantity_available',
        };
        $direction = $dir === SortDirection::Desc ? 'DESC' : 'ASC';

        $rows = $this->db->query(
            'SELECT ' . self::COLUMNS . " FROM products WHERE archived_at IS NULL
             ORDER BY {$column} {$direction}, id {$direction} LIMIT {$limit} OFFSET {$offset}",
        )->fetchAll();

        return array_map(Product::fromRow(...), $rows);
    }

    public function find(int $id): ?Product
    {
        $row = $this->db->query(
            'SELECT ' . self::COLUMNS . ' FROM products WHERE id = ? AND archived_at IS NULL',
            [$id],
        )->fetch();

        return $row === false ? null : Product::fromRow($row);
    }

    public function create(string $name, string $price, int $quantityAvailable): Product
    {
        $this->db->query(
            'INSERT INTO products (name, price, quantity_available) VALUES (?, ?, ?)',
            [$name, $price, $quantityAvailable],
        );

        return $this->find((int) $this->db->pdo()->lastInsertId())
            ?? throw new RuntimeException('Created product could not be read back');
    }

    public function update(int $id, string $name, string $price, int $quantityAvailable): bool
    {
        // Check existence first: MySQL reports 0 affected rows when the values are unchanged.
        if ($this->find($id) === null) {
            return false;
        }

        $this->db->query(
            'UPDATE products SET name = ?, price = ?, quantity_available = ? WHERE id = ? AND archived_at IS NULL',
            [$name, $price, $quantityAvailable, $id],
        );

        return true;
    }

    public function archive(int $id): bool
    {
        return $this->db->query(
            'UPDATE products SET archived_at = CURRENT_TIMESTAMP WHERE id = ? AND archived_at IS NULL',
            [$id],
        )->rowCount() === 1;
    }
}
