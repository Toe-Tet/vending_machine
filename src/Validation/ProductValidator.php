<?php

declare(strict_types=1);

namespace App\Validation;

/**
 * Server-side Product rules, shared by the web forms and the API (spec §4):
 * name 1–100 chars after trimming; price > 0, ≤ 999,999.999, ≤ 3 decimals;
 * quantity_available an integer 0–1,000,000.
 *
 * public/js/app.js (productRules) repeats these rules and messages for inline
 * feedback before submit; change both together.
 */
final readonly class ProductValidator
{
    public const int NAME_MAX = 100;
    public const string PRICE_MAX = '999999.999';
    public const int QUANTITY_MAX = 1_000_000;

    /** @param array<string, mixed> $input */
    public function validate(array $input): ProductValidation
    {
        $name = trim(self::string($input['name'] ?? null));
        $price = trim(self::string($input['price'] ?? null));
        $quantity = trim(self::string($input['quantity_available'] ?? null));
        $old = ['name' => $name, 'price' => $price, 'quantity_available' => $quantity];

        $errors = array_filter([
            'name' => self::nameError($name),
            'price' => self::priceError($price),
            'quantity_available' => self::quantityError($quantity),
        ]);
        if ($errors !== []) {
            return new ProductValidation(null, $errors, $old);
        }

        return new ProductValidation(new ProductInput($name, bcadd($price, '0', 3), (int) $quantity), [], $old);
    }

    private static function nameError(string $name): ?string
    {
        return match (true) {
            $name === '' => 'Enter a name.',
            mb_strlen($name) > self::NAME_MAX => 'Name must be ' . self::NAME_MAX . ' characters or fewer.',
            default => null,
        };
    }

    /** Compared with bcmath on the exact string: floats can't hold 999999.999 or 6.885 exactly (ADR-0002). */
    private static function priceError(string $price): ?string
    {
        if ($price === '') {
            return 'Enter a price.';
        }
        // Plain decimals as type="number" submits them: no exponent, "+", trailing point or separators.
        // A leading "-" still parses so negatives get the "greater than 0" message.
        if (!preg_match('/^-?(\d+(\.\d+)?|\.\d+)$/', $price)) {
            return 'Price must be a number.';
        }

        // Trailing zeros don't count: 1.5000 is 1.500.
        $fraction = rtrim(explode('.', $price . '.')[1], '0');

        return match (true) {
            // Compare at full precision so 0.0001 is "too many decimals", not "not positive".
            bccomp($price, '0', strlen($fraction)) <= 0 => 'Price must be greater than 0.',
            strlen($fraction) > 3 => 'Price can have at most 3 decimal places.',
            bccomp($price, self::PRICE_MAX, 3) > 0 => 'Price must be 999,999.999 or less.',
            default => null,
        };
    }

    private static function quantityError(string $quantity): ?string
    {
        if ($quantity === '') {
            return 'Enter a quantity.';
        }
        if (!preg_match('/^-?\d+$/', $quantity)) {
            return 'Quantity must be a whole number.';
        }

        // bccomp, not (int): an over-long string would overflow to PHP_INT_MAX.
        return match (true) {
            bccomp($quantity, '0') < 0 => 'Quantity cannot be negative.',
            bccomp($quantity, (string) self::QUANTITY_MAX) > 0 => 'Quantity must be 1,000,000 or less.',
            default => null,
        };
    }

    /** Form fields arrive as strings, but a crafted request can send arrays (name[]=x); JSON can send numbers. */
    private static function string(mixed $value): string
    {
        return is_string($value) || is_int($value) || is_float($value) ? (string) $value : '';
    }
}
