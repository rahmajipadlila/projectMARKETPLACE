<?php
/**
 * DASHBOARD ADMIN — Kelola Kategori (CRUD)
 */
require_once __DIR__ . '/../includes/init.php';
require_role('admin');

$editId = (int) ($_GET['edit'] ?? 0);
$errors = [];
$form   = ['name' => '', 'description' => ''];

if (is_post()) {
    verify_csrf();
    $action = (string) ($_POST['action'] ?? '');
    $id     = (int) ($_POST['id'] ?? 0);

    if ($action === 'delete') {
        $cat = db_fetch_one('SELECT name FROM categories WHERE id = ?', 'i', [$id]);
        if ($cat) {
            db_execute('DELETE FROM categories WHERE id = ?', 'i', [$id]); // produk terkait -> category_id NULL
            flash('success', 'Kategori "' . $cat['name'] . '" dihapus.');
        }
        redirect('dashboard/categories.php');
    }

    $form['name']        = trim((string) ($_POST['name'] ?? ''));
    $form['description'] = trim((string) ($_POST['description'] ?? ''));

    if ($form['name'] === '' || mb_strlen($form['name']) > 100) {
        $errors['name'] = 'Nama kategori wajib diisi (maks. 100 karakter).';
    } elseif (db_fetch_value('SELECT COUNT(*) FROM categories WHERE name = ? AND id <> ?', 'si', [$form['name'], $id]) > 0) {
        $errors['name'] = 'Nama kategori sudah digunakan.';
    }
    if (mb_strlen($form['description']) > 255) {
        $errors['description'] = 'Deskripsi maksimal 255 karakter.';
    }

    if (!$errors) {
        $desc = $form['description'] !== '' ? $form['description'] : null;
        if ($action === 'update' && $id > 0) {
            db_execute('UPDATE categories SET name = ?, description = ? WHERE id = ?', 'ssi', [$form['name'], $desc, $id]);
            flash('success', 'Kategori berhasil diperbarui.');
        } else {
            db_execute('INSERT INTO categories (name, description) VALUES (?, ?)', 'ss', [$form['name'], $desc]);
            flash('success', 'Kategori "' . $form['name'] . '" berhasil ditambahkan.');
        }
        redirect('dashboard/categories.php');
    }
    $editId = $action === 'update' ? $id : 0;
}

$editing = $editId ? db_fetch_one('SELECT * FROM categories WHERE id = ?', 'i', [$editId]) : null;
if ($editing && !is_post()) {
    $form = ['name' => $editing['name'], 'description' => (string) $editing['description']];
}

$categories = db_fetch_all(
    'SELECT c.*, COUNT(p.id) AS product_count FROM categories c LEFT JOIN products p ON p.category_id = c.id
      GROUP BY c.id, c.name, c.description, c.created_at ORDER BY c.name'
);

$pageTitle  = 'Kelola Kategori';
$activeMenu = 'dashboard';
$dashMenu   = 'categories';
require __DIR__ . '/../includes/header.php';
?>
<div class="row g-4">
    <?php require __DIR__ . '/../includes/dashboard_sidebar.php'; ?>
    <div class="col-lg-9 col-xl-10">
        <h1 class="h4 fw-bold mb-3">Kelola Kategori</h1>
        <div class="row g-4">
            <div class="col-xl-4">
                <form method="post" class="card border-0 shadow-sm" novalidate>
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="<?= $editing ? 'update' : 'create' ?>">
                    <input type="hidden" name="id" value="<?= (int) ($editing['id'] ?? 0) ?>">
                    <div class="card-header bg-white fw-semibold"><?= $editing ? 'Edit Kategori' : 'Tambah Kategori' ?></div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label" for="name">Nama Kategori</label>
                            <input type="text" class="form-control <?= isset($errors['name']) ? 'is-invalid' : '' ?>" id="name" name="name" value="<?= e($form['name']) ?>" maxlength="100" required>
                            <div class="invalid-feedback"><?= e($errors['name'] ?? '') ?></div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="description">Deskripsi</label>
                            <textarea class="form-control <?= isset($errors['description']) ? 'is-invalid' : '' ?>" id="description" name="description" rows="3" maxlength="255"><?= e($form['description']) ?></textarea>
                            <div class="invalid-feedback"><?= e($errors['description'] ?? '') ?></div>
                        </div>
                    </div>
                    <div class="card-footer bg-white d-flex gap-2">
                        <button type="submit" class="btn btn-brand flex-grow-1"><i class="bi bi-save"></i> Simpan</button>
                        <?php if ($editing): ?><a href="<?= e(url('dashboard/categories.php')) ?>" class="btn btn-outline-secondary">Batal</a><?php endif; ?>
                    </div>
                </form>
            </div>
            <div class="col-xl-8">
                <div class="card border-0 shadow-sm">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light"><tr><th>Nama</th><th>Deskripsi</th><th class="text-center">Produk</th><th class="text-end">Aksi</th></tr></thead>
                            <tbody>
                            <?php if (!$categories): ?>
                                <tr><td colspan="4" class="text-center text-muted py-4">Belum ada kategori.</td></tr>
                            <?php endif; ?>
                            <?php foreach ($categories as $c): ?>
                                <tr class="<?= $editing && $editing['id'] == $c['id'] ? 'table-primary' : '' ?>">
                                    <td class="fw-semibold"><?= e($c['name']) ?></td>
                                    <td class="small text-muted"><?= e($c['description'] ?? '-') ?></td>
                                    <td class="text-center"><span class="badge bg-secondary"><?= (int) $c['product_count'] ?></span></td>
                                    <td class="text-end text-nowrap">
                                        <a href="<?= e(url('dashboard/categories.php?edit=' . $c['id'])) ?>" class="btn btn-sm btn-outline-primary" title="Edit"><i class="bi bi-pencil"></i></a>
                                        <form method="post" class="d-inline">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Hapus"
                                                    data-confirm="Hapus kategori &quot;<?= e($c['name']) ?>&quot;? Produk di kategori ini akan menjadi tanpa kategori."><i class="bi bi-trash"></i></button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
