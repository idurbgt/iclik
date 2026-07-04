# Spesifikasi Aplikasi Iclik — Monitoring Server

> Dokumen ini mendeskripsikan aplikasi **sebagaimana yang diimplementasikan**:
> penyimpanan **MySQL**, monitoring ICMP/ping via cron, dan **notifikasi Telegram**
> saat terjadi transisi status server (putus / terhubung kembali).

## 1. Gambaran Umum
Aplikasi web untuk memantau status server menggunakan ICMP/ping dengan visualisasi peta
interaktif. Saat sebuah server putus atau kembali terhubung, aplikasi mengirim notifikasi
ke Telegram. Nama "Iclik" berasal dari alur utamanya: pengguna **mengklik lokasi di peta**
untuk menempatkan server.

## 2. Teknologi Stack
- **Backend**: PHP (Native/Vanilla), PHP >= 7.4
- **Database**: MySQL >= 5.7 (via PDO)
- **Frontend**: HTML, CSS, JavaScript
- **Library JS**: jQuery 3.6.0 (AJAX & DOM), Leaflet.js 1.9.4 (peta)
- **Peta**: Leaflet.js + tile OpenStreetMap
- **Notifikasi**: Telegram Bot API (cURL, dengan fallback `file_get_contents`)

## 3. Fitur Utama

### 3.1 Manajemen Server
- **Tambah Server** (`api/add_server.php`)
  - Validasi IP (`FILTER_VALIDATE_IP`), cegah duplikat IP pada server aktif
  - Pilih lokasi dari peta (click to select)
  - Ping perdana langsung setelah server dibuat
- **Hapus Server** (`api/delete_server.php`) — soft delete (`is_active = 0`)
- **Cek Status Manual** (`api/check_status.php`) — ping satu server on-demand

> **Edit Server belum diimplementasikan** (lihat [Bagian 13](#13-fitur-belum-diimplementasi)).

### 3.2 Dashboard Monitoring
- **Peta Interaktif** — marker pusat "Monitoring Center" di Jakarta; server sebagai marker
  berwarna (hijau=up, merah=down, abu-abu=unknown); garis koneksi solid (up) / putus-putus (down).
- **Tabel Status Server** — Name, IP, Status, Response Time, Uptime, Last Check, Actions.
- **Incident Feed** — panel berisi riwayat transisi status terbaru (down/up + durasi downtime),
  bersumber dari tabel `incidents`.
- **Auto refresh** setiap 30 detik + tombol refresh manual.

### 3.3 Monitoring Engine
- **ICMP Ping Check** (`cron/ping_check.php`) — cron setiap 1 menit
  - Timeout 5 detik per ping; retry hingga 3× per percobaan
  - Cross-platform (Windows `ping -n`, Linux/Mac `ping -c`)
  - IP di-escape dengan `escapeshellarg()`
- **State machine status** (lihat [Bagian 9](#9-state-machine--notifikasi))
- **Logging** — hasil ping ke `ping_logs`, insiden ke `incidents`, jejak cron ke file harian.

### 3.4 Notifikasi Telegram
- Dikirim **hanya saat transisi status**:
  - 🔴 `up → down` — setelah mencapai `alert_threshold` kegagalan berturut-turut
  - 🟢 `down → up` — seketika, disertai durasi downtime
- Konfigurasi di `config.php` (`telegram.enabled`, `bot_token`, `chat_id`).

## 4. Skema Database (MySQL)

Lihat `sql/schema.sql`. Empat tabel:

### 4.1 `servers`
Konfigurasi server yang dipantau.
```sql
id, name, ip_address, latitude, longitude, description,
is_active, created_at, updated_at
```

### 4.2 `ping_logs`
Riwayat hasil setiap ping.
```sql
id, server_id (FK), status ENUM('up','down'), response_time, checked_at
```

### 4.3 `server_stats`
Statistik + state machine per server (satu baris per server).
```sql
server_id (PK/FK), last_status ENUM('up','down','unknown'),
last_raw_status, last_check, last_response_time, uptime_percentage,
total_checks, total_up, total_down,
consecutive_failures,   -- untuk ambang batas alert
down_since              -- untuk hitung durasi downtime
```

### 4.4 `incidents`
Catatan transisi status (untuk Incident Feed & audit).
```sql
id, server_id, server_name, ip_address, type ENUM('down','up'),
downtime_seconds,       -- terisi saat type='up'
created_at
```

## 5. Struktur File

```
iclik/
├── index.php                 # Dashboard (HTML, JS-driven)
├── config.php                # Konfigurasi DB + Telegram (diproteksi .htaccess)
├── .htaccess                 # Deny akses ke config.php
├── includes/
│   ├── db.php                # getDB() — koneksi MySQL PDO (singleton)
│   ├── functions.php         # pingServer, validateIP, cleanInput, loadConfig,
│   │                         #   formatDate, formatDuration, logMessage
│   ├── monitor.php           # recordCheck() + processCheckResult() (state machine + notif)
│   ├── telegram.php          # sendTelegram()
│   └── .htaccess             # Deny akses langsung
├── api/
│   ├── get_servers.php       # GET
│   ├── add_server.php        # POST
│   ├── delete_server.php     # POST
│   ├── check_status.php      # POST
│   └── get_events.php        # GET  (incident feed)
├── cron/
│   └── ping_check.php
├── assets/
│   ├── css/style.css
│   └── js/app.js
├── sql/
│   └── schema.sql
├── data/
│   ├── logs/                 # log harian (dibuat otomatis)
│   └── .htaccess             # Deny akses
├── SPEC.md
├── LICENSE
└── README.md
```

## 6. User Interface

### 6.1 Layout Dashboard
```
+------------------------------------------+
|  HEADER  [Add Server][Refresh][Last upd] |
+------------------------------------------+
|              MAP (full width)            |
|        Center (Jakarta) + Nodes          |
+---------------------------+--------------+
|  Server Status Table      | Incident     |
|  Name|IP|Status|Resp|...  | Feed (down/up)|
+---------------------------+--------------+
```

### 6.2 Add Server Modal
```
+-------------------------+
|     Add New Server      |
| Server Name: [______]   |
| IP Address:  [______]   |
| Description: [______]   |
| Select Location on Map: |
| [    Map Preview    ]   |
| [Save Server] [Cancel]  |
+-------------------------+
```

## 7. Alur Kerja

### 7.1 Menambah Server
1. Klik "Add Server" → modal muncul
2. Isi nama, IP, deskripsi; klik lokasi di peta
3. Submit (AJAX POST `api/add_server.php`)
4. Validasi IP & koordinat, cek duplikat IP
5. `INSERT` ke `servers` + inisialisasi baris `server_stats`
6. Ping perdana → `processCheckResult()` (log + state machine)
7. Dashboard di-refresh

### 7.2 Monitoring Flow (Cron)
1. Cron menjalankan `ping_check.php` tiap menit
2. Ambil semua server aktif
3. Untuk setiap server: ping (retry 3×) → `processCheckResult()`
4. Bila terjadi transisi status → catat `incidents` + kirim Telegram

### 7.3 Visualisasi Status (Frontend)
1. `app.js` memanggil `api/get_servers.php` & `api/get_events.php` tiap 30 detik
2. Perbarui marker + garis peta, tabel status, dan incident feed

## 8. Implementasi Cek Status (tanpa exec)

Pengecekan status memakai **koneksi TCP via `fsockopen`**, bukan ICMP/`exec`.
Ini penting agar berjalan di server yang menonaktifkan `exec()` (hardening).
Port yang dicoba & timeout diatur di `config.php` (`ping_ports`, `ping_timeout`).

`includes/functions.php`:
```php
function pingServer($ip) {
    $config  = loadConfig();
    $ports   = $config['ping_ports'] ?: [80, 443];
    $timeout = $config['ping_timeout'] ?? 3;

    foreach ($ports as $port) {
        $errno = 0; $errstr = '';
        $start = microtime(true);
        $conn = @fsockopen($ip, (int) $port, $errno, $errstr, $timeout);
        $elapsed = (int) round((microtime(true) - $start) * 1000);

        if ($conn) { fclose($conn); return ['status'=>'up','response_time'=>$elapsed]; }
        // ECONNREFUSED (111/10061) = host hidup, port tertutup → tetap UP
        if ($errno === 111 || $errno === 10061) return ['status'=>'up','response_time'=>$elapsed];
    }
    return ['status' => 'down', 'response_time' => null];
}
```

> Konsekuensi: host yang hanya merespons ICMP dan mem-*drop* semua TCP (fully
> firewalled) akan terbaca `down`. Sesuaikan `ping_ports` dengan layanan yang benar-benar
> terbuka pada target.

## 9. State Machine & Notifikasi

`includes/monitor.php` — `recordCheck()` menjalankan state machine dalam satu transaksi DB
(`SELECT ... FOR UPDATE`) untuk mencegah race condition antara cron dan request user:

- **Ping UP**
  - `consecutive_failures = 0`
  - Jika status terkonfirmasi sebelumnya `down` → **transisi `up`** (recovery), hitung
    `downtime = now - down_since`, kosongkan `down_since`.
  - `last_status = 'up'`
- **Ping DOWN**
  - `consecutive_failures += 1`
  - Jika status terkonfirmasi belum `down` **dan** `consecutive_failures >= alert_threshold`
    → **transisi `down`**, set `down_since = now`, `last_status = 'down'`.
  - Jika belum mencapai threshold → status **ditahan** (pending), belum berubah/alert.

`processCheckResult()` membungkus: `INSERT ping_logs` → `recordCheck()` → bila ada transisi:
`INSERT incidents` + `sendTelegram()` + tulis log file.

Recovery bersifat seketika (1 ping sukses cukup); hanya konfirmasi DOWN yang memakai threshold.

## 10. Konfigurasi (`config.php`)

```php
return [
    'database' => [ 'host'=>'localhost','name'=>'iclik','user'=>'root','pass'=>'','charset'=>'utf8mb4' ],
    'telegram' => [ 'enabled'=>false,'bot_token'=>'','chat_id'=>'','timeout'=>5 ],
    'alert_threshold' => 2,
    'timezone' => 'Asia/Jakarta',
];
```
File ini diproteksi oleh `.htaccess` (deny) agar kredensial tidak terbaca dari browser.

## 11. Kontrak API

Semua endpoint mengembalikan JSON (`Access-Control-Allow-Origin: *`).

- `GET api/get_servers.php` → `{ success, servers:[{id,name,ip_address,latitude,longitude,description,status,response_time,last_check,uptime}] }`
- `POST api/add_server.php` (name, ip_address, latitude, longitude, description) → `{ success, message, initial_status }`
- `POST api/delete_server.php` (id) → `{ success, message }`
- `POST api/check_status.php` (id) → `{ success, status, confirmed_status, transition, response_time, checked_at }`
- `GET api/get_events.php?limit=50` → `{ success, events:[{id,server_id,server_name,ip_address,type,timestamp,downtime}] }`

## 12. Keamanan
Sudah diterapkan:
- Prepared statements (PDO) untuk seluruh query
- Validasi IP (`FILTER_VALIDATE_IP`), sanitasi input (`trim`+`stripslashes`+`htmlspecialchars`)
- Tidak memakai `exec`/shell — cek status via `fsockopen` (tidak ada command injection)
- Transaksi + row lock pada state machine (mitigasi race condition)
- `config.php` & `data/` diproteksi `.htaccess`

Perlu diperhatikan / belum ada:
- Belum ada autentikasi login — siapa pun yang mengakses URL bisa menambah/menghapus server
- Belum ada CSRF protection & rate limiting
- CORS terbuka (`*`)
- Proteksi `.htaccess` hanya berlaku di Apache; pada Nginx perlu aturan setara

## 13. Fitur Belum Diimplementasi
- Edit server
- Notifikasi selain Telegram (email/WhatsApp/Discord)
- Export data / API publik / status page
- Grafik historis response time
- Autentikasi & multi-user, dark mode, grouping server
- Cek non-ICMP (HTTP/HTTPS, TCP port, SSL expiry)

## 14. Deployment
```bash
# 1. Letakkan folder di web server
cd /var/www/html   # extract ke iclik/

# 2. Buat database
mysql -u root -p < sql/schema.sql

# 3. Sesuaikan config.php (DB + Telegram)

# 4. Izin & cron
chmod 755 data/ cron/ping_check.php
crontab -e
* * * * * /usr/bin/php /var/www/html/iclik/cron/ping_check.php

# 5. Akses: http://localhost/iclik/
```
