<?php
/**
 * Logika monitoring: state machine status server + notifikasi Telegram.
 * Dipakai oleh cron, add_server, dan check_status agar perilaku konsisten.
 */
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/telegram.php';

/**
 * Catat hasil satu pengecekan + jalankan state machine berbasis ambang batas.
 *
 * Status "down" baru dikonfirmasi setelah $threshold kegagalan berturut-turut;
 * recovery (kembali "up") seketika. Mengembalikan deskripsi transisi.
 *
 * @return array {
 *   transition: 'down'|'up'|null,
 *   downtime_seconds: int|null,
 *   confirmed_status: 'up'|'down'|'unknown'
 * }
 */
function recordCheck($pdo, $server_id, $rawStatus, $response_time, $threshold = 2) {
    $result = ['transition' => null, 'downtime_seconds' => null, 'confirmed_status' => 'unknown'];

    // Ambil state saat ini (kunci baris agar aman dari race saat cron + request bersamaan)
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("SELECT * FROM server_stats WHERE server_id = ? FOR UPDATE");
        $stmt->execute([$server_id]);
        $stats = $stmt->fetch();

        if (!$stats) {
            // Inisialisasi baris statistik bila belum ada
            $pdo->prepare("INSERT INTO server_stats (server_id) VALUES (?)")->execute([$server_id]);
            $stats = [
                'last_status' => 'unknown', 'consecutive_failures' => 0, 'down_since' => null,
                'total_checks' => 0, 'total_up' => 0, 'total_down' => 0,
            ];
        }

        $prevStatus  = $stats['last_status'] ?? 'unknown';
        $consecFail  = (int) ($stats['consecutive_failures'] ?? 0);
        $totalChecks = (int) ($stats['total_checks'] ?? 0) + 1;
        $totalUp     = (int) ($stats['total_up'] ?? 0);
        $totalDown   = (int) ($stats['total_down'] ?? 0);

        if ($rawStatus === 'up') {
            $totalUp++;
            $newConsec = 0;
            $downSince = null;
            $newStatus = 'up';

            if ($prevStatus === 'down') {
                // Recovery
                if (!empty($stats['down_since'])) {
                    $result['downtime_seconds'] = max(0, time() - strtotime($stats['down_since']));
                }
                $result['transition'] = 'up';
            }
        } else {
            $totalDown++;
            $newConsec = $consecFail + 1;
            $newStatus = $prevStatus;                 // default: tahan status (pending)
            $downSince = $stats['down_since'] ?? null;

            if ($prevStatus !== 'down' && $newConsec >= $threshold) {
                // Konfirmasi DOWN
                $newStatus = 'down';
                $downSince = date('Y-m-d H:i:s');
                $result['transition'] = 'down';
            }
        }

        $uptime = $totalChecks > 0 ? round(($totalUp / $totalChecks) * 100, 2) : 0;

        $upd = $pdo->prepare("
            UPDATE server_stats SET
                last_status = ?,
                last_raw_status = ?,
                last_check = NOW(),
                last_response_time = ?,
                uptime_percentage = ?,
                total_checks = ?,
                total_up = ?,
                total_down = ?,
                consecutive_failures = ?,
                down_since = ?
            WHERE server_id = ?
        ");
        $upd->execute([
            $newStatus, $rawStatus, $response_time, $uptime,
            $totalChecks, $totalUp, $totalDown, $newConsec, $downSince, $server_id,
        ]);

        $result['confirmed_status'] = $newStatus;
        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }

    return $result;
}

/**
 * Proses hasil pengecekan: catat ping_log, jalankan state machine, dan bila
 * terjadi transisi status -> catat incident + kirim notifikasi Telegram.
 *
 * @param PDO   $pdo
 * @param array $server  butuh: id, name, ip_address
 * @param string $rawStatus 'up'|'down'
 * @param int|null $responseTime
 * @return array hasil recordCheck
 */
function processCheckResult($pdo, $server, $rawStatus, $responseTime) {
    $config = loadConfig();
    $threshold = $config['alert_threshold'];

    $server_id = $server['id'];
    $name = (isset($server['name']) && $server['name'] !== '') ? $server['name'] : ('Server #' . $server_id);
    $ip = $server['ip_address'] ?? '-';

    // Catat log ping mentah
    $pdo->prepare("INSERT INTO ping_logs (server_id, status, response_time) VALUES (?, ?, ?)")
        ->execute([$server_id, $rawStatus, $responseTime]);

    // State machine
    $r = recordCheck($pdo, $server_id, $rawStatus, $responseTime, $threshold);

    if ($r['transition'] === 'down') {
        $pdo->prepare("INSERT INTO incidents (server_id, server_name, ip_address, type) VALUES (?, ?, ?, 'down')")
            ->execute([$server_id, $name, $ip]);

        $now = date('Y-m-d H:i:s');
        $msg = "🔴 <b>SERVER DOWN</b>\n"
             . "Nama: <b>" . htmlspecialchars($name) . "</b>\n"
             . "IP: <code>" . htmlspecialchars($ip) . "</code>\n"
             . "Waktu: {$now}";
        sendTelegram($msg, $config);
        logMessage("ALERT DOWN: $name ($ip)");

    } elseif ($r['transition'] === 'up') {
        $downtime = $r['downtime_seconds'];
        $pdo->prepare("INSERT INTO incidents (server_id, server_name, ip_address, type, downtime_seconds) VALUES (?, ?, ?, 'up', ?)")
            ->execute([$server_id, $name, $ip, $downtime]);

        $now = date('Y-m-d H:i:s');
        $durText = formatDuration($downtime);
        $msg = "🟢 <b>SERVER RECOVERED</b>\n"
             . "Nama: <b>" . htmlspecialchars($name) . "</b>\n"
             . "IP: <code>" . htmlspecialchars($ip) . "</code>\n"
             . "Durasi down: {$durText}\n"
             . "Response: " . ($responseTime !== null ? "{$responseTime} ms" : '-') . "\n"
             . "Waktu: {$now}";
        sendTelegram($msg, $config);
        logMessage("RECOVERED: $name ($ip) after $durText");
    }

    return $r;
}
