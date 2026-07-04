# Iclik
Aplikasi web untuk monitoring server menggunakan ICMP/ping dengan visualisasi peta interaktif
dan notifikasi Telegram saat server putus/terhubung kembali.

Nama "Iclik" berasal dari alur utamanya: **klik lokasi di peta** untuk menempatkan server.

## Features

- Server monitoring dengan ICMP/ping via cron
- **Notifikasi Telegram** saat transisi status (down / recovered) — dengan durasi downtime
- Anti-flapping: alert DOWN dikirim setelah ambang kegagalan berturut-turut (default 2×)
- Interactive map visualization (Leaflet.js) dengan titik pusat di Jakarta
- Click-to-select server location
- **Incident Feed** di dashboard (riwayat kejadian down/up dari database)
- Auto refresh dashboard setiap 30 detik
- Retry logic (3×) sebelum satu percobaan dinyatakan gagal
- Uptime percentage & response time statistics
- Penyimpanan MySQL

## Requirements

- PHP >= 7.4
- MySQL >= 5.7 (atau MariaDB setara)
- Web Server (Apache/Nginx) atau `php -S` untuk development
- PHP extensions: `pdo_mysql`, `curl` (opsional, untuk Telegram — ada fallback tanpa cURL)
- **Tidak butuh `exec()`** — pengecekan status memakai koneksi TCP (`fsockopen`), aman di server ber-hardening
- Cron access untuk scheduled jobs

## Installation

### 1. Setup Project
```bash
cd /path/to/webserver
# Download atau extract project files ke folder iclik/
```

### 2. Import Database
```bash
mysql -u root -p < sql/schema.sql
```

### 3. Konfigurasi
Edit `config.php` (diproteksi dari akses browser oleh `.htaccess`):
```php
'database' => [
    'host' => 'localhost',
    'name' => 'iclik',
    'user' => 'root',
    'pass' => '',
],
```
Untuk mengaktifkan notifikasi Telegram, lihat bagian **Telegram** di bawah.

### 4. Set Permissions
```bash
chmod 755 cron/ping_check.php
chmod 755 data/            # untuk file log
```

### 5. Setup Cron Job
```bash
crontab -e
# Jalankan setiap menit:
* * * * * /usr/bin/php /path/to/iclik/cron/ping_check.php
```

### 6. Access Application
```
http://localhost/iclik/
```

## Telegram

1. Chat dengan **@BotFather** di Telegram → `/newbot` → salin **token**.
2. Kirim satu pesan ke bot Anda, lalu buka
   `https://api.telegram.org/bot<TOKEN>/getUpdates` untuk mengambil **chat_id**
   (untuk grup, tambahkan bot ke grup lalu kirim pesan; id grup biasanya negatif).
3. Isi `config.php`:
```php
'telegram' => [
    'enabled'   => true,
    'bot_token' => '123456789:ABCdef...',
    'chat_id'   => '123456789',
    'timeout'   => 5,
],
'alert_threshold' => 2,   // jumlah gagal berturut sebelum alert DOWN
```

Notifikasi hanya dikirim saat **transisi status**:
- 🔴 `up → down` (setelah mencapai `alert_threshold`)
- 🟢 `down → up` (seketika, disertai durasi downtime)

## Project Structure

```
iclik/
├── index.php                 # Dashboard (HTML, JS-driven)
├── config.php                # Konfigurasi DB + Telegram (diproteksi .htaccess)
├── .htaccess                 # Proteksi akses config.php
├── includes/
│   ├── db.php                # Koneksi MySQL (PDO)
│   ├── functions.php         # ping, validasi, config, logging, format
│   ├── monitor.php           # State machine status + notifikasi Telegram
│   └── telegram.php          # Pengirim pesan Telegram
├── api/
│   ├── get_servers.php       # GET  — daftar server + status
│   ├── add_server.php        # POST — tambah server + ping perdana
│   ├── delete_server.php     # POST — soft delete
│   ├── check_status.php      # POST — cek manual satu server
│   └── get_events.php        # GET  — incident feed
├── cron/
│   └── ping_check.php        # Script cron untuk ping semua server aktif
├── assets/
│   ├── css/style.css
│   └── js/app.js             # jQuery + Leaflet: peta, tabel, incident feed
├── sql/
│   └── schema.sql            # Skema database MySQL
├── data/
│   └── logs/                 # Log file harian (dibuat otomatis, diproteksi .htaccess)
├── SPEC.md
├── LICENSE
└── README.md
```

## Usage

### Adding New Server
1. Klik tombol "Add Server"
2. Isi form: Server Name, IP Address, Description (opsional)
3. Klik lokasi di peta untuk memilih koordinat
4. Klik "Save Server" — server langsung di-ping perdana

### Server Monitoring
- Dashboard auto-refresh setiap 30 detik
- Warna status: Hijau = Online, Merah = Offline, Abu-abu = Unknown
- Garis koneksi dari pusat (Jakarta) ke server: solid = aktif, putus-putus = terputus
- Panel **Incident Feed** menampilkan riwayat down/up terbaru

### Delete Server
- Klik tombol "Delete" di tabel, konfirmasi (soft delete)

## Data Storage

Seluruh data disimpan di MySQL:
- `servers` — konfigurasi server
- `ping_logs` — riwayat hasil ping
- `server_stats` — statistik & state machine (status terkonfirmasi, uptime, dll)
- `incidents` — catatan transisi status (untuk incident feed & audit)

## Troubleshooting

### Ping / Cek Status Tidak Bekerja
- Pengecekan memakai TCP (`fsockopen`), bukan ICMP. Pastikan port di `ping_ports`
  (`config.php`, default 80 & 443) sesuai layanan server yang dipantau
- Pastikan firewall server tidak memblok koneksi keluar ke port tersebut
- Uji manual: `php cron/ping_check.php`

### Cron Job Not Running
- Cek crontab: `crontab -l`
- Cek log cron: `/var/log/cron` atau `/var/log/syslog`

### Telegram Tidak Terkirim
- Pastikan `telegram.enabled = true` dan token/chat_id benar
- Uji token: buka `https://api.telegram.org/bot<TOKEN>/getMe`
- Notifikasi hanya dikirim saat transisi, bukan setiap ping

## Testing

Skrip pengujian CLI tersedia di folder `tests/` (butuh PHP + `config.php` terisi):
```bash
php tests/test_monitor.php    # uji state machine, incident, durasi downtime (self-cleaning)
php tests/test_telegram.php   # kirim 1 pesan uji Telegram
php tests/seed_data.php       # isi data contoh untuk uji UI (--clean untuk hapus)
```
Lihat `tests/README.md` untuk detail.

## Notes

- Soft delete (server ditandai tidak aktif, tidak dihapus fisik)
- Ping timeout 5 detik dengan retry 3× per percobaan
- Alert DOWN dikirim setelah `alert_threshold` kegagalan berturut-turut

## Security

- Validasi format IP address
- Prepared statements (PDO) untuk semua query
- Tidak menjalankan perintah shell (`exec`) — cek status via `fsockopen`, cocok untuk server ber-hardening
- `config.php` dan folder `data/` diproteksi dari akses browser via `.htaccess`

## License

MIT License
