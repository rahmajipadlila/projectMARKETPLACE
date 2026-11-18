<?php
/**
 * =====================================================================
 *  ENDPOINT REST API: /api/produk.php
 * =====================================================================
 *  Mendukung:
 *   1. GET    /api/produk.php            -> Menampilkan seluruh daftar produk
 *      Query opsional: ?q=kata&category_id=1&seller_id=2&limit=20&page=1
 *   2. GET    /api/produk.php?id=1       -> Menampilkan detail 1 produk berdasarkan ID
 *   3. POST   /api/produk.php            -> Menambahkan produk baru
 *   4. PUT    /api/produk.php?id=1       -> Memperbarui data produk
 *   5. DELETE /api/produk.php?id=1       -> Menghapus produk
 */

require_once __DIR__ . '/bootstrap.php';

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

/**
 * Mengubah baris hasil query produk menjadi struktur JSON yang rapi & konsisten.
 */
function format_product_row(array $row, bool $isDetail = false): array
{
    $item = [
        'id'              => (int) $row['id'],
        'name'            => $row['name'],
        'description'     => $row['description'],
        'price'           => (float) $row['price'],
        'price_formatted' => api_rupiah($row['price']),
        'stock'           => (int) $row['stock'],
        'in_stock'        => ((int) $row['stock']) > 0,
        'image'           => $row['image'],
        'image_url'       => api_image_url($row['image']),
        'category'        => $row['category_id'] ? [
            'id'   => (int) $row['category_id'],
            'name' => $row['category_name'],
        ] : null,
        'seller'          => [
            'id'         => (int) $row['seller_id'],
            'name'       => $row['seller_name'],
            'store_name' => $row['store_name'] ?: $row['seller_name'],
        ],
        'created_at'      => $row['created_at'],
        'updated_at'      => $row['updated_at'],
    ];

    if ($isDetail) {
        $item['seller']['address'] = $row['seller_address'] ?? null;
        $item['seller']['phone']   = $row['seller_phone'] ?? null;
        $item['total_sold']        = (int) ($row['total_sold'] ?? 0);
    }

    return $item;
}

switch ($method) {
    // =================================================================
    // GET: Daftar Seluruh Produk ATAU Detail Produk Berdasarkan ID
    // =================================================================
    case 'GET':
        // -------------------------------------------------------------
        // A. Jika parameter "id" dikirim -> Detail Produk
        // -------------------------------------------------------------
        if (isset($_GET['id']) && $_GET['id'] !== '') {
            $rawId = $_GET['id'];
            if (!ctype_digit((string) $rawId) || (int) $rawId <= 0) {
                api_error(400, 'Parameter id produk tidak valid. Harus berupa angka bulat positif.');
            }
            $id = (int) $rawId;

            $row = api_fetch_one(
                "SELECT p.*,
                        c.name AS category_name,
                        u.name AS seller_name, u.store_name, u.address AS seller_address, u.phone AS seller_phone,
                        (SELECT COALESCE(SUM(oi.quantity), 0)
                           FROM order_items oi
                           JOIN orders o ON o.id = oi.order_id
                          WHERE oi.product_id = p.id AND o.status <> 'dibatalkan') AS total_sold
                   FROM products p
                   LEFT JOIN categories c ON c.id = p.category_id
                   JOIN users u ON u.id = p.seller_id
                  WHERE p.id = ?",
                'i',
                [$id]
            );

            if (!$row) {
                api_error(404, 'Produk dengan ID ' . $id . ' tidak ditemukan.');
            }

            api_success('Detail produk berhasil diambil.', format_product_row($row, true));
        }

        // -------------------------------------------------------------
        // B. Tanpa parameter "id" -> Daftar Seluruh Produk (+ filter)
        // -------------------------------------------------------------
        $q          = trim((string) ($_GET['q'] ?? ''));
        $categoryId = isset($_GET['category_id']) && $_GET['category_id'] !== '' ? (int) $_GET['category_id'] : 0;
        $sellerId   = isset($_GET['seller_id']) && $_GET['seller_id'] !== '' ? (int) $_GET['seller_id'] : 0;
        $page       = max(1, (int) ($_GET['page'] ?? 1));
        $limit      = min(100, max(1, (int) ($_GET['limit'] ?? 20)));
        $offset     = ($page - 1) * $limit;

        $where  = [];
        $types  = '';
        $params = [];

        if ($q !== '') {
            $where[]  = '(p.name LIKE ? OR p.description LIKE ?)';
            $like     = '%' . $q . '%';
            $types   .= 'ss';
            $params[] = $like;
            $params[] = $like;
        }
        if ($categoryId > 0) {
            $where[]  = 'p.category_id = ?';
            $types   .= 'i';
            $params[] = $categoryId;
        }
        if ($sellerId > 0) {
            $where[]  = 'p.seller_id = ?';
            $types   .= 'i';
            $params[] = $sellerId;
        }
        $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $total = (int) api_fetch_value("SELECT COUNT(*) FROM products p $whereSql", $types, $params);

        $rows = api_fetch_all(
            "SELECT p.*, c.name AS category_name, u.name AS seller_name, u.store_name
               FROM products p
               LEFT JOIN categories c ON c.id = p.category_id
               JOIN users u ON u.id = p.seller_id
               $whereSql
              ORDER BY p.id ASC
              LIMIT ? OFFSET ?",
            $types . 'ii',
            array_merge($params, [$limit, $offset])
        );

        $data = array_map(fn ($r) => format_product_row($r, false), $rows);

        api_success(
            'Daftar produk berhasil diambil.',
            $data,
            200,
            [
                'meta' => [
                    'total_items'  => $total,
                    'current_page' => $page,
                    'per_page'     => $limit,
                    'total_pages'  => max(1, (int) ceil($total / $limit)),
                ],
            ]
        );
        break;

    // =================================================================
    // POST: Menambahkan Produk Baru
    // =================================================================
    case 'POST':
        $input  = api_input();
        $errors = [];

        $sellerId    = (int) ($input['seller_id'] ?? 2);
        $categoryId  = isset($input['category_id']) && $input['category_id'] !== '' ? (int) $input['category_id'] : null;
        $name        = trim((string) ($input['name'] ?? ''));
        $description = trim((string) ($input['description'] ?? ''));
        $price       = $input['price'] ?? null;
        $stock       = $input['stock'] ?? null;
        $image       = isset($input['image']) && trim((string) $input['image']) !== '' ? trim((string) $input['image']) : null;

        if ($name === '' || mb_strlen($name) > 150) {
            $errors['name'] = 'Nama produk wajib diisi (maks. 150 karakter).';
        }
        if (mb_strlen($description) < 10) {
            $errors['description'] = 'Deskripsi produk minimal 10 karakter.';
        }
        if ($price === null || !is_numeric($price) || (float) $price < 0) {
            $errors['price'] = 'Harga produk (price) wajib berupa angka >= 0.';
        }
        if ($stock === null || !is_numeric($stock) || (int) $stock < 0) {
            $errors['stock'] = 'Stok produk (stock) wajib berupa bilangan bulat >= 0.';
        }
        if (!api_fetch_value("SELECT COUNT(*) FROM users WHERE id = ? AND role IN ('seller','admin')", 'i', [$sellerId])) {
            $errors['seller_id'] = 'Penjual (seller_id=' . $sellerId . ') tidak ditemukan.';
        }
        if ($categoryId !== null && !api_fetch_value('SELECT COUNT(*) FROM categories WHERE id = ?', 'i', [$categoryId])) {
            $errors['category_id'] = 'Kategori (category_id=' . $categoryId . ') tidak ditemukan.';
        }

        if ($errors) {
            api_error(422, 'Validasi data produk gagal.', $errors);
        }

        api_execute(
            'INSERT INTO products (seller_id, category_id, name, description, price, stock, image) VALUES (?, ?, ?, ?, ?, ?, ?)',
            'iissdis',
            [$sellerId, $categoryId, $name, $description, (float) $price, (int) $stock, $image]
        );
        $newId = (int) $db->insert_id;

        $created = api_fetch_one(
            'SELECT p.*, c.name AS category_name, u.name AS seller_name, u.store_name
               FROM products p
               LEFT JOIN categories c ON c.id = p.category_id
               JOIN users u ON u.id = p.seller_id
              WHERE p.id = ?',
            'i',
            [$newId]
        );

        api_success('Produk baru berhasil ditambahkan.', format_product_row($created, false), 201);
        break;

    // =================================================================
    // PUT: Memperbarui Data Produk Berdasarkan ID
    // =================================================================
    case 'PUT':
        $id = isset($_GET['id']) && ctype_digit((string) $_GET['id']) ? (int) $_GET['id'] : 0;
        if ($id <= 0) {
            api_error(400, 'Parameter ?id= wajib disertakan untuk memperbarui produk.');
        }
        $existing = api_fetch_one('SELECT * FROM products WHERE id = ?', 'i', [$id]);
        if (!$existing) {
            api_error(404, 'Produk dengan ID ' . $id . ' tidak ditemukan.');
        }

        $input       = api_input();
        $name        = isset($input['name']) ? trim((string) $input['name']) : $existing['name'];
        $description = isset($input['description']) ? trim((string) $input['description']) : $existing['description'];
        $price       = isset($input['price']) ? (float) $input['price'] : (float) $existing['price'];
        $stock       = isset($input['stock']) ? (int) $input['stock'] : (int) $existing['stock'];
        $categoryId  = array_key_exists('category_id', $input) ? ((int) $input['category_id'] ?: null) : $existing['category_id'];

        if ($name === '' || $price < 0 || $stock < 0) {
            api_error(422, 'Data pembaruan produk tidak valid.');
        }

        api_execute(
            'UPDATE products SET category_id = ?, name = ?, description = ?, price = ?, stock = ? WHERE id = ?',
            'issdii',
            [$categoryId, $name, $description, $price, $stock, $id]
        );

        $updated = api_fetch_one(
            'SELECT p.*, c.name AS category_name, u.name AS seller_name, u.store_name
               FROM products p LEFT JOIN categories c ON c.id = p.category_id JOIN users u ON u.id = p.seller_id
              WHERE p.id = ?',
            'i',
            [$id]
        );
        api_success('Produk berhasil diperbarui.', format_product_row($updated, false));
        break;

    // =================================================================
    // DELETE: Menghapus Produk Berdasarkan ID
    // =================================================================
    case 'DELETE':
        $id = isset($_GET['id']) && ctype_digit((string) $_GET['id']) ? (int) $_GET['id'] : 0;
        if ($id <= 0) {
            api_error(400, 'Parameter ?id= wajib disertakan untuk menghapus produk.');
        }
        $existing = api_fetch_one('SELECT id, name FROM products WHERE id = ?', 'i', [$id]);
        if (!$existing) {
            api_error(404, 'Produk dengan ID ' . $id . ' tidak ditemukan.');
        }
        api_execute('DELETE FROM products WHERE id = ?', 'i', [$id]);
        api_success('Produk "' . $existing['name'] . '" (ID ' . $id . ') berhasil dihapus.', ['deleted_id' => $id]);
        break;

    default:
        api_error(405, 'Metode HTTP ' . $method . ' tidak diizinkan pada endpoint ini.');
}
