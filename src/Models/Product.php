<?php

declare(strict_types=1);

namespace App\Models;

use DateTimeImmutable;

final readonly class Product
{
    /** @param numeric-string $price Unit Price, exact to 3 decimals (ADR-0002) */
    public function __construct(
        public int $id,
        public string $name,
        public string $price,
        public int $quantityAvailable,
        public ?DateTimeImmutable $archivedAt = null,
    ) {
    }

    /** @param array<string, mixed> $row */
    public static function fromRow(array $row): self
    {
        return new self(
            (int) $row['id'],
            (string) $row['name'],
            (string) $row['price'],
            (int) $row['quantity_available'],
            $row['archived_at'] === null ? null : new DateTimeImmutable((string) $row['archived_at']),
        );
    }

    /** The Unit Price with exactly three decimals, e.g. "2.500" (ADR-0002: never rounded). */
    public function displayPrice(): string
    {
        return bcadd($this->price, '0', 3);
    }

    public function isSoldOut(): bool
    {
        return $this->quantityAvailable === 0;
    }

    public function isArchived(): bool
    {
        return $this->archivedAt !== null;
    }
}
