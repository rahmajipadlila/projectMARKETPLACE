<?php
/**
 * DASHBOARD — Daftar Pesanan
 * Penjual: pesanan yang berisi produk miliknya. Admin: semua pesanan.
 */
require_once __DIR__ . '/../includes/init.php';
require_role('seller', 'admin');

$user    = current_user();
$isAdmin = $user['role'] === 'admin';
$status  = (string) ($_GET['status'] ?? '');
$q       = trim((string) ($_GET['q'] ?? ''));
$page    = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 15;
if ($status !== '' && !isset(order_statuses()[$status])) {
    $status = '';
}

$join   = $isAdmin ? 'JOIN order_items oi ON oi.order_id = o.id' : 'JOIN order_items oi ON oi.order_id = o.id AND oi.seller_id = ?';
$types  = $isAdmin ? '' : 'i';
$params = $isAdmin ? [] : [$user['id']];
$where  = [];
if ($status !== '') {
    $where[]  = 'o.status = ?';
    $types   .= 's';
    $params[] = $status;
}
if ($q !== '') {
    $where[]  = '(o.order_code LIKE ? OR u.name LIKE ?)';
    $types   .= 'ss';
    $params[] = '%' . $q . '%';
    $params[] = '%' . $q . '%';
}
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$total = (int) db_fetch_value(
    "SELECT COUNT(DISTINCT o.id) FROM orders o $join JOIN users u ON u.id = o.buyer_id $whereSql",
    $types,
    $params
);
$totalPages = max(1, (int) ceil($total / $perPage));
$page       = min($page, $totalPages);

$orders = db_fetch_all(
    "SELECT o.id, o.order_code, o.status, o.created_at, o.payment_method, u.name AS buyer_name,
            SUM(oi.quantity) AS qty, SUM(oi.subtotal) AS amount
       FROM orders o $join
       JOIN users u ON u.id = o.buyer_id
       $whereSql
      GROUP BY o.id, o.order_code, o.status, o.created_at, o.payment_method, u.name
      ORDER BY o.created_at DESC, o.id DESC
      LIMIT ? OFFSET ?",
    $types . 'ii',
    array_merge($params, [$perPage, ($page - 1) * $perPage])
);

$pageTitle  = 'Kelola Pesanan';
$activeMenu = 'dashboard';
$dashMenu   = 'orders';
require __DIR__ . '/../includes/header.php';
?>
<div class="row g-4">
    <?php require __DIR__ . '/../includes/dashboard_sidebar.php'; ?>
    <div class="col-lg-9 col-xl-10">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
            <div>
                <h1 class="h4 fw-bold mb-0"><?= $isAdmin ? 'Semua Pesanan' : 'Pesanan Masuk' ?></h1>
                <small class="text-muted"><?= $total ?> pesanan</small>
            </div>
            <form class="d-flex gap-1" method="get">
                <select name="status" class="form-select form-select-sm" onchange="this.form.submit()" aria-label="Filter status">
                    <option value="">Semua status</option>
                    <?php foreach (order_statuses() as $key => $label): ?>
                        <option value="<?= e($key) ?>" <?= $status === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
                <input type="search" name="q" value="<?= e($q) ?>" class="form-control form-control-sm" placeholder="Kode / nama pembeli">
                <button class="btn btn-sm btn-outline-secondary" aria-label="Cari"><i class="bi bi-search"></i></button>
            </form>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr><th>Kode Pesanan</th><th>Pembeli</th><th>Tanggal</th><th class="text-center">Qty</th><th class="text-end"><?= $isAdmin ? 'Total' : 'Nilai (produk Anda)' ?></th><th>Pembayaran</th><th>Status</th><th></th></tr>
                    </thead>
                    <tbody>
                    <?php if (!$orders): ?>
                        <tr><td colspan="8" class="text-center text-muted py-5"><i class="bi bi-inbox display-6 d-block mb-2"></i>Belum ada pesanan.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($orders as $o): ?>
                        <tr>
                            <td class="fw-semibold"><?= e($o['order_code']) ?></td>
                            <td><?= e($o['buyer_name']) ?></td>
                            <td class="small text-nowrap"><?= tanggal($o['created_at']) ?></td>
                            <td class="text-center"><?= (int) $o['qty'] ?></td>
                            <td class="text-end text-nowrap"><?= rupiah($o['amount']) ?></td>
                            <td class="small"><?= $o['payment_method'] === 'cod' ? 'COD' : 'Transfer' ?></td>
                            <td><?= order_status_badge($o['status']) ?></td>
                            <td class="text-end"><a href="<?= e(url('dashboard/order_detail.php?code=' . urlencode($o['order_code']))) ?>" class="btn btn-sm btn-outline-primary">Detail</a></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="mt-3"><?= pagination($page, $totalPages, array_filter(['status' => $status, 'q' => $q])) ?></div>
    </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
