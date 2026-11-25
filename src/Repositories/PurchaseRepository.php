<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use App\Models\Purchase;
use RuntimeException;

/**
 * Purchases, stored in the `transactions` table. Purchases are an immutable
 * history: there is no update or delete.
 */
final readonly class PurchaseRepository
{
    private const string COLUMNS = 'id, user_id, product_id, quantity, unit_price, total, created_at';

    public function __construct(private Database $db)
    {
    }

    /**
     * Records a Purchase; the Purchase Total is computed here, exactly (ADR-0002).
     *
     * @param numeric-string $unitPrice the Product's Unit Price at the moment of purchase
     */
    public function create(int $userId, int $productId, int $quantity, string $unitPrice): Purchase
    {
        $total = bcmul($unitPrice, (string) $quantity, 3);
        $this->db->query(
            'INSERT INTO transactions (user_id, product_id, quantity, unit_price, total) VALUES (?, ?, ?, ?, ?)',
            [$userId, $productId, $quantity, $unitPrice, $total],
        );

        return $this->find((int) $this->db->pdo()->lastInsertId())
            ?? throw new RuntimeException('Created purchase could not be read back');
    }

    public function find(int $id): ?Purchase
    {
        $row = $this->db->query('SELECT ' . self::COLUMNS . ' FROM transactions WHERE id = ?', [$id])->fetch();

        return $row === false ? null : Purchase::fromRow($row);
    }
}
