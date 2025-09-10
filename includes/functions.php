<?php
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
 * Generate unique ID
 */
function generateId() {
    return uniqid() . '_' . time();
}

/**
 * Format date for display
 */
function formatDate($date) {
    if (!$date) return 'Never';
    return date('H:i:s', strtotime($date));
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
?>