<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use App\Models\Product;
use RuntimeException;

final readonly class PdoProductRepository implements ProductRepository
{
    private const string COLUMNS = 'id, name, price, quantity_available, archived_at';

    public function __construct(private Database $db)
    {
    }

    public function all(): array
    {
        $rows = $this->db->query(
            'SELECT ' . self::COLUMNS . ' FROM products WHERE archived_at IS NULL ORDER BY name, id',
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
