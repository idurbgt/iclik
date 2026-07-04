<?php
/**
 * TEMPLATE konfigurasi Iclik.
 *
 * Cara pakai: salin file ini menjadi config.php lalu isi nilainya.
 *   cp config.example.php config.php   (Linux/Mac)
 *   copy config.example.php config.php (Windows)
 *
 * config.php TIDAK di-commit ke git (lihat .gitignore) karena berisi kredensial.
 *
 * Cara mendapatkan token & chat_id Telegram:
 *   1. Chat dengan @BotFather → /newbot → salin token.
 *   2. Kirim pesan ke bot, buka https://api.telegram.org/bot<TOKEN>/getUpdates
 *      untuk mengambil "chat":{"id": ...}. Untuk grup, id biasanya negatif.
 */

return [
    // Koneksi database MySQL
    'database' => [
        'host'    => 'localhost',
        'name'    => 'iclik',
        'user'    => 'root',
        'pass'    => '',
        'charset' => 'utf8mb4',
    ],

    // Notifikasi Telegram
    'telegram' => [
        'enabled'   => false,        // set true untuk mengaktifkan
        'bot_token' => '',           // contoh: 123456789:ABCdef...
        'chat_id'   => '',           // contoh: 123456789 atau -100xxxxxxxxxx (grup)
        'timeout'   => 5,            // detik
    ],

    // Jumlah kegagalan ping berturut-turut sebelum server dinyatakan DOWN
    'alert_threshold' => 2,

    // Zona waktu untuk timestamp
    'timezone' => 'Asia/Jakarta',
];
