<?php

declare(strict_types=1);

session_start();

require_once __DIR__ . '/../Config/Database.php';
require_once __DIR__ . '/../Auth/AuthService.php';

use StokAman\Auth\AuthService;
use StokAman\Config\Database;

if (!empty($_SESSION['admin'])) {
    header('Location: /dashboard.php');
    exit;
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = (string) ($_POST['username'] ?? '');
    $password = (string) ($_POST['password'] ?? '');

    try {
        $pdo = Database::getConnection();
        $auth = new AuthService($pdo);
        $admin = $auth->attemptLogin($username, $password);

        if ($admin) {
            $_SESSION['admin'] = $admin;
            header('Location: /dashboard.php');
            exit;
        }
        $error = 'Username atau password salah.';
    } catch (Throwable $e) {
        $error = 'Terjadi kesalahan koneksi database: ' . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Admin - StokAman</title>
    <style>
        body { font-family: system-ui, sans-serif; background: #f4f6f9; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
        .card { background: #fff; padding: 2rem; border-radius: 10px; box-shadow: 0 4px 12px rgba(0,0,0,0.08); width: 100%; max-width: 380px; }
        h2 { margin-top: 0; color: #1e293b; }
        label { display: block; margin-top: 1rem; font-weight: 600; font-size: 0.9rem; }
        input { width: 100%; padding: 0.65rem; margin-top: 0.35rem; border: 1px solid #cbd5e1; border-radius: 6px; box-sizing: border-box; }
        button { width: 100%; margin-top: 1.5rem; padding: 0.75rem; background: #0f172a; color: #fff; border: none; border-radius: 6px; font-weight: 600; cursor: pointer; }
        .error { background: #fee2e2; color: #b91c1c; padding: 0.75rem; border-radius: 6px; margin-top: 1rem; font-size: 0.9rem; }
        .back { display: block; text-align: center; margin-top: 1rem; color: #475569; text-decoration: none; font-size: 0.85rem; }
    </style>
</head>
<body>
    <div class="card">
        <h2>Login Admin StokAman</h2>
        <p style="color:#64748b;font-size:0.9rem;">Masuk untuk mengelola stok masuk, scan barang keluar (FIFO), dan riwayat gudang.</p>
        <?php if ($error): ?>
            <div class="error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <form method="POST">
            <label for="username">Username</label>
            <input type="text" id="username" name="username" required autofocus placeholder="admin">

            <label for="password">Password</label>
            <input type="password" id="password" name="password" required placeholder="••••••••">

            <button type="submit">Masuk</button>
        </form>
        <a class="back" href="/index.php">← Kembali ke Landing Page</a>
    </div>
</body>
</html>
