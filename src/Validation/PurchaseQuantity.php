<?php

declare(strict_types=1);

namespace App\Validation;

/**
 * The quantity a Purchase asks for, shared by the web form and the API
 * (spec §4): a whole number ≥ 1. Whether that many are in stock is decided
 * under the row lock by PurchaseService, not here.
 *
 * public/js/app.js (purchase form) repeats these messages; change both together.
 */
final readonly class PurchaseQuantity
{
    /** @param mixed $raw as submitted: a form string, a JSON number, or junk */
    public static function error(mixed $raw): ?string
    {
        $value = self::normalise($raw);

        return match (true) {
            $value === '' => 'Enter a quantity.',
            !preg_match('/^-?\d+$/', $value) => 'Quantity must be a whole number.',
            // bccomp, not (int): an over-long string would overflow.
            bccomp($value, '1') < 0 => 'Quantity must be at least 1.',
            default => null,
        };
    }

    /**
     * The submitted value as a trimmed string, for comparing and re-display: form strings and JSON
     * numbers pass through (a whole float like 2.0 becomes "2"); arrays, booleans and null become ''.
     */
    public static function normalise(mixed $raw): string
    {
        return is_string($raw) || is_int($raw) || is_float($raw) ? trim((string) $raw) : '';
    }
}
