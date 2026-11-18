<?php
/**
 * DASHBOARD — Hapus Produk (DELETE pada CRUD Produk)
 * Hanya menerima POST + token CSRF.
 * Riwayat pesanan tetap aman: order_items.product_id otomatis menjadi NULL
 * (ON DELETE SET NULL) dan nama/harga produk tersimpan sebagai snapshot.
 */
require_once __DIR__ . '/../includes/init.php';
require_role('seller', 'admin');

if (!is_post()) {
    redirect('dashboard/products.php');
}
verify_csrf();

$user    = current_user();
$id      = (int) ($_POST['id'] ?? 0);
$product = db_fetch_one('SELECT id, name, image, seller_id FROM products WHERE id = ?', 'i', [$id]);

if (!$product || ($user['role'] !== 'admin' && (int) $product['seller_id'] !== (int) $user['id'])) {
    flash('danger', 'Produk tidak ditemukan atau bukan milik Anda.');
    redirect('dashboard/products.php');
}

db_execute('DELETE FROM products WHERE id = ?', 'i', [$id]);
delete_product_image($product['image']);

flash('success', 'Produk "' . $product['name'] . '" berhasil dihapus.');
redirect('dashboard/products.php');
