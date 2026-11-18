<?php
/**
 * =====================================================================
 *  BOOTSTRAP REST API — MarketKita Web Service
 *  Menggunakan PHP Native & MySQLi (terhubung ke database Soal 11)
 * =====================================================================
 *  Menangani:
 *   - Header JSON (Content-Type: application/json; charset=utf-8)
 *   - CORS & preflight OPTIONS request
 *   - Koneksi database MySQLi (marketplace_db)
 *   - Helper pembacaan JSON/POST request body
 *   - Helper pengiriman JSON response standar & penanganan error
 */

define('APP_INIT', true);
define('APP_ROOT', dirname(__DIR__));

date_default_timezone_set('Asia/Jakarta');

// Matikan tampilan HTML error bawaan PHP agar output API selalu murni JSON
ini_set('display_errors', '0');
error_reporting(E_ALL);

// ---------------------------------------------------------------------
// 1. Header standar REST API & CORS
// ---------------------------------------------------------------------
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, Accept');
header('X-Content-Type-Options: nosniff');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// ---------------------------------------------------------------------
// 2. Fungsi pengiriman JSON Response terstandar
// ---------------------------------------------------------------------
/**
 * Mengirimkan response JSON terstruktur dan menghentikan eksekusi script.
 *
 * @param int         $httpCode Kode status HTTP (200, 201, 400, 404, 405, 422, 500)
 * @param bool        $success  Status keberhasilan request
 * @param string      $message  Pesan deskriptif
 * @param mixed       $data     Data utama response (array/object/null)
 * @param array       $extra    Field tambahan pada root JSON (mis. meta, errors)
 */
function api_response(int $httpCode, bool $success, string $message, $data = null, array $extra = []): void
{
    if (!headers_sent()) {
        http_response_code($httpCode);
    }

    $payload = [
        'status'    => $success ? 'success' : 'error',
        'code'      => $httpCode,
        'message'   => $message,
        'timestamp' => date('c'),
    ];

    if ($data !== null || $success) {
        $payload['data'] = $data;
    }

    foreach ($extra as $key => $val) {
        $payload[$key] = $val;
    }

    echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

/** Shortcut response sukses (200 / 201). */
function api_success(string $message, $data = null, int $httpCode = 200, array $extra = []): void
{
    api_response($httpCode, true, $message, $data, $extra);
}

/** Shortcut response gagal/error (400 / 404 / 405 / 422 / 500). */
function api_error(int $httpCode, string $message, array $errors = []): void
{
    $extra = $errors ? ['errors' => $errors] : [];
    api_response($httpCode, false, $message, null, $extra);
}

// Tangkap semua exception tak terduga sebagai JSON 500
set_exception_handler(function (Throwable $ex) {
    error_log('[MarketKita API] ' . $ex->getMessage() . ' in ' . $ex->getFile() . ':' . $ex->getLine());
    $debug = defined('APP_DEBUG') && APP_DEBUG
        ? ['detail' => $ex->getMessage()]
        : [];
    api_error(500, 'Terjadi kesalahan internal pada server database/API.', $debug);
});

// ---------------------------------------------------------------------
// 3. Muat konfigurasi & buka koneksi MySQLi
// ---------------------------------------------------------------------
$configFile = APP_ROOT . '/config/config.php';
if (!is_file($configFile)) {
    api_error(500, 'File konfigurasi config/config.php belum ditemukan. Salin dari config/config.example.php.');
}
$config = require $configFile;
define('APP_NAME', $config['app_name'] ?? 'MarketKita');
define('APP_DEBUG', (bool) ($config['debug'] ?? false));

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $db = new mysqli(
        $config['db_host'],
        $config['db_user'],
        $config['db_pass'],
        $config['db_name'],
        (int) ($config['db_port'] ?? 3306)
    );
    $db->set_charset('utf8mb4');
    $db->query("SET time_zone = '+07:00'");
} catch (mysqli_sql_exception $ex) {
    error_log('[MarketKita API] Koneksi DB gagal: ' . $ex->getMessage());
    api_error(500, 'Gagal terhubung ke database MySQL.', APP_DEBUG ? ['db_error' => $ex->getMessage()] : []);
}
unset($config['db_pass']);

// ---------------------------------------------------------------------
// 4. Helper Database (Prepared Statements) & URL
// ---------------------------------------------------------------------
function api_query(string $sql, string $types = '', array $params = []): mysqli_stmt
{
    global $db;
    $stmt = $db->prepare($sql);
    if ($types !== '' && $params) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    return $stmt;
}

function api_fetch_all(string $sql, string $types = '', array $params = []): array
{
    $stmt   = api_query($sql, $types, $params);
    $result = $stmt->get_result();
    $rows   = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    $stmt->close();
    return $rows;
}

function api_fetch_one(string $sql, string $types = '', array $params = []): ?array
{
    $stmt   = api_query($sql, $types, $params);
    $result = $stmt->get_result();
    $row    = $result ? $result->fetch_assoc() : null;
    $stmt->close();
    return $row ?: null;
}

function api_fetch_value(string $sql, string $types = '', array $params = [])
{
    $row = api_fetch_one($sql, $types, $params);
    return $row ? reset($row) : null;
}

function api_execute(string $sql, string $types = '', array $params = []): int
{
    $stmt     = api_query($sql, $types, $params);
    $affected = $stmt->affected_rows;
    $stmt->close();
    return $affected;
}

/** Membentuk base URL lengkap aplikasi (contoh: http://localhost/web-marketplace). */
function api_base_url(): string
{
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    $scheme  = $isHttps ? 'https' : 'http';
    $host    = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $script  = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '/api/index.php');
    $baseDir = rtrim(dirname(dirname($script)), '/');
    return $scheme . '://' . $host . $baseDir;
}

/** Membentuk URL lengkap gambar produk. */
function api_image_url(?string $image): string
{
    if ($image && preg_match('#^https?://#i', $image)) {
        return $image;
    }
    $base = api_base_url();
    if ($image && is_file(APP_ROOT . '/' . ltrim($image, '/'))) {
        return $base . '/' . ltrim($image, '/');
    }
    return $base . '/assets/img/no-image.svg';
}

/**Format Rupiah untuk field tampilan. */
function api_rupiah($amount): string
{
    return 'Rp ' . number_format((float) $amount, 0, ',', '.');
}

/**
 * Membaca input body dari request (mendukung application/json maupun form-data / x-www-form-urlencoded).
 */
function api_input(): array
{
    $raw = file_get_contents('php://input');
    if (is_string($raw) && trim($raw) !== '') {
        $decoded = json_decode($raw, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            return $decoded;
        }
        // Jika Content-Type adalah application/json tetapi JSON rusak, kembalikan error 400
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        if (stripos($contentType, 'application/json') !== false) {
            api_error(400, 'Format body JSON tidak valid: ' . json_last_error_msg());
        }
    }
    if (!empty($_POST)) {
        return $_POST;
    }
    return [];
}
