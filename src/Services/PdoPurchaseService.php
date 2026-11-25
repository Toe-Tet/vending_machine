<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Models\Purchase;
use App\Repositories\PurchaseRepository;

/**
 * spec §4 Purchase, in one database transaction: lock the Product row, check
 * stock, decrement, record the Purchase at the locked Unit Price, commit.
 * The FOR UPDATE lock makes concurrent buyers of the same Product queue, so
 * two buyers of the last unit can't both succeed. Any exception rolls back.
 */
final readonly class PdoPurchaseService implements PurchaseService
{
    public function __construct(private Database $db, private PurchaseRepository $purchases)
    {
    }

    public function purchase(int $userId, int $productId, int $quantity): Purchase
    {
        return $this->db->transaction(function (Database $db) use ($userId, $productId, $quantity): Purchase {
            $row = $db->query(
                'SELECT price, quantity_available FROM products WHERE id = ? AND archived_at IS NULL FOR UPDATE',
                [$productId],
            )->fetch();
            if ($row === false) {
                throw new ProductNotFoundException($productId);
            }

            $available = (int) $row['quantity_available'];
            if ($quantity > $available) {
                throw new InsufficientStockException($available);
            }

            $db->query(
                'UPDATE products SET quantity_available = quantity_available - ? WHERE id = ?',
                [$quantity, $productId],
            );

            /** @var numeric-string $price */
            $price = (string) $row['price'];

            return $this->purchases->create($userId, $productId, $quantity, $price);
        });
    }
}
