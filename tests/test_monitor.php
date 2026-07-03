<?php
/**
 * Uji otomatis state machine monitoring + incident + durasi downtime.
 *
 * Menjalankan skenario transisi status tanpa perlu server betulan mati,
 * karena processCheckResult() menerima status ping sebagai parameter.
 *
 * Jalankan: php tests/test_monitor.php
 *
 * Catatan: skrip membuat 1 server uji sementara lalu menghapusnya (cascade)
 * di akhir, sehingga tidak mengotori data. Jika telegram.enabled = true,
 * dua notifikasi (down & recovery) akan benar-benar terkirim saat uji.
 */

if (php_sapi_name() !== 'cli') {
    die("Jalankan skrip ini dari command line (CLI).\n");
}

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/monitor.php';

loadConfig();

$pass = 0;
$fail = 0;
function check($label, $cond) {
    global $pass, $fail;
    if ($cond) { $pass++; echo "  [PASS] $label\n"; }
    else       { $fail++; echo "  [FAIL] $label\n"; }
}

function getStats($pdo, $id) {
    $s = $pdo->prepare("SELECT * FROM server_stats WHERE server_id = ?");
    $s->execute([$id]);
    return $s->fetch();
}
function countIncidents($pdo, $id, $type = null) {
    if ($type) {
        $s = $pdo->prepare("SELECT COUNT(*) c FROM incidents WHERE server_id = ? AND type = ?");
        $s->execute([$id, $type]);
    } else {
        $s = $pdo->prepare("SELECT COUNT(*) c FROM incidents WHERE server_id = ?");
        $s->execute([$id]);
    }
    return (int) $s->fetch()['c'];
}

try {
    $pdo = getDB();
} catch (Exception $e) {
    die("Gagal koneksi database: " . $e->getMessage() . "\nPeriksa config.php dan pastikan schema sudah di-import.\n");
}

$threshold = (int) loadConfig()['alert_threshold'];
$ip   = '203.0.113.99';           // TEST-NET-3 (dokumentasi, tidak akan bentrok)
$name = '__TEST__ monitor';
$id   = null;

echo "=== Iclik — Test Monitor ===\n";
echo "alert_threshold = $threshold\n\n";

try {
    // --- Setup: server uji bersih ---
    $pdo->prepare("DELETE FROM servers WHERE ip_address = ?")->execute([$ip]); // cascade bila ada sisa
    $pdo->prepare("INSERT INTO servers (name, ip_address, latitude, longitude, description) VALUES (?,?,?,?,?)")
        ->execute([$name, $ip, -6.2, 106.8, 'test']);
    $id = (int) $pdo->lastInsertId();
    $pdo->prepare("INSERT INTO server_stats (server_id) VALUES (?)")->execute([$id]);
    $server = ['id' => $id, 'name' => $name, 'ip_address' => $ip];
    echo "Server uji dibuat: id=$id ($ip)\n\n";

    $expectedChecks = 0;

    // --- Skenario 1: kegagalan pending (belum mencapai threshold) ---
    echo "Skenario 1: $threshold kegagalan pertama\n";
    for ($i = 1; $i < $threshold; $i++) {
        $r = processCheckResult($pdo, $server, 'down', null);
        $expectedChecks++;
        $st = getStats($pdo, $id);
        check("down #$i: belum ada transisi (pending)", $r['transition'] === null);
        check("down #$i: consecutive_failures = $i", (int) $st['consecutive_failures'] === $i);
        check("down #$i: last_status belum 'down'", $st['last_status'] !== 'down');
    }

    // --- Skenario 2: kegagalan ke-threshold → konfirmasi DOWN ---
    echo "\nSkenario 2: kegagalan ke-$threshold (konfirmasi DOWN)\n";
    $r = processCheckResult($pdo, $server, 'down', null);
    $expectedChecks++;
    $st = getStats($pdo, $id);
    check("transisi = 'down'", $r['transition'] === 'down');
    check("last_status = 'down'", $st['last_status'] === 'down');
    check("down_since terisi", !empty($st['down_since']));
    check("incident 'down' = 1", countIncidents($pdo, $id, 'down') === 1);

    // --- Skenario 3: masih down → tidak ada transisi baru ---
    echo "\nSkenario 3: masih down (tidak ada alert ganda)\n";
    $r = processCheckResult($pdo, $server, 'down', 7);
    $expectedChecks++;
    check("tidak ada transisi baru", $r['transition'] === null);
    check("incident 'down' tetap = 1", countIncidents($pdo, $id, 'down') === 1);

    // --- Skenario 4: recovery (down → up) dengan durasi ---
    echo "\nSkenario 4: recovery + durasi downtime\n";
    // Mundurkan down_since ~125 detik untuk mensimulasikan durasi outage
    $pdo->prepare("UPDATE server_stats SET down_since = (NOW() - INTERVAL 125 SECOND) WHERE server_id = ?")
        ->execute([$id]);

    $r = processCheckResult($pdo, $server, 'up', 12);
    $expectedChecks++;
    $st = getStats($pdo, $id);
    check("transisi = 'up' (recovery)", $r['transition'] === 'up');
    check("downtime_seconds >= 120", $r['downtime_seconds'] !== null && $r['downtime_seconds'] >= 120);
    check("last_status = 'up'", $st['last_status'] === 'up');
    check("down_since dikosongkan", empty($st['down_since']));
    check("consecutive_failures = 0", (int) $st['consecutive_failures'] === 0);
    check("incident 'up' = 1", countIncidents($pdo, $id, 'up') === 1);

    // --- Skenario 5: stabil up → tidak ada transisi ---
    echo "\nSkenario 5: stabil up\n";
    $r = processCheckResult($pdo, $server, 'up', 10);
    $expectedChecks++;
    check("tidak ada transisi", $r['transition'] === null);

    // --- Skenario 6: konsistensi statistik ---
    echo "\nSkenario 6: konsistensi statistik\n";
    $st = getStats($pdo, $id);
    $totalUp   = (int) $st['total_up'];
    $totalDown = (int) $st['total_down'];
    check("total_checks = $expectedChecks", (int) $st['total_checks'] === $expectedChecks);
    check("total_up + total_down = total_checks", ($totalUp + $totalDown) === (int) $st['total_checks']);
    check("total incident = 2 (1 down + 1 up)", countIncidents($pdo, $id) === 2);

    // --- Uji unit formatDuration ---
    echo "\nUji formatDuration()\n";
    check("formatDuration(0)    = '0d'",    formatDuration(0) === '0d');
    check("formatDuration(65)   = '1m'",    formatDuration(65) === '1m');
    check("formatDuration(3661) = '1j 1m'", formatDuration(3661) === '1j 1m');
    check("formatDuration(90000)= '1h 1j'", formatDuration(90000) === '1h 1j');
    check("formatDuration(null) = '-'",     formatDuration(null) === '-');

} catch (Exception $e) {
    echo "\nERROR saat menjalankan uji: " . $e->getMessage() . "\n";
    $fail++;
} finally {
    // --- Cleanup ---
    if ($id !== null) {
        $pdo->prepare("DELETE FROM servers WHERE id = ?")->execute([$id]); // cascade
        echo "\nCleanup: server uji id=$id dihapus.\n";
    }
}

echo "\n=== Hasil: $pass PASS, $fail FAIL ===\n";
exit($fail > 0 ? 1 : 0);
