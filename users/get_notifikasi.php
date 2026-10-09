<?php
session_start();
header('Content-Type: application/json');
include "koneksi.php";

if (!isset($_SESSION['id_user'])) {
    echo json_encode(['status'=>'error','msg'=>'Unauthorized']);
    exit;
}

$id_user = (int) $_SESSION['id_user'];

$q = mysqli_prepare($konek,
    "SELECT t.id_transaksi, t.tanggal, t.total, t.metode,
            t.nama_makanan, t.nama_kantin, t.jumlah, t.id_menu,
            t.status, t.antrian, t.is_read,
            t.notif_status_read,
            m.foto
     FROM transaksi t
     LEFT JOIN menu_req m ON t.id_menu = m.id
     WHERE t.id_user = ?
     ORDER BY t.tanggal DESC
     LIMIT 50");

if (!$q) {
    echo json_encode(['status'=>'error','msg'=>'Query error: ' . mysqli_error($konek)]);
    exit;
}

mysqli_stmt_bind_param($q, "i", $id_user);
mysqli_stmt_execute($q);
$result = mysqli_stmt_get_result($q);

$data = [];
while ($row = mysqli_fetch_assoc($result)) {
    $row['is_read'] = ($row['notif_status_read'] === $row['status']) ? 1 : 0;

    switch ($row['status']) {
        case 'pending':
            $row['notif_judul'] = '⏳ Menunggu Konfirmasi Penjual';
            $row['notif_pesan'] = 'Pesanan ' . $row['nama_makanan'] . ' sedang menunggu konfirmasi dari ' . $row['nama_kantin'] . '.';
            break;
        case 'dimasak':
            $row['notif_judul'] = '👨‍🍳 Pesanan Sedang Dimasak';
            $row['notif_pesan'] = $row['nama_kantin'] . ' sedang menyiapkan pesananmu.';
            break;
        case 'siap':
            $row['notif_judul'] = '🛎️ Pesanan Siap Diambil';
            $row['notif_pesan'] = $row['nama_makanan'] . ' sudah siap! Segera ambil di ' . $row['nama_kantin'] . '.';
            break;
        case 'selesai':
            $row['notif_judul'] = '🧾 Tunjukkan Struk ke Penjual';
            $row['notif_pesan'] = 'Tunjukkan struk ke ' . $row['nama_kantin'] . ' untuk konfirmasi pembayaran.';
            break;
        case 'lunas':
            $row['notif_judul'] = '✅ Pesanan Selesai & Lunas';
            $row['notif_pesan'] = 'Pesanan lunas! Jangan lupa beri rating!';
            break;
        case 'ditolak':
            $row['notif_judul'] = '❌ Pesanan Ditolak';
            $row['notif_pesan'] = 'Pesanan ' . $row['nama_makanan'] . ' ditolak oleh ' . $row['nama_kantin'] . '.';
            break;
        default:
            $row['notif_judul'] = 'Update Pesanan';
            $row['notif_pesan'] = 'Status pesananmu diperbarui.';
    }
    $data[] = $row;
}
echo json_encode(['status' => 'ok', 'data' => $data], JSON_UNESCAPED_UNICODE);