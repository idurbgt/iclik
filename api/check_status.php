<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once '../includes/json_handler.php';
require_once '../includes/functions.php';

try {
    $jsonHandler = new JsonHandler();
    
    $id = intval($_POST['id'] ?? 0);

    if ($id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid server ID']);
        exit;
    }

    // Get server info
    $server = $jsonHandler->getServerById($id);
    
    if ($server) {
        $server_id = $server['id'];
        $ip = $server['ip_address'];
        
        // Perform ping check
        $ping_result = pingServer($ip);
        
        // Log result
        $jsonHandler->addPingLog($server_id, $ping_result['status'], $ping_result['response_time']);
        
        // Update stats
        $jsonHandler->updateServerStats($server_id, $ping_result['status'], $ping_result['response_time']);
        
        echo json_encode([
            'success' => true,
            'status' => $ping_result['status'],
            'response_time' => $ping_result['response_time'],
            'checked_at' => date('H:i:s')
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Server not found']);
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>