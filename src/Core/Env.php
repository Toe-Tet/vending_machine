<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Minimal `.env` loader. Real environment variables always win over the file,
 * and a missing file is skipped (normal in production, where Render sets env vars).
 */
final class Env
{
    public static function load(string $path): void
    {
        if (!is_file($path) || !is_readable($path)) {
            return;
        }

        foreach (file($path, FILE_IGNORE_NEW_LINES) ?: [] as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
                continue;
            }

            [$key, $value] = array_map(trim(...), explode('=', $line, 2));
            if ($key === '' || getenv($key) !== false) {
                continue;
            }

            $value = self::unquote($value);
            putenv("{$key}={$value}");
            $_ENV[$key] = $value;
        }
    }

    public static function get(string $key, ?string $default = null): ?string
    {
        $value = getenv($key);

        return $value === false ? $default : $value;
    }

    private static function unquote(string $value): string
    {
        $length = strlen($value);
        if ($length >= 2) {
            $first = $value[0];
            if (($first === '"' || $first === "'") && $value[$length - 1] === $first) {
                return substr($value, 1, -1);
            }
        }

        return $value;
    }
}
