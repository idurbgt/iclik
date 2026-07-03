<?php
/**
 * Uji pengiriman notifikasi Telegram.
 * Mengirim satu pesan uji menggunakan kredensial di config.php.
 *
 * Jalankan: php tests/test_telegram.php
 */

if (php_sapi_name() !== 'cli') {
    die("Jalankan skrip ini dari command line (CLI).\n");
}

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/telegram.php';

$config = loadConfig();

echo "=== Iclik — Test Telegram ===\n";

if (empty($config['telegram']['enabled'])) {
    echo "Telegram dinonaktifkan di config.php.\n";
    echo "Set 'telegram' => ['enabled' => true, 'bot_token' => '...', 'chat_id' => '...'] lalu ulangi.\n";
    exit(1);
}

if (empty($config['telegram']['bot_token']) || empty($config['telegram']['chat_id'])) {
    echo "bot_token / chat_id belum diisi di config.php.\n";
    exit(1);
}

$message = "✅ <b>Iclik — Test Notifikasi</b>\n"
         . "Konfigurasi Telegram berfungsi dengan baik.\n"
         . "Waktu: " . date('Y-m-d H:i:s');

echo "Mengirim pesan uji...\n";
$ok = sendTelegram($message, $config);

if ($ok) {
    echo "BERHASIL. Silakan cek Telegram Anda.\n";
    exit(0);
} else {
    echo "GAGAL mengirim. Periksa bot_token, chat_id, koneksi internet, dan ekstensi cURL.\n";
    echo "Uji token manual: https://api.telegram.org/bot<TOKEN>/getMe\n";
    exit(1);
}
