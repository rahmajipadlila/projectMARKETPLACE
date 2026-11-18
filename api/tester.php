<?php
/**
 * =====================================================================
 *  API TESTER & DOKUMENTASI INTERAKTIF (Bergaya Postman di Browser)
 *  Memudahkan pengujian & screenshot seluruh endpoint REST API.
 * =====================================================================
 */
require_once __DIR__ . '/../includes/init.php';

$pageTitle  = 'REST API Tester & Dokumentasi';
$activeMenu = 'api';
$baseApiUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http')
    . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . BASE_URL . '/api';
require __DIR__ . '/../includes/header.php';
?>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <span class="badge bg-primary mb-1">WEB SERVICE / REST API</span>
        <h1 class="h4 fw-bold mb-0"><i class="bi bi-braces-asterisk text-brand"></i> REST API Console &amp; Dokumentasi Endpoint</h1>
        <small class="text-muted">Base URL: <code><?= e($baseApiUrl) ?></code></small>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= e(url('api/produk.php')) ?>" target="_blank" class="btn btn-sm btn-outline-primary"><i class="bi bi-box-arrow-up-right"></i> Buka JSON /api/produk.php</a>
        <a href="<?= e(url('api/kategori.php')) ?>" target="_blank" class="btn btn-sm btn-outline-secondary"><i class="bi bi-box-arrow-up-right"></i> Buka JSON /api/kategori.php</a>
    </div>
</div>

<!-- Daftar Endpoint Preset -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white fw-semibold"><i class="bi bi-list-check"></i> Pilih Skenario Pengujian Cepat (Klik tombol "Uji" untuk memuat ke konsol di bawah)</div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0 small">
            <thead class="table-light">
                <tr>
                    <th style="width:45px">No</th>
                    <th style="width:90px">Method</th>
                    <th>Endpoint URL</th>
                    <th>Fungsi / Skenario Uji</th>
                    <th class="text-end" style="width:180px">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>1</td>
                    <td><span class="badge bg-success">GET</span></td>
                    <td><code>/api/produk.php</code></td>
                    <td>Menampilkan seluruh daftar produk (8 produk)</td>
                    <td class="text-end">
                        <button type="button" class="btn btn-sm btn-primary preset-btn"
                                data-method="GET" data-endpoint="/produk.php" data-body="">
                            <i class="bi bi-play-fill"></i> Uji di Konsol
                        </button>
                        <a href="<?= e(url('api/produk.php')) ?>" target="_blank" class="btn btn-sm btn-outline-secondary" title="Buka di tab baru"><i class="bi bi-box-arrow-up-right"></i></a>
                    </td>
                </tr>
                <tr>
                    <td>2</td>
                    <td><span class="badge bg-success">GET</span></td>
                    <td><code>/api/produk.php?id=1</code></td>
                    <td>Menampilkan detail produk berdasarkan ID (ID = 1)</td>
                    <td class="text-end">
                        <button type="button" class="btn btn-sm btn-primary preset-btn"
                                data-method="GET" data-endpoint="/produk.php?id=1" data-body="">
                            <i class="bi bi-play-fill"></i> Uji di Konsol
                        </button>
                        <a href="<?= e(url('api/produk.php?id=1')) ?>" target="_blank" class="btn btn-sm btn-outline-secondary" title="Buka di tab baru"><i class="bi bi-box-arrow-up-right"></i></a>
                    </td>
                </tr>
                <tr>
                    <td>3</td>
                    <td><span class="badge bg-success">GET</span></td>
                    <td><code>/api/kategori.php</code></td>
                    <td>Menampilkan daftar seluruh kategori beserta jumlah produk</td>
                    <td class="text-end">
                        <button type="button" class="btn btn-sm btn-primary preset-btn"
                                data-method="GET" data-endpoint="/kategori.php" data-body="">
                            <i class="bi bi-play-fill"></i> Uji di Konsol
                        </button>
                        <a href="<?= e(url('api/kategori.php')) ?>" target="_blank" class="btn btn-sm btn-outline-secondary" title="Buka di tab baru"><i class="bi bi-box-arrow-up-right"></i></a>
                    </td>
                </tr>
                <tr>
                    <td>4</td>
                    <td><span class="badge bg-warning text-dark">POST</span></td>
                    <td><code>/api/pesanan.php</code></td>
                    <td>Membuat pesanan baru (simpan ke <code>orders</code> &amp; <code>order_items</code> + kurangi stok)</td>
                    <td class="text-end">
                        <button type="button" class="btn btn-sm btn-warning fw-semibold preset-btn"
                                data-method="POST" data-endpoint="/pesanan.php"
                                data-body='{
  "buyer_id": 4,
  "recipient_name": "Budi Santoso",
  "phone": "081200000004",
  "shipping_address": "Jl. Sudirman No. 21, RT 02/RW 05, Surabaya 60271",
  "payment_method": "cod",
  "notes": "Tolong dibungkus bubble wrap tebal",
  "items": [
    { "product_id": 1, "quantity": 1 },
    { "product_id": 7, "quantity": 2 }
  ]
}'>
                            <i class="bi bi-send-fill"></i> Uji POST Pesanan
                        </button>
                    </td>
                </tr>
                <tr>
                    <td>5</td>
                    <td><span class="badge bg-success">GET</span></td>
                    <td><code>/api/pesanan.php</code></td>
                    <td>Menampilkan daftar pesanan yang telah tersimpan di database</td>
                    <td class="text-end">
                        <button type="button" class="btn btn-sm btn-primary preset-btn"
                                data-method="GET" data-endpoint="/pesanan.php" data-body="">
                            <i class="bi bi-play-fill"></i> Uji di Konsol
                        </button>
                        <a href="<?= e(url('api/pesanan.php')) ?>" target="_blank" class="btn btn-sm btn-outline-secondary" title="Buka di tab baru"><i class="bi bi-box-arrow-up-right"></i></a>
                    </td>
                </tr>
                <tr>
                    <td>6</td>
                    <td><span class="badge bg-danger">GET 404</span></td>
                    <td><code>/api/produk.php?id=999</code></td>
                    <td>Uji penanganan error saat data produk tidak ditemukan (HTTP 404)</td>
                    <td class="text-end">
                        <button type="button" class="btn btn-sm btn-outline-danger preset-btn"
                                data-method="GET" data-endpoint="/produk.php?id=999" data-body="">
                            <i class="bi bi-bug"></i> Uji Error 404
                        </button>
                        <a href="<?= e(url('api/produk.php?id=999')) ?>" target="_blank" class="btn btn-sm btn-outline-secondary" title="Buka di tab baru"><i class="bi bi-box-arrow-up-right"></i></a>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<!-- Konsol Pengujian Interaktif (Mirip Postman) -->
<div class="card border-0 shadow-sm">
    <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
        <span class="fw-semibold"><i class="bi bi-terminal"></i> HTTP Request &amp; Response Inspector</span>
        <span class="badge bg-secondary" id="api-base-label"><?= e($baseApiUrl) ?></span>
    </div>
    <div class="card-body">
        <form id="api-test-form" class="row g-2 align-items-center mb-3">
            <div class="col-sm-3 col-md-2">
                <select id="req-method" class="form-select fw-bold">
                    <option value="GET">GET</option>
                    <option value="POST">POST</option>
                    <option value="PUT">PUT</option>
                    <option value="DELETE">DELETE</option>
                </select>
            </div>
            <div class="col-sm-6 col-md-8">
                <input type="text" id="req-url" class="form-control font-monospace" value="<?= e($baseApiUrl . '/produk.php') ?>" required>
            </div>
            <div class="col-sm-3 col-md-2">
                <button type="submit" class="btn btn-brand w-100" id="send-btn"><i class="bi bi-send"></i> Send</button>
            </div>
        </form>

        <div class="row g-3">
            <div class="col-lg-5">
                <label class="form-label small fw-bold text-uppercase text-muted" for="req-body">Request Body (JSON — untuk POST / PUT)</label>
                <textarea id="req-body" class="form-control font-monospace small" rows="13" placeholder="Kosongkan untuk request GET"></textarea>
            </div>
            <div class="col-lg-7">
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <span class="small fw-bold text-uppercase text-muted">Response JSON</span>
                    <div class="d-flex gap-2 align-items-center">
                        <span id="res-status" class="badge bg-secondary">Belum dikirim</span>
                        <span id="res-time" class="badge bg-light text-dark border">- ms</span>
                    </div>
                </div>
                <pre id="res-output" class="bg-dark text-light p-3 rounded-3 small mb-0" style="height:295px;overflow:auto">Klik tombol "Send" atau pilih salah satu skenario uji di atas...</pre>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    const baseApi   = <?= json_encode($baseApiUrl) ?>;
    const form      = document.getElementById('api-test-form');
    const methodEl  = document.getElementById('req-method');
    const urlEl     = document.getElementById('req-url');
    const bodyEl    = document.getElementById('req-body');
    const statusEl  = document.getElementById('res-status');
    const timeEl    = document.getElementById('res-time');
    const outputEl  = document.getElementById('res-output');

    async function sendRequest() {
        const method = methodEl.value;
        const url    = urlEl.value.trim();
        const opts   = { method, headers: { 'Accept': 'application/json' } };

        if (method === 'POST' || method === 'PUT') {
            opts.headers['Content-Type'] = 'application/json';
            opts.body = bodyEl.value;
        }

        statusEl.className = 'badge bg-secondary';
        statusEl.textContent = 'Mengirim...';
        outputEl.textContent = 'Menunggu respons dari ' + url + ' ...';

        const t0 = performance.now();
        try {
            const res  = await fetch(url, opts);
            const dt   = Math.round(performance.now() - t0);
            const text = await res.text();
            timeEl.textContent = dt + ' ms';

            statusEl.textContent = 'HTTP ' + res.status + ' ' + res.statusText;
            statusEl.className   = 'badge ' + (res.ok ? 'bg-success' : 'bg-danger');

            try {
                const json = JSON.parse(text);
                outputEl.textContent = JSON.stringify(json, null, 2);
            } catch (_) {
                outputEl.textContent = text;
            }
        } catch (err) {
            statusEl.className   = 'badge bg-danger';
            statusEl.textContent = 'Network Error';
            outputEl.textContent = String(err);
        }
    }

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        sendRequest();
    });

    document.querySelectorAll('.preset-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            methodEl.value = btn.getAttribute('data-method');
            urlEl.value    = baseApi + btn.getAttribute('data-endpoint');
            bodyEl.value   = btn.getAttribute('data-body') || '';
            sendRequest();
        });
    });

    // Jalankan otomatis GET /api/produk.php saat halaman dibuka
    sendRequest();
})();
</script>

<?php require __DIR__ . '/../includes/footer.php'; ?>
