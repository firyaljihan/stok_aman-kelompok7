<?php

declare(strict_types=1);

session_start();

require_once __DIR__ . '/../Config/Database.php';

use StokAman\Config\Database;

$products = [];
$dbError = null;

try {
    $pdo = Database::getConnection();
    $stmt = $pdo->query(
        'SELECT p.sku, p.name, p.min_stock,
                COALESCE(SUM(sb.remaining_qty), 0) AS current_stock
         FROM products p
         LEFT JOIN stock_batches sb ON sb.product_id = p.id
         GROUP BY p.id, p.sku, p.name, p.min_stock
         ORDER BY p.sku ASC'
    );
    $products = $stmt->fetchAll();
} catch (Throwable $e) {
    $dbError = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>StokAman — Inventori Gudang UMKM</title>
    <style>
        body { font-family: system-ui, sans-serif; margin: 0; background: #f8fafc; color: #0f172a; }
        header { background: #0f172a; color: #fff; padding: 1rem 2rem; display: flex; justify-content: space-between; align-items: center; }
        header a { color: #fff; text-decoration: none; background: #2563eb; padding: 0.5rem 1rem; border-radius: 6px; font-weight: 600; font-size: 0.9rem; }
        .hero { max-width: 960px; margin: 2.5rem auto 1.5rem; padding: 0 1.5rem; }
        .hero h1 { margin-bottom: 0.5rem; font-size: 2rem; }
        .hero p { color: #475569; max-width: 650px; line-height: 1.6; }
        .container { max-width: 960px; margin: 1.5rem auto 3rem; padding: 0 1.5rem; }
        .card { background: #fff; border-radius: 10px; padding: 1.5rem; box-shadow: 0 2px 8px rgba(0,0,0,0.06); }
        table { width: 100%; border-collapse: collapse; margin-top: 1rem; }
        th, td { text-align: left; padding: 0.75rem; border-bottom: 1px solid #e2e8f0; }
        th { background: #f1f5f9; font-size: 0.85rem; text-transform: uppercase; color: #475569; }
        .badge-ok { background: #dcfce7; color: #166534; padding: 0.25rem 0.6rem; border-radius: 999px; font-size: 0.8rem; font-weight: 600; }
        .badge-low { background: #fee2e2; color: #991b1b; padding: 0.25rem 0.6rem; border-radius: 999px; font-size: 0.8rem; font-weight: 600; }
    </style>
</head>
<body>
    <header>
        <strong>StokAman</strong>
        <?php if (!empty($_SESSION['admin'])): ?>
            <a href="/dashboard.php">Buka Dashboard Stok</a>
        <?php else: ?>
            <a href="/login.php">Login Admin</a>
        <?php endif; ?>
    </header>

    <section class="hero">
        <h1>Kontrol Stok Gudang UMKM Tanpa Selisih</h1>
        <p>Pencatatan stok masuk berbasis batch dan pengeluaran barang cepat via scan kode SKU dengan metode <strong>FIFO (First-In, First-Out)</strong> otomatis.</p>
    </section>

    <div class="container">
        <div class="card">
            <h2 style="margin-top:0;">Ringkasan Stok Terkini</h2>
            <?php if ($dbError): ?>
                <p style="color:#b91c1c;">Gagal memuat data stok: <?= htmlspecialchars($dbError) ?></p>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Kode SKU</th>
                            <th>Nama Produk</th>
                            <th>Sisa Stok</th>
                            <th>Batas Minimum</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($products as $p): ?>
                            <?php
                            $stock = (int) $p['current_stock'];
                            $min = (int) $p['min_stock'];
                            $isLow = $stock <= $min;
                            ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($p['sku']) ?></strong></td>
                                <td><?= htmlspecialchars($p['name']) ?></td>
                                <td><?= $stock ?></td>
                                <td><?= $min ?></td>
                                <td>
                                    <?php if ($isLow): ?>
                                        <span class="badge-low">Stok Menipis</span>
                                    <?php else: ?>
                                        <span class="badge-ok">Aman</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($products)): ?>
                            <tr><td colspan="5">Belum ada data produk.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
