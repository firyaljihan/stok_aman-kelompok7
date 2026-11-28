<?php

declare(strict_types=1);

require_once __DIR__ . '/../Config/Database.php';

use StokAman\Config\Database;

header('Content-Type: application/json');

try {
    $pdo = Database::getConnection();
    $pdo->query('SELECT 1');

    http_response_code(200);
    echo json_encode([
        'status' => 'ok',
        'database' => 'connected',
        'timestamp' => date(DATE_ATOM),
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'database' => 'disconnected',
        'message' => $e->getMessage(),
    ]);
}