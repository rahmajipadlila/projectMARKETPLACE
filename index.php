<?php
/**
 * HALAMAN DAFTAR PRODUK (Beranda)
 * Fitur: pencarian, filter kategori, pengurutan, dan pagination.
 */
require_once __DIR__ . '/includes/init.php';

$q        = trim((string) ($_GET['q'] ?? ''));
$category = (int) ($_GET['category'] ?? 0);
$sort     = (string) ($_GET['sort'] ?? 'newest');
$page     = max(1, (int) ($_GET['page'] ?? 1));
$perPage  = 12;

$sorts = [
    'newest'     => ['Terbaru', 'p.created_at DESC, p.id DESC'],
    'price_asc'  => ['Harga Terendah', 'p.price ASC'],
    'price_desc' => ['Harga Tertinggi', 'p.price DESC'],
    'name'       => ['Nama A-Z', 'p.name ASC'],
];
if (!isset($sorts[$sort])) {
    $sort = 'newest';
}

// Bangun klausa WHERE secara dinamis dengan prepared statement
$where  = [];
$types  = '';
$params = [];
if ($q !== '') {
    $where[]  = '(p.name LIKE ? OR p.description LIKE ?)';
    $like     = '%' . $q . '%';
    $types   .= 'ss';
    $params[] = $like;
    $params[] = $like;
}
if ($category > 0) {
    $where[]  = 'p.category_id = ?';
    $types   .= 'i';
    $params[] = $category;
}
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$total      = (int) db_fetch_value("SELECT COUNT(*) FROM products p $whereSql", $types, $params);
$totalPages = max(1, (int) ceil($total / $perPage));
$page       = min($page, $totalPages);
$offset     = ($page - 1) * $perPage;

$products = db_fetch_all(
    "SELECT p.id, p.name, p.description, p.price, p.stock, p.image,
            c.name AS category_name, u.store_name, u.name AS seller_name
       FROM products p
       LEFT JOIN categories c ON c.id = p.category_id
       JOIN users u ON u.id = p.seller_id
       $whereSql
      ORDER BY {$sorts[$sort][1]}
      LIMIT ? OFFSET ?",
    $types . 'ii',
    array_merge($params, [$perPage, $offset])
);

$categories = db_fetch_all(
    'SELECT c.id, c.name, COUNT(p.id) AS total
       FROM categories c LEFT JOIN products p ON p.category_id = c.id
      GROUP BY c.id, c.name ORDER BY c.name'
);

$pageTitle  = 'Daftar Produk';
$activeMenu = 'home';
require __DIR__ . '/includes/header.php';
?>

<?php if ($q === '' && $category === 0 && $page === 1): ?>
<section class="hero rounded-4 p-4 p-md-5 mb-4 text-white shadow-sm">
    <div class="row align-items-center">
        <div class="col-md-8">
            <h1 class="display-6 fw-bold">Belanja mudah, jualan pun gampang.</h1>
            <p class="lead mb-4">Temukan produk pilihan dari berbagai penjual terpercaya di <?= e(APP_NAME) ?>.</p>
            <a href="#produk" class="btn btn-warning btn-lg fw-semibold"><i class="bi bi-bag"></i> Mulai Belanja</a>
            <?php if (!is_logged_in()): ?>
                <a href="<?= e(url('register.php?role=seller')) ?>" class="btn btn-outline-light btn-lg ms-2">Jadi Penjual</a>
            <?php endif; ?>
        </div>
        <div class="col-md-4 d-none d-md-block text-center">
            <i class="bi bi-basket2-fill hero-icon"></i>
        </div>
    </div>
</section>
<?php endif; ?>

<div class="row g-4" id="produk">
    <!-- Sidebar kategori -->
    <aside class="col-lg-3">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white fw-semibold"><i class="bi bi-grid"></i> Kategori</div>
            <div class="list-group list-group-flush">
                <a href="<?= e(url('index.php?' . http_build_query(['q' => $q, 'sort' => $sort]))) ?>"
                   class="list-group-item list-group-item-action d-flex justify-content-between <?= $category === 0 ? 'active' : '' ?>">
                    Semua Kategori
                </a>
                <?php foreach ($categories as $cat): ?>
                    <a href="<?= e(url('index.php?' . http_build_query(['q' => $q, 'category' => $cat['id'], 'sort' => $sort]))) ?>"
                       class="list-group-item list-group-item-action d-flex justify-content-between align-items-center <?= $category === (int) $cat['id'] ? 'active' : '' ?>">
                        <?= e($cat['name']) ?>
                        <span class="badge bg-secondary-subtle text-secondary-emphasis rounded-pill"><?= (int) $cat['total'] ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </aside>

    <!-- Daftar produk -->
    <section class="col-lg-9">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
            <div>
                <h2 class="h4 fw-bold mb-0">
                    <?= $q !== '' ? 'Hasil pencarian "' . e($q) . '"' : 'Daftar Produk' ?>
                </h2>
                <small class="text-muted"><?= $total ?> produk ditemukan</small>
            </div>
            <form method="get" class="d-flex align-items-center gap-2">
                <input type="hidden" name="q" value="<?= e($q) ?>">
                <input type="hidden" name="category" value="<?= $category ?: '' ?>">
                <label for="sort" class="small text-muted text-nowrap">Urutkan</label>
                <select name="sort" id="sort" class="form-select form-select-sm" onchange="this.form.submit()">
                    <?php foreach ($sorts as $key => $s): ?>
                        <option value="<?= e($key) ?>" <?= $sort === $key ? 'selected' : '' ?>><?= e($s[0]) ?></option>
                    <?php endforeach; ?>
                </select>
            </form>
        </div>

        <?php if (!$products): ?>
            <div class="text-center py-5 bg-white rounded-3 shadow-sm">
                <i class="bi bi-search display-4 text-muted"></i>
                <p class="mt-3 mb-1 fw-semibold">Produk tidak ditemukan</p>
                <p class="text-muted small">Coba kata kunci atau kategori lain.</p>
                <a href="<?= e(url('index.php')) ?>" class="btn btn-primary btn-sm">Lihat semua produk</a>
            </div>
        <?php else: ?>
            <div class="row row-cols-2 row-cols-md-3 g-3">
                <?php foreach ($products as $p): ?>
                    <div class="col">
                        <div class="card product-card h-100 border-0 shadow-sm">
                            <a href="<?= e(url('product.php?id=' . $p['id'])) ?>" class="ratio ratio-1x1 d-block bg-light rounded-top overflow-hidden">
                                <img src="<?= e(product_image($p['image'])) ?>" alt="<?= e($p['name']) ?>" class="object-fit-cover" loading="lazy">
                            </a>
                            <div class="card-body d-flex flex-column p-3">
                                <?php if ($p['category_name']): ?>
                                    <span class="badge bg-primary-subtle text-primary-emphasis align-self-start mb-2"><?= e($p['category_name']) ?></span>
                                <?php endif; ?>
                                <h3 class="h6 card-title mb-1">
                                    <a href="<?= e(url('product.php?id=' . $p['id'])) ?>" class="stretched-link text-decoration-none text-dark"><?= e($p['name']) ?></a>
                                </h3>
                                <div class="fw-bold text-brand mb-1"><?= rupiah($p['price']) ?></div>
                                <small class="text-muted mt-auto"><i class="bi bi-shop-window"></i> <?= e($p['store_name'] ?: $p['seller_name']) ?></small>
                                <small class="<?= $p['stock'] > 0 ? 'text-success' : 'text-danger' ?>">
                                    <?= $p['stock'] > 0 ? 'Stok: ' . (int) $p['stock'] : 'Stok habis' ?>
                                </small>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="mt-4">
                <?= pagination($page, $totalPages, array_filter(['q' => $q, 'category' => $category ?: null, 'sort' => $sort])) ?>
            </div>
        <?php endif; ?>
    </section>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
