<?php defined('APP_INIT') or exit('No direct access'); ?>
    </div><!-- /.container -->
</main>

<footer class="bg-dark text-light pt-4 pb-3 mt-auto">
    <div class="container">
        <div class="row g-3">
            <div class="col-md-5">
                <h5 class="fw-bold"><i class="bi bi-bag-heart-fill text-warning"></i> <?= e(APP_NAME) ?></h5>
                <p class="small text-secondary mb-0">Marketplace sederhana yang mempertemukan penjual dan pembeli.
                    Dibangun dengan PHP Native, MySQLi, dan Bootstrap 5.</p>
            </div>
            <div class="col-6 col-md-3">
                <h6 class="text-uppercase small fw-bold">Navigasi</h6>
                <ul class="list-unstyled small mb-0">
                    <li><a class="link-light link-underline-opacity-0" href="<?= e(url('index.php')) ?>">Daftar Produk</a></li>
                    <li><a class="link-light link-underline-opacity-0" href="<?= e(url('cart.php')) ?>">Keranjang</a></li>
                    <li><a class="link-light link-underline-opacity-0" href="<?= e(url('register.php')) ?>">Daftar Akun</a></li>
                </ul>
            </div>
            <div class="col-6 col-md-4">
                <h6 class="text-uppercase small fw-bold">Mulai Berjualan</h6>
                <p class="small text-secondary mb-0">Daftar sebagai <strong class="text-light">Penjual</strong> dan kelola produk Anda melalui dashboard.</p>
            </div>
        </div>
        <hr class="border-secondary">
        <p class="small text-secondary text-center mb-0">&copy; <?= date('Y') ?> <?= e(APP_NAME) ?>. Dibuat untuk tugas Web Marketplace.</p>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= e(url('assets/js/app.js')) ?>?v=1.0"></script>
</body>
</html>
