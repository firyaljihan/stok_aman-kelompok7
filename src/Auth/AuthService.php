<?php

declare(strict_types=1);

namespace StokAman\Auth;

use PDO;

class AuthService
{
    public function __construct(private PDO $pdo)
    {
    }

    public function attemptLogin(string $username, string $password): ?array
    {
        $username = trim($username);
        if ($username === '' || $password === '') {
            return null;
        }

        $stmt = $this->pdo->prepare(
            'SELECT id, username, password_hash FROM users WHERE username = :username LIMIT 1'
        );
        $stmt->execute(['username' => $username]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, (string) $user['password_hash'])) {
            return null;
        }

        return [
            'id' => (int) $user['id'],
            'username' => (string) $user['username'],
        ];
    }
}