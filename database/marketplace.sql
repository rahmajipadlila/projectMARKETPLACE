-- =====================================================================
--  WEB MARKETPLACE - Struktur Database & Data Awal
--  DBMS     : MySQL 5.7+ / MariaDB 10.3+ (kompatibel InfinityFree)
--  Engine   : InnoDB (mendukung FOREIGN KEY & TRANSACTION)
--  Charset  : utf8mb4
--
--  Cara pakai:
--   - Lokal (XAMPP): buat database "marketplace_db" lalu import file ini.
--   - InfinityFree : buat database dari Control Panel (mis. if0_xxxx_marketplace),
--                    buka phpMyAdmin database tsb, lalu Import file ini.
--  CATATAN: File ini sengaja TIDAK berisi "CREATE DATABASE" karena
--           InfinityFree tidak mengizinkan pembuatan database via SQL.
-- =====================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;
SET time_zone = '+07:00';

DROP TABLE IF EXISTS `order_items`;
DROP TABLE IF EXISTS `orders`;
DROP TABLE IF EXISTS `carts`;
DROP TABLE IF EXISTS `products`;
DROP TABLE IF EXISTS `categories`;
DROP TABLE IF EXISTS `users`;

-- ---------------------------------------------------------------------
-- 1. users : pengguna aplikasi (admin, penjual/seller, pembeli/buyer)
-- ---------------------------------------------------------------------
CREATE TABLE `users` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`       VARCHAR(100) NOT NULL,
  `email`      VARCHAR(150) NOT NULL,
  `password`   VARCHAR(255) NOT NULL,
  `role`       ENUM('admin','seller','buyer') NOT NULL DEFAULT 'buyer',
  `phone`      VARCHAR(20)  DEFAULT NULL,
  `address`    TEXT         DEFAULT NULL,
  `store_name` VARCHAR(100) DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_users_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 2. categories : kategori produk
-- ---------------------------------------------------------------------
CREATE TABLE `categories` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`        VARCHAR(100) NOT NULL,
  `description` VARCHAR(255) DEFAULT NULL,
  `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_categories_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 3. products : produk yang ditawarkan penjual
--    FK seller_id   -> users.id       (1 penjual : N produk)
--    FK category_id -> categories.id  (1 kategori : N produk)
-- ---------------------------------------------------------------------
CREATE TABLE `products` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `seller_id`   INT UNSIGNED NOT NULL,
  `category_id` INT UNSIGNED DEFAULT NULL,
  `name`        VARCHAR(150) NOT NULL,
  `description` TEXT NOT NULL,
  `price`       DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `stock`       INT UNSIGNED NOT NULL DEFAULT 0,
  `image`       VARCHAR(255) DEFAULT NULL,
  `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_products_seller` (`seller_id`),
  KEY `idx_products_category` (`category_id`),
  KEY `idx_products_name` (`name`),
  CONSTRAINT `fk_products_seller` FOREIGN KEY (`seller_id`)
      REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_products_category` FOREIGN KEY (`category_id`)
      REFERENCES `categories` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 4. carts : isi keranjang belanja pembeli
--    FK user_id    -> users.id     (1 pembeli : N item keranjang)
--    FK product_id -> products.id  (1 produk  : N item keranjang)
--    UNIQUE(user_id, product_id) -> satu produk hanya 1 baris per pembeli
-- ---------------------------------------------------------------------
CREATE TABLE `carts` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`    INT UNSIGNED NOT NULL,
  `product_id` INT UNSIGNED NOT NULL,
  `quantity`   INT UNSIGNED NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_carts_user_product` (`user_id`, `product_id`),
  KEY `idx_carts_product` (`product_id`),
  CONSTRAINT `fk_carts_user` FOREIGN KEY (`user_id`)
      REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_carts_product` FOREIGN KEY (`product_id`)
      REFERENCES `products` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 5. orders : header transaksi pemesanan
--    FK buyer_id -> users.id  (1 pembeli : N pesanan)
-- ---------------------------------------------------------------------
CREATE TABLE `orders` (
  `id`               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `order_code`       VARCHAR(30)  NOT NULL,
  `buyer_id`         INT UNSIGNED NOT NULL,
  `recipient_name`   VARCHAR(100) NOT NULL,
  `phone`            VARCHAR(20)  NOT NULL,
  `shipping_address` TEXT NOT NULL,
  `notes`            VARCHAR(255) DEFAULT NULL,
  `payment_method`   ENUM('cod','transfer') NOT NULL DEFAULT 'cod',
  `total_amount`     DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  `status`           ENUM('pending','diproses','dikirim','selesai','dibatalkan') NOT NULL DEFAULT 'pending',
  `created_at`       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_orders_code` (`order_code`),
  KEY `idx_orders_buyer` (`buyer_id`),
  KEY `idx_orders_status` (`status`),
  CONSTRAINT `fk_orders_buyer` FOREIGN KEY (`buyer_id`)
      REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 6. order_items : detail item pada setiap pesanan
--    FK order_id   -> orders.id    (1 pesanan : N item)
--    FK product_id -> products.id  (SET NULL bila produk dihapus,
--                                   data historis tetap aman karena
--                                   nama & harga disimpan sebagai snapshot)
--    FK seller_id  -> users.id     (penjual pemilik produk saat dipesan)
-- ---------------------------------------------------------------------
CREATE TABLE `order_items` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `order_id`     INT UNSIGNED NOT NULL,
  `product_id`   INT UNSIGNED DEFAULT NULL,
  `seller_id`    INT UNSIGNED DEFAULT NULL,
  `product_name` VARCHAR(150) NOT NULL,
  `price`        DECIMAL(12,2) NOT NULL,
  `quantity`     INT UNSIGNED NOT NULL,
  `subtotal`     DECIMAL(14,2) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_items_order` (`order_id`),
  KEY `idx_items_product` (`product_id`),
  KEY `idx_items_seller` (`seller_id`),
  CONSTRAINT `fk_items_order` FOREIGN KEY (`order_id`)
      REFERENCES `orders` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_items_product` FOREIGN KEY (`product_id`)
      REFERENCES `products` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_items_seller` FOREIGN KEY (`seller_id`)
      REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- =====================================================================
--  DATA AWAL (SEED)
--  Akun demo:
--    admin@marketplace.test   / admin123   (admin)
--    toko.andi@marketplace.test / seller123 (seller)
--    toko.sari@marketplace.test / seller123 (seller)
--    budi@marketplace.test    / buyer123   (buyer)
--  >> SEGERA GANTI PASSWORD akun demo setelah aplikasi online. <<
-- =====================================================================

INSERT INTO `users` (`id`, `name`, `email`, `password`, `role`, `phone`, `address`, `store_name`) VALUES
(1, 'Administrator', 'admin@marketplace.test', '$2y$10$Q94Tsf250FPocAYDH4pR4eHfNXoGfGDAoF.qivZzS29Lb7IEcyliu', 'admin', '081200000001', 'Kantor Pusat Marketplace, Jakarta', NULL),
(2, 'Andi Pratama', 'toko.andi@marketplace.test', '$2y$10$asxVyuvRgyDYP/lohGbFLuMe08DV5SUBSVACK0ixM5UW8kmIPnXaq', 'seller', '081200000002', 'Jl. Merdeka No. 10, Bandung', 'Andi Gadget Store'),
(3, 'Sari Wulandari', 'toko.sari@marketplace.test', '$2y$10$asxVyuvRgyDYP/lohGbFLuMe08DV5SUBSVACK0ixM5UW8kmIPnXaq', 'seller', '081200000003', 'Jl. Malioboro No. 5, Yogyakarta', 'Sari Fashion & Food'),
(4, 'Budi Santoso', 'budi@marketplace.test', '$2y$10$Kl3xtJzFA3vJc90JeQ3ADuoksBT3TfwvplWDzZauldtx6JTRIomNS', 'buyer', '081200000004', 'Jl. Sudirman No. 21, RT 02/RW 05, Surabaya 60271', NULL);

INSERT INTO `categories` (`id`, `name`, `description`) VALUES
(1, 'Elektronik', 'Gadget, aksesoris, dan perangkat elektronik'),
(2, 'Fashion', 'Pakaian, sepatu, dan tas'),
(3, 'Makanan & Minuman', 'Makanan ringan, kopi, dan minuman'),
(4, 'Rumah Tangga', 'Perlengkapan dan dekorasi rumah');

INSERT INTO `products` (`id`, `seller_id`, `category_id`, `name`, `description`, `price`, `stock`, `image`) VALUES
(1, 2, 1, 'Headphone Bluetooth Over-Ear', 'Headphone nirkabel dengan Bluetooth 5.3, baterai tahan hingga 40 jam, bantalan telinga empuk, dan fitur noise reduction. Cocok untuk bekerja, belajar, maupun bermain game.', 349000.00, 25, 'assets/img/products/headphone-bluetooth.jpg'),
(2, 2, 1, 'Smartwatch Fitness Tracker', 'Jam tangan pintar dengan layar AMOLED 1.4 inci, pemantau detak jantung, SpO2, langkah kaki, notifikasi smartphone, dan tahan air IP68.', 499000.00, 15, 'assets/img/products/smartwatch.jpg'),
(3, 2, 2, 'Tas Ransel Laptop 15 inci', 'Tas ransel berbahan polyester tahan air dengan kompartemen laptop hingga 15.6 inci, port USB charging, dan banyak saku penyimpanan.', 275000.00, 30, 'assets/img/products/tas-ransel.jpg'),
(4, 2, 4, 'Lampu Meja LED Minimalis', 'Lampu belajar LED dengan 3 mode warna dan 5 tingkat kecerahan, leher fleksibel, hemat energi, dan nyaman di mata.', 129000.00, 40, 'assets/img/products/lampu-meja.jpg'),
(5, 3, 2, 'Kemeja Flanel Pria', 'Kemeja flanel motif kotak berbahan katun premium yang lembut dan nyaman dipakai sehari-hari. Tersedia ukuran M, L, XL.', 159000.00, 50, 'assets/img/products/kemeja-flanel.jpg'),
(6, 3, 2, 'Sneakers Putih Kanvas', 'Sepatu sneakers kanvas warna putih dengan sol karet anti-slip. Desain minimalis, mudah dipadukan dengan berbagai gaya pakaian.', 225000.00, 35, 'assets/img/products/sneakers-putih.jpg'),
(7, 3, 3, 'Kopi Arabika Gayo 250 gr', 'Biji kopi arabika asli dataran tinggi Gayo, Aceh. Roasting medium dengan cita rasa fruity dan aroma khas. Tersedia biji utuh atau bubuk.', 85000.00, 60, 'assets/img/products/kopi-arabika.jpg'),
(8, 3, 3, 'Keripik Singkong Balado 200 gr', 'Keripik singkong renyah dengan bumbu balado pedas manis khas Padang. Dibuat dari singkong pilihan tanpa bahan pengawet.', 25000.00, 100, 'assets/img/products/keripik-singkong.jpg');
