<?php

declare(strict_types=1);

require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/../Config/Database.php';

use StokAman\Config\Database;

$admin = requireAdmin();
$pdo = Database::getConnection();

$flashSuccess = null;
$flashError = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');

    try {
        if ($action === 'create_category') {
            $name = trim((string) ($_POST['name'] ?? ''));
            if ($name === '') {
                throw new InvalidArgumentException('Nama kategori wajib diisi.');
            }
            $stmt = $pdo->prepare('INSERT INTO categories (name) VALUES (:name)');
            $stmt->execute(['name' => $name]);
            $flashSuccess = "Kategori '{$name}' berhasil ditambahkan.";
        } elseif ($action === 'update_category') {
            $id = (int) ($_POST['id'] ?? 0);
            $name = trim((string) ($_POST['name'] ?? ''));
            if ($id <= 0 || $name === '') {
                throw new InvalidArgumentException('ID dan nama kategori wajib valid.');
            }
            $stmt = $pdo->prepare('UPDATE categories SET name = :name WHERE id = :id');
            $stmt->execute(['name' => $name, 'id' => $id]);
            $flashSuccess = 'Kategori berhasil diperbarui.';
        } elseif ($action === 'delete_category') {
            $id = (int) ($_POST['id'] ?? 0);
            if ($id <= 0) {
                throw new InvalidArgumentException('ID kategori tidak valid.');
            }
            $stmt = $pdo->prepare('DELETE FROM categories WHERE id = :id');
            $stmt->execute(['id' => $id]);
            $flashSuccess = 'Kategori berhasil dihapus.';
        } elseif ($action === 'create_supplier') {
            $name = trim((string) ($_POST['name'] ?? ''));
            $phone = trim((string) ($_POST['phone'] ?? '')) ?: null;
            $address = trim((string) ($_POST['address'] ?? '')) ?: null;
            if ($name === '') {
                throw new InvalidArgumentException('Nama pemasok wajib diisi.');
            }
            $stmt = $pdo->prepare('INSERT INTO suppliers (name, phone, address) VALUES (:name, :phone, :address)');
            $stmt->execute(['name' => $name, 'phone' => $phone, 'address' => $address]);
            $flashSuccess = "Pemasok '{$name}' berhasil ditambahkan.";
        } elseif ($action === 'update_supplier') {
            $id = (int) ($_POST['id'] ?? 0);
            $name = trim((string) ($_POST['name'] ?? ''));
            $phone = trim((string) ($_POST['phone'] ?? '')) ?: null;
            $address = trim((string) ($_POST['address'] ?? '')) ?: null;
            if ($id <= 0 || $name === '') {
                throw new InvalidArgumentException('ID dan nama pemasok wajib valid.');
            }
            $stmt = $pdo->prepare(
                'UPDATE suppliers SET name = :name, phone = :phone, address = :address WHERE id = :id'
            );
            $stmt->execute(['name' => $name, 'phone' => $phone, 'address' => $address, 'id' => $id]);
            $flashSuccess = 'Data pemasok berhasil diperbarui.';
        } elseif ($action === 'delete_supplier') {
            $id = (int) ($_POST['id'] ?? 0);
            if ($id <= 0) {
                throw new InvalidArgumentException('ID pemasok tidak valid.');
            }
            $stmt = $pdo->prepare('DELETE FROM suppliers WHERE id = :id');
            $stmt->execute(['id' => $id]);
            $flashSuccess = 'Data pemasok berhasil dihapus.';
        }
    } catch (Throwable $e) {
        $flashError = $e->getMessage();
    }
}

$categories = $pdo->query('SELECT id, name FROM categories ORDER BY name ASC')->fetchAll();
$suppliers = $pdo->query('SELECT id, name, phone, address FROM suppliers ORDER BY name ASC')->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Master Kategori & Pemasok - StokAman</title>
    <style>
        body { font-family: system-ui, sans-serif; margin: 0; background: #f1f5f9; color: #0f172a; }
        header { background: #0f172a; color: #fff; padding: 1rem 2rem; display: flex; justify-content: space-between; align-items: center; }
        header a { color: #cbd5e1; text-decoration: none; margin-left: 1rem; font-size: 0.9rem; }
        .container { max-width: 1080px; margin: 1.5rem auto 3rem; padding: 0 1.5rem; }
        .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(460px, 1fr)); gap: 1.25rem; }
        .card { background: #fff; border-radius: 10px; padding: 1.25rem; box-shadow: 0 2px 6px rgba(0,0,0,0.05); margin-bottom: 1.25rem; }
        h3 { margin-top: 0; }
        input { padding: 0.5rem; border: 1px solid #cbd5e1; border-radius: 6px; }
        button { padding: 0.45rem 0.75rem; border: none; border-radius: 6px; font-weight: 600; cursor: pointer; }
        .btn-add { background: #2563eb; color: #fff; }
        .btn-save { background: #16a34a; color: #fff; }
        .btn-del { background: #dc2626; color: #fff; }
        .alert-ok { background: #dcfce7; color: #166534; padding: 0.85rem; border-radius: 8px; margin-bottom: 1rem; }
        .alert-err { background: #fee2e2; color: #991b1b; padding: 0.85rem; border-radius: 8px; margin-bottom: 1rem; }
        table { width: 100%; border-collapse: collapse; margin-top: 1rem; font-size: 0.9rem; }
        th, td { text-align: left; padding: 0.6rem; border-bottom: 1px solid #e2e8f0; }
        .inline-form { display: flex; gap: 0.4rem; flex-wrap: wrap; align-items: center; }
    </style>
</head>
<body>
    <header>
        <div><strong>StokAman</strong> — Master Data (<?= htmlspecialchars($admin['username']) ?>)</div>
        <div>
            <a href="/dashboard.php">Dashboard Stok</a>
            <a href="/index.php">Landing Page</a>
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
            <div class="card">
                <h3>CRUD Kategori Produk</h3>
                <form method="POST" class="inline-form">
                    <input type="hidden" name="action" value="create_category">
                    <input type="text" name="name" placeholder="Nama kategori baru" required>
                    <button type="submit" class="btn-add">Tambah</button>
                </form>

                <table>
                    <thead>
                        <tr><th>Kategori</th><th>Aksi</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($categories as $c): ?>
                            <tr>
                                <td>
                                    <form method="POST" class="inline-form">
                                        <input type="hidden" name="action" value="update_category">
                                        <input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
                                        <input type="text" name="name" value="<?= htmlspecialchars($c['name']) ?>" required>
                                        <button type="submit" class="btn-save">Simpan</button>
                                    </form>
                                </td>
                                <td>
                                    <form method="POST" onsubmit="return confirm('Hapus kategori ini?');">
                                        <input type="hidden" name="action" value="delete_category">
                                        <input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
                                        <button type="submit" class="btn-del">Hapus</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="card">
                <h3>CRUD Pemasok (Supplier)</h3>
                <form method="POST" class="inline-form">
                    <input type="hidden" name="action" value="create_supplier">
                    <input type="text" name="name" placeholder="Nama pemasok" required>
                    <input type="text" name="phone" placeholder="No. HP">
                    <input type="text" name="address" placeholder="Alamat">
                    <button type="submit" class="btn-add">Tambah</button>
                </form>

                <table>
                    <thead>
                        <tr><th>Pemasok</th><th>Aksi</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($suppliers as $s): ?>
                            <tr>
                                <td>
                                    <form method="POST" class="inline-form">
                                        <input type="hidden" name="action" value="update_supplier">
                                        <input type="hidden" name="id" value="<?= (int) $s['id'] ?>">
                                        <input type="text" name="name" value="<?= htmlspecialchars($s['name']) ?>" required>
                                        <input type="text" name="phone" value="<?= htmlspecialchars((string) ($s['phone'] ?? '')) ?>" placeholder="HP">
                                        <input type="text" name="address" value="<?= htmlspecialchars((string) ($s['address'] ?? '')) ?>" placeholder="Alamat">
                                        <button type="submit" class="btn-save">Simpan</button>
                                    </form>
                                </td>
                                <td>
                                    <form method="POST" onsubmit="return confirm('Hapus pemasok ini?');">
                                        <input type="hidden" name="action" value="delete_supplier">
                                        <input type="hidden" name="id" value="<?= (int) $s['id'] ?>">
                                        <button type="submit" class="btn-del">Hapus</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>
