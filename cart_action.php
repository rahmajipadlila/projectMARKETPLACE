<?php
/**
 * PROSES AKSI KERANJANG BELANJA (hanya POST)
 * action: add | update | remove | clear
 */
require_once __DIR__ . '/includes/init.php';

if (!is_post()) {
    redirect('cart.php');
}

// Pengunjung belum login: arahkan ke login lalu kembali ke halaman produk
if (!is_logged_in()) {
    $pid = (int) ($_POST['product_id'] ?? 0);
    $_SESSION['intended'] = $pid ? url('product.php?id=' . $pid) : url('cart.php');
    flash('warning', 'Silakan login sebagai pembeli untuk menambahkan produk ke keranjang.');
    redirect('login.php');
}
require_role('buyer');
verify_csrf();

$user   = current_user();
$action = (string) ($_POST['action'] ?? '');

switch ($action) {
    // --------------------------------------------------------------
    // Tambah produk ke keranjang
    // --------------------------------------------------------------
    case 'add':
        $productId = (int) ($_POST['product_id'] ?? 0);
        $qty       = max(1, (int) ($_POST['quantity'] ?? 1));

        $product = db_fetch_one('SELECT id, name, stock FROM products WHERE id = ?', 'i', [$productId]);
        if (!$product) {
            flash('danger', 'Produk tidak ditemukan.');
            redirect('index.php');
        }
        if ((int) $product['stock'] < 1) {
            flash('warning', 'Maaf, stok produk "' . $product['name'] . '" sudah habis.');
            redirect('product.php?id=' . $productId);
        }

        $existing = (int) db_fetch_value('SELECT quantity FROM carts WHERE user_id = ? AND product_id = ?', 'ii', [$user['id'], $productId]);
        $newQty   = $existing + $qty;
        if ($newQty > (int) $product['stock']) {
            $newQty = (int) $product['stock'];
            flash('warning', 'Jumlah disesuaikan dengan stok tersedia (' . $product['stock'] . ').');
        }

        // UPSERT: tambah baris baru atau perbarui jumlah jika sudah ada
        db_execute(
            'INSERT INTO carts (user_id, product_id, quantity) VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE quantity = VALUES(quantity)',
            'iii',
            [$user['id'], $productId, $newQty]
        );

        if (!empty($_POST['buy_now'])) {
            redirect('checkout.php');
        }
        flash('success', '"' . $product['name'] . '" berhasil ditambahkan ke keranjang.');
        redirect('cart.php');
        break;

    // --------------------------------------------------------------
    // Ubah jumlah item
    // --------------------------------------------------------------
    case 'update':
        $quantities = $_POST['qty'] ?? [];
        if (!is_array($quantities)) {
            redirect('cart.php');
        }
        foreach ($quantities as $cartId => $qty) {
            $cartId = (int) $cartId;
            $qty    = (int) $qty;
            $row    = db_fetch_one(
                'SELECT c.id, p.stock, p.name FROM carts c JOIN products p ON p.id = c.product_id
                  WHERE c.id = ? AND c.user_id = ?',
                'ii',
                [$cartId, $user['id']]
            );
            if (!$row) {
                continue;
            }
            if ($qty <= 0) {
                db_execute('DELETE FROM carts WHERE id = ? AND user_id = ?', 'ii', [$cartId, $user['id']]);
                continue;
            }
            if ($qty > (int) $row['stock']) {
                $qty = (int) $row['stock'];
                flash('warning', 'Jumlah "' . $row['name'] . '" disesuaikan dengan stok tersedia (' . $row['stock'] . ').');
            }
            if ($qty <= 0) {
                db_execute('DELETE FROM carts WHERE id = ? AND user_id = ?', 'ii', [$cartId, $user['id']]);
            } else {
                db_execute('UPDATE carts SET quantity = ? WHERE id = ? AND user_id = ?', 'iii', [$qty, $cartId, $user['id']]);
            }
        }
        flash('success', 'Keranjang berhasil diperbarui.');
        redirect('cart.php');
        break;

    // --------------------------------------------------------------
    // Hapus satu item
    // --------------------------------------------------------------
    case 'remove':
        $cartId = (int) ($_POST['cart_id'] ?? 0);
        db_execute('DELETE FROM carts WHERE id = ? AND user_id = ?', 'ii', [$cartId, $user['id']]);
        flash('success', 'Produk dihapus dari keranjang.');
        redirect('cart.php');
        break;

    // --------------------------------------------------------------
    // Kosongkan keranjang
    // --------------------------------------------------------------
    case 'clear':
        db_execute('DELETE FROM carts WHERE user_id = ?', 'i', [$user['id']]);
        flash('success', 'Keranjang telah dikosongkan.');
        redirect('cart.php');
        break;

    default:
        redirect('cart.php');
}
