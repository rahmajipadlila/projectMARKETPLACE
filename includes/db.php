<?php
/**
 * Koneksi database menggunakan ekstensi MySQLi (gaya OOP).
 * Variabel global: $db (instance mysqli)
 */

defined('APP_INIT') or exit('No direct access');

// Lempar exception untuk setiap error MySQLi agar mudah ditangani
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
    error_log('[MarketKita] Koneksi database gagal: ' . $ex->getMessage());
    http_response_code(500);
    $msg = APP_DEBUG ? '<pre>' . htmlspecialchars($ex->getMessage(), ENT_QUOTES, 'UTF-8') . '</pre>' : '';
    exit('<div style="font-family:sans-serif;max-width:640px;margin:60px auto;padding:24px;border:1px solid #f5c2c7;background:#f8d7da;border-radius:8px;color:#842029">'
        . '<h3 style="margin-top:0">Koneksi database gagal</h3>'
        . '<p>Periksa kembali pengaturan database pada <code>config/config.php</code>.</p>' . $msg . '</div>');
}

// Kosongkan password dari memori setelah koneksi dibuat
unset($config['db_pass']);

/**
 * Menjalankan prepared statement dan mengembalikan mysqli_stmt.
 *
 * @param string $sql    Query dengan placeholder "?"
 * @param string $types  Tipe parameter (i, d, s, b) — contoh "isd"
 * @param array  $params Nilai parameter
 */
function db_query(string $sql, string $types = '', array $params = []): mysqli_stmt
{
    global $db;
    $stmt = $db->prepare($sql);
    if ($types !== '' && $params) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    return $stmt;
}

/** Ambil semua baris hasil query sebagai array asosiatif. */
function db_fetch_all(string $sql, string $types = '', array $params = []): array
{
    $stmt   = db_query($sql, $types, $params);
    $result = $stmt->get_result();
    $rows   = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    $stmt->close();
    return $rows;
}

/** Ambil satu baris hasil query (atau null). */
function db_fetch_one(string $sql, string $types = '', array $params = []): ?array
{
    $stmt   = db_query($sql, $types, $params);
    $result = $stmt->get_result();
    $row    = $result ? $result->fetch_assoc() : null;
    $stmt->close();
    return $row ?: null;
}

/** Ambil satu nilai (kolom pertama baris pertama). */
function db_fetch_value(string $sql, string $types = '', array $params = [])
{
    $row = db_fetch_one($sql, $types, $params);
    return $row ? reset($row) : null;
}

/** Jalankan INSERT/UPDATE/DELETE, kembalikan jumlah baris terpengaruh. */
function db_execute(string $sql, string $types = '', array $params = []): int
{
    $stmt     = db_query($sql, $types, $params);
    $affected = $stmt->affected_rows;
    $stmt->close();
    return $affected;
}

/** ID terakhir hasil INSERT. */
function db_insert_id(): int
{
    global $db;
    return (int) $db->insert_id;
}
