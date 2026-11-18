<?php
/**
 * HALAMAN KERANJANG BELANJA
 */
require_once __DIR__ . '/includes/init.php';

$user = current_user();

if ($user && $user['role'] !== 'buyer') {
    flash('info', 'Keranjang belanja hanya tersedia untuk akun Pembeli.');
    redirect('dashboard/index.php');
}

$items = [];
if ($user) {
    $items = db_fetch_all(
        'SELECT c.id AS cart_id, c.quantity, p.id AS product_id, p.name, p.price, p.stock, p.image,
                u.store_name, u.name AS seller_name
           FROM carts c
           JOIN products p ON p.id = c.product_id
           JOIN users u ON u.id = p.seller_id
          WHERE c.user_id = ?
          ORDER BY c.created_at DESC',
        'i',
        [$user['id']]
    );
}

$totalQty   = 0;
$totalPrice = 0;
$hasProblem = false;
foreach ($items as &$it) {
    $it['subtotal'] = $it['price'] * $it['quantity'];
    $it['problem']  = $it['stock'] < $it['quantity'];
    $hasProblem     = $hasProblem || $it['problem'];
    $totalQty      += $it['quantity'];
    $totalPrice    += $it['subtotal'];
}
unset($it);

$pageTitle  = 'Keranjang Belanja';
$activeMenu = 'cart';
require __DIR__ . '/includes/header.php';
?>

<h1 class="h4 fw-bold mb-3"><i class="bi bi-cart3"></i> Keranjang Belanja</h1>

<?php if (!$user): ?>
    <div class="text-center py-5 bg-white rounded-3 shadow-sm">
        <i class="bi bi-person-lock display-4 text-muted"></i>
        <p class="mt-3 fw-semibold">Silakan login untuk melihat keranjang belanja Anda.</p>
        <a href="<?= e(url('login.php')) ?>" class="btn btn-brand">Masuk</a>
        <a href="<?= e(url('register.php')) ?>" class="btn btn-outline-secondary">Daftar</a>
    </div>
<?php elseif (!$items): ?>
    <div class="text-center py-5 bg-white rounded-3 shadow-sm">
        <i class="bi bi-cart-x display-4 text-muted"></i>
        <p class="mt-3 mb-1 fw-semibold">Keranjang Anda masih kosong</p>
        <p class="text-muted small">Yuk, temukan produk menarik dan tambahkan ke keranjang.</p>
        <a href="<?= e(url('index.php')) ?>" class="btn btn-brand"><i class="bi bi-shop"></i> Mulai Belanja</a>
    </div>
<?php else: ?>
    <?php if ($hasProblem): ?>
        <div class="alert alert-warning"><i class="bi bi-exclamation-triangle"></i>
            Beberapa produk melebihi stok yang tersedia. Silakan perbarui jumlahnya sebelum checkout.</div>
    <?php endif; ?>

    <div class="row g-4">
        <div class="col-lg-8">
            <form action="<?= e(url('cart_action.php')) ?>" method="post" id="cart-form">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="update">
                <div class="card border-0 shadow-sm">
                    <div class="table-responsive">
                        <table class="table align-middle mb-0 cart-table">
                            <thead class="table-light">
                                <tr>
                                    <th>Produk</th>
                                    <th class="text-end">Harga</th>
                                    <th class="text-center" style="width:130px">Jumlah</th>
                                    <th class="text-end">Subtotal</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($items as $it): ?>
                                <tr class="<?= $it['problem'] ? 'table-warning' : '' ?>">
                                    <td>
                                        <div class="d-flex align-items-center gap-3">
                                            <img src="<?= e(product_image($it['image'])) ?>" alt="" class="cart-thumb rounded">
                                            <div>
                                                <a href="<?= e(url('product.php?id=' . $it['product_id'])) ?>" class="fw-semibold text-decoration-none text-dark"><?= e($it['name']) ?></a>
                                                <div class="small text-muted"><i class="bi bi-shop-window"></i> <?= e($it['store_name'] ?: $it['seller_name']) ?></div>
                                                <div class="small <?= $it['problem'] ? 'text-danger fw-semibold' : 'text-muted' ?>">Stok: <?= (int) $it['stock'] ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="text-end text-nowrap"><?= rupiah($it['price']) ?></td>
                                    <td>
                                        <input type="number" name="qty[<?= (int) $it['cart_id'] ?>]" value="<?= (int) $it['quantity'] ?>"
                                               min="0" max="<?= max((int) $it['stock'], (int) $it['quantity']) ?>" class="form-control form-control-sm text-center" aria-label="Jumlah">
                                    </td>
                                    <td class="text-end fw-semibold text-nowrap"><?= rupiah($it['subtotal']) ?></td>
                                    <td class="text-end">
                                        <button type="submit" form="remove-<?= (int) $it['cart_id'] ?>" class="btn btn-sm btn-outline-danger"
                                                data-confirm="Hapus produk ini dari keranjang?" title="Hapus"><i class="bi bi-trash"></i></button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="card-footer bg-white d-flex flex-wrap gap-2 justify-content-between">
                        <a href="<?= e(url('index.php')) ?>" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Lanjut Belanja</a>
                        <div class="d-flex gap-2">
                            <button type="submit" form="clear-cart" class="btn btn-outline-danger btn-sm" data-confirm="Kosongkan seluruh keranjang?"><i class="bi bi-x-circle"></i> Kosongkan</button>
                            <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-arrow-repeat"></i> Perbarui Keranjang</button>
                        </div>
                    </div>
                </div>
            </form>

            <?php foreach ($items as $it): ?>
                <form action="<?= e(url('cart_action.php')) ?>" method="post" id="remove-<?= (int) $it['cart_id'] ?>" class="d-none">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="remove">
                    <input type="hidden" name="cart_id" value="<?= (int) $it['cart_id'] ?>">
                </form>
            <?php endforeach; ?>
            <form action="<?= e(url('cart_action.php')) ?>" method="post" id="clear-cart" class="d-none">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="clear">
            </form>
        </div>

        <div class="col-lg-4">
            <div class="card border-0 shadow-sm sticky-lg-top" style="top: 90px">
                <div class="card-body">
                    <h2 class="h6 fw-bold mb-3">Ringkasan Belanja</h2>
                    <div class="d-flex justify-content-between small mb-2">
                        <span>Total item</span><span><?= $totalQty ?> barang</span>
                    </div>
                    <div class="d-flex justify-content-between small mb-2">
                        <span>Ongkos kirim</span><span class="text-success">Gratis</span>
                    </div>
                    <hr>
                    <div class="d-flex justify-content-between fw-bold fs-5 mb-3">
                        <span>Total</span><span class="text-brand"><?= rupiah($totalPrice) ?></span>
                    </div>
                    <?php if ($hasProblem): ?>
                        <button class="btn btn-secondary w-100" disabled>Checkout</button>
                    <?php else: ?>
                        <a href="<?= e(url('checkout.php')) ?>" class="btn btn-brand w-100 btn-lg"><i class="bi bi-credit-card"></i> Checkout</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
