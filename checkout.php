<?php
/**
 * HALAMAN CHECKOUT & PROSES PEMBUATAN PESANAN
 * Menyimpan transaksi ke tabel orders + order_items menggunakan
 * DATABASE TRANSACTION (BEGIN/COMMIT/ROLLBACK) agar data konsisten:
 *   1. Kunci baris produk (SELECT ... FOR UPDATE) & validasi stok
 *   2. INSERT ke orders (header pesanan)
 *   3. INSERT ke order_items (detail pesanan, snapshot nama & harga)
 *   4. UPDATE stok produk
 *   5. DELETE isi keranjang
 */
require_once __DIR__ . '/includes/init.php';
require_role('buyer');

$user   = current_user();
$errors = [];

$form = [
    'recipient_name'   => $user['name'],
    'phone'            => $user['phone'] ?? '',
    'shipping_address' => $user['address'] ?? '',
    'notes'            => '',
    'payment_method'   => 'cod',
];

if (is_post()) {
    verify_csrf();

    foreach (array_keys($form) as $key) {
        $form[$key] = trim((string) ($_POST[$key] ?? ''));
    }

    // ---------------- Validasi input ----------------
    if ($form['recipient_name'] === '' || mb_strlen($form['recipient_name']) > 100) {
        $errors['recipient_name'] = 'Nama penerima wajib diisi (maks. 100 karakter).';
    }
    if (!preg_match('/^[0-9+\-\s]{8,20}$/', $form['phone'])) {
        $errors['phone'] = 'Nomor telepon tidak valid (8–20 digit).';
    }
    if (mb_strlen($form['shipping_address']) < 10) {
        $errors['shipping_address'] = 'Alamat pengiriman minimal 10 karakter.';
    }
    if (mb_strlen($form['notes']) > 255) {
        $errors['notes'] = 'Catatan maksimal 255 karakter.';
    }
    if (!in_array($form['payment_method'], ['cod', 'transfer'], true)) {
        $errors['payment_method'] = 'Metode pembayaran tidak valid.';
    }

    if (!$errors) {
        $db->begin_transaction();
        try {
            // 1. Ambil isi keranjang & kunci baris produk terkait
            $cartItems = db_fetch_all(
                'SELECT c.product_id, c.quantity, p.name, p.price, p.stock, p.seller_id
                   FROM carts c JOIN products p ON p.id = c.product_id
                  WHERE c.user_id = ?
                  FOR UPDATE',
                'i',
                [$user['id']]
            );
            if (!$cartItems) {
                throw new RuntimeException('Keranjang Anda kosong.');
            }

            $total = 0;
            foreach ($cartItems as $ci) {
                if ((int) $ci['quantity'] > (int) $ci['stock']) {
                    throw new RuntimeException('Stok "' . $ci['name'] . '" tidak mencukupi (tersisa ' . $ci['stock'] . ').');
                }
                $total += $ci['price'] * $ci['quantity'];
            }

            // 2. Simpan header pesanan
            $orderCode = 'INV-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
            $notes     = $form['notes'] !== '' ? $form['notes'] : null;
            db_execute(
                'INSERT INTO orders (order_code, buyer_id, recipient_name, phone, shipping_address, notes, payment_method, total_amount, status)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, \'pending\')',
                'sisssssd',
                [$orderCode, $user['id'], $form['recipient_name'], $form['phone'], $form['shipping_address'], $notes, $form['payment_method'], $total]
            );
            $orderId = db_insert_id();

            // 3 & 4. Simpan detail pesanan dan kurangi stok
            $stmtItem  = $db->prepare(
                'INSERT INTO order_items (order_id, product_id, seller_id, product_name, price, quantity, subtotal)
                 VALUES (?, ?, ?, ?, ?, ?, ?)'
            );
            $stmtStock = $db->prepare('UPDATE products SET stock = stock - ? WHERE id = ? AND stock >= ?');

            foreach ($cartItems as $ci) {
                $productId = (int) $ci['product_id'];
                $sellerId  = (int) $ci['seller_id'];
                $name      = $ci['name'];
                $price     = (float) $ci['price'];
                $qty       = (int) $ci['quantity'];
                $subtotal  = $price * $qty;

                $stmtItem->bind_param('iiisdid', $orderId, $productId, $sellerId, $name, $price, $qty, $subtotal);
                $stmtItem->execute();

                $stmtStock->bind_param('iii', $qty, $productId, $qty);
                $stmtStock->execute();
                if ($stmtStock->affected_rows !== 1) {
                    throw new RuntimeException('Stok "' . $name . '" berubah. Silakan periksa kembali keranjang Anda.');
                }
            }
            $stmtItem->close();
            $stmtStock->close();

            // 5. Kosongkan keranjang
            db_execute('DELETE FROM carts WHERE user_id = ?', 'i', [$user['id']]);

            // Simpan telepon & alamat ke profil bila sebelumnya kosong
            if (empty($user['phone']) || empty($user['address'])) {
                db_execute(
                    'UPDATE users SET phone = COALESCE(NULLIF(phone, \'\'), ?), address = COALESCE(NULLIF(address, \'\'), ?) WHERE id = ?',
                    'ssi',
                    [$form['phone'], $form['shipping_address'], $user['id']]
                );
            }

            $db->commit();

            flash('success', 'Pesanan ' . $orderCode . ' berhasil dibuat. Terima kasih telah berbelanja!');
            redirect('order_detail.php?code=' . urlencode($orderCode));
        } catch (RuntimeException $ex) {
            $db->rollback();
            flash('danger', $ex->getMessage());
            redirect('cart.php');
        } catch (Throwable $ex) {
            $db->rollback();
            error_log('[MarketKita] Checkout gagal: ' . $ex->getMessage());
            $errors['general'] = 'Terjadi kesalahan saat menyimpan pesanan. Silakan coba lagi.'
                . (APP_DEBUG ? ' (' . $ex->getMessage() . ')' : '');
        }
    }
}

// ---------------- Data ringkasan untuk tampilan ----------------
$items = db_fetch_all(
    'SELECT c.quantity, p.id, p.name, p.price, p.stock, p.image
       FROM carts c JOIN products p ON p.id = c.product_id
      WHERE c.user_id = ? ORDER BY c.created_at DESC',
    'i',
    [$user['id']]
);
if (!$items) {
    flash('warning', 'Keranjang Anda kosong. Tambahkan produk terlebih dahulu.');
    redirect('cart.php');
}
$total = 0;
foreach ($items as $it) {
    if ($it['quantity'] > $it['stock']) {
        flash('warning', 'Stok "' . $it['name'] . '" tidak mencukupi. Perbarui keranjang Anda.');
        redirect('cart.php');
    }
    $total += $it['price'] * $it['quantity'];
}

$pageTitle  = 'Checkout';
$activeMenu = 'cart';
require __DIR__ . '/includes/header.php';
?>

<nav aria-label="breadcrumb">
    <ol class="breadcrumb small">
        <li class="breadcrumb-item"><a href="<?= e(url('cart.php')) ?>">Keranjang</a></li>
        <li class="breadcrumb-item active" aria-current="page">Checkout</li>
    </ol>
</nav>

<h1 class="h4 fw-bold mb-3"><i class="bi bi-credit-card"></i> Checkout</h1>

<?php if (!empty($errors['general'])): ?>
    <div class="alert alert-danger"><?= e($errors['general']) ?></div>
<?php endif; ?>

<form method="post" class="row g-4 needs-validation" novalidate data-once>
    <?= csrf_field() ?>
    <div class="col-lg-7">
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white fw-semibold"><i class="bi bi-geo-alt"></i> Alamat Pengiriman</div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label" for="recipient_name">Nama Penerima</label>
                        <input type="text" class="form-control <?= isset($errors['recipient_name']) ? 'is-invalid' : '' ?>" id="recipient_name" name="recipient_name"
                               value="<?= e($form['recipient_name']) ?>" maxlength="100" required>
                        <div class="invalid-feedback"><?= e($errors['recipient_name'] ?? 'Nama penerima wajib diisi.') ?></div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="phone">No. Telepon</label>
                        <input type="tel" class="form-control <?= isset($errors['phone']) ? 'is-invalid' : '' ?>" id="phone" name="phone"
                               value="<?= e($form['phone']) ?>" pattern="[0-9+\-\s]{8,20}" placeholder="08xxxxxxxxxx" required>
                        <div class="invalid-feedback"><?= e($errors['phone'] ?? 'Nomor telepon tidak valid.') ?></div>
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="shipping_address">Alamat Lengkap</label>
                        <textarea class="form-control <?= isset($errors['shipping_address']) ? 'is-invalid' : '' ?>" id="shipping_address" name="shipping_address"
                                  rows="3" minlength="10" required placeholder="Nama jalan, nomor rumah, RT/RW, kelurahan, kecamatan, kota, kode pos"><?= e($form['shipping_address']) ?></textarea>
                        <div class="invalid-feedback"><?= e($errors['shipping_address'] ?? 'Alamat minimal 10 karakter.') ?></div>
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="notes">Catatan untuk Penjual <span class="text-muted small">(opsional)</span></label>
                        <input type="text" class="form-control <?= isset($errors['notes']) ? 'is-invalid' : '' ?>" id="notes" name="notes"
                               value="<?= e($form['notes']) ?>" maxlength="255" placeholder="Contoh: warna hitam, ukuran L">
                        <div class="invalid-feedback"><?= e($errors['notes'] ?? '') ?></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white fw-semibold"><i class="bi bi-wallet2"></i> Metode Pembayaran</div>
            <div class="card-body">
                <div class="form-check border rounded p-3 ps-5 mb-2">
                    <input class="form-check-input" type="radio" name="payment_method" id="pay_cod" value="cod" <?= $form['payment_method'] === 'cod' ? 'checked' : '' ?>>
                    <label class="form-check-label w-100" for="pay_cod">
                        <strong>Bayar di Tempat (COD)</strong>
                        <div class="small text-muted">Bayar tunai saat pesanan diterima.</div>
                    </label>
                </div>
                <div class="form-check border rounded p-3 ps-5">
                    <input class="form-check-input" type="radio" name="payment_method" id="pay_transfer" value="transfer" <?= $form['payment_method'] === 'transfer' ? 'checked' : '' ?>>
                    <label class="form-check-label w-100" for="pay_transfer">
                        <strong>Transfer Bank</strong>
                        <div class="small text-muted">Instruksi pembayaran ditampilkan setelah pesanan dibuat.</div>
                    </label>
                </div>
                <?php if (isset($errors['payment_method'])): ?>
                    <div class="text-danger small mt-2"><?= e($errors['payment_method']) ?></div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card border-0 shadow-sm sticky-lg-top" style="top: 90px">
            <div class="card-header bg-white fw-semibold"><i class="bi bi-bag"></i> Ringkasan Pesanan</div>
            <ul class="list-group list-group-flush">
                <?php foreach ($items as $it): ?>
                    <li class="list-group-item d-flex gap-3 align-items-center">
                        <img src="<?= e(product_image($it['image'])) ?>" alt="" class="cart-thumb rounded">
                        <div class="flex-grow-1">
                            <div class="small fw-semibold"><?= e($it['name']) ?></div>
                            <div class="small text-muted"><?= (int) $it['quantity'] ?> × <?= rupiah($it['price']) ?></div>
                        </div>
                        <div class="small fw-semibold text-nowrap"><?= rupiah($it['price'] * $it['quantity']) ?></div>
                    </li>
                <?php endforeach; ?>
            </ul>
            <div class="card-body">
                <div class="d-flex justify-content-between small mb-1"><span>Subtotal</span><span><?= rupiah($total) ?></span></div>
                <div class="d-flex justify-content-between small mb-2"><span>Ongkos kirim</span><span class="text-success">Gratis</span></div>
                <hr>
                <div class="d-flex justify-content-between fw-bold fs-5 mb-3"><span>Total Bayar</span><span class="text-brand"><?= rupiah($total) ?></span></div>
                <button type="submit" class="btn btn-brand btn-lg w-100"><i class="bi bi-check2-circle"></i> Buat Pesanan</button>
                <a href="<?= e(url('cart.php')) ?>" class="btn btn-link w-100 mt-1">Kembali ke keranjang</a>
            </div>
        </div>
    </div>
</form>

<?php require __DIR__ . '/includes/footer.php'; ?>
