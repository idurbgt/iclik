<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once '../includes/db.php';
require_once '../includes/functions.php';

try {
    loadConfig();
    $pdo = getDB();

    $limit = intval($_GET['limit'] ?? 50);
    if ($limit <= 0 || $limit > 200) {
        $limit = 50;
    }

    $stmt = $pdo->prepare("
        SELECT id, server_id, server_name, ip_address, type, downtime_seconds, created_at
        FROM incidents
        ORDER BY created_at DESC, id DESC
        LIMIT ?
    ");
    $stmt->bindValue(1, $limit, PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt->fetchAll();

    $events = [];
    foreach ($rows as $row) {
        $events[] = [
            'id'          => (int) $row['id'],
            'server_id'   => (int) $row['server_id'],
            'server_name' => $row['server_name'],
            'ip_address'  => $row['ip_address'],
            'type'        => $row['type'],
            'timestamp'   => $row['created_at'],
            'downtime'    => $row['downtime_seconds'] !== null ? formatDuration($row['downtime_seconds']) : null,
        ];
    }

    echo json_encode(['success' => true, 'events' => $events]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
