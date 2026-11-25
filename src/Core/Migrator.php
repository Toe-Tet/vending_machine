<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;

/**
 * Applies numbered migrations from a directory in filename order, recording
 * each in a `migrations` table so a second run is a no-op.
 * A migration is either a `.sql` file (one or more `;`-separated statements)
 * or a `.php` file returning `callable(Database): void`.
 */
final readonly class Migrator
{
    public function __construct(private Database $db, private string $directory)
    {
    }

    /**
     * @param callable(string): void $log
     * @return list<string> names of the migrations applied in this run
     */
    public function run(callable $log): array
    {
        $this->db->pdo()->exec(
            'CREATE TABLE IF NOT EXISTS migrations (
                name       VARCHAR(255) NOT NULL PRIMARY KEY,
                applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',
        );

        $applied = array_flip($this->db->query('SELECT name FROM migrations')->fetchAll(\PDO::FETCH_COLUMN));

        $files = glob($this->directory . '/*.{sql,php}', GLOB_BRACE) ?: [];
        sort($files);

        $ran = [];
        foreach ($files as $file) {
            $name = basename($file);
            if (isset($applied[$name])) {
                continue;
            }

            $log("Migrating {$name}");
            $this->apply($file);
            $this->db->query('INSERT INTO migrations (name) VALUES (?)', [$name]);
            $ran[] = $name;
        }

        $log($ran === [] ? 'Nothing to migrate.' : 'Applied ' . count($ran) . ' migration(s).');

        return $ran;
    }

    private function apply(string $file): void
    {
        if (str_ends_with($file, '.php')) {
            $migration = require $file;
            if (!is_callable($migration)) {
                throw new RuntimeException("{$file} must return a callable(Database): void");
            }
            $migration($this->db);

            return;
        }

        $sql = (string) file_get_contents($file);
        foreach (preg_split('/;\s*(?:\R|$)/', $sql) ?: [] as $statement) {
            if (trim(preg_replace('/^\s*--.*$/m', '', $statement) ?? '') !== '') {
                $this->db->pdo()->exec($statement);
            }
        }
    }
}
