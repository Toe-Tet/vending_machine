<?php

declare(strict_types=1);

namespace App\Core;

use PDO;
use Pdo\Mysql;
use PDOStatement;
use Throwable;

/**
 * The single PDO connection. Connects lazily, throws on every error, uses real
 * (non-emulated) prepared statements, and talks TLS when a CA is configured.
 */
final class Database
{
    private ?PDO $pdo = null;

    /** @param array<int, mixed> $options */
    public function __construct(
        private readonly string $dsn,
        private readonly string $user,
        private readonly string $password,
        private readonly array $options = [],
    ) {
    }

    public static function fromConfig(Config $config): self
    {
        $options = [];
        if ($config->dbSslCa !== null) {
            $options[Mysql::ATTR_SSL_CA] = self::caFile($config->dbSslCa);
            $options[Mysql::ATTR_SSL_VERIFY_SERVER_CERT] = true;
        }

        return new self(
            sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $config->dbHost, $config->dbPort, $config->dbName),
            $config->dbUser,
            $config->dbPassword,
            $options,
        );
    }

    public function pdo(): PDO
    {
        return $this->pdo ??= new PDO($this->dsn, $this->user, $this->password, $this->options + [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    }

    /** @param array<int|string, mixed> $params */
    public function query(string $sql, array $params = []): PDOStatement
    {
        $statement = $this->pdo()->prepare($sql);
        $statement->execute($params);

        return $statement;
    }

    /**
     * Runs $work inside a database transaction: commits on return, rolls back
     * and rethrows on any exception.
     *
     * @template T
     * @param callable(self): T $work
     * @return T
     */
    public function transaction(callable $work): mixed
    {
        $pdo = $this->pdo();
        $pdo->beginTransaction();
        try {
            $result = $work($this);
            $pdo->commit();

            return $result;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * DB_SSL_CA may be a path to the CA file or the PEM contents themselves
     * (convenient for hosted env-var dashboards); the latter is written to a temp file.
     */
    private static function caFile(string $value): string
    {
        if (!str_starts_with(ltrim($value), '-----BEGIN')) {
            return $value;
        }

        $path = sys_get_temp_dir() . '/db-ca-' . hash('sha256', $value) . '.pem';
        if (!is_file($path)) {
            file_put_contents($path, $value, LOCK_EX);
        }

        return $path;
    }
}
