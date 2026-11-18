<?php
/**
 * =====================================================================
 *  ENDPOINT INDEKS REST API: /api/index.php
 *  Menampilkan daftar seluruh endpoint REST API yang tersedia.
 * =====================================================================
 */

require_once __DIR__ . '/bootstrap.php';

$base = api_base_url();

api_success('Selamat datang di REST API ' . APP_NAME . '. Layanan aktif dan terhubung ke database MySQL.', [
    'service'   => APP_NAME . ' REST API',
    'version'   => '1.0.0',
    'base_url'  => $base . '/api',
    'endpoints' => [
        [
            'no'          => 1,
            'method'      => 'GET',
            'endpoint'    => '/api/produk.php',
            'full_url'    => $base . '/api/produk.php',
            'description' => 'Menampilkan seluruh daftar produk (mendukung filter ?q=, ?category_id=, ?seller_id=, ?page=, ?limit=)',
        ],
        [
            'no'          => 2,
            'method'      => 'GET',
            'endpoint'    => '/api/produk.php?id=1',
            'full_url'    => $base . '/api/produk.php?id=1',
            'description' => 'Menampilkan detail satu produk berdasarkan ID',
        ],
        [
            'no'          => 3,
            'method'      => 'GET',
            'endpoint'    => '/api/kategori.php',
            'full_url'    => $base . '/api/kategori.php',
            'description' => 'Menampilkan daftar kategori beserta jumlah produk',
        ],
        [
            'no'          => 4,
            'method'      => 'GET',
            'endpoint'    => '/api/kategori.php?id=1',
            'full_url'    => $base . '/api/kategori.php?id=1',
            'description' => 'Menampilkan detail kategori beserta daftar produk di dalamnya',
        ],
        [
            'no'          => 5,
            'method'      => 'POST',
            'endpoint'    => '/api/pesanan.php',
            'full_url'    => $base . '/api/pesanan.php',
            'description' => 'Membuat dan menyimpan transaksi pesanan baru ke database (orders & order_items) serta mengurangi stok produk',
        ],
        [
            'no'          => 6,
            'method'      => 'GET',
            'endpoint'    => '/api/pesanan.php',
            'full_url'    => $base . '/api/pesanan.php',
            'description' => 'Menampilkan daftar pesanan atau detail pesanan berdasarkan ?id= atau ?code=INV-...',
        ],
    ],
]);
