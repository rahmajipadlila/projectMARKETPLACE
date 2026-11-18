/**
 * MarketKita — Interaksi sisi klien (UI helpers)
 */
(function () {
    'use strict';

    // 1. Tombol +/- jumlah produk
    document.querySelectorAll('[data-qty]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var input = btn.parentElement.querySelector('input[type="number"]');
            if (!input) return;
            var delta = parseInt(btn.getAttribute('data-qty'), 10) || 0;
            var min = parseInt(input.min, 10) || 1;
            var max = parseInt(input.max, 10) || 999999;
            var val = (parseInt(input.value, 10) || min) + delta;
            input.value = Math.max(min, Math.min(max, val));
        });
    });

    // 2. Konfirmasi hapus / batalkan
    document.addEventListener('click', function (e) {
        var btn = e.target.closest('[data-confirm]');
        if (!btn) return;
        var msg = btn.getAttribute('data-confirm') || 'Apakah Anda yakin?';
        if (!window.confirm(msg)) {
            e.preventDefault();
        }
    });

    // 3. Tampilkan/sembunyikan password
    document.querySelectorAll('[data-toggle-password]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var target = document.querySelector(btn.getAttribute('data-toggle-password'));
            if (!target) return;
            var isPassword = target.type === 'password';
            target.type = isPassword ? 'text' : 'password';
            var icon = btn.querySelector('i');
            if (icon) {
                icon.className = isPassword ? 'bi bi-eye-slash' : 'bi bi-eye';
            }
        });
    });

    // 4. Tampilkan field "Nama Toko" di halaman registrasi bila memilih Penjual
    var roleInputs = document.querySelectorAll('input[name="role"]');
    var storeGroup = document.getElementById('store-name-group');
    if (roleInputs.length && storeGroup) {
        var storeInput = document.getElementById('store_name');
        var syncRole = function () {
            var checked = document.querySelector('input[name="role"]:checked');
            var isSeller = checked && checked.value === 'seller';
            storeGroup.classList.toggle('d-none', !isSeller);
            if (storeInput) storeInput.required = isSeller;
        };
        roleInputs.forEach(function (r) { r.addEventListener('change', syncRole); });
        syncRole();
    }

    // 5. Pratinjau gambar produk sebelum diunggah
    document.querySelectorAll('input[type="file"][data-preview]').forEach(function (input) {
        input.addEventListener('change', function () {
            var img = document.querySelector(input.getAttribute('data-preview'));
            var file = input.files && input.files[0];
            if (!img || !file) return;
            var reader = new FileReader();
            reader.onload = function (ev) { img.src = ev.target.result; };
            reader.readAsDataURL(file);
        });
    });

    // 6. Cegah klik ganda (double submit) pada form penting
    document.querySelectorAll('form[data-once]').forEach(function (form) {
        form.addEventListener('submit', function (e) {
            if (form.dataset.submitted === '1') {
                e.preventDefault();
                return;
            }
            form.dataset.submitted = '1';
        });
    });
})();
