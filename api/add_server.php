<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/monitor.php';

try {
    loadConfig();
    $pdo = getDB();

    // Ambil & validasi input
    $name        = cleanInput($_POST['name'] ?? '');
    $ip_address  = cleanInput($_POST['ip_address'] ?? '');
    $latitude    = floatval($_POST['latitude'] ?? 0);
    $longitude   = floatval($_POST['longitude'] ?? 0);
    $description = cleanInput($_POST['description'] ?? '');

    // Validasi IP
    if (!validateIP($ip_address)) {
        echo json_encode(['success' => false, 'message' => 'Invalid IP address format']);
        exit;
    }

    // Validasi koordinat (harus dipilih dari peta)
    if ($latitude == 0 && $longitude == 0) {
        echo json_encode(['success' => false, 'message' => 'Please select location on map']);
        exit;
    }

    // Cek duplikat IP pada server aktif
    $dup = $pdo->prepare("SELECT id FROM servers WHERE ip_address = ? AND is_active = 1");
    $dup->execute([$ip_address]);
    if ($dup->fetch()) {
        echo json_encode(['success' => false, 'message' => 'IP address already exists']);
        exit;
    }

    // Simpan server
    $ins = $pdo->prepare("
        INSERT INTO servers (name, ip_address, latitude, longitude, description)
        VALUES (?, ?, ?, ?, ?)
    ");
    $ins->execute([$name, $ip_address, $latitude, $longitude, $description]);
    $server_id = (int) $pdo->lastInsertId();

    // Inisialisasi baris statistik
    $pdo->prepare("INSERT INTO server_stats (server_id) VALUES (?)")->execute([$server_id]);

    // Ping perdana (status awal 'unknown' -> satu kegagalan belum memicu alert)
    $ping_result = pingServer($ip_address);
    $serverSnapshot = ['id' => $server_id, 'name' => $name, 'ip_address' => $ip_address];
    processCheckResult($pdo, $serverSnapshot, $ping_result['status'], $ping_result['response_time']);

    echo json_encode([
        'success'        => true,
        'message'        => 'Server added successfully',
        'initial_status' => $ping_result['status'],
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
