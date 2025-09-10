<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once '../includes/json_handler.php';
require_once '../includes/functions.php';

try {
    $jsonHandler = new JsonHandler();

    // Get and validate input
    $name = cleanInput($_POST['name'] ?? '');
    $ip_address = cleanInput($_POST['ip_address'] ?? '');
    $latitude = floatval($_POST['latitude'] ?? 0);
    $longitude = floatval($_POST['longitude'] ?? 0);
    $description = cleanInput($_POST['description'] ?? '');

    // Validate IP
    if (!validateIP($ip_address)) {
        echo json_encode(['success' => false, 'message' => 'Invalid IP address format']);
        exit;
    }

    // Validate coordinates
    if ($latitude == 0 || $longitude == 0) {
        echo json_encode(['success' => false, 'message' => 'Please select location on map']);
        exit;
    }

    // Prepare data
    $serverData = [
        'name' => $name,
        'ip_address' => $ip_address,
        'latitude' => $latitude,
        'longitude' => $longitude,
        'description' => $description
    ];

    // Add server
    $result = $jsonHandler->addServer($serverData);
    
    if ($result['success']) {
        $server_id = $result['server_id'];
        
        // Initial ping check
        $ping_result = pingServer($ip_address);
        
        // Log ping result
        $jsonHandler->addPingLog($server_id, $ping_result['status'], $ping_result['response_time']);
        
        // Update stats
        $jsonHandler->updateServerStats($server_id, $ping_result['status'], $ping_result['response_time']);
        
        echo json_encode([
            'success' => true, 
            'message' => 'Server added successfully',
            'initial_status' => $ping_result['status']
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => $result['message']]);
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>