<?php

declare(strict_types=1);

use App\Core\Database;
use App\Models\Role;

/*
 * Demo data. Runs once (tracked by the migrator) and each insert is also
 * guarded, so re-running can never duplicate rows or resurrect an Archived Product.
 *
 * Demo accounts (also documented in the README and on the login page):
 *   admin@example.com / admin1234  (Admin)
 *   user@example.com  / user12345  (User)
 */
return static function (Database $db): void {
    $products = [
        ['Coke', '3.990', 10],
        ['Pepsi', '6.885', 10],
        ['Water', '0.500', 10],
    ];
    foreach ($products as [$name, $price, $quantity]) {
        $db->query(
            'INSERT INTO products (name, price, quantity_available)
             SELECT ?, ?, ? FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM products WHERE name = ?)',
            [$name, $price, $quantity, $name],
        );
    }

    $users = [
        ['Admin', 'admin@example.com', 'admin1234', Role::Admin],
        ['Demo User', 'user@example.com', 'user12345', Role::User],
    ];
    foreach ($users as [$name, $email, $password, $role]) {
        $db->query(
            'INSERT IGNORE INTO users (name, email, password_hash, role) VALUES (?, ?, ?, ?)',
            [$name, $email, password_hash($password, PASSWORD_DEFAULT), $role->value],
        );
    }
};
