<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/monitor.php';

try {
    loadConfig();
    $pdo = getDB();

    $id = intval($_POST['id'] ?? 0);
    if ($id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid server ID']);
        exit;
    }

    // Ambil server aktif
    $stmt = $pdo->prepare("SELECT id, name, ip_address FROM servers WHERE id = ? AND is_active = 1");
    $stmt->execute([$id]);
    $server = $stmt->fetch();

    if (!$server) {
        echo json_encode(['success' => false, 'message' => 'Server not found']);
        exit;
    }

    // Ping + proses (log + state machine + notifikasi bila ada transisi)
    $ping_result = pingServer($server['ip_address']);
    $check = processCheckResult($pdo, $server, $ping_result['status'], $ping_result['response_time']);

    echo json_encode([
        'success'          => true,
        'status'           => $ping_result['status'],
        'confirmed_status' => $check['confirmed_status'],
        'transition'       => $check['transition'],
        'response_time'    => $ping_result['response_time'],
        'checked_at'       => date('H:i:s'),
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
