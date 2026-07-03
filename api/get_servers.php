<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once '../includes/db.php';
require_once '../includes/functions.php';

try {
    loadConfig();
    $pdo = getDB();

    $sql = "
        SELECT
            s.id, s.name, s.ip_address, s.latitude, s.longitude, s.description,
            ss.last_status, ss.last_response_time, ss.last_check, ss.uptime_percentage
        FROM servers s
        LEFT JOIN server_stats ss ON s.id = ss.server_id
        WHERE s.is_active = 1
        ORDER BY s.id DESC
    ";
    $rows = $pdo->query($sql)->fetchAll();

    $response = [];
    foreach ($rows as $row) {
        $response[] = [
            'id'            => (int) $row['id'],
            'name'          => $row['name'],
            'ip_address'    => $row['ip_address'],
            'latitude'      => floatval($row['latitude']),
            'longitude'     => floatval($row['longitude']),
            'description'   => $row['description'],
            'status'        => $row['last_status'] ?: 'unknown',
            'response_time' => $row['last_response_time'],
            'last_check'    => formatDate($row['last_check']),
            'uptime'        => number_format($row['uptime_percentage'] ?: 0, 2),
        ];
    }

    echo json_encode(['success' => true, 'servers' => $response]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
