<?php
/**
 * DASHBOARD — Form Tambah / Edit Produk (CREATE & UPDATE pada CRUD Produk)
 */
require_once __DIR__ . '/../includes/init.php';
require_role('seller', 'admin');

$user    = current_user();
$isAdmin = $user['role'] === 'admin';
$id      = (int) ($_GET['id'] ?? 0);
$isEdit  = $id > 0;
$product = null;

if ($isEdit) {
    $product = db_fetch_one('SELECT * FROM products WHERE id = ?', 'i', [$id]);
    if (!$product || (!$isAdmin && (int) $product['seller_id'] !== (int) $user['id'])) {
        flash('danger', 'Produk tidak ditemukan atau bukan milik Anda.');
        redirect('dashboard/products.php');
    }
}

$categories = db_fetch_all('SELECT id, name FROM categories ORDER BY name');
$sellers    = $isAdmin ? db_fetch_all("SELECT id, name, store_name FROM users WHERE role = 'seller' ORDER BY name") : [];

$form = [
    'name'        => $product['name'] ?? '',
    'category_id' => $product['category_id'] ?? '',
    'price'       => isset($product['price']) ? (string) (float) $product['price'] : '',
    'stock'       => $product['stock'] ?? '',
    'description' => $product['description'] ?? '',
    'seller_id'   => $product['seller_id'] ?? ($isAdmin ? ($sellers[0]['id'] ?? '') : $user['id']),
];
$errors = [];

if (is_post()) {
    verify_csrf();
    foreach (array_keys($form) as $key) {
        $form[$key] = trim((string) ($_POST[$key] ?? ''));
    }
    if (!$isAdmin) {
        $form['seller_id'] = $isEdit ? $product['seller_id'] : $user['id'];
    }

    // ---------------- Validasi ----------------
    if ($form['name'] === '' || mb_strlen($form['name']) > 150) {
        $errors['name'] = 'Nama produk wajib diisi (maks. 150 karakter).';
    }
    $categoryId = (int) $form['category_id'];
    if ($categoryId <= 0 || !db_fetch_value('SELECT COUNT(*) FROM categories WHERE id = ?', 'i', [$categoryId])) {
        $errors['category_id'] = 'Pilih kategori produk.';
    }
    $price = $form['price'];
    if (preg_match('/^\d{1,3}(\.\d{3})+$/', $price)) {   // dukung format ribuan "150.000"
        $price = str_replace('.', '', $price);
    }
    $price = str_replace(',', '.', $price);
    if (!is_numeric($price) || (float) $price < 0 || (float) $price > 9999999999) {
        $errors['price'] = 'Harga harus berupa angka positif.';
    }
    if (!ctype_digit((string) $form['stock']) || (int) $form['stock'] > 1000000) {
        $errors['stock'] = 'Stok harus berupa bilangan bulat ≥ 0.';
    }
    if (mb_strlen($form['description']) < 10) {
        $errors['description'] = 'Deskripsi minimal 10 karakter.';
    }
    $sellerId = (int) $form['seller_id'];
    if ($isAdmin && !db_fetch_value("SELECT COUNT(*) FROM users WHERE id = ? AND role = 'seller'", 'i', [$sellerId])) {
        $errors['seller_id'] = 'Pilih penjual yang valid.';
    }

    // ---------------- Upload gambar ----------------
    $newImage = null;
    if (!$errors) {
        [$newImage, $uploadError] = upload_product_image($_FILES['image'] ?? []);
        if ($uploadError) {
            $errors['image'] = $uploadError;
        }
    }

    if (!$errors) {
        $priceVal = (float) $price;
        $stockVal = (int) $form['stock'];

        if ($isEdit) {
            $image = $product['image'];
            if ($newImage) {
                delete_product_image($product['image']);
                $image = $newImage;
            } elseif (!empty($_POST['remove_image'])) {
                delete_product_image($product['image']);
                $image = null;
            }
            db_execute(
                'UPDATE products SET seller_id = ?, category_id = ?, name = ?, description = ?, price = ?, stock = ?, image = ? WHERE id = ?',
                'iissdisi',
                [$sellerId, $categoryId, $form['name'], $form['description'], $priceVal, $stockVal, $image, $id]
            );
            flash('success', 'Produk "' . $form['name'] . '" berhasil diperbarui.');
        } else {
            db_execute(
                'INSERT INTO products (seller_id, category_id, name, description, price, stock, image) VALUES (?, ?, ?, ?, ?, ?, ?)',
                'iissdis',
                [$sellerId, $categoryId, $form['name'], $form['description'], $priceVal, $stockVal, $newImage]
            );
            flash('success', 'Produk "' . $form['name'] . '" berhasil ditambahkan.');
        }
        redirect('dashboard/products.php');
    } elseif ($newImage) {
        delete_product_image($newImage); // batalkan file yang sudah terunggah
    }
}

$pageTitle  = $isEdit ? 'Edit Produk' : 'Tambah Produk';
$activeMenu = 'dashboard';
$dashMenu   = 'products';
require __DIR__ . '/../includes/header.php';
$invalid = fn ($f) => isset($errors[$f]) ? 'is-invalid' : '';
?>
<div class="row g-4">
    <?php require __DIR__ . '/../includes/dashboard_sidebar.php'; ?>
    <div class="col-lg-9 col-xl-10">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb small">
                <li class="breadcrumb-item"><a href="<?= e(url('dashboard/products.php')) ?>">Produk</a></li>
                <li class="breadcrumb-item active"><?= e($pageTitle) ?></li>
            </ol>
        </nav>
        <h1 class="h4 fw-bold mb-3"><?= e($pageTitle) ?></h1>

        <?php if ($errors): ?>
            <div class="alert alert-danger small"><i class="bi bi-exclamation-circle"></i> Periksa kembali isian formulir di bawah.</div>
        <?php endif; ?>

        <form method="post" enctype="multipart/form-data" class="card border-0 shadow-sm" novalidate data-once>
            <?= csrf_field() ?>
            <div class="card-body p-4">
                <div class="row g-4">
                    <div class="col-md-8">
                        <div class="mb-3">
                            <label class="form-label" for="name">Nama Produk <span class="text-danger">*</span></label>
                            <input type="text" class="form-control <?= $invalid('name') ?>" id="name" name="name" value="<?= e($form['name']) ?>" maxlength="150" required>
                            <div class="invalid-feedback"><?= e($errors['name'] ?? '') ?></div>
                        </div>
                        <div class="row g-3 mb-3">
                            <?php if ($isAdmin): ?>
                                <div class="col-md-6">
                                    <label class="form-label" for="seller_id">Penjual <span class="text-danger">*</span></label>
                                    <select class="form-select <?= $invalid('seller_id') ?>" id="seller_id" name="seller_id" required>
                                        <?php foreach ($sellers as $s): ?>
                                            <option value="<?= (int) $s['id'] ?>" <?= (string) $form['seller_id'] === (string) $s['id'] ? 'selected' : '' ?>>
                                                <?= e(($s['store_name'] ?: $s['name']) . ' — ' . $s['name']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="invalid-feedback"><?= e($errors['seller_id'] ?? '') ?></div>
                                </div>
                            <?php endif; ?>
                            <div class="col-md-6">
                                <label class="form-label" for="category_id">Kategori <span class="text-danger">*</span></label>
                                <select class="form-select <?= $invalid('category_id') ?>" id="category_id" name="category_id" required>
                                    <option value="">— Pilih kategori —</option>
                                    <?php foreach ($categories as $c): ?>
                                        <option value="<?= (int) $c['id'] ?>" <?= (string) $form['category_id'] === (string) $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="invalid-feedback"><?= e($errors['category_id'] ?? '') ?></div>
                            </div>
                        </div>
                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label class="form-label" for="price">Harga (Rp) <span class="text-danger">*</span></label>
                                <div class="input-group has-validation">
                                    <span class="input-group-text">Rp</span>
                                    <input type="number" class="form-control <?= $invalid('price') ?>" id="price" name="price" value="<?= e($form['price']) ?>" min="0" step="1" required>
                                    <div class="invalid-feedback"><?= e($errors['price'] ?? '') ?></div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="stock">Stok <span class="text-danger">*</span></label>
                                <input type="number" class="form-control <?= $invalid('stock') ?>" id="stock" name="stock" value="<?= e((string) $form['stock']) ?>" min="0" step="1" required>
                                <div class="invalid-feedback"><?= e($errors['stock'] ?? '') ?></div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="description">Deskripsi <span class="text-danger">*</span></label>
                            <textarea class="form-control <?= $invalid('description') ?>" id="description" name="description" rows="7" required><?= e($form['description']) ?></textarea>
                            <div class="invalid-feedback"><?= e($errors['description'] ?? '') ?></div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="image">Gambar Produk</label>
                        <div class="ratio ratio-1x1 bg-light rounded-3 overflow-hidden mb-2 border">
                            <img src="<?= e(product_image($product['image'] ?? null)) ?>" id="image-preview" alt="Pratinjau gambar" class="object-fit-cover">
                        </div>
                        <input type="file" class="form-control <?= $invalid('image') ?>" id="image" name="image" accept="image/jpeg,image/png,image/webp,image/gif" data-preview="#image-preview">
                        <div class="invalid-feedback"><?= e($errors['image'] ?? '') ?></div>
                        <div class="form-text">JPG, PNG, WEBP, atau GIF. Maks. 2 MB.</div>
                        <?php if ($isEdit && !empty($product['image'])): ?>
                            <div class="form-check mt-2">
                                <input class="form-check-input" type="checkbox" name="remove_image" value="1" id="remove_image">
                                <label class="form-check-label small" for="remove_image">Hapus gambar saat ini</label>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="card-footer bg-white d-flex justify-content-between">
                <a href="<?= e(url('dashboard/products.php')) ?>" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Batal</a>
                <button type="submit" class="btn btn-brand"><i class="bi bi-save"></i> <?= $isEdit ? 'Simpan Perubahan' : 'Simpan Produk' ?></button>
            </div>
        </form>
    </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
