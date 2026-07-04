<?php
require_once __DIR__ . '/telegram.php';

/**
 * Cek status server via koneksi TCP (fsockopen) — TIDAK memakai exec()/ICMP,
 * sehingga aman di server yang menonaktifkan exec (hardening).
 *
 * Host dianggap UP bila salah satu port berhasil dikoneksi ATAU secara aktif
 * menolak koneksi (connection refused = host hidup, hanya port tertutup).
 * Port yang dicoba & timeout dapat diatur di config.php (ping_ports, ping_timeout).
 *
 * @param string $ip
 * @return array ['status' => 'up'|'down', 'response_time' => int|null]
 */
function pingServer($ip) {
    $config  = loadConfig();
    $ports   = !empty($config['ping_ports']) ? $config['ping_ports'] : [80, 443];
    $timeout = $config['ping_timeout'] ?? 3;

    foreach ($ports as $port) {
        $errno = 0;
        $errstr = '';
        $start = microtime(true);
        $conn = @fsockopen($ip, (int) $port, $errno, $errstr, $timeout);
        $elapsed = (int) round((microtime(true) - $start) * 1000);

        if ($conn) {
            fclose($conn);
            return ['status' => 'up', 'response_time' => $elapsed];
        }

        // ECONNREFUSED (Linux 111 / Windows 10061): host hidup, port tertutup → tetap UP
        if ($errno === 111 || $errno === 10061) {
            return ['status' => 'up', 'response_time' => $elapsed];
        }
        // Selain itu (timeout / unreachable) → coba port berikutnya
    }

    return ['status' => 'down', 'response_time' => null];
}

/**
 * Validate IP address format
 */
function validateIP($ip) {
    return filter_var($ip, FILTER_VALIDATE_IP) !== false;
}

/**
 * Clean and sanitize input data
 */
function cleanInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

/**
 * Format date for display (jam:menit:detik)
 */
function formatDate($date) {
    if (!$date) return 'Never';
    return date('H:i:s', strtotime($date));
}

/**
 * Ubah durasi (detik) menjadi teks ringkas: "2j 5m", "1h 3j", dst.
 */
function formatDuration($seconds) {
    if ($seconds === null || $seconds < 0) {
        return '-';
    }
    $seconds = (int) $seconds;
    $d = intdiv($seconds, 86400);
    $h = intdiv($seconds % 86400, 3600);
    $m = intdiv($seconds % 3600, 60);
    $s = $seconds % 60;

    $parts = [];
    if ($d > 0) $parts[] = "{$d}h";   // hari
    if ($h > 0) $parts[] = "{$h}j";   // jam
    if ($m > 0) $parts[] = "{$m}m";   // menit
    if (empty($parts)) $parts[] = "{$s}d"; // detik
    return implode(' ', $parts);
}

/**
 * Log message to file
 */
function logMessage($message) {
    $logDir = dirname(__DIR__) . '/data/logs/';
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }

    $logFile = $logDir . date('Y-m-d') . '.log';
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$timestamp] $message\n", FILE_APPEND | LOCK_EX);
}

/**
 * Muat konfigurasi aplikasi (config.php). Mengembalikan default aman untuk
 * bagian telegram/threshold/timezone bila tidak diisi.
 */
function loadConfig() {
    static $config = null;
    if ($config !== null) {
        return $config;
    }

    $path = dirname(__DIR__) . '/config.php';
    $config = file_exists($path) ? require $path : [];

    $config['database'] = array_merge([
        'host'    => 'localhost',
        'name'    => 'iclik',
        'user'    => 'root',
        'pass'    => '',
        'charset' => 'utf8mb4',
    ], $config['database'] ?? []);

    $config['telegram'] = array_merge([
        'enabled'   => false,
        'bot_token' => '',
        'chat_id'   => '',
        'timeout'   => 5,
    ], $config['telegram'] ?? []);

    $config['alert_threshold'] = $config['alert_threshold'] ?? 2;
    $config['timezone'] = $config['timezone'] ?? 'Asia/Jakarta';
    $config['ping_ports'] = !empty($config['ping_ports']) ? $config['ping_ports'] : [80, 443];
    $config['ping_timeout'] = $config['ping_timeout'] ?? 3;

    // Samakan zona waktu PHP aplikasi (idempoten karena config di-cache)
    date_default_timezone_set($config['timezone']);

    return $config;
}
