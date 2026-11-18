<?php
/**
 * HALAMAN REGISTRASI AKUN (Pembeli / Penjual)
 */
require_once __DIR__ . '/includes/init.php';

if (is_logged_in()) {
    redirect('index.php');
}

$errors = [];
$form   = [
    'name'       => '',
    'email'      => '',
    'phone'      => '',
    'role'       => ($_GET['role'] ?? '') === 'seller' ? 'seller' : 'buyer',
    'store_name' => '',
];

if (is_post()) {
    verify_csrf();
    foreach (array_keys($form) as $key) {
        $form[$key] = trim((string) ($_POST[$key] ?? ''));
    }
    $password = (string) ($_POST['password'] ?? '');
    $confirm  = (string) ($_POST['password_confirm'] ?? '');

    if ($form['name'] === '' || mb_strlen($form['name']) > 100) {
        $errors['name'] = 'Nama wajib diisi (maks. 100 karakter).';
    }
    if (!filter_var($form['email'], FILTER_VALIDATE_EMAIL) || mb_strlen($form['email']) > 150) {
        $errors['email'] = 'Format email tidak valid.';
    } elseif (db_fetch_value('SELECT COUNT(*) FROM users WHERE email = ?', 's', [$form['email']]) > 0) {
        $errors['email'] = 'Email sudah terdaftar. Silakan gunakan email lain atau login.';
    }
    if ($form['phone'] !== '' && !preg_match('/^[0-9+\-\s]{8,20}$/', $form['phone'])) {
        $errors['phone'] = 'Nomor telepon tidak valid.';
    }
    if (!in_array($form['role'], ['buyer', 'seller'], true)) {
        $errors['role'] = 'Jenis akun tidak valid.';
    }
    if ($form['role'] === 'seller' && ($form['store_name'] === '' || mb_strlen($form['store_name']) > 100)) {
        $errors['store_name'] = 'Nama toko wajib diisi untuk akun penjual.';
    }
    if (strlen($password) < 6) {
        $errors['password'] = 'Password minimal 6 karakter.';
    } elseif ($password !== $confirm) {
        $errors['password_confirm'] = 'Konfirmasi password tidak cocok.';
    }

    if (!$errors) {
        db_execute(
            'INSERT INTO users (name, email, password, role, phone, store_name) VALUES (?, ?, ?, ?, ?, ?)',
            'ssssss',
            [
                $form['name'],
                $form['email'],
                password_hash($password, PASSWORD_DEFAULT),
                $form['role'],
                $form['phone'] !== '' ? $form['phone'] : null,
                $form['role'] === 'seller' ? $form['store_name'] : null,
            ]
        );
        login_user(['id' => db_insert_id()]);
        flash('success', 'Registrasi berhasil! Selamat bergabung di ' . APP_NAME . '.');
        redirect($form['role'] === 'seller' ? 'dashboard/index.php' : 'index.php');
    }
}

$pageTitle = 'Daftar Akun';
require __DIR__ . '/includes/header.php';

$invalid = fn ($f) => isset($errors[$f]) ? 'is-invalid' : '';
?>

<div class="row justify-content-center">
    <div class="col-md-8 col-lg-6">
        <div class="card border-0 shadow-sm auth-card">
            <div class="card-body p-4 p-md-5">
                <div class="text-center mb-4">
                    <i class="bi bi-person-plus display-5 text-brand"></i>
                    <h1 class="h4 fw-bold mt-2">Buat Akun Baru</h1>
                    <p class="text-muted small">Daftar gratis sebagai pembeli atau penjual</p>
                </div>

                <form method="post" novalidate class="needs-validation">
                    <?= csrf_field() ?>
                    <div class="mb-3">
                        <label class="form-label d-block">Daftar sebagai</label>
                        <div class="btn-group w-100" role="group" aria-label="Jenis akun">
                            <input type="radio" class="btn-check" name="role" id="role_buyer" value="buyer" <?= $form['role'] === 'buyer' ? 'checked' : '' ?>>
                            <label class="btn btn-outline-primary" for="role_buyer"><i class="bi bi-bag"></i> Pembeli</label>
                            <input type="radio" class="btn-check" name="role" id="role_seller" value="seller" <?= $form['role'] === 'seller' ? 'checked' : '' ?>>
                            <label class="btn btn-outline-primary" for="role_seller"><i class="bi bi-shop"></i> Penjual</label>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="name" class="form-label">Nama Lengkap</label>
                        <input type="text" class="form-control <?= $invalid('name') ?>" id="name" name="name" value="<?= e($form['name']) ?>" maxlength="100" required>
                        <div class="invalid-feedback"><?= e($errors['name'] ?? 'Nama wajib diisi.') ?></div>
                    </div>
                    <div class="mb-3" id="store-name-group">
                        <label for="store_name" class="form-label">Nama Toko</label>
                        <input type="text" class="form-control <?= $invalid('store_name') ?>" id="store_name" name="store_name" value="<?= e($form['store_name']) ?>" maxlength="100">
                        <div class="invalid-feedback"><?= e($errors['store_name'] ?? 'Nama toko wajib diisi.') ?></div>
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-md-7">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" class="form-control <?= $invalid('email') ?>" id="email" name="email" value="<?= e($form['email']) ?>" required autocomplete="email">
                            <div class="invalid-feedback"><?= e($errors['email'] ?? 'Masukkan email yang valid.') ?></div>
                        </div>
                        <div class="col-md-5">
                            <label for="phone" class="form-label">No. Telepon <span class="text-muted small">(opsional)</span></label>
                            <input type="tel" class="form-control <?= $invalid('phone') ?>" id="phone" name="phone" value="<?= e($form['phone']) ?>" pattern="[0-9+\-\s]{8,20}">
                            <div class="invalid-feedback"><?= e($errors['phone'] ?? 'Nomor telepon tidak valid.') ?></div>
                        </div>
                    </div>
                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label for="password" class="form-label">Password</label>
                            <input type="password" class="form-control <?= $invalid('password') ?>" id="password" name="password" minlength="6" required autocomplete="new-password">
                            <div class="invalid-feedback"><?= e($errors['password'] ?? 'Password minimal 6 karakter.') ?></div>
                        </div>
                        <div class="col-md-6">
                            <label for="password_confirm" class="form-label">Konfirmasi Password</label>
                            <input type="password" class="form-control <?= $invalid('password_confirm') ?>" id="password_confirm" name="password_confirm" minlength="6" required autocomplete="new-password">
                            <div class="invalid-feedback"><?= e($errors['password_confirm'] ?? 'Konfirmasi password wajib diisi.') ?></div>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-brand w-100 btn-lg">Daftar</button>
                </form>
                <p class="text-center small mt-4 mb-0">Sudah punya akun? <a href="<?= e(url('login.php')) ?>">Masuk</a></p>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
