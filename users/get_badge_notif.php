<?php
/**
 * get_badge_notif.php
 * Endpoint ringan untuk refresh badge jumlah notif aktif (pending + diproses).
 */
session_start();
header('Content-Type: application/json');
include "koneksi.php";


if (!isset($_SESSION['id_user'])) {
    echo json_encode(['total' => 0]);
    exit;
}

// Ganti query lama:
// "WHERE id_user=? AND status IN ('pending','diproses') AND is_read = 0"

// Dengan:
$q = mysqli_prepare($konek,
    "SELECT COUNT(*) AS total FROM transaksi 
     WHERE id_user = ? 
     AND (notif_status_read IS NULL OR notif_status_read != status)");
mysqli_stmt_bind_param($q, "i", $id_user);
mysqli_stmt_execute($q);
$row = mysqli_fetch_assoc(mysqli_stmt_get_result($q));
echo json_encode(['total' => (int)($row['total'] ?? 0)]);
