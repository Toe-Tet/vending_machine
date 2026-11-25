<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use App\Models\Role;
use App\Models\User;
use PDOException;
use RuntimeException;

final readonly class PdoUserRepository implements UserRepository
{
    private const string COLUMNS = 'id, name, email, password_hash, role';

    public function __construct(private Database $db)
    {
    }

    public function find(int $id): ?User
    {
        $row = $this->db->query('SELECT ' . self::COLUMNS . ' FROM users WHERE id = ?', [$id])->fetch();

        return $row === false ? null : User::fromRow($row);
    }

    public function findByEmail(string $email): ?User
    {
        $row = $this->db->query('SELECT ' . self::COLUMNS . ' FROM users WHERE email = ?', [$email])->fetch();

        return $row === false ? null : User::fromRow($row);
    }

    public function create(string $name, string $email, string $passwordHash, Role $role = Role::User): User
    {
        try {
            $this->db->query(
                'INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, ?)',
                [$name, $email, $passwordHash, $role->value],
            );
        } catch (PDOException $e) {
            // 1062 = duplicate key; the only unique key besides the id is the email.
            if (($e->errorInfo[1] ?? null) === 1062) {
                throw new DuplicateEmailException("{$email} is already registered", previous: $e);
            }
            throw $e;
        }

        return $this->find((int) $this->db->pdo()->lastInsertId())
            ?? throw new RuntimeException('Created user could not be read back');
    }

    public function update(int $id, string $name, string $email): bool
    {
        if ($this->find($id) === null) {
            return false;
        }

        $this->db->query('UPDATE users SET name = ?, email = ? WHERE id = ?', [$name, $email, $id]);

        return true;
    }

    public function updatePassword(int $id, string $passwordHash): bool
    {
        return $this->db->query('UPDATE users SET password_hash = ? WHERE id = ?', [$passwordHash, $id])
            ->rowCount() === 1;
    }

    public function delete(int $id): bool
    {
        return $this->db->query('DELETE FROM users WHERE id = ?', [$id])->rowCount() === 1;
    }
}
