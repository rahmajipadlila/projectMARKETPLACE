<?php
/**
 * =====================================================================
 *  ENDPOINT REST API: /api/kategori.php
 * =====================================================================
 *  Mendukung:
 *   1. GET /api/kategori.php       -> Menampilkan seluruh daftar kategori beserta jumlah produk
 *   2. GET /api/kategori.php?id=1  -> Menampilkan detail kategori beserta daftar produknya
 */

require_once __DIR__ . '/bootstrap.php';

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

if ($method !== 'GET') {
    api_error(405, 'Metode HTTP ' . $method . ' tidak diizinkan. Gunakan GET.');
}

// -----------------------------------------------------------------
// A. Detail Kategori Berdasarkan ID (+ daftar produk di dalamnya)
// -----------------------------------------------------------------
if (isset($_GET['id']) && $_GET['id'] !== '') {
    $rawId = $_GET['id'];
    if (!ctype_digit((string) $rawId) || (int) $rawId <= 0) {
        api_error(400, 'Parameter id kategori tidak valid.');
    }
    $id = (int) $rawId;

    $cat = api_fetch_one(
        'SELECT c.id, c.name, c.description, c.created_at, COUNT(p.id) AS product_count
           FROM categories c
           LEFT JOIN products p ON p.category_id = c.id
          WHERE c.id = ?
          GROUP BY c.id, c.name, c.description, c.created_at',
        'i',
        [$id]
    );

    if (!$cat) {
        api_error(404, 'Kategori dengan ID ' . $id . ' tidak ditemukan.');
    }

    $products = api_fetch_all(
        'SELECT p.id, p.name, p.price, p.stock, p.image, u.store_name, u.name AS seller_name
           FROM products p
           JOIN users u ON u.id = p.seller_id
          WHERE p.category_id = ?
          ORDER BY p.id ASC',
        'i',
        [$id]
    );

    $formattedProducts = array_map(fn ($p) => [
        'id'              => (int) $p['id'],
        'name'            => $p['name'],
        'price'           => (float) $p['price'],
        'price_formatted' => api_rupiah($p['price']),
        'stock'           => (int) $p['stock'],
        'image_url'       => api_image_url($p['image']),
        'store_name'      => $p['store_name'] ?: $p['seller_name'],
    ], $products);

    api_success('Detail kategori berhasil diambil.', [
        'id'            => (int) $cat['id'],
        'name'          => $cat['name'],
        'description'   => $cat['description'],
        'product_count' => (int) $cat['product_count'],
        'created_at'    => $cat['created_at'],
        'products'      => $formattedProducts,
    ]);
}

// -----------------------------------------------------------------
// B. Daftar Seluruh Kategori
// -----------------------------------------------------------------
$rows = api_fetch_all(
    'SELECT c.id, c.name, c.description, c.created_at, COUNT(p.id) AS product_count
       FROM categories c
       LEFT JOIN products p ON p.category_id = c.id
      GROUP BY c.id, c.name, c.description, c.created_at
      ORDER BY c.id ASC'
);

$data = array_map(fn ($c) => [
    'id'            => (int) $c['id'],
    'name'          => $c['name'],
    'description'   => $c['description'],
    'product_count' => (int) $c['product_count'],
    'created_at'    => $c['created_at'],
], $rows);

api_success('Daftar kategori berhasil diambil.', $data, 200, [
    'meta' => ['total_categories' => count($data)],
]);
