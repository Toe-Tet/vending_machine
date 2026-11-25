<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;

/**
 * PHP's native session, started on first use (in practice every HTML page,
 * since the layout needs the CSRF token and who is signed in). Cookie flags come from the constructor:
 * HttpOnly and SameSite=Lax always, Secure when asked (spec §4 Users & auth).
 */
final class NativeSession implements Session
{
    private bool $started = false;

    public function __construct(private readonly bool $secureCookie)
    {
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $this->start();

        return $_SESSION[$key] ?? $default;
    }

    public function set(string $key, mixed $value): void
    {
        $this->start();
        $_SESSION[$key] = $value;
    }

    public function remove(string $key): void
    {
        $this->start();
        unset($_SESSION[$key]);
    }

    public function regenerate(): void
    {
        $this->start();
        session_regenerate_id(true);
    }

    public function clear(): void
    {
        $this->start();
        $_SESSION = [];
        session_regenerate_id(true);
    }

    public function id(): string
    {
        $this->start();

        return (string) session_id();
    }

    private function start(): void
    {
        if ($this->started) {
            return;
        }

        $started = session_start([
            'name' => 'vm_session',
            'cookie_lifetime' => 0,
            'cookie_path' => '/',
            'cookie_secure' => $this->secureCookie,
            'cookie_httponly' => true,
            'cookie_samesite' => 'Lax',
            'use_strict_mode' => true,
            'use_only_cookies' => true,
        ]);
        if (!$started) {
            throw new RuntimeException('Could not start the session');
        }
        $this->started = true;
    }
}
