<?php

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function requireAdmin(): array
{
    if (empty($_SESSION['admin'])) {
        header('Location: /login.php');
        exit;
    }

    return $_SESSION['admin'];
}
