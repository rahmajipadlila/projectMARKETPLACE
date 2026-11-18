<?php
/**
 * HALAMAN PROFIL PENGGUNA (ubah data diri & password)
 */
require_once __DIR__ . '/includes/init.php';
require_login();

$user   = current_user();
$errors = [];
$form   = [
    'name'       => $user['name'],
    'phone'      => $user['phone'] ?? '',
    'address'    => $user['address'] ?? '',
    'store_name' => $user['store_name'] ?? '',
];

if (is_post()) {
    verify_csrf();
    foreach (array_keys($form) as $key) {
        $form[$key] = trim((string) ($_POST[$key] ?? ''));
    }
    $currentPw = (string) ($_POST['current_password'] ?? '');
    $newPw     = (string) ($_POST['new_password'] ?? '');

    if ($form['name'] === '' || mb_strlen($form['name']) > 100) {
        $errors['name'] = 'Nama wajib diisi (maks. 100 karakter).';
    }
    if ($form['phone'] !== '' && !preg_match('/^[0-9+\-\s]{8,20}$/', $form['phone'])) {
        $errors['phone'] = 'Nomor telepon tidak valid.';
    }
    if ($user['role'] === 'seller' && $form['store_name'] === '') {
        $errors['store_name'] = 'Nama toko wajib diisi.';
    }
    if ($newPw !== '') {
        $hash = db_fetch_value('SELECT password FROM users WHERE id = ?', 'i', [$user['id']]);
        if (!password_verify($currentPw, (string) $hash)) {
            $errors['current_password'] = 'Password saat ini salah.';
        } elseif (strlen($newPw) < 6) {
            $errors['new_password'] = 'Password baru minimal 6 karakter.';
        }
    }

    if (!$errors) {
        db_execute(
            'UPDATE users SET name = ?, phone = ?, address = ?, store_name = ? WHERE id = ?',
            'ssssi',
            [
                $form['name'],
                $form['phone'] !== '' ? $form['phone'] : null,
                $form['address'] !== '' ? $form['address'] : null,
                $user['role'] === 'seller' ? $form['store_name'] : null,
                $user['id'],
            ]
        );
        if ($newPw !== '') {
            db_execute('UPDATE users SET password = ? WHERE id = ?', 'si', [password_hash($newPw, PASSWORD_DEFAULT), $user['id']]);
        }
        flash('success', 'Profil berhasil diperbarui.');
        redirect('profile.php');
    }
}

$pageTitle = 'Profil Saya';
require __DIR__ . '/includes/header.php';
$invalid = fn ($f) => isset($errors[$f]) ? 'is-invalid' : '';
?>

<div class="row justify-content-center">
    <div class="col-lg-8">
        <h1 class="h4 fw-bold mb-3"><i class="bi bi-person-gear"></i> Profil Saya</h1>
        <form method="post" class="card border-0 shadow-sm" novalidate>
            <?= csrf_field() ?>
            <div class="card-body p-4">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Email</label>
                        <input type="email" class="form-control" value="<?= e($user['email']) ?>" disabled>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Jenis Akun</label>
                        <input type="text" class="form-control" value="<?= e(role_label($user['role'])) ?>" disabled>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="name">Nama Lengkap</label>
                        <input type="text" class="form-control <?= $invalid('name') ?>" id="name" name="name" value="<?= e($form['name']) ?>" required>
                        <div class="invalid-feedback"><?= e($errors['name'] ?? '') ?></div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="phone">No. Telepon</label>
                        <input type="tel" class="form-control <?= $invalid('phone') ?>" id="phone" name="phone" value="<?= e($form['phone']) ?>">
                        <div class="invalid-feedback"><?= e($errors['phone'] ?? '') ?></div>
                    </div>
                    <?php if ($user['role'] === 'seller'): ?>
                        <div class="col-12">
                            <label class="form-label" for="store_name">Nama Toko</label>
                            <input type="text" class="form-control <?= $invalid('store_name') ?>" id="store_name" name="store_name" value="<?= e($form['store_name']) ?>">
                            <div class="invalid-feedback"><?= e($errors['store_name'] ?? '') ?></div>
                        </div>
                    <?php endif; ?>
                    <div class="col-12">
                        <label class="form-label" for="address">Alamat</label>
                        <textarea class="form-control" id="address" name="address" rows="3"><?= e($form['address']) ?></textarea>
                    </div>
                </div>
                <hr class="my-4">
                <h2 class="h6 fw-bold">Ganti Password <span class="text-muted fw-normal small">(kosongkan jika tidak diubah)</span></h2>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label" for="current_password">Password Saat Ini</label>
                        <input type="password" class="form-control <?= $invalid('current_password') ?>" id="current_password" name="current_password" autocomplete="current-password">
                        <div class="invalid-feedback"><?= e($errors['current_password'] ?? '') ?></div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="new_password">Password Baru</label>
                        <input type="password" class="form-control <?= $invalid('new_password') ?>" id="new_password" name="new_password" autocomplete="new-password">
                        <div class="invalid-feedback"><?= e($errors['new_password'] ?? '') ?></div>
                    </div>
                </div>
            </div>
            <div class="card-footer bg-white text-end">
                <button type="submit" class="btn btn-brand"><i class="bi bi-save"></i> Simpan Perubahan</button>
            </div>
        </form>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
