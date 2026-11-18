<?php
/**
 * DASHBOARD — Ringkasan (Penjual & Admin)
 */
require_once __DIR__ . '/../includes/init.php';
require_role('seller', 'admin');

$user    = current_user();
$isAdmin = $user['role'] === 'admin';

if ($isAdmin) {
    $stats = [
        ['Total Produk', (int) db_fetch_value('SELECT COUNT(*) FROM products'), 'bi-box-seam', 'primary'],
        ['Total Pesanan', (int) db_fetch_value('SELECT COUNT(*) FROM orders'), 'bi-receipt', 'warning'],
        ['Total Pengguna', (int) db_fetch_value('SELECT COUNT(*) FROM users'), 'bi-people', 'info'],
        ['Total Penjualan', rupiah(db_fetch_value("SELECT COALESCE(SUM(total_amount),0) FROM orders WHERE status <> 'dibatalkan'")), 'bi-cash-stack', 'success'],
    ];
    $recentOrders = db_fetch_all(
        'SELECT o.order_code, o.total_amount AS amount, o.status, o.created_at, u.name AS buyer_name
           FROM orders o JOIN users u ON u.id = o.buyer_id
          ORDER BY o.created_at DESC, o.id DESC LIMIT 8'
    );
    $lowStock = db_fetch_all('SELECT id, name, stock FROM products WHERE stock <= 5 ORDER BY stock ASC LIMIT 5');
} else {
    $sid   = (int) $user['id'];
    $stats = [
        ['Produk Saya', (int) db_fetch_value('SELECT COUNT(*) FROM products WHERE seller_id = ?', 'i', [$sid]), 'bi-box-seam', 'primary'],
        ['Pesanan Masuk', (int) db_fetch_value('SELECT COUNT(DISTINCT order_id) FROM order_items WHERE seller_id = ?', 'i', [$sid]), 'bi-receipt', 'warning'],
        ['Barang Terjual', (int) db_fetch_value(
            "SELECT COALESCE(SUM(oi.quantity),0) FROM order_items oi JOIN orders o ON o.id = oi.order_id
              WHERE oi.seller_id = ? AND o.status <> 'dibatalkan'", 'i', [$sid]), 'bi-bag-check', 'info'],
        ['Pendapatan', rupiah(db_fetch_value(
            "SELECT COALESCE(SUM(oi.subtotal),0) FROM order_items oi JOIN orders o ON o.id = oi.order_id
              WHERE oi.seller_id = ? AND o.status <> 'dibatalkan'", 'i', [$sid])), 'bi-cash-stack', 'success'],
    ];
    $recentOrders = db_fetch_all(
        'SELECT o.order_code, SUM(oi.subtotal) AS amount, o.status, o.created_at, u.name AS buyer_name
           FROM orders o
           JOIN order_items oi ON oi.order_id = o.id AND oi.seller_id = ?
           JOIN users u ON u.id = o.buyer_id
          GROUP BY o.id, o.order_code, o.status, o.created_at, u.name
          ORDER BY o.created_at DESC, o.id DESC LIMIT 8',
        'i',
        [$sid]
    );
    $lowStock = db_fetch_all('SELECT id, name, stock FROM products WHERE seller_id = ? AND stock <= 5 ORDER BY stock ASC LIMIT 5', 'i', [$sid]);
}

$pageTitle  = 'Dashboard';
$activeMenu = 'dashboard';
$dashMenu   = 'index';
require __DIR__ . '/../includes/header.php';
?>
<div class="row g-4">
    <?php require __DIR__ . '/../includes/dashboard_sidebar.php'; ?>
    <div class="col-lg-9 col-xl-10">
        <h1 class="h4 fw-bold mb-1">Halo, <?= e($user['name']) ?> 👋</h1>
        <p class="text-muted">Berikut ringkasan aktivitas <?= $isAdmin ? 'marketplace' : 'toko Anda' ?>.</p>

        <div class="row row-cols-1 row-cols-sm-2 row-cols-xl-4 g-3 mb-4">
            <?php foreach ($stats as [$label, $value, $icon, $color]): ?>
                <div class="col">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body d-flex align-items-center gap-3">
                            <div class="stat-icon bg-<?= $color ?>-subtle text-<?= $color ?>-emphasis"><i class="bi <?= $icon ?>"></i></div>
                            <div>
                                <div class="small text-muted"><?= e($label) ?></div>
                                <div class="fs-5 fw-bold"><?= e((string) $value) ?></div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="row g-4">
            <div class="col-xl-8">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center">
                        <span class="fw-semibold">Pesanan Terbaru</span>
                        <a href="<?= e(url('dashboard/orders.php')) ?>" class="small">Lihat semua</a>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0 small">
                            <thead class="table-light"><tr><th>Kode</th><th>Pembeli</th><th>Tanggal</th><th class="text-end">Nilai</th><th>Status</th></tr></thead>
                            <tbody>
                            <?php if (!$recentOrders): ?>
                                <tr><td colspan="5" class="text-center text-muted py-4">Belum ada pesanan.</td></tr>
                            <?php endif; ?>
                            <?php foreach ($recentOrders as $o): ?>
                                <tr>
                                    <td><a href="<?= e(url('dashboard/order_detail.php?code=' . urlencode($o['order_code']))) ?>"><?= e($o['order_code']) ?></a></td>
                                    <td><?= e($o['buyer_name']) ?></td>
                                    <td class="text-nowrap"><?= tanggal($o['created_at']) ?></td>
                                    <td class="text-end text-nowrap"><?= rupiah($o['amount']) ?></td>
                                    <td><?= order_status_badge($o['status']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="col-xl-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white fw-semibold"><i class="bi bi-exclamation-triangle text-warning"></i> Stok Menipis (≤ 5)</div>
                    <ul class="list-group list-group-flush small">
                        <?php if (!$lowStock): ?>
                            <li class="list-group-item text-muted text-center py-4">Semua stok aman 👍</li>
                        <?php endif; ?>
                        <?php foreach ($lowStock as $p): ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <a href="<?= e(url('dashboard/product_form.php?id=' . $p['id'])) ?>" class="text-decoration-none"><?= e($p['name']) ?></a>
                                <span class="badge bg-<?= $p['stock'] == 0 ? 'danger' : 'warning text-dark' ?>"><?= (int) $p['stock'] ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
