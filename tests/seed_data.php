<?php
/**
 * Isi data contoh untuk pengujian UI / manual.
 * Menambah beberapa server demo beserta status awalnya (tanpa menunggu cron),
 * termasuk satu insiden agar Incident Feed langsung terlihat.
 *
 * Jalankan: php tests/seed_data.php
 * Hapus data demo: php tests/seed_data.php --clean
 */

if (php_sapi_name() !== 'cli') {
    die("Jalankan skrip ini dari command line (CLI).\n");
}

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

loadConfig();
$pdo = getDB();

$clean = in_array('--clean', $argv, true);

// Server demo ditandai deskripsi '[demo]' agar mudah dibersihkan
$samples = [
    // name, ip, lat, lng, status, response_time, is_down_incident
    ['Demo Google DNS', '8.8.8.8',       1.3521,  103.8198, 'up',      15,  false],
    ['Demo Router',     '192.168.1.1',  -6.2088,  106.8456, 'up',       3,  false],
    ['Demo Unreachable','203.0.113.10',  3.1390,  101.6869, 'down',   null,  true],
];

if ($clean) {
    $stmt = $pdo->prepare("DELETE FROM servers WHERE description = '[demo]'");
    $stmt->execute();
    echo "Menghapus " . $stmt->rowCount() . " server demo.\n";
    exit(0);
}

$added = 0;
foreach ($samples as $s) {
    list($name, $ip, $lat, $lng, $status, $rt, $isDownIncident) = $s;

    // Lewati bila IP sudah ada & aktif
    $dup = $pdo->prepare("SELECT id FROM servers WHERE ip_address = ? AND is_active = 1");
    $dup->execute([$ip]);
    if ($dup->fetch()) {
        echo "Lewati $name ($ip): sudah ada.\n";
        continue;
    }

    $pdo->prepare("INSERT INTO servers (name, ip_address, latitude, longitude, description) VALUES (?,?,?,?, '[demo]')")
        ->execute([$name, $ip, $lat, $lng]);
    $id = (int) $pdo->lastInsertId();

    // Statistik awal (tanpa cron) supaya warna & uptime langsung tampil
    $downSince = $isDownIncident ? date('Y-m-d H:i:s', time() - 300) : null; // 5 menit lalu
    $pdo->prepare("
        INSERT INTO server_stats
            (server_id, last_status, last_raw_status, last_check, last_response_time,
             uptime_percentage, total_checks, total_up, total_down, consecutive_failures, down_since)
        VALUES (?, ?, ?, NOW(), ?, ?, 1, ?, ?, ?, ?)
    ")->execute([
        $id, $status, $status, $rt,
        $status === 'up' ? 100 : 0,
        $status === 'up' ? 1 : 0,
        $status === 'up' ? 0 : 1,
        $status === 'up' ? 0 : 2,
        $downSince,
    ]);

    // Ping log awal
    $pdo->prepare("INSERT INTO ping_logs (server_id, status, response_time) VALUES (?, ?, ?)")
        ->execute([$id, $status, $rt]);

    // Insiden demo untuk yang down
    if ($isDownIncident) {
        $pdo->prepare("INSERT INTO incidents (server_id, server_name, ip_address, type, created_at) VALUES (?, ?, ?, 'down', ?)")
            ->execute([$id, $name, $ip, $downSince]);
    }

    echo "Ditambah: $name ($ip) — status $status\n";
    $added++;
}

echo "\nSelesai. $added server demo ditambahkan.\n";
echo "Buka http://localhost/iclik/ untuk melihat.\n";
echo "Untuk menghapus data demo: php tests/seed_data.php --clean\n";
