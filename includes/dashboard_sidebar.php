<?php
/**
 * Sidebar navigasi dashboard (penjual & admin).
 * Variabel: $dashMenu (index, products, orders, categories, users)
 */
defined('APP_INIT') or exit('No direct access');
$dashMenu = $dashMenu ?? '';
$isAdmin  = has_role('admin');
$menus    = [
    'index'    => ['dashboard/index.php', 'bi-speedometer2', 'Ringkasan'],
    'products' => ['dashboard/products.php', 'bi-box-seam', $isAdmin ? 'Semua Produk' : 'Produk Saya'],
    'orders'   => ['dashboard/orders.php', 'bi-receipt', $isAdmin ? 'Semua Pesanan' : 'Pesanan Masuk'],
];
if ($isAdmin) {
    $menus['categories'] = ['dashboard/categories.php', 'bi-tags', 'Kategori'];
    $menus['users']      = ['dashboard/users.php', 'bi-people', 'Pengguna'];
}
?>
<aside class="col-lg-3 col-xl-2">
    <div class="card border-0 shadow-sm dash-sidebar">
        <div class="card-body pb-2">
            <div class="small text-muted">Dashboard</div>
            <div class="fw-bold"><?= e($isAdmin ? 'Administrator' : (current_user()['store_name'] ?: 'Toko Saya')) ?></div>
        </div>
        <div class="list-group list-group-flush">
            <?php foreach ($menus as $key => [$href, $icon, $label]): ?>
                <a href="<?= e(url($href)) ?>" class="list-group-item list-group-item-action <?= $dashMenu === $key ? 'active' : '' ?>">
                    <i class="bi <?= e($icon) ?> me-2"></i><?= e($label) ?>
                </a>
            <?php endforeach; ?>
            <a href="<?= e(url('dashboard/product_form.php')) ?>" class="list-group-item list-group-item-action text-success">
                <i class="bi bi-plus-circle me-2"></i>Tambah Produk
            </a>
        </div>
    </div>
</aside>
