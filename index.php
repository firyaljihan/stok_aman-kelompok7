<?php
$appName = "StokAman - Kelompok 7";
$phpVersion = phpversion();
$serverTime = date("Y-m-d H:i:s");
$containerHost = gethostname();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $appName ?></title>
    <style>
        body {
            font-family: system-ui, -apple-system, sans-serif;
            background-color: #f4f6f9;
            color: #1f2937;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
        }
        .card {
            background: #ffffff;
            padding: 2rem;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
            max-width: 480px;
            width: 100%;
        }
        .badge {
            display: inline-block;
            background: #dcfce7;
            color: #166534;
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.85rem;
            font-weight: 600;
            margin-bottom: 1rem;
        }
        h1 { margin: 0 0 0.5rem; font-size: 1.5rem; }
        p { margin: 0 0 1.25rem; color: #4b5563; }
        ul { list-style: none; padding: 0; margin: 0; border-top: 1px solid #e5e7eb; }
        li { padding: 0.65rem 0; border-bottom: 1px solid #e5e7eb; font-size: 0.95rem; }
    </style>
</head>
<body>
    <div class="card">
        <span class="badge">CI/CD Pipeline Active</span>
        <h1><?= $appName ?></h1>
        <p>Aplikasi inventori stok gudang UMKM (PHP Native) berhasil dijalankan di dalam Docker container.</p>
        <ul>
            <li><strong>Versi PHP:</strong> <?= $phpVersion ?></li>
            <li><strong>Container ID:</strong> <?= $containerHost ?></li>
            <li><strong>Waktu Server:</strong> <?= $serverTime ?></li>
        </ul>
    </div>
</body>
</html>