<?php
/**
 * get_pesanan.php
 * Mengembalikan JSON daftar transaksi milik user yang login.
 * ?filter=All | Sedang Diproses | Proses | Siap Ambil | Selesai | Dibatalkan
 *
 * Mapping filter → status DB:
 *   All             → semua
 *   Sedang Diproses → pending   (menunggu konfirmasi penjual)
 *   Proses          → dimasak   (penjual sedang masak)
 *   Siap Ambil      → siap      (siap diambil, tunggu struk)
 *   Selesai         → selesai   (penjual klik selesai, pembeli tunjukkan struk)
 *   Lunas           → lunas     (penjual konfirm lunas, tampil rating)
 *   Dibatalkan      → ditolak
 */
session_start();
header('Content-Type: application/json');
include "koneksi.php";


if (!isset($_SESSION['id_user'])) {
    echo json_encode(['status'=>'error','msg'=>'Unauthorized']);
    exit;
}

$id_user = (int) $_SESSION['id_user'];
$filter  = $_GET['filter'] ?? 'All';

$filterMap = [
    'All'             => null,
    'Sedang Diproses' => 'pending',
    'Proses'          => 'dimasak',
    'Siap Ambil'      => 'siap',
    'Selesai'         => 'selesai',
    'Lunas'           => 'lunas',
    'Dibatalkan'      => 'batal',
];

$status_filter = $filterMap[$filter] ?? null;

if ($status_filter) {
    $q = mysqli_prepare($konek,
        "SELECT t.id_transaksi, t.id_user, t.tanggal, t.total, t.metode,
                t.nama_makanan, t.nama_kantin, t.jumlah, t.id_menu,
                t.status, t.antrian,
                m.foto, m.id_kantin,
                u.pp AS foto_user
         FROM transaksi t
         LEFT JOIN menu_req m ON t.id_menu = m.id
         LEFT JOIN users u ON t.id_user = u.id_user
         WHERE t.id_user = ? AND t.status = ?
         ORDER BY t.tanggal DESC");
    mysqli_stmt_bind_param($q, "is", $id_user, $status_filter);
} else {
    $q = mysqli_prepare($konek,
        "SELECT t.id_transaksi, t.id_user, t.tanggal, t.total, t.metode,
                t.nama_makanan, t.nama_kantin, t.jumlah, t.id_menu,
                t.status, t.antrian,
                m.foto, m.id_kantin,
                u.pp AS foto_user
         FROM transaksi t
         LEFT JOIN menu_req m ON t.id_menu = m.id
         LEFT JOIN users u ON t.id_user = u.id_user
         WHERE t.id_user = ?
         ORDER BY t.tanggal DESC");
    mysqli_stmt_bind_param($q, "i", $id_user);
}

mysqli_stmt_execute($q);
$result = mysqli_stmt_get_result($q);
$data   = [];
while ($row = mysqli_fetch_assoc($result)) {
    $data[] = $row;
}

echo json_encode(['status' => 'ok', 'data' => $data]);