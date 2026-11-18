<?php
/**
 * File inisialisasi aplikasi.
 * Di-include oleh SETIAP halaman sebelum melakukan apa pun.
 *  - Memuat konfigurasi
 *  - Memulai session yang aman
 *  - Membuka koneksi database MySQLi
 *  - Memuat fungsi bantuan (helpers)
 */

define('APP_INIT', true);
define('APP_ROOT', dirname(__DIR__));

date_default_timezone_set('Asia/Jakarta');

// ------------------------------------------------------------------
// 1. Konfigurasi
// ------------------------------------------------------------------
$configFile = APP_ROOT . '/config/config.php';
if (!is_file($configFile)) {
    http_response_code(500);
    exit('<h3 style="font-family:sans-serif">Konfigurasi belum dibuat.</h3>'
        . '<p style="font-family:sans-serif">Salin <code>config/config.example.php</code> menjadi '
        . '<code>config/config.php</code> lalu isi kredensial database Anda.</p>');
}
$config = require $configFile;

define('APP_NAME', $config['app_name'] ?? 'MarketKita');
define('APP_DEBUG', (bool) ($config['debug'] ?? false));

if (APP_DEBUG) {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);
}

// ------------------------------------------------------------------
// 2. Base URL (path) aplikasi
//    Mendukung aplikasi di root domain (InfinityFree) maupun subfolder
//    (http://localhost/web-marketplace) tanpa perlu diubah manual.
// ------------------------------------------------------------------
function detect_base_path(): string
{
    $scriptFile = realpath($_SERVER['SCRIPT_FILENAME'] ?? '') ?: '';
    $appRoot    = realpath(APP_ROOT) ?: APP_ROOT;
    $scriptFile = str_replace('\\', '/', $scriptFile);
    $appRoot    = str_replace('\\', '/', $appRoot);
    $scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');

    if ($scriptFile !== '' && stripos($scriptFile, $appRoot) === 0) {
        $suffix = substr($scriptFile, strlen($appRoot));        // contoh: /dashboard/products.php
        if ($suffix !== '' && substr($scriptName, -strlen($suffix)) === $suffix) {
            return rtrim(substr($scriptName, 0, -strlen($suffix)), '/');
        }
    }
    return rtrim(dirname($scriptName), '/\\');
}

$baseUrl = trim((string) ($config['base_url'] ?? ''));
define('BASE_URL', $baseUrl !== '' ? rtrim($baseUrl, '/') : detect_base_path());

// ------------------------------------------------------------------
// 3. Session aman
// ------------------------------------------------------------------
if (session_status() === PHP_SESSION_NONE) {
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    session_name('MARKETKITA_SID');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

// ------------------------------------------------------------------
// 4. Helper & penanganan error
// ------------------------------------------------------------------
require_once __DIR__ . '/functions.php';

set_exception_handler(function (Throwable $ex) {
    error_log('[MarketKita] ' . $ex->getMessage() . ' in ' . $ex->getFile() . ':' . $ex->getLine());
    if (!headers_sent()) {
        http_response_code(500);
    }
    $detail = APP_DEBUG
        ? '<pre style="white-space:pre-wrap">' . e($ex->getMessage() . "\n" . $ex->getFile() . ':' . $ex->getLine()) . '</pre>'
        : '';
    echo '<div style="font-family:sans-serif;max-width:640px;margin:60px auto;padding:24px;border:1px solid #f5c2c7;background:#f8d7da;border-radius:8px;color:#842029">'
        . '<h3 style="margin-top:0">Terjadi kesalahan pada server</h3>'
        . '<p>Maaf, permintaan Anda tidak dapat diproses. Silakan coba beberapa saat lagi.</p>'
        . $detail
        . '<a href="' . e(url('index.php')) . '">&larr; Kembali ke beranda</a></div>';
    exit;
});

// ------------------------------------------------------------------
// 5. Koneksi database (MySQLi)
// ------------------------------------------------------------------
require_once __DIR__ . '/db.php';
