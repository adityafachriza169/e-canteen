<?php
ob_start();
session_start();
include 'koneksi.php';
ob_clean();
header('Content-Type: application/json');

if (!isset($_SESSION['id_user'])) {
    echo json_encode(['status' => 'error']); exit;
}

$id_user       = (int) $_SESSION['id_user'];
$id_transaksi  = (int) ($_POST['id_transaksi'] ?? 0);

// Ambil status saat ini
$q = mysqli_prepare($konek,
    "SELECT status FROM transaksi WHERE id_transaksi = ? AND id_user = ? LIMIT 1");
mysqli_stmt_bind_param($q, "ii", $id_transaksi, $id_user);
mysqli_stmt_execute($q);
$row = mysqli_fetch_assoc(mysqli_stmt_get_result($q));

if (!$row) {
    echo json_encode(['status' => 'error', 'msg' => 'Not found']); exit;
}

// Simpan status yang sudah dibaca → notif_status_read = status sekarang
$upd = mysqli_prepare($konek,
    "UPDATE transaksi SET is_read = 1, notif_status_read = ? 
     WHERE id_transaksi = ? AND id_user = ?");
mysqli_stmt_bind_param($upd, "sii", $row['status'], $id_transaksi, $id_user);
mysqli_stmt_execute($upd);

// Hitung sisa unread (status berbeda dari notif_status_read)
$cnt = mysqli_prepare($konek,
    "SELECT COUNT(*) AS total FROM transaksi 
     WHERE id_user = ? AND (notif_status_read IS NULL OR notif_status_read != status)");
mysqli_stmt_bind_param($cnt, "i", $id_user);
mysqli_stmt_execute($cnt);
$total = (int) mysqli_fetch_assoc(mysqli_stmt_get_result($cnt))['total'];

echo json_encode(['status' => 'ok', 'total_notif' => $total]);