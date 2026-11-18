<?php
/**
 * DASHBOARD — Detail Pesanan & Ubah Status
 * Alur status: pending → diproses → dikirim → selesai
 *              pending/diproses → dibatalkan (stok dikembalikan)
 */
require_once __DIR__ . '/../includes/init.php';
require_role('seller', 'admin');

$user    = current_user();
$isAdmin = $user['role'] === 'admin';
$code    = (string) ($_GET['code'] ?? '');

$order = db_fetch_one(
    'SELECT o.*, u.name AS buyer_name, u.email AS buyer_email FROM orders o JOIN users u ON u.id = o.buyer_id WHERE o.order_code = ?',
    's',
    [$code]
);
$ownsItems = $order && ($isAdmin || db_fetch_value('SELECT COUNT(*) FROM order_items WHERE order_id = ? AND seller_id = ?', 'ii', [$order['id'], $user['id']]) > 0);
if (!$order || !$ownsItems) {
    flash('danger', 'Pesanan tidak ditemukan.');
    redirect('dashboard/orders.php');
}

// Transisi status yang diizinkan
$transitions = [
    'pending'    => ['diproses', 'dibatalkan'],
    'diproses'   => ['dikirim', 'dibatalkan'],
    'dikirim'    => ['selesai'],
    'selesai'    => [],
    'dibatalkan' => [],
];

if (is_post()) {
    verify_csrf();
    $newStatus = (string) ($_POST['status'] ?? '');

    $db->begin_transaction();
    try {
        $current = db_fetch_value('SELECT status FROM orders WHERE id = ? FOR UPDATE', 'i', [$order['id']]);
        if (!in_array($newStatus, $transitions[$current] ?? [], true)) {
            throw new RuntimeException('Perubahan status dari "' . (order_statuses()[$current] ?? $current) . '" ke "' . (order_statuses()[$newStatus] ?? $newStatus) . '" tidak diizinkan.');
        }
        if ($newStatus === 'dibatalkan') {
            db_execute(
                'UPDATE products p JOIN order_items oi ON oi.product_id = p.id
                    SET p.stock = p.stock + oi.quantity WHERE oi.order_id = ?',
                'i',
                [$order['id']]
            );
        }
        db_execute('UPDATE orders SET status = ? WHERE id = ?', 'si', [$newStatus, $order['id']]);
        $db->commit();
        flash('success', 'Status pesanan diubah menjadi "' . order_statuses()[$newStatus] . '".');
    } catch (RuntimeException $ex) {
        $db->rollback();
        flash('danger', $ex->getMessage());
    }
    redirect('dashboard/order_detail.php?code=' . urlencode($order['order_code']));
}

// Admin melihat semua item, penjual hanya item miliknya
$items = $isAdmin
    ? db_fetch_all('SELECT oi.*, p.image, u.store_name, u.name AS seller_name FROM order_items oi LEFT JOIN products p ON p.id = oi.product_id LEFT JOIN users u ON u.id = oi.seller_id WHERE oi.order_id = ? ORDER BY oi.id', 'i', [$order['id']])
    : db_fetch_all('SELECT oi.*, p.image, u.store_name, u.name AS seller_name FROM order_items oi LEFT JOIN products p ON p.id = oi.product_id LEFT JOIN users u ON u.id = oi.seller_id WHERE oi.order_id = ? AND oi.seller_id = ? ORDER BY oi.id', 'ii', [$order['id'], $user['id']]);
$itemsTotal = array_sum(array_column($items, 'subtotal'));

$pageTitle  = 'Pesanan ' . $order['order_code'];
$activeMenu = 'dashboard';
$dashMenu   = 'orders';
require __DIR__ . '/../includes/header.php';
?>
<div class="row g-4">
    <?php require __DIR__ . '/../includes/dashboard_sidebar.php'; ?>
    <div class="col-lg-9 col-xl-10">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb small">
                <li class="breadcrumb-item"><a href="<?= e(url('dashboard/orders.php')) ?>">Pesanan</a></li>
                <li class="breadcrumb-item active"><?= e($order['order_code']) ?></li>
            </ol>
        </nav>
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
            <div>
                <h1 class="h4 fw-bold mb-0">Pesanan <?= e($order['order_code']) ?></h1>
                <small class="text-muted"><?= tanggal($order['created_at']) ?> • <?= e(payment_label($order['payment_method'])) ?></small>
            </div>
            <div class="fs-5"><?= order_status_badge($order['status']) ?></div>
        </div>

        <div class="row g-4">
            <div class="col-xl-8">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white fw-semibold"><i class="bi bi-box-seam"></i> Item Pesanan<?= $isAdmin ? '' : ' (produk Anda)' ?></div>
                    <div class="table-responsive">
                        <table class="table align-middle mb-0">
                            <thead class="table-light"><tr><th>Produk</th><?php if ($isAdmin): ?><th>Penjual</th><?php endif; ?><th class="text-end">Harga</th><th class="text-center">Qty</th><th class="text-end">Subtotal</th></tr></thead>
                            <tbody>
                            <?php foreach ($items as $it): ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            <img src="<?= e(product_image($it['image'])) ?>" alt="" class="cart-thumb rounded">
                                            <span class="fw-semibold"><?= e($it['product_name']) ?></span>
                                        </div>
                                    </td>
                                    <?php if ($isAdmin): ?><td class="small"><?= e($it['store_name'] ?: ($it['seller_name'] ?: '-')) ?></td><?php endif; ?>
                                    <td class="text-end text-nowrap"><?= rupiah($it['price']) ?></td>
                                    <td class="text-center"><?= (int) $it['quantity'] ?></td>
                                    <td class="text-end text-nowrap fw-semibold"><?= rupiah($it['subtotal']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                            <tfoot class="table-light">
                                <tr><th colspan="<?= $isAdmin ? 4 : 3 ?>" class="text-end">Total<?= $isAdmin ? '' : ' (produk Anda)' ?></th><th class="text-end text-brand"><?= rupiah($itemsTotal) ?></th></tr>
                                <?php if (!$isAdmin && (float) $itemsTotal !== (float) $order['total_amount']): ?>
                                    <tr><td colspan="4" class="small text-muted text-end">Total keseluruhan pesanan (termasuk penjual lain): <?= rupiah($order['total_amount']) ?></td></tr>
                                <?php endif; ?>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
            <div class="col-xl-4">
                <div class="card border-0 shadow-sm mb-3">
                    <div class="card-header bg-white fw-semibold"><i class="bi bi-arrow-repeat"></i> Ubah Status</div>
                    <div class="card-body">
                        <?php $next = $transitions[$order['status']]; ?>
                        <?php if (!$next): ?>
                            <p class="small text-muted mb-0">Pesanan sudah <?= e(strtolower(order_statuses()[$order['status']])) ?>; status tidak dapat diubah lagi.</p>
                        <?php else: ?>
                            <form method="post">
                                <?= csrf_field() ?>
                                <select name="status" class="form-select mb-2" aria-label="Status baru">
                                    <?php foreach ($next as $s): ?>
                                        <option value="<?= e($s) ?>"><?= e(order_statuses()[$s]) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <button type="submit" class="btn btn-brand w-100" data-confirm="Ubah status pesanan ini?">Simpan Status</button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white fw-semibold"><i class="bi bi-person"></i> Pembeli & Pengiriman</div>
                    <div class="card-body small">
                        <div class="fw-semibold"><?= e($order['buyer_name']) ?></div>
                        <div class="text-muted mb-2"><?= e($order['buyer_email']) ?></div>
                        <div><strong>Penerima:</strong> <?= e($order['recipient_name']) ?></div>
                        <div><strong>Telepon:</strong> <?= e($order['phone']) ?></div>
                        <div><strong>Alamat:</strong><br><?= nl2br(e($order['shipping_address'])) ?></div>
                        <?php if ($order['notes']): ?><div class="mt-2"><strong>Catatan:</strong> <?= e($order['notes']) ?></div><?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
