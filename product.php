<?php
/**
 * HALAMAN DETAIL PRODUK
 */
require_once __DIR__ . '/includes/init.php';

$id      = (int) ($_GET['id'] ?? 0);
$product = db_fetch_one(
    'SELECT p.*, c.name AS category_name, u.name AS seller_name, u.store_name, u.address AS seller_address
       FROM products p
       LEFT JOIN categories c ON c.id = p.category_id
       JOIN users u ON u.id = p.seller_id
      WHERE p.id = ?',
    'i',
    [$id]
);

if (!$product) {
    http_response_code(404);
    $pageTitle = 'Produk tidak ditemukan';
    require __DIR__ . '/includes/header.php';
    echo '<div class="text-center py-5"><i class="bi bi-emoji-frown display-3 text-muted"></i>'
        . '<h1 class="h4 mt-3">Produk tidak ditemukan</h1>'
        . '<p class="text-muted">Produk mungkin telah dihapus oleh penjual.</p>'
        . '<a class="btn btn-primary" href="' . e(url('index.php')) . '">Kembali ke daftar produk</a></div>';
    require __DIR__ . '/includes/footer.php';
    exit;
}

$user    = current_user();
$isOwner = $user && ($user['role'] === 'admin' || (int) $user['id'] === (int) $product['seller_id']);

// Jumlah produk ini yang sudah ada di keranjang pembeli
$inCart = 0;
if ($user && $user['role'] === 'buyer') {
    $inCart = (int) db_fetch_value('SELECT quantity FROM carts WHERE user_id = ? AND product_id = ?', 'ii', [$user['id'], $id]);
}

$soldCount = (int) db_fetch_value(
    "SELECT COALESCE(SUM(oi.quantity),0) FROM order_items oi JOIN orders o ON o.id = oi.order_id
      WHERE oi.product_id = ? AND o.status <> 'dibatalkan'",
    'i',
    [$id]
);

$related = [];
if ($product['category_id']) {
    $related = db_fetch_all(
        'SELECT id, name, price, image FROM products WHERE category_id = ? AND id <> ? ORDER BY RAND() LIMIT 4',
        'ii',
        [$product['category_id'], $id]
    );
}

$pageTitle  = $product['name'];
$activeMenu = 'home';
require __DIR__ . '/includes/header.php';
?>

<nav aria-label="breadcrumb">
    <ol class="breadcrumb small">
        <li class="breadcrumb-item"><a href="<?= e(url('index.php')) ?>">Produk</a></li>
        <?php if ($product['category_name']): ?>
            <li class="breadcrumb-item"><a href="<?= e(url('index.php?category=' . $product['category_id'])) ?>"><?= e($product['category_name']) ?></a></li>
        <?php endif; ?>
        <li class="breadcrumb-item active" aria-current="page"><?= e($product['name']) ?></li>
    </ol>
</nav>

<div class="card border-0 shadow-sm">
    <div class="card-body p-3 p-md-4">
        <div class="row g-4">
            <div class="col-md-5">
                <div class="ratio ratio-1x1 bg-light rounded-3 overflow-hidden">
                    <img src="<?= e(product_image($product['image'])) ?>" alt="<?= e($product['name']) ?>" class="object-fit-cover">
                </div>
            </div>
            <div class="col-md-7">
                <?php if ($product['category_name']): ?>
                    <span class="badge bg-primary-subtle text-primary-emphasis mb-2"><?= e($product['category_name']) ?></span>
                <?php endif; ?>
                <h1 class="h3 fw-bold"><?= e($product['name']) ?></h1>
                <div class="text-muted small mb-3">
                    <i class="bi bi-bag-check"></i> Terjual <?= $soldCount ?> &nbsp;•&nbsp;
                    <span class="<?= $product['stock'] > 0 ? 'text-success' : 'text-danger' ?>">
                        <i class="bi bi-box-seam"></i> <?= $product['stock'] > 0 ? 'Stok tersedia: ' . (int) $product['stock'] : 'Stok habis' ?>
                    </span>
                </div>
                <div class="display-6 fw-bold text-brand mb-4"><?= rupiah($product['price']) ?></div>

                <?php if ($isOwner): ?>
                    <div class="alert alert-info d-flex justify-content-between align-items-center">
                        <span><i class="bi bi-info-circle"></i> Anda adalah pengelola produk ini.</span>
                        <a href="<?= e(url('dashboard/product_form.php?id=' . $product['id'])) ?>" class="btn btn-sm btn-primary"><i class="bi bi-pencil"></i> Edit Produk</a>
                    </div>
                <?php elseif ($user && $user['role'] !== 'buyer'): ?>
                    <div class="alert alert-secondary small">Hanya akun <strong>Pembeli</strong> yang dapat menambahkan produk ke keranjang.</div>
                <?php elseif ($product['stock'] > 0): ?>
                    <form action="<?= e(url('cart_action.php')) ?>" method="post" class="mb-3">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="add">
                        <input type="hidden" name="product_id" value="<?= (int) $product['id'] ?>">
                        <label class="form-label fw-semibold" for="quantity">Jumlah</label>
                        <div class="d-flex flex-wrap gap-2 align-items-center">
                            <div class="input-group qty-group" style="max-width: 150px">
                                <button class="btn btn-outline-secondary" type="button" data-qty="-1" aria-label="Kurangi">−</button>
                                <input type="number" class="form-control text-center" id="quantity" name="quantity" value="1" min="1" max="<?= (int) $product['stock'] ?>" required>
                                <button class="btn btn-outline-secondary" type="button" data-qty="1" aria-label="Tambah">+</button>
                            </div>
                            <button type="submit" class="btn btn-brand btn-lg flex-grow-1 flex-md-grow-0">
                                <i class="bi bi-cart-plus"></i> Tambah ke Keranjang
                            </button>
                            <button type="submit" name="buy_now" value="1" class="btn btn-outline-brand btn-lg flex-grow-1 flex-md-grow-0">
                                Beli Sekarang
                            </button>
                        </div>
                        <?php if ($inCart > 0): ?>
                            <div class="form-text"><i class="bi bi-cart-check"></i> Sudah ada <?= $inCart ?> item produk ini di keranjang Anda.</div>
                        <?php endif; ?>
                    </form>
                <?php else: ?>
                    <button class="btn btn-secondary btn-lg" disabled><i class="bi bi-x-circle"></i> Stok Habis</button>
                <?php endif; ?>

                <hr>
                <div class="d-flex align-items-center gap-3">
                    <div class="seller-avatar"><i class="bi bi-shop"></i></div>
                    <div>
                        <div class="fw-semibold"><?= e($product['store_name'] ?: $product['seller_name']) ?></div>
                        <small class="text-muted"><i class="bi bi-geo-alt"></i> <?= e(excerpt($product['seller_address'] ?: 'Indonesia', 60)) ?></small>
                    </div>
                </div>
            </div>
        </div>

        <hr class="my-4">
        <h2 class="h5 fw-bold">Deskripsi Produk</h2>
        <div class="text-body-secondary lh-lg"><?= nl2br(e($product['description'])) ?></div>
    </div>
</div>

<?php if ($related): ?>
    <h2 class="h5 fw-bold mt-5 mb-3">Produk Terkait</h2>
    <div class="row row-cols-2 row-cols-md-4 g-3">
        <?php foreach ($related as $r): ?>
            <div class="col">
                <div class="card product-card h-100 border-0 shadow-sm">
                    <div class="ratio ratio-1x1 bg-light rounded-top overflow-hidden">
                        <img src="<?= e(product_image($r['image'])) ?>" alt="<?= e($r['name']) ?>" class="object-fit-cover" loading="lazy">
                    </div>
                    <div class="card-body p-3">
                        <a href="<?= e(url('product.php?id=' . $r['id'])) ?>" class="stretched-link text-decoration-none text-dark small fw-semibold"><?= e($r['name']) ?></a>
                        <div class="fw-bold text-brand small"><?= rupiah($r['price']) ?></div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
