<?php
// php/koneksi.php
// Sesuaikan dengan konfigurasi server kamu
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'db _canteen');

$konek = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
$konek->set_charset('utf8mb4');

if ($konek->connect_error) {
    http_response_code(500);
    die(json_encode(['error' => 'Koneksi database gagal']));
}
