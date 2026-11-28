<?php

declare(strict_types=1);

$path = __DIR__ . '/src/Http/dashboard.php';
$content = file_get_contents($path);

if ($content === false) {
    fwrite(STDERR, "Gagal membaca src/Http/dashboard.php\n");
    exit(1);
}

// Hapus script kamera lama kalau sempat ada sebagian
$content = preg_replace(
    '#<script src="https://unpkg\.com/html5-qrcode[^>]*></script>\s*<script>.*?</script>#s',
    '',
    $content
) ?? $content;

$oldSubmit = '<button type="submit" class="btn-out">Proses Barang Keluar (FIFO)</button>';
$newSubmit = <<<'HTML'
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
HTML;

if (!str_contains($content, 'id="btnToggleCamera"')) {
    if (!str_contains($content, $oldSubmit)) {
        fwrite(STDERR, "Tombol submit barang keluar tidak ditemukan di src/Http/dashboard.php\n");
        exit(1);
    }
    $content = str_replace($oldSubmit, $newSubmit, $content);
}

$camScript = <<<'HTML'

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

                    if (stockOutForm) {
                        stockOutForm.requestSubmit();
                    }
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
HTML;

if (!str_contains($content, 'startLaptopCamera')) {
    $content = str_replace('</body>', $camScript, $content);
}

file_put_contents($path, $content);
echo "Tombol dan scanner kamera laptop berhasil dipasang!\n";