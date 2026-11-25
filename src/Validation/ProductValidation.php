<?php

declare(strict_types=1);

namespace App\Validation;

/**
 * Outcome of validating a Product form or API body: either a clean ProductInput,
 * or per-field error messages. $old holds the submitted values (as strings) for
 * re-rendering the form either way.
 */
final readonly class ProductValidation
{
    /**
     * @param array<string, string> $errors field => message, empty when valid
     * @param array<string, string> $old field => submitted value
     */
    public function __construct(
        public ?ProductInput $input,
        public array $errors,
        public array $old,
    ) {
    }
}
