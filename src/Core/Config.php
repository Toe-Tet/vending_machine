<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Application settings read from the environment. Construction fails fast on
 * configuration that must not reach production (see spec §2 Configuration).
 */
final readonly class Config
{
    public const int MIN_PRODUCTION_SECRET_LENGTH = 32;

    public function __construct(
        public string $appEnv,
        public string $appUrl,
        public string $dbHost,
        public int $dbPort,
        public string $dbName,
        public string $dbUser,
        public string $dbPassword,
        public ?string $dbSslCa,
        public string $jwtSecret,
    ) {
        if (!in_array($appEnv, ['local', 'testing', 'production'], true)) {
            throw new ConfigurationException("APP_ENV must be local, testing or production; got '{$appEnv}'");
        }

        if ($jwtSecret === '') {
            throw new ConfigurationException('JWT_SECRET must be set');
        }

        if ($appEnv === 'production' && strlen($jwtSecret) < self::MIN_PRODUCTION_SECRET_LENGTH) {
            throw new ConfigurationException(
                'JWT_SECRET must be at least ' . self::MIN_PRODUCTION_SECRET_LENGTH . ' characters in production',
            );
        }
    }

    public static function fromEnvironment(): self
    {
        $sslCa = Env::get('DB_SSL_CA', '');

        return new self(
            appEnv: Env::get('APP_ENV', 'production'),
            appUrl: rtrim(Env::get('APP_URL', 'http://localhost:8080'), '/'),
            dbHost: Env::get('DB_HOST', '127.0.0.1'),
            dbPort: (int) Env::get('DB_PORT', '3306'),
            dbName: Env::get('DB_NAME', 'vending'),
            dbUser: Env::get('DB_USER', 'vending'),
            dbPassword: Env::get('DB_PASSWORD', ''),
            dbSslCa: $sslCa === '' ? null : $sslCa,
            jwtSecret: Env::get('JWT_SECRET', ''),
        );
    }

    public function isProduction(): bool
    {
        return $this->appEnv === 'production';
    }

    /** Session cookies are always Secure in production, otherwise only over HTTPS. */
    public function secureCookies(bool $requestIsHttps): bool
    {
        return $this->isProduction() || $requestIsHttps;
    }
}
