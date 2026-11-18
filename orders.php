<?php
/**
 * HALAMAN RIWAYAT PESANAN PEMBELI
 */
require_once __DIR__ . '/includes/init.php';
require_role('buyer');

$user   = current_user();
$status = (string) ($_GET['status'] ?? '');
if ($status !== '' && !isset(order_statuses()[$status])) {
    $status = '';
}

$sql    = 'SELECT o.*, (SELECT COALESCE(SUM(quantity),0) FROM order_items WHERE order_id = o.id) AS total_qty,
                  (SELECT product_name FROM order_items WHERE order_id = o.id ORDER BY id LIMIT 1) AS first_item,
                  (SELECT COUNT(*) FROM order_items WHERE order_id = o.id) AS item_count
             FROM orders o WHERE o.buyer_id = ?';
$types  = 'i';
$params = [$user['id']];
if ($status !== '') {
    $sql     .= ' AND o.status = ?';
    $types   .= 's';
    $params[] = $status;
}
$sql   .= ' ORDER BY o.created_at DESC, o.id DESC';
$orders = db_fetch_all($sql, $types, $params);

$pageTitle  = 'Pesanan Saya';
$activeMenu = 'orders';
require __DIR__ . '/includes/header.php';
?>

<h1 class="h4 fw-bold mb-3"><i class="bi bi-receipt"></i> Pesanan Saya</h1>

<ul class="nav nav-pills mb-3 flex-nowrap overflow-auto">
    <li class="nav-item"><a class="nav-link <?= $status === '' ? 'active' : '' ?>" href="<?= e(url('orders.php')) ?>">Semua</a></li>
    <?php foreach (order_statuses() as $key => $label): ?>
        <li class="nav-item"><a class="nav-link text-nowrap <?= $status === $key ? 'active' : '' ?>" href="<?= e(url('orders.php?status=' . $key)) ?>"><?= e($label) ?></a></li>
    <?php endforeach; ?>
</ul>

<?php if (!$orders): ?>
    <div class="text-center py-5 bg-white rounded-3 shadow-sm">
        <i class="bi bi-receipt-cutoff display-4 text-muted"></i>
        <p class="mt-3 fw-semibold">Belum ada pesanan.</p>
        <a href="<?= e(url('index.php')) ?>" class="btn btn-brand">Mulai Belanja</a>
    </div>
<?php else: ?>
    <div class="vstack gap-3">
        <?php foreach ($orders as $o): ?>
            <div class="card border-0 shadow-sm">
                <div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-3">
                    <div>
                        <div class="d-flex align-items-center gap-2 mb-1">
                            <span class="fw-bold"><?= e($o['order_code']) ?></span>
                            <?= order_status_badge($o['status']) ?>
                        </div>
                        <div class="small text-muted"><i class="bi bi-calendar3"></i> <?= tanggal($o['created_at']) ?> • <?= e(payment_label($o['payment_method'])) ?></div>
                        <div class="small mt-1">
                            <?= e($o['first_item']) ?>
                            <?php if ($o['item_count'] > 1): ?><span class="text-muted">+<?= $o['item_count'] - 1 ?> produk lainnya</span><?php endif; ?>
                            <span class="text-muted">(<?= (int) $o['total_qty'] ?> barang)</span>
                        </div>
                    </div>
                    <div class="text-end">
                        <div class="small text-muted">Total Belanja</div>
                        <div class="fw-bold text-brand fs-5"><?= rupiah($o['total_amount']) ?></div>
                        <a href="<?= e(url('order_detail.php?code=' . urlencode($o['order_code']))) ?>" class="btn btn-sm btn-outline-primary mt-1">Lihat Detail</a>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
