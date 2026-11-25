<?php

declare(strict_types=1);

namespace App\Models;

use DateTimeImmutable;

/** A row of the `transactions` table (ADR-0003: we say Purchase in code). */
final readonly class Purchase
{
    /**
     * @param numeric-string $unitPrice snapshot of the Product's price at purchase time
     * @param numeric-string $total Purchase Total, unitPrice × quantity exactly
     */
    public function __construct(
        public int $id,
        public int $userId,
        public int $productId,
        public int $quantity,
        public string $unitPrice,
        public string $total,
        public DateTimeImmutable $createdAt,
    ) {
    }

    /** @param array<string, mixed> $row */
    public static function fromRow(array $row): self
    {
        return new self(
            (int) $row['id'],
            (int) $row['user_id'],
            (int) $row['product_id'],
            (int) $row['quantity'],
            (string) $row['unit_price'],
            (string) $row['total'],
            new DateTimeImmutable((string) $row['created_at']),
        );
    }
}
