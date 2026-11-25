<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Per-visitor key/value storage that survives between requests.
 */
interface Session
{
    public function get(string $key, mixed $default = null): mixed;

    public function set(string $key, mixed $value): void;

    public function remove(string $key): void;

    /** Issues a new session id, discarding the old one (prevents session fixation). */
    public function regenerate(): void;

    /** Empties the session and issues a new id. */
    public function clear(): void;

    public function id(): string;
}
