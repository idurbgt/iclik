# Tests

Skrip pengujian manual/CLI untuk Iclik. Membutuhkan PHP terpasang dan `config.php`
yang sudah diisi (database wajib; Telegram opsional).

Jalankan dari root proyek:

## 1. Uji state machine + incident (otomatis)
```bash
php tests/test_monitor.php
```
Menguji alur transisi status (pending → down → recovery), pencatatan incident,
perhitungan durasi downtime, konsistensi statistik, dan `formatDuration()`.
Membuat 1 server uji sementara lalu menghapusnya otomatis (tidak mengotori data).

> Jika `telegram.enabled = true`, uji ini akan benar-benar mengirim 2 notifikasi
> (down & recovery) ke Telegram Anda.

## 2. Uji notifikasi Telegram
```bash
php tests/test_telegram.php
```
Mengirim satu pesan uji memakai `bot_token` & `chat_id` di `config.php`.
Berhenti dengan pesan jika Telegram belum diaktifkan/diisi.

## 3. Data contoh untuk uji UI
```bash
php tests/seed_data.php          # tambah server demo (+1 insiden)
php tests/seed_data.php --clean  # hapus semua server demo
```
Menambah beberapa server demo lengkap dengan status awal, sehingga peta,
tabel, dan Incident Feed langsung terlihat tanpa menunggu cron.
