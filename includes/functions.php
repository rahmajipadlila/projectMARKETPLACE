<?php
/**
 * Kumpulan fungsi bantuan (helpers) aplikasi.
 */

defined('APP_INIT') or exit('No direct access');

// ==================================================================
// Output & URL
// ==================================================================

/** Escape output HTML untuk mencegah XSS. */
function e($value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Membuat URL relatif terhadap root aplikasi. */
function url(string $path = ''): string
{
    return BASE_URL . '/' . ltrim($path, '/');
}

/**
 * Redirect lalu hentikan eksekusi.
 * $path relatif ("cart.php") akan diawali BASE_URL; path absolut ("/...")
 * atau URL lengkap ("https://...") dipakai apa adanya.
 */
function redirect(string $path): void
{
    $target = preg_match('#^(https?://|/)#i', $path) ? $path : url($path);
    header('Location: ' . $target);
    exit;
}

/** URL gambar produk (atau placeholder jika kosong / file tidak ada). */
function product_image(?string $image): string
{
    if ($image && preg_match('#^https?://#i', $image)) {
        return $image;
    }
    if ($image && is_file(APP_ROOT . '/' . ltrim($image, '/'))) {
        return url($image);
    }
    return url('assets/img/no-image.svg');
}

/** Format angka ke Rupiah. */
function rupiah($amount): string
{
    return 'Rp ' . number_format((float) $amount, 0, ',', '.');
}

/** Format tanggal Indonesia. */
function tanggal(?string $datetime, bool $withTime = true): string
{
    if (!$datetime) {
        return '-';
    }
    $bulan = [1 => 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
    $ts    = strtotime($datetime);
    $out   = date('j', $ts) . ' ' . $bulan[(int) date('n', $ts)] . ' ' . date('Y', $ts);
    return $withTime ? $out . ', ' . date('H:i', $ts) : $out;
}

/** Potong teks panjang. */
function excerpt(?string $text, int $length = 90): string
{
    $text = trim(preg_replace('/\s+/', ' ', strip_tags((string) $text)));
    return mb_strlen($text) > $length ? mb_substr($text, 0, $length) . '…' : $text;
}

/** Label & warna badge untuk status pesanan. */
function order_status_badge(string $status): string
{
    $map = [
        'pending'    => ['Menunggu', 'warning text-dark'],
        'diproses'   => ['Diproses', 'info text-dark'],
        'dikirim'    => ['Dikirim', 'primary'],
        'selesai'    => ['Selesai', 'success'],
        'dibatalkan' => ['Dibatalkan', 'danger'],
    ];
    [$label, $color] = $map[$status] ?? [ucfirst($status), 'secondary'];
    return '<span class="badge bg-' . $color . '">' . e($label) . '</span>';
}

function order_statuses(): array
{
    return ['pending' => 'Menunggu', 'diproses' => 'Diproses', 'dikirim' => 'Dikirim', 'selesai' => 'Selesai', 'dibatalkan' => 'Dibatalkan'];
}

function payment_label(string $method): string
{
    return $method === 'transfer' ? 'Transfer Bank' : 'Bayar di Tempat (COD)';
}

function role_label(string $role): string
{
    return ['admin' => 'Admin', 'seller' => 'Penjual', 'buyer' => 'Pembeli'][$role] ?? $role;
}

// ==================================================================
// Flash message & data form lama
// ==================================================================

/** Simpan pesan flash (success, danger, warning, info). */
function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

/** Ambil & hapus seluruh pesan flash. */
function get_flashes(): array
{
    $flashes = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $flashes;
}

/** Simpan input form agar bisa ditampilkan kembali saat validasi gagal. */
function set_old(array $data): void
{
    unset($data['password'], $data['password_confirm'], $data['csrf_token']);
    $_SESSION['old'] = $data;
}

function old(string $key, $default = '')
{
    return $_SESSION['old'][$key] ?? $default;
}

function clear_old(): void
{
    unset($_SESSION['old']);
}

// ==================================================================
// Keamanan: CSRF
// ==================================================================

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

/** Validasi token CSRF untuk setiap request POST. */
function verify_csrf(): void
{
    $token = $_POST['csrf_token'] ?? '';
    if (!is_string($token) || !hash_equals(csrf_token(), $token)) {
        http_response_code(419);
        flash('danger', 'Sesi formulir telah kedaluwarsa. Silakan coba lagi.');
        redirect($_SERVER['HTTP_REFERER'] ?? 'index.php');
    }
}

function is_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

// ==================================================================
// Autentikasi & otorisasi
// ==================================================================

/** Data user yang sedang login (di-cache per request). */
function current_user(): ?array
{
    static $user = false;
    if ($user !== false) {
        return $user;
    }
    if (empty($_SESSION['user_id'])) {
        return $user = null;
    }
    $user = db_fetch_one(
        'SELECT id, name, email, role, phone, address, store_name, created_at FROM users WHERE id = ?',
        'i',
        [(int) $_SESSION['user_id']]
    );
    if (!$user) {
        unset($_SESSION['user_id']);
    }
    return $user;
}

function is_logged_in(): bool
{
    return current_user() !== null;
}

function has_role(string ...$roles): bool
{
    $user = current_user();
    return $user && in_array($user['role'], $roles, true);
}

function login_user(array $user): void
{
    session_regenerate_id(true);
    $_SESSION['user_id'] = (int) $user['id'];
}

/** Wajib login. Simpan URL tujuan agar bisa kembali setelah login. */
function require_login(): void
{
    if (!is_logged_in()) {
        $_SESSION['intended'] = $_SERVER['REQUEST_URI'] ?? url('index.php');
        flash('warning', 'Silakan login terlebih dahulu.');
        redirect('login.php');
    }
}

/** Wajib memiliki salah satu role tertentu. */
function require_role(string ...$roles): void
{
    require_login();
    if (!has_role(...$roles)) {
        http_response_code(403);
        flash('danger', 'Anda tidak memiliki akses ke halaman tersebut.');
        redirect('index.php');
    }
}

// ==================================================================
// Keranjang
// ==================================================================

/** Jumlah total item di keranjang user login (untuk badge navbar). */
function cart_count(): int
{
    $user = current_user();
    if (!$user || $user['role'] !== 'buyer') {
        return 0;
    }
    return (int) db_fetch_value('SELECT COALESCE(SUM(quantity),0) FROM carts WHERE user_id = ?', 'i', [$user['id']]);
}

// ==================================================================
// Upload gambar produk
// ==================================================================

/**
 * Validasi & simpan gambar produk.
 * @return array [string|null $path, string|null $error]
 */
function upload_product_image(array $file): array
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return [null, null];
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return [null, 'Upload gambar gagal (kode ' . (int) $file['error'] . ').'];
    }
    if ($file['size'] > 2 * 1024 * 1024) {
        return [null, 'Ukuran gambar maksimal 2 MB.'];
    }

    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];
    $info    = @getimagesize($file['tmp_name']);
    $mime    = $info['mime'] ?? '';
    if (function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime  = finfo_file($finfo, $file['tmp_name']) ?: $mime;
        finfo_close($finfo);
    }
    if (!$info || !isset($allowed[$mime])) {
        return [null, 'Format gambar harus JPG, PNG, WEBP, atau GIF.'];
    }

    $dir = APP_ROOT . '/uploads/products';
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    $filename = 'p_' . date('YmdHis') . '_' . bin2hex(random_bytes(6)) . '.' . $allowed[$mime];
    if (!move_uploaded_file($file['tmp_name'], $dir . '/' . $filename)) {
        return [null, 'Gagal menyimpan file gambar ke server.'];
    }
    return ['uploads/products/' . $filename, null];
}

/** Hapus file gambar hasil upload (gambar seed bawaan tidak dihapus). */
function delete_product_image(?string $path): void
{
    if ($path && strpos($path, 'uploads/products/') === 0) {
        $full = APP_ROOT . '/' . $path;
        if (is_file($full)) {
            @unlink($full);
        }
    }
}

// ==================================================================
// Pagination
// ==================================================================

/**
 * Render navigasi halaman Bootstrap.
 * @param array $query parameter GET yang dipertahankan
 */
function pagination(int $page, int $totalPages, array $query = []): string
{
    if ($totalPages <= 1) {
        return '';
    }
    $html = '<nav aria-label="Navigasi halaman"><ul class="pagination justify-content-center">';
    $link = function (int $p) use ($query) {
        return '?' . http_build_query(array_merge($query, ['page' => $p]));
    };
    $html .= '<li class="page-item ' . ($page <= 1 ? 'disabled' : '') . '"><a class="page-link" href="' . e($link(max(1, $page - 1))) . '">&laquo;</a></li>';
    for ($i = 1; $i <= $totalPages; $i++) {
        if ($i === 1 || $i === $totalPages || abs($i - $page) <= 2) {
            $html .= '<li class="page-item ' . ($i === $page ? 'active' : '') . '"><a class="page-link" href="' . e($link($i)) . '">' . $i . '</a></li>';
        } elseif (abs($i - $page) === 3) {
            $html .= '<li class="page-item disabled"><span class="page-link">…</span></li>';
        }
    }
    $html .= '<li class="page-item ' . ($page >= $totalPages ? 'disabled' : '') . '"><a class="page-link" href="' . e($link(min($totalPages, $page + 1))) . '">&raquo;</a></li>';
    return $html . '</ul></nav>';
}
