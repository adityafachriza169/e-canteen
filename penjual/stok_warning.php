<?php
// =============================================
// stok_warning.php — API: menu stok ≤ 10
// =============================================
session_start();
header('Content-Type: application/json');

// Auth check
if (!isset($_SESSION['username']) || (int)($_SESSION['role'] ?? 0) !== 1) {
    echo json_encode(['items' => []]);
    exit;
}

include "../users/koneksi.php";
if (!isset($db) && isset($konek)) $db = $konek;

$ses_username = $_SESSION['username'];

// Ambil id_kantin penjual ini
$stmt = mysqli_prepare($db, "SELECT id_kantin FROM penjual WHERE username = ? LIMIT 1");
mysqli_stmt_bind_param($stmt, 's', $ses_username);
mysqli_stmt_execute($stmt);
$row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

if (!$row) {
    echo json_encode(['items' => []]);
    exit;
}
$id_kantin = (int) $row['id_kantin'];

// Ambil menu dengan stok 1–10 (aktif, bukan 0/habis total)
$stmt2 = mysqli_prepare($db,
    "SELECT id, nama_makanan, stok
     FROM menu_req
     WHERE id_kantin = ?
       AND aktif = 1
       AND stok > 0
       AND stok <= 10
     ORDER BY stok ASC
     LIMIT 20"
);
mysqli_stmt_bind_param($stmt2, 'i', $id_kantin);
mysqli_stmt_execute($stmt2);
$res = mysqli_stmt_get_result($stmt2);

$items = [];
while ($m = mysqli_fetch_assoc($res)) {
    $items[] = [
        'id'   => (int) $m['id'],
        'nama' => $m['nama_makanan'],
        'stok' => (int) $m['stok'],
    ];
}
mysqli_stmt_close($stmt2);

echo json_encode(['items' => $items]);
