<?php
/**
 * HALAMAN LOGIN
 */
require_once __DIR__ . '/includes/init.php';

if (is_logged_in()) {
    redirect(has_role('buyer') ? 'index.php' : 'dashboard/index.php');
}

$error = '';
$email = '';

if (is_post()) {
    verify_csrf();
    $email    = trim((string) ($_POST['email'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    // Pembatasan percobaan login sederhana (5x gagal -> tunggu 60 detik)
    $attempts  = $_SESSION['login_attempts'] ?? 0;
    $lockUntil = $_SESSION['login_lock_until'] ?? 0;

    if ($lockUntil > time()) {
        $error = 'Terlalu banyak percobaan login. Coba lagi dalam ' . ($lockUntil - time()) . ' detik.';
    } elseif ($email === '' || $password === '') {
        $error = 'Email dan password wajib diisi.';
    } else {
        $user = db_fetch_one('SELECT id, name, password, role FROM users WHERE email = ?', 's', [$email]);
        if ($user && password_verify($password, $user['password'])) {
            unset($_SESSION['login_attempts'], $_SESSION['login_lock_until']);
            login_user($user);
            flash('success', 'Selamat datang kembali, ' . $user['name'] . '!');

            $intended = $_SESSION['intended'] ?? null;
            unset($_SESSION['intended']);
            if ($intended && strpos($intended, '/') === 0 && strpos($intended, '//') !== 0) {
                redirect($intended); // hanya URL internal (diawali "/")
            }
            redirect($user['role'] === 'buyer' ? 'index.php' : 'dashboard/index.php');
        }
        $attempts++;
        $_SESSION['login_attempts'] = $attempts;
        if ($attempts >= 5) {
            $_SESSION['login_lock_until'] = time() + 60;
            $_SESSION['login_attempts']   = 0;
        }
        $error = 'Email atau password salah.';
    }
}

$pageTitle = 'Masuk';
require __DIR__ . '/includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-7 col-lg-5">
        <div class="card border-0 shadow-sm auth-card">
            <div class="card-body p-4 p-md-5">
                <div class="text-center mb-4">
                    <i class="bi bi-person-circle display-5 text-brand"></i>
                    <h1 class="h4 fw-bold mt-2">Masuk ke <?= e(APP_NAME) ?></h1>
                    <p class="text-muted small">Belanja atau kelola toko Anda</p>
                </div>

                <?php if ($error): ?>
                    <div class="alert alert-danger small"><i class="bi bi-exclamation-circle"></i> <?= e($error) ?></div>
                <?php endif; ?>

                <form method="post" novalidate class="needs-validation">
                    <?= csrf_field() ?>
                    <div class="mb-3">
                        <label for="email" class="form-label">Email</label>
                        <input type="email" class="form-control" id="email" name="email" value="<?= e($email) ?>" required autofocus autocomplete="email">
                        <div class="invalid-feedback">Masukkan email yang valid.</div>
                    </div>
                    <div class="mb-4">
                        <label for="password" class="form-label">Password</label>
                        <div class="input-group">
                            <input type="password" class="form-control" id="password" name="password" required autocomplete="current-password">
                            <button class="btn btn-outline-secondary" type="button" data-toggle-password="#password" aria-label="Tampilkan password"><i class="bi bi-eye"></i></button>
                            <div class="invalid-feedback">Password wajib diisi.</div>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-brand w-100 btn-lg">Masuk</button>
                </form>

                <p class="text-center small mt-4 mb-0">Belum punya akun? <a href="<?= e(url('register.php')) ?>">Daftar sekarang</a></p>
            </div>
        </div>

        <div class="card border-0 bg-light mt-3">
            <div class="card-body small">
                <div class="fw-semibold mb-1"><i class="bi bi-info-circle"></i> Akun Demo</div>
                <table class="table table-sm table-borderless mb-0 small">
                    <tr><td>Pembeli</td><td><code>budi@marketplace.test</code></td><td><code>buyer123</code></td></tr>
                    <tr><td>Penjual</td><td><code>toko.andi@marketplace.test</code></td><td><code>seller123</code></td></tr>
                    <tr><td>Admin</td><td><code>admin@marketplace.test</code></td><td><code>admin123</code></td></tr>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
