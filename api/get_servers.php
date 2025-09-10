<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once '../includes/json_handler.php';
require_once '../includes/functions.php';

try {
    $jsonHandler = new JsonHandler();
    $servers = $jsonHandler->getServers();
    
    $response = [];
    foreach ($servers as $server) {
        $response[] = [
            'id' => $server['id'],
            'name' => $server['name'],
            'ip_address' => $server['ip_address'],
            'latitude' => floatval($server['latitude']),
            'longitude' => floatval($server['longitude']),
            'description' => $server['description'],
            'status' => $server['last_status'] ?: 'unknown',
            'response_time' => $server['last_response_time'],
            'last_check' => formatDate($server['last_check']),
            'uptime' => number_format($server['uptime_percentage'] ?: 0, 2)
        ];
    }

    echo json_encode(['success' => true, 'servers' => $response]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>