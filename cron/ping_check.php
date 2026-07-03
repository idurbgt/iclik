<?php
/**
 * Cron script untuk mengecek status semua server aktif.
 * Jalankan setiap menit via cron:
 * * * * * * /usr/bin/php /path/to/iclik/cron/ping_check.php
 */

require_once dirname(__FILE__) . '/../includes/db.php';
require_once dirname(__FILE__) . '/../includes/functions.php';
require_once dirname(__FILE__) . '/../includes/monitor.php';

loadConfig(); // set timezone dari config

echo "[" . date('Y-m-d H:i:s') . "] Starting ping checks...\n";

try {
    $pdo = getDB();

    // Ambil semua server aktif
    $servers = $pdo->query("SELECT id, name, ip_address FROM servers WHERE is_active = 1")->fetchAll();
    $total_servers = count($servers);
    $checked = 0;

    echo "Found $total_servers active servers to check\n";

    foreach ($servers as $server) {
        $server_id = $server['id'];
        $name = ($server['name'] !== '' && $server['name'] !== null) ? $server['name'] : ('Server #' . $server_id);
        $ip = $server['ip_address'];

        echo "Checking $name ($ip)... ";

        // Ping dengan retry
        $max_retries = 3;
        $ping_result = null;

        for ($i = 0; $i < $max_retries; $i++) {
            $ping_result = pingServer($ip);
            if ($ping_result['status'] === 'up') {
                break;
            }
            if ($i < $max_retries - 1) {
                sleep(1);
            }
        }

        // Proses hasil: log + state machine + notifikasi Telegram (bila ada transisi)
        $check = processCheckResult($pdo, $server, $ping_result['status'], $ping_result['response_time']);

        $status_text = $ping_result['status'] === 'up'
            ? "UP (" . $ping_result['response_time'] . "ms)"
            : "DOWN";
        if ($check['transition'] === 'down') {
            $status_text .= " [ALERT: DOWN]";
        } elseif ($check['transition'] === 'up') {
            $status_text .= " [RECOVERED]";
        }
        echo "$status_text\n";

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
