<?php

declare(strict_types=1);

require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/../Config/Database.php';
require_once __DIR__ . '/../Inventory/StockService.php';

use StokAman\Config\Database;
use StokAman\Inventory\StockService;

$admin = requireAdmin();
$pdo = Database::getConnection();
$stockService = new StockService($pdo);

$flashSuccess = null;
$flashError = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');

    try {
        if ($action === 'stock_in') {
            $sku = (string) ($_POST['sku'] ?? '');
            $batchCode = (string) ($_POST['batch_code'] ?? '');
            $qty = (int) ($_POST['qty'] ?? 0);
            $note = trim((string) ($_POST['note'] ?? '')) ?: null;

            $res = $stockService->recordStockIn($sku, $qty, $batchCode, 0.0, $note);
            $flashSuccess = "Barang masuk berhasil: {$res['product_name']} ({$res['sku']}) +{$res['qty_in']} | Sisa stok: {$res['stock_after']}";
        } elseif ($action === 'stock_out') {
            $sku = (string) ($_POST['sku'] ?? '');
            $qty = (int) ($_POST['qty'] ?? 1);
            $note = trim((string) ($_POST['note'] ?? '')) ?: null;

            $res = $stockService->recordStockOut($sku, $qty, $note);
            $batchInfo = implode(', ', array_map(
                fn($b) => "{$b['batch_code']} (-{$b['qty_taken']})",
                $res['fifo_batches']
            ));
            $flashSuccess = "Barang keluar (FIFO) berhasil: {$res['product_name']} ({$res['sku']}) -{$res['qty_out']} [Batch: {$batchInfo}] | Sisa stok: {$res['stock_after']}";
        } elseif ($action === 'add_product') {
            $sku = strtoupper(trim((string) ($_POST['sku'] ?? '')));
            $name = trim((string) ($_POST['name'] ?? ''));
            $categoryId = (int) ($_POST['category_id'] ?? 0) ?: null;
            $supplierId = (int) ($_POST['supplier_id'] ?? 0) ?: null;
            $minStock = max(0, (int) ($_POST['min_stock'] ?? 5));

            if ($sku === '' || $name === '') {
                throw new InvalidArgumentException('SKU dan nama produk wajib diisi.');
            }

            $check = $pdo->prepare('SELECT id FROM products WHERE sku = :sku LIMIT 1');
            $check->execute(['sku' => $sku]);
            if ($check->fetch()) {
                throw new RuntimeException("SKU '{$sku}' sudah terdaftar. Gunakan SKU unik.");
            }

            $ins = $pdo->prepare(
                'INSERT INTO products (sku, name, category_id, supplier_id, min_stock)
                 VALUES (:sku, :name, :category_id, :supplier_id, :min_stock)'
            );
            $ins->execute([
                'sku' => $sku,
                'name' => $name,
                'category_id' => $categoryId,
                'supplier_id' => $supplierId,
                'min_stock' => $minStock,
            ]);
            $flashSuccess = "Produk baru '{$name}' ({$sku}) berhasil ditambahkan.";
        } elseif ($action === 'update_product') {
            $id = (int) ($_POST['id'] ?? 0);
            $name = trim((string) ($_POST['name'] ?? ''));
            $categoryId = (int) ($_POST['category_id'] ?? 0) ?: null;
            $supplierId = (int) ($_POST['supplier_id'] ?? 0) ?: null;
            $minStock = (int) ($_POST['min_stock'] ?? -1);

            if ($id <= 0 || $name === '') {
                throw new InvalidArgumentException('ID dan nama produk wajib diisi.');
            }
            if ($minStock < 0) {
                throw new InvalidArgumentException('Stok minimum tidak boleh negatif.');
            }

            $upd = $pdo->prepare(
                'UPDATE products
                 SET name = :name, category_id = :category_id, supplier_id = :supplier_id, min_stock = :min_stock
                 WHERE id = :id'
            );
            $upd->execute([
                'name' => $name,
                'category_id' => $categoryId,
                'supplier_id' => $supplierId,
                'min_stock' => $minStock,
                'id' => $id,
            ]);
            $flashSuccess = "Data produk '{$name}' berhasil diperbarui.";
        }
    } catch (Throwable $e) {
        $flashError = $e->getMessage();
    }
}

$categories = $pdo->query('SELECT id, name FROM categories ORDER BY name ASC')->fetchAll();
$suppliers = $pdo->query('SELECT id, name, phone FROM suppliers ORDER BY name ASC')->fetchAll();

$products = $pdo->query(
    'SELECT p.id, p.sku, p.name, p.category_id, p.supplier_id, p.min_stock,
            c.name AS category_name,
            s.name AS supplier_name,
            s.phone AS supplier_phone,
            COALESCE(SUM(sb.remaining_qty), 0) AS current_stock
     FROM products p
     LEFT JOIN categories c ON c.id = p.category_id
     LEFT JOIN suppliers s ON s.id = p.supplier_id
     LEFT JOIN stock_batches sb ON sb.product_id = p.id
     GROUP BY p.id, p.sku, p.name, p.category_id, p.supplier_id, p.min_stock, c.name, s.name, s.phone
     ORDER BY p.sku ASC'
)->fetchAll();

$movements = $pdo->query(
    'SELECT sm.created_at, p.sku, p.name, sb.batch_code, sm.movement_type,
            sm.qty, sm.stock_before, sm.stock_after, sm.note
     FROM stock_movements sm
     JOIN products p ON p.id = sm.product_id
     LEFT JOIN stock_batches sb ON sb.id = sm.batch_id
     ORDER BY sm.id DESC
     LIMIT 25'
)->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Stok - StokAman</title>
    <style>
        body { font-family: system-ui, sans-serif; margin: 0; background: #f1f5f9; color: #0f172a; }
        header { background: #0f172a; color: #fff; padding: 1rem 2rem; display: flex; justify-content: space-between; align-items: center; }
        header a { color: #cbd5e1; text-decoration: none; margin-left: 1rem; font-size: 0.9rem; }
        .container { max-width: 1200px; margin: 1.5rem auto 3rem; padding: 0 1.5rem; }
        .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(310px, 1fr)); gap: 1.25rem; margin-bottom: 1.5rem; }
        .card { background: #fff; border-radius: 10px; padding: 1.25rem; box-shadow: 0 2px 6px rgba(0,0,0,0.05); margin-bottom: 1.25rem; overflow-x: auto; }
        h3 { margin-top: 0; font-size: 1.05rem; }
        label { display: block; margin-top: 0.75rem; font-size: 0.85rem; font-weight: 600; }
        input, select { width: 100%; padding: 0.55rem; margin-top: 0.25rem; border: 1px solid #cbd5e1; border-radius: 6px; box-sizing: border-box; background: #fff; }
        button { margin-top: 1rem; padding: 0.65rem 1rem; border: none; border-radius: 6px; font-weight: 600; cursor: pointer; width: 100%; }
        .btn-in { background: #16a34a; color: #fff; }
        .btn-out { background: #dc2626; color: #fff; }
        .btn-neutral { background: #2563eb; color: #fff; }
        .btn-sm { margin-top: 0; padding: 0.45rem 0.75rem; width: auto; background: #0f172a; color: #fff; font-size: 0.82rem; }
        .input-inline { margin-top: 0; padding: 0.4rem 0.5rem; font-size: 0.85rem; min-width: 140px; }
        .input-min { width: 70px; margin-top: 0; padding: 0.4rem 0.5rem; font-size: 0.85rem; }
        .alert-ok { background: #dcfce7; color: #166534; padding: 0.85rem; border-radius: 8px; margin-bottom: 1rem; }
        .alert-err { background: #fee2e2; color: #991b1b; padding: 0.85rem; border-radius: 8px; margin-bottom: 1rem; }
        table { width: 100%; border-collapse: collapse; font-size: 0.88rem; }
        th, td { text-align: left; padding: 0.6rem; border-bottom: 1px solid #e2e8f0; vertical-align: middle; }
        th { background: #f8fafc; font-size: 0.78rem; text-transform: uppercase; color: #475569; }
        .tag-in { color: #15803d; font-weight: 700; }
        .tag-out { color: #b91c1c; font-weight: 700; }
        .sub-info { display: block; font-size: 0.76rem; color: #64748b; margin-top: 0.2rem; }
    </style>
</head>
<body>
    <header>
        <div><strong>StokAman</strong> — Admin: <?= htmlspecialchars($admin['username']) ?></div>
        <div>
            <a href="/masters.php">Master Kategori & Pemasok</a>
            <a href="/index.php">Landing Page</a>
            <a href="/health.php" target="_blank">/health</a>
            <a href="/logout.php">Logout</a>
        </div>
    </header>

    <div class="container">
        <?php if ($flashSuccess): ?>
            <div class="alert-ok"><?= htmlspecialchars($flashSuccess) ?></div>
        <?php endif; ?>
        <?php if ($flashError): ?>
            <div class="alert-err"><?= htmlspecialchars($flashError) ?></div>
        <?php endif; ?>

        <div class="grid">
            <!-- Form 1: Scan / Input Barang Keluar (FIFO) -->
            <div class="card" style="border-top: 4px solid #dc2626;">
                <h3>Scan / Input Barang Keluar (FIFO)</h3>
                <form method="POST">
                    <input type="hidden" name="action" value="stock_out">
                    <label for="scan_sku">Scan Barcode / Ketik Kode SKU</label>
                    <input type="text" id="scan_sku" name="sku" required autofocus placeholder="Arahkan scanner ke sini (misal: SKU-001)">

                    <label for="out_qty">Jumlah Keluar</label>
                    <input type="number" id="out_qty" name="qty" value="1" min="1" required>

                    <label for="out_note">Catatan (Opsional)</label>
                    <input type="text" id="out_note" name="note" placeholder="Order Marketplace / Kasir">

                    <button type="button" id="btnToggleCamera" style="margin-top:0.5rem;padding:0.5rem 0.75rem;background:#2563eb;color:#fff;border:none;border-radius:6px;font-size:0.84rem;font-weight:600;cursor:pointer;width:100%;">
                        📷 Aktifkan Kamera Laptop
                    </button>

                    <div id="cameraBox" style="display:none;margin-top:0.75rem;border:1px solid #cbd5e1;border-radius:8px;padding:0.6rem;background:#f8fafc;">
                        <div id="qr-reader" style="width:100%;border-radius:6px;overflow:hidden;background:#0f172a;"></div>
                        <div id="scanStatus" style="font-size:0.78rem;color:#475569;margin-top:0.45rem;">Menyiapkan kamera laptop...</div>
                        <button type="button" id="btnStopCamera" style="margin-top:0.5rem;padding:0.4rem 0.7rem;background:#64748b;color:#fff;border:none;border-radius:6px;font-size:0.78rem;font-weight:600;cursor:pointer;width:100%;">
                            Matikan Kamera
                        </button>
                    </div>

                    <button type="submit" class="btn-out">Proses Barang Keluar (FIFO)</button>
                </form>
            </div>

            <!-- Form 2: Input Manual Barang Masuk -->
            <div class="card" style="border-top: 4px solid #16a34a;">
                <h3>Input Manual Barang Masuk</h3>
                <form method="POST">
                    <input type="hidden" name="action" value="stock_in">
                    <label for="in_sku">Kode SKU Produk</label>
                    <input type="text" id="in_sku" name="sku" required placeholder="Contoh: SKU-001">

                    <label for="batch_code">Kode Batch Masuk</label>
                    <input type="text" id="batch_code" name="batch_code" required placeholder="Contoh: BATCH-2026-01">

                    <label for="in_qty">Jumlah Stok Masuk</label>
                    <input type="number" id="in_qty" name="qty" min="1" required placeholder="10">

                    <label for="in_note">Catatan Transaksi (Opsional)</label>
                    <input type="text" id="in_note" name="note" placeholder="No. Surat Jalan / Restock">

                    <button type="submit" class="btn-in">Simpan Barang Masuk</button>
                </form>
            </div>

            <!-- Form 3: Tambah Master Produk Baru -->
            <div class="card" style="border-top: 4px solid #2563eb;">
                <h3>Tambah Master Produk Baru</h3>
                <form method="POST">
                    <input type="hidden" name="action" value="add_product">
                    <label for="new_sku">SKU Unik</label>
                    <input type="text" id="new_sku" name="sku" required placeholder="Contoh: SKU-003">

                    <label for="new_name">Nama Produk</label>
                    <input type="text" id="new_name" name="name" required placeholder="Nama barang">

                    <label for="new_cat">Kategori</label>
                    <select id="new_cat" name="category_id">
                        <option value="">-- Pilih Kategori --</option>
                        <?php foreach ($categories as $c): ?>
                            <option value="<?= (int) $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
                        <?php endforeach; ?>
                    </select>

                    <label for="new_sup">Pemasok (Supplier)</label>
                    <select id="new_sup" name="supplier_id">
                        <option value="">-- Pilih Pemasok --</option>
                        <?php foreach ($suppliers as $s): ?>
                            <option value="<?= (int) $s['id'] ?>">
                                <?= htmlspecialchars($s['name']) ?><?= $s['phone'] ? ' (' . htmlspecialchars($s['phone']) . ')' : '' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <label for="new_min">Batas Stok Minimum</label>
                    <input type="number" id="new_min" name="min_stock" value="5" min="0" required>

                    <button type="submit" class="btn-neutral">Tambah Produk</button>
                </form>
            </div>
        </div>

        <!-- Tabel Sisa Stok Terkini + Edit Produk, Kategori & Pemasok -->
        <div class="card">
            <h3>Sisa Stok Terkini & Pengaturan Produk</h3>
            <table>
                <thead>
                    <tr>
                        <th>SKU</th>
                        <th>Nama Produk</th>
                        <th>Kategori</th>
                        <th>Pemasok (Kontak)</th>
                        <th>Sisa Stok</th>
                        <th>Min.</th>
                        <th>Status</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($products as $p): ?>
                        <?php $isLow = (int) $p['current_stock'] <= (int) $p['min_stock']; ?>
                        <?php $formId = 'edit-prod-' . (int) $p['id']; ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($p['sku']) ?></strong> <button type="button" class="btn-sm" style="background:#475569;margin-left:0.35rem;" onclick="showSkuQr(<?= htmlspecialchars(json_encode($p['sku']), ENT_QUOTES) ?>, <?= htmlspecialchars(json_encode($p['name']), ENT_QUOTES) ?>)">QR</button></td>
                            <td>
                                <input class="input-inline" type="text" name="name" form="<?= $formId ?>" value="<?= htmlspecialchars($p['name']) ?>" required>
                            </td>
                            <td>
                                <select class="input-inline" name="category_id" form="<?= $formId ?>">
                                    <option value="">- Tanpa Kategori -</option>
                                    <?php foreach ($categories as $c): ?>
                                        <option value="<?= (int) $c['id'] ?>" <?= (int) $p['category_id'] === (int) $c['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($c['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td>
                                <select class="input-inline" name="supplier_id" form="<?= $formId ?>">
                                    <option value="">- Tanpa Pemasok -</option>
                                    <?php foreach ($suppliers as $s): ?>
                                        <option value="<?= (int) $s['id'] ?>" <?= (int) $p['supplier_id'] === (int) $s['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($s['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <?php if (!empty($p['supplier_phone'])): ?>
                                    <span class="sub-info">📞 <?= htmlspecialchars($p['supplier_phone']) ?></span>
                                <?php endif; ?>
                            </td>
                            <td><strong><?= (int) $p['current_stock'] ?></strong></td>
                            <td>
                                <input class="input-min" type="number" name="min_stock" form="<?= $formId ?>" value="<?= (int) $p['min_stock'] ?>" min="0" required>
                            </td>
                            <td><?= $isLow ? '⚠️ Menipis' : '✅ Aman' ?></td>
                            <td>
                                <form id="<?= $formId ?>" method="POST" style="margin:0;">
                                    <input type="hidden" name="action" value="update_product">
                                    <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                                    <button type="submit" class="btn-sm">Simpan</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Tabel History Keluar Masuk (Kartu Stok) -->
        <div class="card">
            <h3>History Keluar-Masuk Stok (Kartu Stok)</h3>
            <table>
                <thead>
                    <tr>
                        <th>Waktu</th>
                        <th>SKU</th>
                        <th>Produk</th>
                        <th>Batch</th>
                        <th>Tipe</th>
                        <th>Qty</th>
                        <th>Stok Sebelum</th>
                        <th>Stok Sesudah</th>
                        <th>Catatan</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($movements as $m): ?>
                        <tr>
                            <td><?= htmlspecialchars($m['created_at']) ?></td>
                            <td><?= htmlspecialchars($m['sku']) ?></td>
                            <td><?= htmlspecialchars($m['name']) ?></td>
                            <td><?= htmlspecialchars((string) ($m['batch_code'] ?? '-')) ?></td>
                            <td class="<?= $m['movement_type'] === 'IN' ? 'tag-in' : 'tag-out' ?>">
                                <?= htmlspecialchars($m['movement_type']) ?>
                            </td>
                            <td><?= (int) $m['qty'] ?></td>
                            <td><?= (int) $m['stock_before'] ?></td>
                            <td><?= (int) $m['stock_after'] ?></td>
                            <td><?= htmlspecialchars((string) ($m['note'] ?? '-')) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($movements)): ?>
                        <tr><td colspan="9">Belum ada riwayat transaksi stok.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <!-- Modal QR SKU -->
    <div id="qrModal" style="display:none;position:fixed;inset:0;background:rgba(15,23,42,0.6);z-index:999;justify-content:center;align-items:center;padding:1rem;">
        <div style="background:#fff;padding:1.5rem;border-radius:12px;max-width:320px;width:100%;text-align:center;box-shadow:0 10px 25px rgba(0,0,0,0.2);">
            <h3 id="qrTitle" style="margin:0 0 0.25rem;"></h3>
            <div id="qrSubtitle" style="font-size:0.85rem;color:#64748b;margin-bottom:1rem;"></div>
            <div id="qrCanvasBox" style="display:flex;justify-content:center;padding:0.75rem;background:#fff;border:1px solid #e2e8f0;border-radius:8px;"></div>
            <p style="font-size:0.78rem;color:#64748b;margin:0.85rem 0 1rem;">Arahkan kamera laptop ke QR ini untuk uji scan barang keluar (FIFO).</p>
            <button type="button" onclick="closeSkuQr()" style="margin-top:0;background:#0f172a;color:#fff;padding:0.55rem 1rem;border:none;border-radius:6px;font-weight:600;cursor:pointer;width:100%;">Tutup</button>
        </div>
    </div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<script>
function showSkuQr(sku, name) {
    const modal = document.getElementById('qrModal');
    const box = document.getElementById('qrCanvasBox');
    document.getElementById('qrTitle').textContent = sku;
    document.getElementById('qrSubtitle').textContent = name;
    box.innerHTML = '';

    if (typeof QRCode !== 'undefined') {
        new QRCode(box, {
            text: sku,
            width: 180,
            height: 180
        });
    } else {
        box.textContent = 'Library QR gagal dimuat. Kode SKU: ' + sku;
    }

    modal.style.display = 'flex';
}

function closeSkuQr() {
    document.getElementById('qrModal').style.display = 'none';
}

document.getElementById('qrModal')?.addEventListener('click', function (e) {
    if (e.target === this) closeSkuQr();
});
</script>

<script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
<script>
(function () {
    const skuInput = document.getElementById('scan_sku');
    const stockOutForm = skuInput ? skuInput.closest('form') : null;
    const btnToggle = document.getElementById('btnToggleCamera');
    const btnStop = document.getElementById('btnStopCamera');
    const cameraBox = document.getElementById('cameraBox');
    const statusEl = document.getElementById('scanStatus');

    let html5QrCode = null;
    let isScanning = false;

    if (skuInput) {
        skuInput.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' && skuInput.value.trim() !== '' && stockOutForm) {
                e.preventDefault();
                stockOutForm.requestSubmit();
            }
        });
    }

    async function stopCamera() {
        if (html5QrCode && isScanning) {
            try {
                await html5QrCode.stop();
                await html5QrCode.clear();
            } catch (e) {}
        }
        isScanning = false;
        if (cameraBox) {
            cameraBox.style.display = 'none';
        }
    }

    async function startLaptopCamera() {
        if (isScanning) return;

        if (typeof Html5Qrcode === 'undefined') {
            alert('Library kamera gagal dimuat. Pastikan koneksi internet aktif atau gunakan input manual SKU.');
            return;
        }

        cameraBox.style.display = 'block';
        statusEl.textContent = 'Meminta izin akses kamera laptop...';

        try {
            if (!html5QrCode) {
                html5QrCode = new Html5Qrcode('qr-reader');
            }

            await html5QrCode.start(
                { facingMode: 'user' },
                { fps: 10, qrbox: { width: 220, height: 140 } },
                async (decodedText) => {
                    const cleanSku = (decodedText || '').trim().toUpperCase();
                    if (!cleanSku) return;

                    statusEl.textContent = 'SKU terbaca: ' + cleanSku + ' — memproses FIFO...';
                    skuInput.value = cleanSku;
                    await stopCamera();

                    const qtyInput = stockOutForm ? stockOutForm.querySelector('input[name="qty"]') : null;
                    if (qtyInput) {
                        qtyInput.focus();
                        qtyInput.select();
                    }
                    alert('SKU "' + cleanSku + '" terbaca. Silakan atur Jumlah Keluar (misal 10), lalu klik Proses Barang Keluar (FIFO).');
                },
                () => {}
            );

            isScanning = true;
            statusEl.textContent = 'Kamera laptop aktif. Arahkan QR / Barcode SKU ke kotak scan.';
        } catch (err) {
            statusEl.textContent = 'Kamera tidak bisa dibuka: ' + err;
            alert('Tidak dapat membuka kamera laptop: ' + err);
            await stopCamera();
        }
    }

    if (btnToggle) {
        btnToggle.addEventListener('click', startLaptopCamera);
    }

    if (btnStop) {
        btnStop.addEventListener('click', stopCamera);
    }

    window.addEventListener('beforeunload', stopCamera);

})();
</script>
</body>
</html>
