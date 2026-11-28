<?php

declare(strict_types=1);

$path = __DIR__ . '/src/Http/dashboard.php';
$content = file_get_contents($path);

if ($content === false) {
    fwrite(STDERR, "Gagal membaca src/Http/dashboard.php\n");
    exit(1);
}

$searchSkuCell = '<td><strong><?= htmlspecialchars($p[\'sku\']) ?></strong></td>';
$replaceSkuCell = '<td><strong><?= htmlspecialchars($p[\'sku\']) ?></strong> '
    . '<button type="button" class="btn-sm" style="background:#475569;margin-left:0.35rem;" '
    . 'onclick="showSkuQr(<?= htmlspecialchars(json_encode($p[\'sku\']), ENT_QUOTES) ?>, '
    . '<?= htmlspecialchars(json_encode($p[\'name\']), ENT_QUOTES) ?>)">QR</button></td>';

if (!str_contains($content, $replaceSkuCell)) {
    $content = str_replace($searchSkuCell, $replaceSkuCell, $content);
}

$qrBlock = <<<'HTML'
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
</body>
HTML;

if (!str_contains($content, 'id="qrModal"')) {
    $content = str_replace('</body>', $qrBlock, $content);
}

file_put_contents($path, $content);
echo "Berhasil update src/Http/dashboard.php\n";