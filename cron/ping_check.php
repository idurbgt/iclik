<?php
/**
 * Cron script to check all servers status
 * Run this script every minute via cron:
 * * * * * * /usr/bin/php /path/to/uptime-php/cron/ping_check.php
 */

require_once dirname(__FILE__) . '/../includes/json_handler.php';
require_once dirname(__FILE__) . '/../includes/functions.php';

// Set timezone
date_default_timezone_set('Asia/Jakarta');

echo "[" . date('Y-m-d H:i:s') . "] Starting ping checks...\n";

try {
    $jsonHandler = new JsonHandler();

    // Get all active servers
    $servers = $jsonHandler->getServers();
    $total_servers = count($servers);
    $checked = 0;
    
    echo "Found $total_servers active servers to check\n";

    foreach ($servers as $server) {
        $server_id = $server['id'];
        $name = $server['name'] ?: 'Server #' . $server_id;
        $ip = $server['ip_address'];
        
        echo "Checking $name ($ip)... ";
        
        // Ping server with retry logic
        $max_retries = 3;
        $ping_result = null;
        
        for ($i = 0; $i < $max_retries; $i++) {
            $ping_result = pingServer($ip);
            if ($ping_result['status'] === 'up') {
                break;
            }
            if ($i < $max_retries - 1) {
                sleep(1); // Wait 1 second before retry
            }
        }
        
        // Log result
        $jsonHandler->addPingLog($server_id, $ping_result['status'], $ping_result['response_time']);
        
        // Update stats
        $jsonHandler->updateServerStats($server_id, $ping_result['status'], $ping_result['response_time']);
        
        $status_text = $ping_result['status'] === 'up' ? 
            "UP (" . $ping_result['response_time'] . "ms)" : 
            "DOWN";
        echo "$status_text\n";
        
        // Log to file
        logMessage("Server $name ($ip): $status_text");
        
        $checked++;
    }

    echo "[" . date('Y-m-d H:i:s') . "] Completed checking $checked/$total_servers servers\n";
    echo "----------------------------------------\n";

    logMessage("Completed ping check for $checked servers");
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    logMessage("ERROR: " . $e->getMessage());
    exit(1);
}

exit(0);
?>