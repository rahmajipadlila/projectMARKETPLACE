<?php
/**
 * DASHBOARD — Daftar Produk (READ pada CRUD Produk)
 * Penjual hanya melihat produk miliknya; admin melihat semua produk.
 */
require_once __DIR__ . '/../includes/init.php';
require_role('seller', 'admin');

$user    = current_user();
$isAdmin = $user['role'] === 'admin';
$q       = trim((string) ($_GET['q'] ?? ''));
$page    = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 10;

$where  = [];
$types  = '';
$params = [];
if (!$isAdmin) {
    $where[]  = 'p.seller_id = ?';
    $types   .= 'i';
    $params[] = $user['id'];
}
if ($q !== '') {
    $where[]  = 'p.name LIKE ?';
    $types   .= 's';
    $params[] = '%' . $q . '%';
}
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$total      = (int) db_fetch_value("SELECT COUNT(*) FROM products p $whereSql", $types, $params);
$totalPages = max(1, (int) ceil($total / $perPage));
$page       = min($page, $totalPages);

$products = db_fetch_all(
    "SELECT p.*, c.name AS category_name, u.store_name, u.name AS seller_name
       FROM products p
       LEFT JOIN categories c ON c.id = p.category_id
       JOIN users u ON u.id = p.seller_id
       $whereSql
      ORDER BY p.created_at DESC, p.id DESC
      LIMIT ? OFFSET ?",
    $types . 'ii',
    array_merge($params, [$perPage, ($page - 1) * $perPage])
);

$pageTitle  = 'Kelola Produk';
$activeMenu = 'dashboard';
$dashMenu   = 'products';
require __DIR__ . '/../includes/header.php';
?>
<div class="row g-4">
    <?php require __DIR__ . '/../includes/dashboard_sidebar.php'; ?>
    <div class="col-lg-9 col-xl-10">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
            <div>
                <h1 class="h4 fw-bold mb-0"><?= $isAdmin ? 'Semua Produk' : 'Produk Saya' ?></h1>
                <small class="text-muted"><?= $total ?> produk</small>
            </div>
            <div class="d-flex gap-2">
                <form class="d-flex" method="get">
                    <input type="search" name="q" value="<?= e($q) ?>" class="form-control form-control-sm" placeholder="Cari nama produk...">
                    <button class="btn btn-sm btn-outline-secondary ms-1" aria-label="Cari"><i class="bi bi-search"></i></button>
                </form>
                <a href="<?= e(url('dashboard/product_form.php')) ?>" class="btn btn-sm btn-success text-nowrap"><i class="bi bi-plus-lg"></i> Tambah Produk</a>
            </div>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th style="width:50px">#</th>
                            <th>Produk</th>
                            <?php if ($isAdmin): ?><th>Penjual</th><?php endif; ?>
                            <th>Kategori</th>
                            <th class="text-end">Harga</th>
                            <th class="text-center">Stok</th>
                            <th class="text-end" style="width:150px">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (!$products): ?>
                        <tr><td colspan="7" class="text-center text-muted py-5">
                            <i class="bi bi-box display-6 d-block mb-2"></i>Belum ada produk.
                            <a href="<?= e(url('dashboard/product_form.php')) ?>">Tambah produk pertama Anda</a>
                        </td></tr>
                    <?php endif; ?>
                    <?php foreach ($products as $i => $p): ?>
                        <tr>
                            <td class="text-muted small"><?= ($page - 1) * $perPage + $i + 1 ?></td>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <img src="<?= e(product_image($p['image'])) ?>" alt="" class="cart-thumb rounded">
                                    <div>
                                        <div class="fw-semibold"><?= e($p['name']) ?></div>
                                        <div class="small text-muted">Diperbarui <?= tanggal($p['updated_at']) ?></div>
                                    </div>
                                </div>
                            </td>
                            <?php if ($isAdmin): ?><td class="small"><?= e($p['store_name'] ?: $p['seller_name']) ?></td><?php endif; ?>
                            <td class="small"><?= e($p['category_name'] ?? '-') ?></td>
                            <td class="text-end text-nowrap"><?= rupiah($p['price']) ?></td>
                            <td class="text-center">
                                <span class="badge bg-<?= $p['stock'] == 0 ? 'danger' : ($p['stock'] <= 5 ? 'warning text-dark' : 'success') ?>"><?= (int) $p['stock'] ?></span>
                            </td>
                            <td class="text-end text-nowrap">
                                <a href="<?= e(url('product.php?id=' . $p['id'])) ?>" class="btn btn-sm btn-outline-secondary" title="Lihat"><i class="bi bi-eye"></i></a>
                                <a href="<?= e(url('dashboard/product_form.php?id=' . $p['id'])) ?>" class="btn btn-sm btn-outline-primary" title="Edit"><i class="bi bi-pencil"></i></a>
                                <form action="<?= e(url('dashboard/product_delete.php')) ?>" method="post" class="d-inline">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger" title="Hapus"
                                            data-confirm="Hapus produk &quot;<?= e($p['name']) ?>&quot;? Tindakan ini tidak dapat dibatalkan."><i class="bi bi-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="mt-3"><?= pagination($page, $totalPages, array_filter(['q' => $q])) ?></div>
    </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
