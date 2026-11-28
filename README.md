# StokAman — Sistem Inventori Gudang UMKM (Studi Kasus K11)

**StokAman** adalah aplikasi web pencatatan dan manajemen stok gudang berbasis **PHP Native** dan **MySQL** yang dirancang untuk mencegah selisih stok pada operasional UMKM. Pada implementasi studi kasus ini, sistem diaplikasikan untuk mitra UMKM **YUMMIOU** (produk *keychain* / gantungan kunci) guna mencatat pemasokan barang berbasis *batch* serta pengeluaran barang secara cepat melalui pemindaian kode barcode/QR SKU di kasir menggunakan metode **FIFO (*First-In, First-Out*)**.

Proyek ini dikembangkan sebagai bagian dari **Tugas Besar 1 Mata Kuliah Komputasi Awan** dengan alur otomatisasi CI/CD menggunakan **GitHub**, **Jenkins**, **Docker**, **Docker Hub**, dan pemindaian keamanan *container image* menggunakan **Trivy**.

---

## Anggota Kelompok 7 & Pembagian Peran

| Nama Anggota | Peran Utama | Tanggung Jawab |
| :--- | :--- | :--- |
| **Rizqy Dharmawan** | Tech Lead / Backend Engineer | Arsitektur aplikasi PHP Native, skema database & *seeder*, logika aturan bisnis stok (FIFO & anti-negatif), serta endpoint `/health`. |
| **Firyal Jihan Haura** | DevOps Engineer | Konfigurasi Jenkins, GitHub Webhook via Ngrok, Jenkins Credentials, `Jenkinsfile`, dan integrasi *security scan* Trivy. |
| **Berlian Sepasya Rekardian** | Container Engineer | Penulisan `Dockerfile`, `docker-compose.yml`, konfigurasi `.env.example`, manajemen volume, dan *healthcheck* database. |
| **Farhan Jessen Irfananda** | QA Engineer & Tech Writer | Penyusunan *automated test* (`tests/run_tests.php`), skenario uji gagal-pulih (*quality gate*), dokumentasi `README.md`, diagram alur, dan laporan akhir. |

---

## Tech Stack

* **Backend & Web UI:** PHP 8.2 Native (PDO), HTML5, CSS3, JavaScript (`html5-qrcode` & `qrcodejs`)
* **Database:** MySQL 8.0
* **Containerization:** Docker, Docker Compose
* **CI/CD & Security:** Jenkins, GitHub Webhook, Ngrok, Docker Hub, Aqua Security Trivy

---

## Fitur Wajib & Aturan Bisnis (K11)

### Fitur Utama
1. **Manajemen Master Data:** CRUD produk, kategori produk, dan data pemasok (*supplier*).
2. **Transaksi Barang Masuk (*Batch*):** Pencatatan stok masuk berdasarkan kode *batch*, tanggal penerimaan, dan harga satuan.
3. **Transaksi Barang Keluar (Scan Barcode/QR Kasir):** Pemindaian kode SKU produk melalui *barcode scanner* atau kamera untuk memproses barang keluar secara cepat.
4. **Kartu Stok (*Stock Movements*):** Riwayat lengkap mutasi barang masuk (`IN`) dan keluar (`OUT`) beserta stok sebelum dan sesudah transaksi.
5. **Peringatan Stok Minimum:** Indikator status otomatis (`Aman` vs `Stok Menipis`) ketika sisa stok menyentuh atau berada di bawah batas `min_stock`.
6. **Health Check Endpoint:** Endpoint `GET /health.php` yang memverifikasi konektivitas aplikasi dan database (mengembalikan HTTP `200 OK`).

### Aturan Bisnis yang Diterapkan & Diuji
* **Stok Tidak Boleh Negatif:** Sistem menolak transaksi barang keluar apabila jumlah yang diminta melebihi sisa stok yang tersedia.
* **Akumulasi Stok Otomatis:** Jumlah stok terkini dihitung dari akumulasi transaksi masuk dan keluar pada *batch* stok, bukan melalui pengisian manual.
* **Keunikan SKU / Kode Barcode:** Setiap produk memiliki kode SKU unik (`UNIQUE` constraint di database dan validasi pada aplikasi) untuk mencegah duplikasi pemindaian.
* **Pengeluaran Barang Metode FIFO:** Pengurangan stok saat barang keluar otomatis memotong *batch* dengan tanggal masuk (`received_date`) paling awal terlebih dahulu.

---

## Struktur Proyek

```text
stok_aman-kelompok7/
├── database/
│   ├── schema.sql              # Definisi tabel (users, categories, suppliers, products, stock_batches, stock_movements)
│   └── seed.sql                # Data awal (akun admin, kategori, pemasok, produk contoh)
├── src/
│   ├── Auth/
│   │   └── AuthService.php     # Autentikasi login admin
│   ├── Config/
│   │   └── Database.php        # Koneksi PDO MySQL dari environment variables
│   ├── Http/
│   │   ├── auth_check.php      # Middleware pengecekan sesi login
│   │   ├── dashboard.php       # Dashboard transaksi masuk/keluar (scan QR/barcode), master produk & kartu stok
│   │   ├── health.php          # Endpoint health check (GET /health.php)
│   │   ├── index.php           # Halaman utama & ringkasan stok publik
│   │   ├── login.php           # Halaman login admin
│   │   ├── logout.php          # Proses logout
│   │   └── masters.php         # CRUD master kategori & pemasok
│   └── Inventory/
│       └── StockService.php    # Logika inti transaksi masuk/keluar, validasi anti-minus, dan alokasi FIFO
├── tests/
│   └── run_tests.php           # Automated test suite untuk koneksi DB dan aturan bisnis StokAman
├── .env.example                # Template variabel environment
├── .gitignore                  # Daftar file yang dikecualikan dari Git (termasuk .env)
├── Dockerfile                  # Instruksi build container image aplikasi PHP
├── docker-compose.yml          # Orkestrasi service aplikasi dan database MySQL
└── Jenkinsfile                 # Definisi pipeline CI/CD Jenkins & security scan Trivy