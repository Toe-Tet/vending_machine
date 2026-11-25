<?php

declare(strict_types=1);

namespace App\Core;

use Attribute;

/**
 * Declares an HTTP route on a controller method, e.g. #[Route('GET', '/products/{id}')].
 * Routes are discovered by the Router via reflection.
 */
#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final readonly class Route
{
    public string $method;

    public function __construct(string $method, public string $path)
    {
        $this->method = strtoupper($method);
    }
}
