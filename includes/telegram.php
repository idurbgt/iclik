<?php
/**
 * Modul notifikasi Telegram.
 * Mengirim pesan via Telegram Bot API dengan timeout agar tidak menghambat cron.
 */

/**
 * Kirim pesan ke Telegram.
 *
 * @param string $message  Teks pesan (mendukung HTML: <b>, <i>, <code>, dll)
 * @param array  $config   Konfigurasi dari config.php
 * @return bool            true jika terkirim, false bila dinonaktifkan/gagal
 */
function sendTelegram($message, $config) {
    $tg = $config['telegram'] ?? [];

    if (empty($tg['enabled'])) {
        return false;
    }

    $token   = $tg['bot_token'] ?? '';
    $chatId  = $tg['chat_id'] ?? '';
    $timeout = $tg['timeout'] ?? 5;

    if ($token === '' || $chatId === '') {
        return false;
    }

    $url = "https://api.telegram.org/bot{$token}/sendMessage";
    $params = [
        'chat_id'                  => $chatId,
        'text'                     => $message,
        'parse_mode'               => 'HTML',
        'disable_web_page_preview' => true,
    ];

    // Utamakan cURL bila tersedia
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query($params),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $timeout,
            CURLOPT_CONNECTTIMEOUT => $timeout,
        ]);
        $response = curl_exec($ch);
        $ok = ($response !== false) && curl_errno($ch) === 0;
        curl_close($ch);
        return $ok;
    }

    // Fallback: file_get_contents dengan stream context
    $context = stream_context_create([
        'http' => [
            'method'        => 'POST',
            'header'        => 'Content-Type: application/x-www-form-urlencoded',
            'content'       => http_build_query($params),
            'timeout'       => $timeout,
            'ignore_errors' => true,
        ],
    ]);

    $result = @file_get_contents($url, false, $context);
    return $result !== false;
}
