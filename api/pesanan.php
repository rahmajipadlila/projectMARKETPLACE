<?php
/**
 * =====================================================================
 *  ENDPOINT REST API: /api/pesanan.php
 * =====================================================================
 *  Mendukung:
 *   1. POST /api/pesanan.php                  -> Membuat pesanan baru (menyimpan ke orders & order_items
 *                                                serta mengurangi stok products secara atomik)
 *   2. GET  /api/pesanan.php                  -> Menampilkan daftar seluruh pesanan
 *   3. GET  /api/pesanan.php?id=1             -> Menampilkan detail pesanan berdasarkan ID
 *   4. GET  /api/pesanan.php?code=INV-...     -> Menampilkan detail pesanan berdasarkan Kode Invoice
 */

require_once __DIR__ . '/bootstrap.php';

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

/**
 * Mengambil rincian item untuk suatu order_id.
 */
function fetch_order_items(int $orderId): array
{
    $items = api_fetch_all(
        'SELECT oi.id, oi.product_id, oi.seller_id, oi.product_name, oi.price, oi.quantity, oi.subtotal,
                u.store_name, u.name AS seller_name
           FROM order_items oi
           LEFT JOIN users u ON u.id = oi.seller_id
          WHERE oi.order_id = ?
          ORDER BY oi.id ASC',
        'i',
        [$orderId]
    );

    return array_map(fn ($it) => [
        'id'                 => (int) $it['id'],
        'product_id'         => $it['product_id'] !== null ? (int) $it['product_id'] : null,
        'product_name'       => $it['product_name'],
        'seller_id'          => $it['seller_id'] !== null ? (int) $it['seller_id'] : null,
        'store_name'         => $it['store_name'] ?: ($it['seller_name'] ?? null),
        'price'              => (float) $it['price'],
        'price_formatted'    => api_rupiah($it['price']),
        'quantity'           => (int) $it['quantity'],
        'subtotal'           => (float) $it['subtotal'],
        'subtotal_formatted' => api_rupiah($it['subtotal']),
    ], $items);
}

/**
 * Memformat baris pesanan beserta itemnya menjadi struktur JSON standar.
 */
function format_order_row(array $order, bool $withItems = true): array
{
    $data = [
        'id'                     => (int) $order['id'],
        'order_code'             => $order['order_code'],
        'buyer'                  => [
            'id'    => (int) $order['buyer_id'],
            'name'  => $order['buyer_name'] ?? null,
            'email' => $order['buyer_email'] ?? null,
        ],
        'recipient_name'         => $order['recipient_name'],
        'phone'                  => $order['phone'],
        'shipping_address'       => $order['shipping_address'],
        'notes'                  => $order['notes'],
        'payment_method'         => $order['payment_method'],
        'total_amount'           => (float) $order['total_amount'],
        'total_amount_formatted' => api_rupiah($order['total_amount']),
        'status'                 => $order['status'],
        'created_at'             => $order['created_at'],
        'updated_at'             => $order['updated_at'],
    ];

    if ($withItems) {
        $data['items'] = fetch_order_items((int) $order['id']);
    }

    return $data;
}

switch ($method) {
    // =================================================================
    // GET: Daftar Pesanan atau Detail Pesanan
    // =================================================================
    case 'GET':
        // A. Detail berdasarkan ?id= atau ?code=
        if ((isset($_GET['id']) && $_GET['id'] !== '') || (isset($_GET['code']) && $_GET['code'] !== '')) {
            if (isset($_GET['id']) && $_GET['id'] !== '') {
                if (!ctype_digit((string) $_GET['id']) || (int) $_GET['id'] <= 0) {
                    api_error(400, 'Parameter id pesanan tidak valid.');
                }
                $order = api_fetch_one(
                    'SELECT o.*, u.name AS buyer_name, u.email AS buyer_email
                       FROM orders o JOIN users u ON u.id = o.buyer_id
                      WHERE o.id = ?',
                    'i',
                    [(int) $_GET['id']]
                );
            } else {
                $code  = trim((string) $_GET['code']);
                $order = api_fetch_one(
                    'SELECT o.*, u.name AS buyer_name, u.email AS buyer_email
                       FROM orders o JOIN users u ON u.id = o.buyer_id
                      WHERE o.order_code = ?',
                    's',
                    [$code]
                );
            }

            if (!$order) {
                api_error(404, 'Data pesanan tidak ditemukan.');
            }

            api_success('Detail pesanan berhasil diambil.', format_order_row($order, true));
        }

        // B. Daftar seluruh pesanan (dapat difilter dengan ?buyer_id= atau ?status=)
        $buyerId = isset($_GET['buyer_id']) && ctype_digit((string) $_GET['buyer_id']) ? (int) $_GET['buyer_id'] : 0;
        $status  = trim((string) ($_GET['status'] ?? ''));

        $where  = [];
        $types  = '';
        $params = [];
        if ($buyerId > 0) {
            $where[]  = 'o.buyer_id = ?';
            $types   .= 'i';
            $params[] = $buyerId;
        }
        if ($status !== '') {
            $where[]  = 'o.status = ?';
            $types   .= 's';
            $params[] = $status;
        }
        $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $rows = api_fetch_all(
            "SELECT o.*, u.name AS buyer_name, u.email AS buyer_email
               FROM orders o
               JOIN users u ON u.id = o.buyer_id
               $whereSql
              ORDER BY o.id DESC
              LIMIT 50",
            $types,
            $params
        );

        $data = array_map(fn ($r) => format_order_row($r, true), $rows);

        api_success('Daftar pesanan berhasil diambil.', $data, 200, [
            'meta' => ['total_orders' => count($data)],
        ]);
        break;

    // =================================================================
    // POST: Membuat Pesanan Baru
    // =================================================================
    case 'POST':
        $input = api_input();
        if (!$input) {
            api_error(400, 'Request body kosong. Kirimkan data pesanan dalam format JSON atau form-data.');
        }

        $buyerId         = isset($input['buyer_id']) ? (int) $input['buyer_id'] : 4; // default Budi Santoso (buyer demo)
        $recipientName   = trim((string) ($input['recipient_name'] ?? ''));
        $phone           = trim((string) ($input['phone'] ?? ''));
        $shippingAddress = trim((string) ($input['shipping_address'] ?? ''));
        $notes           = isset($input['notes']) && trim((string) $input['notes']) !== '' ? trim((string) $input['notes']) : null;
        $paymentMethod   = strtolower(trim((string) ($input['payment_method'] ?? 'cod')));
        $items           = $input['items'] ?? null;

        // Dukung juga format singkat single-item: product_id & quantity langsung di root body
        if ($items === null && isset($input['product_id'])) {
            $items = [
                [
                    'product_id' => (int) $input['product_id'],
                    'quantity'   => (int) ($input['quantity'] ?? 1),
                ],
            ];
        }

        // Validasi data pembeli & pengiriman
        $errors = [];
        $buyer  = api_fetch_one('SELECT id, name, email, phone, address FROM users WHERE id = ?', 'i', [$buyerId]);
        if (!$buyer) {
            $errors['buyer_id'] = 'Pembeli dengan buyer_id=' . $buyerId . ' tidak ditemukan.';
        } else {
            // Jika nama penerima/telepon/alamat tidak diisi, gunakan profil default pembeli
            if ($recipientName === '') {
                $recipientName = $buyer['name'];
            }
            if ($phone === '' && !empty($buyer['phone'])) {
                $phone = $buyer['phone'];
            }
            if ($shippingAddress === '' && !empty($buyer['address'])) {
                $shippingAddress = $buyer['address'];
            }
        }

        if ($recipientName === '' || mb_strlen($recipientName) > 100) {
            $errors['recipient_name'] = 'Nama penerima (recipient_name) wajib diisi (maks. 100 karakter).';
        }
        if (!preg_match('/^[0-9+\-\s]{8,20}$/', $phone)) {
            $errors['phone'] = 'Nomor telepon (phone) wajib valid (8-20 digit).';
        }
        if (mb_strlen($shippingAddress) < 10) {
            $errors['shipping_address'] = 'Alamat pengiriman (shipping_address) minimal 10 karakter.';
        }
        if (!in_array($paymentMethod, ['cod', 'transfer'], true)) {
            $errors['payment_method'] = "Metode pembayaran (payment_method) harus 'cod' atau 'transfer'.";
        }
        if (!is_array($items) || count($items) === 0) {
            $errors['items'] = 'Daftar barang (items) wajib berisi minimal 1 produk [{product_id, quantity}].';
        } else {
            foreach ($items as $idx => $it) {
                $pid = (int) ($it['product_id'] ?? 0);
                $qty = (int) ($it['quantity'] ?? 0);
                if ($pid <= 0 || $qty <= 0) {
                    $errors["items.$idx"] = 'Setiap item wajib memiliki product_id > 0 dan quantity > 0.';
                }
            }
        }

        if ($errors) {
            api_error(422, 'Validasi data pesanan gagal.', $errors);
        }

        // Gabungkan kuantitas bila ada product_id yang duplikat di dalam array items
        $normalizedItems = [];
        foreach ($items as $it) {
            $pid = (int) $it['product_id'];
            $qty = (int) $it['quantity'];
            $normalizedItems[$pid] = ($normalizedItems[$pid] ?? 0) + $qty;
        }

        // -------------------------------------------------------------
        // Eksekusi Transaksi Database Atomik (ACID Transaction)
        // -------------------------------------------------------------
        $db->begin_transaction();
        try {
            $resolvedItems = [];
            $totalAmount   = 0.0;

            $stmtLock = $db->prepare('SELECT id, seller_id, name, price, stock FROM products WHERE id = ? FOR UPDATE');
            foreach ($normalizedItems as $pid => $qty) {
                $stmtLock->bind_param('i', $pid);
                $stmtLock->execute();
                $res     = $stmtLock->get_result();
                $product = $res ? $res->fetch_assoc() : null;

                if (!$product) {
                    $stmtLock->close();
                    $db->rollback();
                    api_error(404, 'Produk dengan ID ' . $pid . ' tidak ditemukan.');
                }
                if ((int) $product['stock'] < $qty) {
                    $stmtLock->close();
                    $db->rollback();
                    api_error(422, 'Stok produk "' . $product['name'] . '" tidak mencukupi (tersedia: ' . $product['stock'] . ', diminta: ' . $qty . ').');
                }

                $price    = (float) $product['price'];
                $subtotal = $price * $qty;
                $totalAmount += $subtotal;

                $resolvedItems[] = [
                    'product_id'   => (int) $product['id'],
                    'seller_id'    => (int) $product['seller_id'],
                    'product_name' => $product['name'],
                    'price'        => $price,
                    'quantity'     => $qty,
                    'subtotal'     => $subtotal,
                ];
            }
            $stmtLock->close();

            // 1. INSERT header pesanan ke tabel orders
            $orderCode = 'INV-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
            api_execute(
                "INSERT INTO orders (order_code, buyer_id, recipient_name, phone, shipping_address, notes, payment_method, total_amount, status)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending')",
                'sisssssd',
                [$orderCode, $buyerId, $recipientName, $phone, $shippingAddress, $notes, $paymentMethod, $totalAmount]
            );
            $orderId = (int) $db->insert_id;

            // 2. INSERT detail ke order_items & 3. UPDATE stok di products
            $stmtItem  = $db->prepare(
                'INSERT INTO order_items (order_id, product_id, seller_id, product_name, price, quantity, subtotal)
                 VALUES (?, ?, ?, ?, ?, ?, ?)'
            );
            $stmtStock = $db->prepare('UPDATE products SET stock = stock - ? WHERE id = ? AND stock >= ?');

            foreach ($resolvedItems as $ri) {
                $stmtItem->bind_param(
                    'iiisdid',
                    $orderId,
                    $ri['product_id'],
                    $ri['seller_id'],
                    $ri['product_name'],
                    $ri['price'],
                    $ri['quantity'],
                    $ri['subtotal']
                );
                $stmtItem->execute();

                $stmtStock->bind_param('iii', $ri['quantity'], $ri['product_id'], $ri['quantity']);
                $stmtStock->execute();
            }
            $stmtItem->close();
            $stmtStock->close();

            $db->commit();

            // Ambil kembali data pesanan yang baru dibuat
            $createdOrder = api_fetch_one(
                'SELECT o.*, u.name AS buyer_name, u.email AS buyer_email
                   FROM orders o JOIN users u ON u.id = o.buyer_id
                  WHERE o.id = ?',
                'i',
                [$orderId]
            );

            api_success('Pesanan baru berhasil dibuat dan disimpan ke database.', format_order_row($createdOrder, true), 201);
        } catch (Throwable $ex) {
            $db->rollback();
            throw $ex;
        }
        break;

    default:
        api_error(405, 'Metode HTTP ' . $method . ' tidak diizinkan pada endpoint ini.');
}
