<?php
/**
 * Template header (navbar) — di-include di awal setiap halaman.
 * Variabel opsional sebelum include:
 *   $pageTitle  : judul halaman
 *   $activeMenu : penanda menu aktif (home, cart, orders, dashboard)
 */
defined('APP_INIT') or exit('No direct access');

$pageTitle  = $pageTitle ?? APP_NAME;
$activeMenu = $activeMenu ?? '';
$authUser   = current_user();
$cartCount  = cart_count();
$searchQ    = isset($_GET['q']) && is_string($_GET['q']) ? $_GET['q'] : '';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="<?= e(APP_NAME) ?> — marketplace sederhana untuk penjual dan pembeli.">
    <title><?= e($pageTitle) ?> | <?= e(APP_NAME) ?></title>
    <link rel="icon" href="<?= e(url('assets/img/favicon.svg')) ?>" type="image/svg+xml">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="<?= e(url('assets/css/style.css')) ?>?v=1.0" rel="stylesheet">
</head>
<body class="d-flex flex-column min-vh-100">

<nav class="navbar navbar-expand-lg navbar-dark bg-brand sticky-top shadow-sm">
    <div class="container">
        <a class="navbar-brand fw-bold d-flex align-items-center gap-2" href="<?= e(url('index.php')) ?>">
            <i class="bi bi-bag-heart-fill"></i> <?= e(APP_NAME) ?>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav"
                aria-controls="mainNav" aria-expanded="false" aria-label="Toggle navigasi">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="mainNav">
            <form class="d-flex mx-lg-4 my-2 my-lg-0 flex-grow-1" role="search" action="<?= e(url('index.php')) ?>" method="get">
                <div class="input-group">
                    <input class="form-control" type="search" name="q" placeholder="Cari produk..." value="<?= e($searchQ) ?>" aria-label="Cari produk">
                    <button class="btn btn-warning" type="submit" aria-label="Cari"><i class="bi bi-search"></i></button>
                </div>
            </form>

            <ul class="navbar-nav ms-auto align-items-lg-center gap-lg-1">
                <li class="nav-item">
                    <a class="nav-link <?= $activeMenu === 'home' ? 'active' : '' ?>" href="<?= e(url('index.php')) ?>">
                        <i class="bi bi-shop"></i> Produk
                    </a>
                </li>

                <?php if (!$authUser || $authUser['role'] === 'buyer'): ?>
                    <li class="nav-item">
                        <a class="nav-link position-relative <?= $activeMenu === 'cart' ? 'active' : '' ?>" href="<?= e(url('cart.php')) ?>">
                            <i class="bi bi-cart3"></i> Keranjang
                            <?php if ($cartCount > 0): ?>
                                <span class="badge rounded-pill bg-warning text-dark"><?= $cartCount ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                <?php endif; ?>

                <?php if ($authUser): ?>
                    <?php if ($authUser['role'] === 'buyer'): ?>
                        <li class="nav-item">
                            <a class="nav-link <?= $activeMenu === 'orders' ? 'active' : '' ?>" href="<?= e(url('orders.php')) ?>">
                                <i class="bi bi-receipt"></i> Pesanan Saya
                            </a>
                        </li>
                    <?php else: ?>
                        <li class="nav-item">
                            <a class="nav-link <?= $activeMenu === 'dashboard' ? 'active' : '' ?>" href="<?= e(url('dashboard/index.php')) ?>">
                                <i class="bi bi-speedometer2"></i> Dashboard
                            </a>
                        </li>
                    <?php endif; ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-person-circle"></i> <?= e(explode(' ', $authUser['name'])[0]) ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end shadow">
                            <li><h6 class="dropdown-header"><?= e($authUser['name']) ?><br><small class="text-muted"><?= e(role_label($authUser['role'])) ?></small></h6></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="<?= e(url('profile.php')) ?>"><i class="bi bi-person-gear me-2"></i>Profil Saya</a></li>
                            <li>
                                <form action="<?= e(url('logout.php')) ?>" method="post" class="m-0">
                                    <?= csrf_field() ?>
                                    <button type="submit" class="dropdown-item text-danger"><i class="bi bi-box-arrow-right me-2"></i>Keluar</button>
                                </form>
                            </li>
                        </ul>
                    </li>
                <?php else: ?>
                    <li class="nav-item"><a class="nav-link" href="<?= e(url('login.php')) ?>"><i class="bi bi-box-arrow-in-right"></i> Masuk</a></li>
                    <li class="nav-item"><a class="btn btn-warning btn-sm ms-lg-2 fw-semibold" href="<?= e(url('register.php')) ?>">Daftar</a></li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>

<main class="flex-grow-1 py-4">
    <div class="container">
        <?php foreach (get_flashes() as $flash): ?>
            <div class="alert alert-<?= e($flash['type']) ?> alert-dismissible fade show shadow-sm" role="alert">
                <?= e($flash['message']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button>
            </div>
        <?php endforeach; ?>
