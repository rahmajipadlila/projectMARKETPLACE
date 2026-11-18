<?php
/**
 * CONTOH KONFIGURASI APLIKASI
 * ------------------------------------------------------------------
 * 1. Salin file ini menjadi "config.php" di folder yang sama.
 * 2. Isi kredensial database sesuai lingkungan Anda (lokal / InfinityFree).
 *
 * File "config.php" SUDAH DIMASUKKAN ke .gitignore sehingga
 * password database TIDAK akan ikut terunggah ke GitHub.
 */

defined('APP_INIT') or exit('No direct access');

return [
    // ----- Database -----
    // Lokal (XAMPP)       : host "localhost", user "root", password "" (kosong)
    // InfinityFree        : lihat menu "MySQL Databases" di Control Panel,
    //                       contoh host "sql123.infinityfree.com",
    //                       user "if0_12345678", db "if0_12345678_marketplace"
    'db_host' => 'localhost',
    'db_user' => 'root',
    'db_pass' => '',
    'db_name' => 'marketplace_db',
    'db_port' => 3306,

    // ----- Aplikasi -----
    'app_name' => 'MarketKita',

    // URL dasar aplikasi TANPA garis miring di akhir.
    // Lokal (htdocs/web-marketplace) : 'http://localhost/web-marketplace'
    // InfinityFree                   : 'https://namaanda.infinityfreeapp.com'
    // Kosongkan ('') untuk deteksi otomatis.
    'base_url' => '',

    // Tampilkan detail error (true hanya untuk pengembangan lokal)
    'debug' => false,
];
