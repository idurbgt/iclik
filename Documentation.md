# Dokumentasi Iclik

Aplikasi monitoring server via ICMP/ping dengan peta interaktif, notifikasi Telegram,
dan Incident Feed. Dokumen ini terdiri dari dua bagian:

1. [Panduan Instalasi](#1-panduan-instalasi)
2. [Panduan Penggunaan](#2-panduan-penggunaan)

---

# 1. Panduan Instalasi

## 1.1 Kebutuhan Sistem

| Komponen | Versi / Keterangan |
|----------|--------------------|
| PHP | >= 7.4 |
| MySQL / MariaDB | >= 5.7 |
| Web Server | Apache / Nginx, atau `php -S` untuk development |
| Ekstensi PHP | `pdo_mysql` (wajib), `curl` (opsional, untuk Telegram — ada fallback) |
| Fungsi PHP | `exec()` harus aktif (untuk menjalankan `ping`) |
| Akses cron | Untuk pengecekan otomatis tiap menit |

> Cek ekstensi terpasang: `php -m` — pastikan ada `pdo_mysql`.
> Cek `exec()` aktif: pastikan `exec` tidak ada di `disable_functions` pada `php.ini`.

## 1.2 Langkah Instalasi

### Langkah 1 — Tempatkan File Proyek
Letakkan folder proyek di direktori web server, misalnya:
```bash
# Linux (Apache)
cd /var/www/html
# salin/extract proyek ke folder: iclik/

# Windows (XAMPP)
# salin/extract ke: C:\xampp\htdocs\iclik\
```

### Langkah 2 — Buat & Import Database
Import skema database (membuat database `iclik` beserta tabelnya):
```bash
mysql -u root -p < sql/schema.sql
```
Skema membuat 4 tabel: `servers`, `ping_logs`, `server_stats`, `incidents`.

### Langkah 3 — Konfigurasi Aplikasi
Edit file `config.php` (file ini diproteksi dari akses browser oleh `.htaccess`):
```php
return [
    // Koneksi database
    'database' => [
        'host'    => 'localhost',
        'name'    => 'iclik',
        'user'    => 'root',
        'pass'    => '',          // isi password MySQL Anda
        'charset' => 'utf8mb4',
    ],

    // Notifikasi Telegram (lihat Langkah 6)
    'telegram' => [
        'enabled'   => false,
        'bot_token' => '',
        'chat_id'   => '',
        'timeout'   => 5,
    ],

    // Jumlah kegagalan berturut sebelum status dinyatakan DOWN
    'alert_threshold' => 2,

    // Zona waktu
    'timezone' => 'Asia/Jakarta',
];
```

### Langkah 4 — Atur Izin (Linux)
```bash
chmod 755 data/                  # untuk file log yang dibuat otomatis
chmod 755 cron/ping_check.php
```
Pastikan user web server dapat menulis ke folder `data/`.

### Langkah 5 — Setup Cron Job
Cron menjalankan pengecekan ping setiap menit.
```bash
crontab -e
```
Tambahkan baris (sesuaikan path PHP & proyek):
```
* * * * * /usr/bin/php /var/www/html/iclik/cron/ping_check.php >> /var/www/html/iclik/data/logs/cron.log 2>&1
```

**Windows (Task Scheduler):** buat task yang menjalankan
`C:\xampp\php\php.exe C:\xampp\htdocs\iclik\cron\ping_check.php` dengan trigger tiap 1 menit.

### Langkah 6 — (Opsional) Aktifkan Notifikasi Telegram
1. Buka Telegram, chat dengan **@BotFather** → kirim `/newbot` → ikuti instruksi → salin **token**.
2. Kirim satu pesan apa saja ke bot Anda.
3. Buka di browser: `https://api.telegram.org/bot<TOKEN>/getUpdates` → cari `"chat":{"id": ...}` → itulah **chat_id**.
   (Untuk grup: tambahkan bot ke grup, kirim pesan; id grup biasanya diawali tanda minus.)
4. Isi `config.php`:
```php
'telegram' => [
    'enabled'   => true,
    'bot_token' => '123456789:ABCdefGhIJKlmno...',
    'chat_id'   => '123456789',
    'timeout'   => 5,
],
```

### Langkah 7 — Akses Aplikasi
Buka di browser:
```
http://localhost/iclik/
```

## 1.3 Verifikasi Instalasi

Jalankan skrip pengujian (butuh PHP di command line):

```bash
# Uji koneksi DB + state machine + incident (aman, tidak mengotori data)
php tests/test_monitor.php

# Uji kiriman Telegram (jika sudah diaktifkan)
php tests/test_telegram.php

# Isi data contoh agar dashboard langsung terlihat
php tests/seed_data.php
```

`test_monitor.php` akan menampilkan deretan `[PASS]` dan ringkasan. Jika semua PASS,
instalasi backend Anda benar. Untuk menghapus data contoh: `php tests/seed_data.php --clean`.

## 1.4 Troubleshooting Instalasi

| Masalah | Kemungkinan Penyebab & Solusi |
|---------|-------------------------------|
| Halaman blank / error koneksi | Kredensial DB di `config.php` salah, atau schema belum di-import. |
| Ping selalu "down" | `exec()` dinonaktifkan, atau user tidak boleh menjalankan `ping`. Uji: `php cron/ping_check.php`. |
| Cron tidak jalan | Cek `crontab -l`; cek path PHP (`which php`); cek `data/logs/cron.log`. |
| Telegram tidak terkirim | `enabled` belum `true`, token/chat_id salah. Uji token: `https://api.telegram.org/bot<TOKEN>/getMe`. |
| Data bisa dibuka dari browser | Pastikan Apache membaca `.htaccess` (`AllowOverride All`). Di Nginx, tambahkan aturan `deny` setara untuk `config.php` dan `data/`. |

---

# 2. Panduan Penggunaan

## 2.1 Tampilan Dashboard

Setelah membuka `http://localhost/iclik/`, Anda akan melihat:

- **Header** — judul + tombol **Add Server**, **Refresh**, dan waktu update terakhir.
- **Peta** — titik pusat "Monitoring Center" (Jakarta) dan marker setiap server.
- **Tabel Server Status** — daftar server beserta status, response time, uptime, dll.
- **Incident Feed** — riwayat kejadian server putus/terhubung terbaru.

Dashboard **auto-refresh setiap 30 detik**. Anda juga bisa menekan **Refresh** kapan saja.

### Arti Warna Status
| Warna | Status | Keterangan |
|-------|--------|------------|
| 🟢 Hijau | UP | Server online (ping berhasil) |
| 🔴 Merah | DOWN | Server offline (gagal setelah ambang batas) |
| ⚪ Abu-abu | UNKNOWN | Belum pernah dicek / status belum pasti |

Garis dari pusat ke server:
- **Garis solid** = koneksi aktif (up)
- **Garis putus-putus** = koneksi terputus (down/unknown)

## 2.2 Menambah Server

1. Klik tombol **Add Server**.
2. Isi formulir:
   - **Server Name** — nama server (mis. "Web Server 1").
   - **IP Address** — alamat IP yang akan dipantau (mis. `192.168.1.1`).
   - **Description** — deskripsi opsional.
3. **Klik lokasi di peta** pada modal untuk menentukan koordinat server.
   Koordinat yang terpilih akan ditampilkan di bawah peta.
4. Klik **Save Server**.

Setelah disimpan, aplikasi langsung melakukan **ping perdana** dan menampilkan status awal.

> **Validasi:** IP harus berformat valid dan belum terdaftar (tidak boleh duplikat).
> Lokasi di peta wajib dipilih.

## 2.3 Memantau Status

- **Tabel** menampilkan: Name, IP Address, Status, Response Time (ms), Uptime (%), Last Check.
- **Klik marker** di peta untuk melihat popup detail (IP, status, response, uptime, deskripsi).
- **Uptime %** = persentase pengecekan yang berhasil dari total pengecekan.

Pengecekan otomatis dijalankan oleh cron setiap menit dengan mekanisme:
- Setiap server di-ping (dengan retry hingga 3× per percobaan, timeout 5 detik).
- Hasil dicatat ke riwayat dan statistik diperbarui.

## 2.4 Notifikasi Telegram

Notifikasi dikirim **hanya saat terjadi perubahan status** (bukan setiap ping), sehingga tidak berisik:

- 🔴 **SERVER DOWN** — dikirim setelah server gagal ping **berturut-turut** sebanyak
  `alert_threshold` kali (default 2×). Ini mencegah alarm palsu akibat gangguan sesaat.
- 🟢 **SERVER RECOVERED** — dikirim **seketika** saat server kembali online, disertai
  **durasi downtime** (berapa lama server sempat mati).

Contoh pesan:
```
🔴 SERVER DOWN
Nama: Web Server 1
IP: 192.168.1.1
Waktu: 2026-07-03 14:05:12
```
```
🟢 SERVER RECOVERED
Nama: Web Server 1
IP: 192.168.1.1
Durasi down: 4m
Response: 12 ms
Waktu: 2026-07-03 14:09:30
```

> **Mengatur sensitivitas:** ubah `alert_threshold` di `config.php`. Nilai lebih besar
> = lebih toleran terhadap gangguan sesaat (alert lebih jarang, tapi lebih lambat).

## 2.5 Incident Feed

Panel **Incident Feed** di dashboard menampilkan riwayat transisi status terbaru:
- 🔴 entri **DOWN** saat server terkonfirmasi putus.
- 🟢 entri **RECOVERED** saat pulih, lengkap dengan durasi downtime.

Setiap entri menampilkan nama server, IP, dan waktu kejadian. Panel ikut ter-refresh
otomatis bersama dashboard.

## 2.6 Menghapus Server

1. Pada tabel, klik tombol **Delete** di baris server.
2. Konfirmasi penghapusan.

Penghapusan bersifat **soft delete** — server ditandai tidak aktif dan tidak lagi
ditampilkan/dipantau, namun datanya tetap tersimpan di database.

## 2.7 Tips Penggunaan

- **Uji alur down→up tanpa server mati:** jalankan `php tests/test_monitor.php`. Jika
  Telegram aktif, Anda akan menerima notifikasi uji.
- **Isi data demo cepat:** `php tests/seed_data.php` menambah beberapa server contoh
  agar peta & feed langsung terisi.
- **Ambang batas alert:** untuk jaringan yang kurang stabil, naikkan `alert_threshold`
  agar tidak sering menerima alarm palsu.
- **Zona waktu:** pastikan `timezone` di `config.php` sesuai lokasi Anda agar timestamp benar.

## 2.8 Ringkasan Alur Kerja

```
[Tambah Server]  → ping perdana → tampil di peta & tabel
        │
        ▼
[Cron tiap menit] → ping semua server → catat hasil
        │
        ├─ status berubah UP→DOWN (≥ ambang)  → catat incident + 🔴 Telegram
        ├─ status berubah DOWN→UP             → catat incident + 🟢 Telegram (+ durasi)
        │
        ▼
[Dashboard auto-refresh 30 detik] → perbarui peta, tabel, Incident Feed
```
