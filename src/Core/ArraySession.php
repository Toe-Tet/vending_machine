<?php

declare(strict_types=1);

namespace App\Core;

/** In-memory Session for unit tests and CLI scripts. */
final class ArraySession implements Session
{
    private string $id;

    /** @param array<string, mixed> $data */
    public function __construct(private array $data = [])
    {
        $this->id = bin2hex(random_bytes(16));
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->data[$key] ?? $default;
    }

    public function set(string $key, mixed $value): void
    {
        $this->data[$key] = $value;
    }

    public function remove(string $key): void
    {
        unset($this->data[$key]);
    }

    public function regenerate(): void
    {
        $this->id = bin2hex(random_bytes(16));
    }

    public function clear(): void
    {
        $this->data = [];
        $this->regenerate();
    }

    public function id(): string
    {
        return $this->id;
    }
}
