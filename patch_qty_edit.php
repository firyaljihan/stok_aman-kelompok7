<?php

declare(strict_types=1);

$path = __DIR__ . '/src/Http/dashboard.php';
$content = file_get_contents($path);

if ($content === false) {
    fwrite(STDERR, "Gagal membaca src/Http/dashboard.php\n");
    exit(1);
}

// 1. Pastikan input qty bisa diedit bebas dan tambahkan tombol cepat +5 / +10 jika ada input qty
$content = preg_replace(
    '/(<input[^>]*name=["\']qty["\'][^>]*>)/i',
    '$1<div style="display:flex;gap:0.35rem;margin-top:0.35rem;">'
    . '<button type="button" onclick="addOutQty(1)" style="padding:0.25rem 0.5rem;border:1px solid #cbd5e1;background:#fff;border-radius:5px;font-size:0.78rem;cursor:pointer;">+1</button>'
    . '<button type="button" onclick="addOutQty(5)" style="padding:0.25rem 0.5rem;border:1px solid #cbd5e1;background:#fff;border-radius:5px;font-size:0.78rem;cursor:pointer;">+5</button>'
    . '<button type="button" onclick="addOutQty(10)" style="padding:0.25rem 0.5rem;border:1px solid #cbd5e1;background:#fff;border-radius:5px;font-size:0.78rem;cursor:pointer;">+10</button>'
    . '</div>',
    $content,
    1
) ?? $content;

// 2. Jangan auto-submit saat scan QR; isi SKU lalu arahkan fokus ke input qty
$autoSubmitSnippet = <<<'JS'
                    skuInput.value = cleanSku;
                    await stopCamera();

                    if (stockOutForm) {
                        stockOutForm.requestSubmit();
                    }
JS;

$editFirstSnippet = <<<'JS'
                    skuInput.value = cleanSku;
                    await stopCamera();

                    const qtyInput = stockOutForm ? stockOutForm.querySelector('input[name="qty"]') : null;
                    if (qtyInput) {
                        qtyInput.focus();
                        qtyInput.select();
                    }
                    alert('SKU "' + cleanSku + '" terbaca. Silakan atur Jumlah Keluar (misal 10), lalu klik Proses Barang Keluar (FIFO).');
JS;

if (str_contains($content, $autoSubmitSnippet)) {
    $content = str_replace($autoSubmitSnippet, $editFirstSnippet, $content);
}

// 3. Tambahkan helper addOutQty() sebelum </script> kamera
$helperFn = <<<'JS'

function addOutQty(delta) {
    const qtyInput = document.querySelector('input[name="qty"]');
    if (!qtyInput) return;
    const current = parseInt(qtyInput.value || '0', 10);
    const next = Math.max(1, (isNaN(current) ? 0 : current) + delta);
    qtyInput.value = String(next);
    qtyInput.focus();
}
JS;

if (!str_contains($content, 'function addOutQty(') && str_contains($content, 'window.addEventListener(\'beforeunload\', stopCamera);')) {
    $content = str_replace(
        'window.addEventListener(\'beforeunload\', stopCamera);',
        'window.addEventListener(\'beforeunload\', stopCamera);' . $helperFn,
        $content
    );
}

file_put_contents($path, $content);
echo "Update edit jumlah sebelum proses barang keluar berhasil dipasang!\n";