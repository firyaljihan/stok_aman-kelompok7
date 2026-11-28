<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/Config/Database.php';
require_once __DIR__ . '/../src/Inventory/StockService.php';

use StokAman\Config\Database;
use StokAman\Inventory\StockService;

function assertTrue(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "[FAIL] {$message}\n");
        exit(1);
    }
    echo "[PASS] {$message}\n";
}

$pdo = Database::getConnection();
$stockService = new StockService($pdo);

// 1. Pastikan user admin seed ada di database
$stmtAdmin = $pdo->prepare('SELECT id, username FROM users WHERE username = :u LIMIT 1');
$stmtAdmin->execute(['u' => 'admin']);
$adminRow = $stmtAdmin->fetch();
assertTrue(is_array($adminRow) && $adminRow['username'] === 'admin', 'User seed admin tersedia di database');

// 2. Siapkan SKU khusus testing agar data utama tidak terganggu
$testSku = 'SKU-TEST-FIFO';
$pdo->prepare('DELETE FROM products WHERE sku = :sku')->execute(['sku' => $testSku]);
$pdo->prepare('INSERT INTO products (sku, name, min_stock) VALUES (:sku, :name, :min)')
    ->execute(['sku' => $testSku, 'name' => 'Produk Uji FIFO', 'min' => 3]);

// 3. Test Barang Masuk 2 Batch berbeda (Batch Lama & Batch Baru)
$in1 = $stockService->recordStockIn($testSku, 5, 'BATCH-OLD', 10000.0, 'Batch pertama');
assertTrue((int) $in1['stock_after'] === 5, 'Barang masuk Batch-1 menambah stok menjadi 5');

$in2 = $stockService->recordStockIn($testSku, 10, 'BATCH-NEW', 12000.0, 'Batch kedua');
assertTrue((int) $in2['stock_after'] === 15, 'Barang masuk Batch-2 menambah stok menjadi 15');

// 4. Test Barang Keluar 7 unit -> harus menghabiskan 5 dari BATCH-OLD dulu, lalu 2 dari BATCH-NEW (FIFO)
$out1 = $stockService->recordStockOut($testSku, 7, 'Uji keluar FIFO');
assertTrue((int) $out1['stock_after'] === 8, 'Barang keluar 7 unit menyisakan stok 8');
assertTrue(
    count($out1['fifo_batches']) === 2
    && $out1['fifo_batches'][0]['batch_code'] === 'BATCH-OLD'
    && (int) $out1['fifo_batches'][0]['qty_taken'] === 5
    && $out1['fifo_batches'][1]['batch_code'] === 'BATCH-NEW'
    && (int) $out1['fifo_batches'][1]['qty_taken'] === 2,
    'Alokasi FIFO mengambil stok dari batch terlama (BATCH-OLD) lebih dulu'
);

// 5. Test Proteksi Stok Tidak Boleh Minus (sisa 8, coba keluarkan 20)
$blocked = false;
try {
    $stockService->recordStockOut($testSku, 20, 'Uji melebihi stok');
} catch (RuntimeException $e) {
    $blocked = true;
}
assertTrue($blocked, 'Transaksi keluar melebihi sisa stok ditolak (anti-minus)');

// Bersihkan data uji
$pdo->prepare('DELETE FROM products WHERE sku = :sku')->execute(['sku' => $testSku]);
echo "\nSemua automated test StokAman berhasil dijalankan!\n";