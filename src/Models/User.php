<?php

namespace App\Models;

use App\Core\Database;

class User
{
    private string $username;

    public function __construct()
    {
        $config = require dirname(__DIR__, 2) . '/config/app.php';
        $this->username = $config['auth']['username'];
    }

    public function authenticate(string $username, string $password): bool
    {
        if ($username !== $this->username) {
            return false;
        }

        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('SELECT password_hash FROM users WHERE username = ?');
        $stmt->execute([$username]);
        $row = $stmt->fetch();

        if (!$row) {
            return false;
        }

        return password_verify($password, $row['password_hash']);
    }

    public function updatePassword(string $currentPassword, string $newPassword): bool
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('SELECT password_hash FROM users WHERE username = ?');
        $stmt->execute([$this->username]);
        $row = $stmt->fetch();

        if (!$row) {
            return false;
        }

        if (!password_verify($currentPassword, $row['password_hash'])) {
            return false;
        }

        $newHash = password_hash($newPassword, PASSWORD_ARGON2ID);

        $update = $pdo->prepare('UPDATE users SET password_hash = ? WHERE username = ?');
        return $update->execute([$newHash, $this->username]);
    }

    public function getUsername(): string
    {
        return $this->username;
    }
}
