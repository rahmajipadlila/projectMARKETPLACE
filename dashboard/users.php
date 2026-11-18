<?php
/**
 * DASHBOARD ADMIN — Daftar Pengguna
 */
require_once __DIR__ . '/../includes/init.php';
require_role('admin');

$me = current_user();

if (is_post()) {
    verify_csrf();
    $id = (int) ($_POST['id'] ?? 0);
    if ($id === (int) $me['id']) {
        flash('danger', 'Anda tidak dapat menghapus akun Anda sendiri.');
    } else {
        $target = db_fetch_one('SELECT name FROM users WHERE id = ?', 'i', [$id]);
        if ($target) {
            // Hapus file gambar produk milik user (jika penjual) sebelum baris dihapus (CASCADE)
            foreach (db_fetch_all('SELECT image FROM products WHERE seller_id = ?', 'i', [$id]) as $p) {
                delete_product_image($p['image']);
            }
            db_execute('DELETE FROM users WHERE id = ?', 'i', [$id]);
            flash('success', 'Pengguna "' . $target['name'] . '" berhasil dihapus.');
        }
    }
    redirect('dashboard/users.php');
}

$role = (string) ($_GET['role'] ?? '');
$sql  = 'SELECT u.*,
                (SELECT COUNT(*) FROM products WHERE seller_id = u.id) AS product_count,
                (SELECT COUNT(*) FROM orders WHERE buyer_id = u.id) AS order_count
           FROM users u';
$users = in_array($role, ['admin', 'seller', 'buyer'], true)
    ? db_fetch_all($sql . ' WHERE u.role = ? ORDER BY u.created_at DESC', 's', [$role])
    : db_fetch_all($sql . ' ORDER BY u.created_at DESC');

$pageTitle  = 'Kelola Pengguna';
$activeMenu = 'dashboard';
$dashMenu   = 'users';
require __DIR__ . '/../includes/header.php';
?>
<div class="row g-4">
    <?php require __DIR__ . '/../includes/dashboard_sidebar.php'; ?>
    <div class="col-lg-9 col-xl-10">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
            <h1 class="h4 fw-bold mb-0">Kelola Pengguna</h1>
            <div class="btn-group btn-group-sm">
                <a href="<?= e(url('dashboard/users.php')) ?>" class="btn btn-outline-secondary <?= $role === '' ? 'active' : '' ?>">Semua</a>
                <?php foreach (['buyer', 'seller', 'admin'] as $r): ?>
                    <a href="<?= e(url('dashboard/users.php?role=' . $r)) ?>" class="btn btn-outline-secondary <?= $role === $r ? 'active' : '' ?>"><?= e(role_label($r)) ?></a>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="card border-0 shadow-sm">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light"><tr><th>Nama</th><th>Email</th><th>Peran</th><th class="text-center">Produk</th><th class="text-center">Pesanan</th><th>Terdaftar</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($users as $u): ?>
                        <tr>
                            <td>
                                <div class="fw-semibold"><?= e($u['name']) ?></div>
                                <?php if ($u['store_name']): ?><div class="small text-muted"><i class="bi bi-shop"></i> <?= e($u['store_name']) ?></div><?php endif; ?>
                            </td>
                            <td class="small"><?= e($u['email']) ?></td>
                            <td><span class="badge bg-<?= ['admin' => 'dark', 'seller' => 'primary', 'buyer' => 'success'][$u['role']] ?>"><?= e(role_label($u['role'])) ?></span></td>
                            <td class="text-center"><?= (int) $u['product_count'] ?></td>
                            <td class="text-center"><?= (int) $u['order_count'] ?></td>
                            <td class="small text-nowrap"><?= tanggal($u['created_at'], false) ?></td>
                            <td class="text-end">
                                <?php if ((int) $u['id'] !== (int) $me['id']): ?>
                                    <form method="post" class="d-inline">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger" title="Hapus"
                                                data-confirm="Hapus pengguna &quot;<?= e($u['name']) ?>&quot;? Seluruh produk & pesanan miliknya ikut terhapus."><i class="bi bi-trash"></i></button>
                                    </form>
                                <?php else: ?>
                                    <span class="badge bg-light text-muted">Anda</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
