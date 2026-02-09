<?php

namespace App\Commands;

use App\Core\Database;

class PasswordResetCommand extends Command
{
    protected string $name = 'password:reset';
    protected string $description = "Reset a user's password";

    public function execute(array $args): int
    {
        if (count($args) < 2) {
            $this->error('Usage: php cli password:reset <username> <password>');
            return 1;
        }

        $username = $args[0];
        $password = $args[1];

        $pdo = Database::getConnection();

        // Look up user by username
        $stmt = $pdo->prepare('SELECT id FROM users WHERE username = ?');
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if (!$user) {
            $this->error("User not found: {$username}");
            return 1;
        }

        // Hash password and update
        $hash = password_hash($password, PASSWORD_ARGON2ID);
        $stmt = $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
        $stmt->execute([$hash, $user['id']]);

        $this->success("Password reset successfully for user: {$username}");
        return 0;
    }
}
