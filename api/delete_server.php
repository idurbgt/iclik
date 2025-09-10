<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once '../includes/json_handler.php';

try {
    $jsonHandler = new JsonHandler();
    
    $id = intval($_POST['id'] ?? 0);

    if ($id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid server ID']);
        exit;
    }

    // Soft delete server
    $result = $jsonHandler->deleteServer($id);
    
    if ($result['success']) {
        echo json_encode(['success' => true, 'message' => 'Server deleted successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => $result['message']]);
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>