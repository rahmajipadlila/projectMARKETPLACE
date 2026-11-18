<?php
/**
 * HALAMAN DETAIL PESANAN (PEMBELI)
 * Termasuk aksi pembatalan pesanan berstatus "pending" (stok dikembalikan).
 */
require_once __DIR__ . '/includes/init.php';
require_role('buyer');

$user  = current_user();
$code  = (string) ($_GET['code'] ?? '');
$order = db_fetch_one('SELECT * FROM orders WHERE order_code = ? AND buyer_id = ?', 'si', [$code, $user['id']]);

if (!$order) {
    flash('danger', 'Pesanan tidak ditemukan.');
    redirect('orders.php');
}

// ------------------------------------------------------------------
// Batalkan pesanan (hanya jika masih pending)
// ------------------------------------------------------------------
if (is_post() && ($_POST['action'] ?? '') === 'cancel') {
    verify_csrf();
    $db->begin_transaction();
    try {
        $locked = db_fetch_one('SELECT id, status FROM orders WHERE id = ? AND buyer_id = ? FOR UPDATE', 'ii', [$order['id'], $user['id']]);
        if (!$locked || $locked['status'] !== 'pending') {
            throw new RuntimeException('Pesanan tidak dapat dibatalkan karena sudah diproses.');
        }
        // Kembalikan stok produk
        db_execute(
            'UPDATE products p JOIN order_items oi ON oi.product_id = p.id
                SET p.stock = p.stock + oi.quantity
              WHERE oi.order_id = ?',
            'i',
            [$order['id']]
        );
        db_execute("UPDATE orders SET status = 'dibatalkan' WHERE id = ?", 'i', [$order['id']]);
        $db->commit();
        flash('success', 'Pesanan ' . $order['order_code'] . ' berhasil dibatalkan.');
    } catch (RuntimeException $ex) {
        $db->rollback();
        flash('danger', $ex->getMessage());
    }
    redirect('order_detail.php?code=' . urlencode($order['order_code']));
}

$items = db_fetch_all(
    'SELECT oi.*, p.image, u.store_name, u.name AS seller_name
       FROM order_items oi
       LEFT JOIN products p ON p.id = oi.product_id
       LEFT JOIN users u ON u.id = oi.seller_id
      WHERE oi.order_id = ? ORDER BY oi.id',
    'i',
    [$order['id']]
);

// Langkah progres status
$steps   = ['pending' => 'Menunggu', 'diproses' => 'Diproses', 'dikirim' => 'Dikirim', 'selesai' => 'Selesai'];
$stepIdx = array_search($order['status'], array_keys($steps), true);

$pageTitle  = 'Detail Pesanan ' . $order['order_code'];
$activeMenu = 'orders';
require __DIR__ . '/includes/header.php';
?>

<nav aria-label="breadcrumb">
    <ol class="breadcrumb small">
        <li class="breadcrumb-item"><a href="<?= e(url('orders.php')) ?>">Pesanan Saya</a></li>
        <li class="breadcrumb-item active" aria-current="page"><?= e($order['order_code']) ?></li>
    </ol>
</nav>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <h1 class="h4 fw-bold mb-0">Pesanan <?= e($order['order_code']) ?></h1>
        <small class="text-muted">Dibuat pada <?= tanggal($order['created_at']) ?></small>
    </div>
    <div class="fs-5"><?= order_status_badge($order['status']) ?></div>
</div>

<?php if ($order['status'] !== 'dibatalkan'): ?>
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <div class="order-steps d-flex justify-content-between">
                <?php $i = 0; foreach ($steps as $key => $label): ?>
                    <div class="order-step text-center <?= $stepIdx !== false && $i <= $stepIdx ? 'done' : '' ?>">
                        <div class="step-dot mx-auto"><i class="bi bi-check-lg"></i></div>
                        <div class="small mt-1"><?= e($label) ?></div>
                    </div>
                <?php $i++; endforeach; ?>
            </div>
        </div>
    </div>
<?php endif; ?>

<div class="row g-4">
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white fw-semibold"><i class="bi bi-box-seam"></i> Produk Dipesan</div>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead class="table-light">
                        <tr><th>Produk</th><th class="text-end">Harga</th><th class="text-center">Jumlah</th><th class="text-end">Subtotal</th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($items as $it): ?>
                        <tr>
                            <td>
                                <div class="d-flex align-items-center gap-3">
                                    <img src="<?= e(product_image($it['image'])) ?>" alt="" class="cart-thumb rounded">
                                    <div>
                                        <?php if ($it['product_id']): ?>
                                            <a href="<?= e(url('product.php?id=' . $it['product_id'])) ?>" class="fw-semibold text-decoration-none text-dark"><?= e($it['product_name']) ?></a>
                                        <?php else: ?>
                                            <span class="fw-semibold"><?= e($it['product_name']) ?></span> <span class="badge bg-secondary">dihapus</span>
                                        <?php endif; ?>
                                        <div class="small text-muted"><i class="bi bi-shop-window"></i> <?= e($it['store_name'] ?: ($it['seller_name'] ?: '-')) ?></div>
                                    </div>
                                </div>
                            </td>
                            <td class="text-end text-nowrap"><?= rupiah($it['price']) ?></td>
                            <td class="text-center"><?= (int) $it['quantity'] ?></td>
                            <td class="text-end fw-semibold text-nowrap"><?= rupiah($it['subtotal']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr class="table-light"><th colspan="3" class="text-end">Total Bayar</th><th class="text-end text-brand fs-5"><?= rupiah($order['total_amount']) ?></th></tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white fw-semibold"><i class="bi bi-truck"></i> Pengiriman</div>
            <div class="card-body small">
                <div class="fw-semibold"><?= e($order['recipient_name']) ?></div>
                <div><?= e($order['phone']) ?></div>
                <div class="text-muted"><?= nl2br(e($order['shipping_address'])) ?></div>
                <?php if ($order['notes']): ?>
                    <hr><div><strong>Catatan:</strong> <?= e($order['notes']) ?></div>
                <?php endif; ?>
            </div>
        </div>
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white fw-semibold"><i class="bi bi-wallet2"></i> Pembayaran</div>
            <div class="card-body small">
                <div class="fw-semibold mb-1"><?= e(payment_label($order['payment_method'])) ?></div>
                <?php if ($order['payment_method'] === 'transfer' && $order['status'] === 'pending'): ?>
                    <div class="alert alert-info small mb-0">
                        Silakan transfer <strong><?= rupiah($order['total_amount']) ?></strong> ke rekening
                        <strong>BCA 123-456-7890 a.n. <?= e(APP_NAME) ?></strong> dan cantumkan kode
                        <strong><?= e($order['order_code']) ?></strong> pada berita transfer.
                    </div>
                <?php elseif ($order['payment_method'] === 'cod'): ?>
                    <div class="text-muted">Siapkan uang tunai sebesar <?= rupiah($order['total_amount']) ?> saat pesanan tiba.</div>
                <?php endif; ?>
            </div>
        </div>
        <?php if ($order['status'] === 'pending'): ?>
            <form method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="cancel">
                <button type="submit" class="btn btn-outline-danger w-100" data-confirm="Yakin ingin membatalkan pesanan ini?">
                    <i class="bi bi-x-circle"></i> Batalkan Pesanan
                </button>
            </form>
        <?php endif; ?>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
