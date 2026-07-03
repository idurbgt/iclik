<?php
/**
 * Koneksi database MySQL (PDO).
 */
require_once __DIR__ . '/functions.php';

/**
 * Ambil instance PDO (singleton).
 *
 * @return PDO
 * @throws PDOException bila koneksi gagal
 */
function getDB() {
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }

    $cfg = loadConfig()['database'];
    $dsn = "mysql:host={$cfg['host']};dbname={$cfg['name']};charset={$cfg['charset']}";

    $pdo = new PDO($dsn, $cfg['user'], $cfg['pass'], [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);

    return $pdo;
}
