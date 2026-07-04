# Panduan Deploy Iclik ke VPS (subdomain `ojs.sentradata.id`)

Panduan ini mengasumsikan: VPS Linux (Ubuntu/Debian), akses SSH (sudo), dan stack
**PHP + MySQL + web server** sudah terpasang. Target akhir: aplikasi terbuka di
`http://ojs.sentradata.id` (lalu di-HTTPS-kan).

Ganti nilai berikut sesuai punya Anda:
- `SERVER_IP` — IP publik VPS
- `PASSWORD_KUAT` — password database yang Anda buat

---

## Langkah 1 — Arahkan DNS Subdomain

Di panel DNS domain `sentradata.id`, buat record:

| Type | Name | Value        | TTL  |
|------|------|--------------|------|
| A    | ojs  | `SERVER_IP`  | 3600 |

Verifikasi (tunggu propagasi beberapa menit):
```bash
ping ojs.sentradata.id       # harus mengarah ke SERVER_IP
# atau: dig +short ojs.sentradata.id
```

---

## Langkah 2 — Clone Repo ke Server

SSH ke server, lalu clone ke folder docroot subdomain:
```bash
sudo mkdir -p /var/www/ojs.sentradata.id
sudo chown -R $USER:$USER /var/www/ojs.sentradata.id

git clone https://github.com/idurbgt/iclik.git /var/www/ojs.sentradata.id
cd /var/www/ojs.sentradata.id
```
> Jika repo **private**, gunakan Personal Access Token:
> `git clone https://<TOKEN>@github.com/idurbgt/iclik.git /var/www/ojs.sentradata.id`

---

## Langkah 3 — Buat Database

Impor skema (otomatis membuat database `iclik` + semua tabel):
```bash
sudo mysql < /var/www/ojs.sentradata.id/sql/schema.sql
# atau bila pakai password root:
# mysql -u root -p < /var/www/ojs.sentradata.id/sql/schema.sql
```

Buat user database khusus (lebih aman daripada memakai root):
```bash
sudo mysql
```
Di prompt MySQL:
```sql
CREATE USER 'iclik_user'@'localhost' IDENTIFIED BY 'PASSWORD_KUAT';
GRANT ALL PRIVILEGES ON iclik.* TO 'iclik_user'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

Cek tabel sudah terbuat:
```bash
sudo mysql -e "USE iclik; SHOW TABLES;"
# Harus muncul: servers, ping_logs, server_stats, incidents
```

---

## Langkah 4 — Konfigurasi Aplikasi

`config.php` tidak ikut di repo (berisi rahasia). Salin dari template lalu isi:
```bash
cd /var/www/ojs.sentradata.id
cp config.example.php config.php
nano config.php
```
Isi minimal bagian database:
```php
'database' => [
    'host'    => 'localhost',
    'name'    => 'iclik',
    'user'    => 'iclik_user',
    'pass'    => 'PASSWORD_KUAT',
    'charset' => 'utf8mb4',
],
```
(Telegram bisa diisi nanti — lihat Langkah 9.)

---

## Langkah 5 — Set Izin File

Web server (biasanya `www-data`) perlu membaca semua file dan **menulis** ke `data/`:
```bash
sudo chown -R www-data:www-data /var/www/ojs.sentradata.id
sudo find /var/www/ojs.sentradata.id -type d -exec chmod 755 {} \;
sudo find /var/www/ojs.sentradata.id -type f -exec chmod 644 {} \;
sudo chmod -R 775 /var/www/ojs.sentradata.id/data
sudo chmod 640 /var/www/ojs.sentradata.id/config.php
```

---

## Langkah 6 — Konfigurasi Web Server (subdomain)

### 6A. Jika APACHE

Buat vhost:
```bash
sudo nano /etc/apache2/sites-available/ojs.sentradata.id.conf
```
Isi:
```apache
<VirtualHost *:80>
    ServerName ojs.sentradata.id
    DocumentRoot /var/www/ojs.sentradata.id

    <Directory /var/www/ojs.sentradata.id>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog  ${APACHE_LOG_DIR}/ojs_error.log
    CustomLog ${APACHE_LOG_DIR}/ojs_access.log combined
</VirtualHost>
```
> `AllowOverride All` WAJIB agar `.htaccess` aktif — inilah yang memproteksi
> `config.php`, `data/`, dan `includes/`.

Aktifkan:
```bash
sudo a2ensite ojs.sentradata.id.conf
sudo a2enmod rewrite
sudo apache2ctl configtest && sudo systemctl reload apache2
```

### 6B. Jika NGINX

> Nginx **tidak membaca `.htaccess`**, jadi proteksi file rahasia harus ditulis manual di sini.

Buat server block:
```bash
sudo nano /etc/nginx/sites-available/ojs.sentradata.id
```
Isi (sesuaikan versi PHP-FPM — cek dengan `ls /run/php/`):
```nginx
server {
    listen 80;
    server_name ojs.sentradata.id;
    root /var/www/ojs.sentradata.id;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.1-fpm.sock;   # <-- sesuaikan versi
    }

    # Proteksi file rahasia & internal (pengganti .htaccess)
    location = /config.php        { deny all; return 404; }
    location ^~ /includes/        { deny all; return 404; }
    location ^~ /data/            { deny all; return 404; }
    location ^~ /tests/           { deny all; return 404; }
    location ~ /\.(ht|git)        { deny all; return 404; }
}
```
Aktifkan:
```bash
sudo ln -s /etc/nginx/sites-available/ojs.sentradata.id /etc/nginx/sites-enabled/
sudo nginx -t && sudo systemctl reload nginx
```

---

## Langkah 7 — Uji Backend (sebelum buka browser)

```bash
cd /var/www/ojs.sentradata.id
php tests/test_monitor.php     # harus semua [PASS]
```
Jika ada `[FAIL]` koneksi → cek `config.php`. Pengecekan status memakai TCP
(`fsockopen`), jadi TIDAK butuh `exec()` — aman di server ber-hardening. Sesuaikan
`ping_ports` di `config.php` dengan port layanan yang dipantau (default 80 & 443).

(Opsional) isi data contoh agar dashboard langsung terlihat:
```bash
php tests/seed_data.php
```

---

## Langkah 8 — Buka di Browser

```
http://ojs.sentradata.id
```
Anda seharusnya melihat dashboard peta + tabel + Incident Feed.

---

## Langkah 9 — Setup Cron (monitoring otomatis)

Jalankan pengecekan tiap menit sebagai user web server:
```bash
sudo crontab -u www-data -e
```
Tambahkan:
```
* * * * * /usr/bin/php /var/www/ojs.sentradata.id/cron/ping_check.php >> /var/www/ojs.sentradata.id/data/logs/cron.log 2>&1
```
Cek path PHP dengan `which php` bila `/usr/bin/php` tidak sesuai.

---

## Langkah 10 — HTTPS (Let's Encrypt) — sangat disarankan

```bash
# Apache
sudo apt install certbot python3-certbot-apache -y
sudo certbot --apache -d ojs.sentradata.id

# Nginx
sudo apt install certbot python3-certbot-nginx -y
sudo certbot --nginx -d ojs.sentradata.id
```
Certbot otomatis mengubah vhost ke HTTPS dan memperpanjang sertifikat.
Setelah ini akses via `https://ojs.sentradata.id`.

---

## Langkah 11 — Aktifkan Notifikasi Telegram (opsional)

Edit `config.php`:
```php
'telegram' => [
    'enabled'   => true,
    'bot_token' => 'TOKEN_DARI_BOTFATHER',
    'chat_id'   => 'CHAT_ID_ANDA',
    'timeout'   => 5,
],
```
Uji:
```bash
php tests/test_telegram.php
```

---

## Update Aplikasi ke Depan

Saat ada perubahan di GitHub, cukup di server:
```bash
cd /var/www/ojs.sentradata.id
git pull
sudo chown -R www-data:www-data data
```
`config.php` tidak akan tertimpa (di-gitignore).

---

## Troubleshooting Cepat

| Gejala | Solusi |
|--------|--------|
| 403 Forbidden | Cek izin folder & `Require all granted` (Apache) / `root` benar (Nginx). |
| 500 / halaman putih | Lihat log: `sudo tail -f /var/log/apache2/ojs_error.log` atau `/var/log/nginx/error.log`. Sering karena config DB salah. |
| Bisa buka `config.php` dari browser | Apache: pastikan `AllowOverride All`. Nginx: pastikan blok `location = /config.php` ada. |
| Status selalu down | Cek pakai TCP (`fsockopen`) bukan ICMP. Sesuaikan `ping_ports` di `config.php` dengan port yang benar-benar terbuka di server target; cek firewall keluar. |
| Cron tak jalan | `sudo tail -f /var/www/ojs.sentradata.id/data/logs/cron.log`; cek path PHP. |
| Subdomain tak terbuka | DNS belum propagasi / A record salah; cek `dig +short ojs.sentradata.id`. |
