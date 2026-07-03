<?php
require_once __DIR__ . '/telegram.php';

/**
 * Ping server using ICMP
 */
function pingServer($ip) {
    $timeout = 5;

    // Sanitize IP address
    $ip = escapeshellarg($ip);

    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
        // Windows
        $ping = exec("ping -n 1 -w " . ($timeout * 1000) . " $ip", $output, $status);
    } else {
        // Linux/Mac
        $ping = exec("ping -c 1 -W $timeout $ip 2>&1", $output, $status);
    }

    if ($status === 0) {
        // Parse response time from output
        $output_string = implode(' ', $output);
        preg_match('/time[<=]([0-9.]+)\s*ms/', $output_string, $matches);
        $responseTime = isset($matches[1]) ? round(floatval($matches[1])) : 0;

        return [
            'status' => 'up',
            'response_time' => $responseTime
        ];
    }

    return [
        'status' => 'down',
        'response_time' => null
    ];
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

    // Samakan zona waktu PHP aplikasi (idempoten karena config di-cache)
    date_default_timezone_set($config['timezone']);

    return $config;
}
