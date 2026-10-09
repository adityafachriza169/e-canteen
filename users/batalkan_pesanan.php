<?php
/**
 * batalkan_pesanan.php
 * User hanya bisa batalkan pesanan dengan status 'pending'.
 * Setelah penjual konfirmasi (status diproses), user tidak bisa membatalkan.
 */
session_start();
header('Content-Type: application/json');
include "koneksi.php";

if (!isset($_SESSION['id_user']) || (int)($_SESSION['role'] ?? -1) !== 2) {
    echo json_encode(['status'=>'error','msg'=>'Unauthorized']);
    exit;
}

$id_user      = (int) $_SESSION['id_user'];
$id_transaksi = (int) ($_POST['id_transaksi'] ?? 0);

if (!$id_transaksi) {
    echo json_encode(['status'=>'error','msg'=>'ID transaksi tidak valid']);
    exit;
}

// Cek kepemilikan & status
$cek = mysqli_prepare($konek,
    "SELECT id_transaksi, status, jumlah, id_menu FROM transaksi
     WHERE id_transaksi = ? AND id_user = ?");
mysqli_stmt_bind_param($cek, "ii", $id_transaksi, $id_user);
mysqli_stmt_execute($cek);
$row = mysqli_fetch_assoc(mysqli_stmt_get_result($cek));

if (!$row) {
    echo json_encode(['status'=>'error','msg'=>'Pesanan tidak ditemukan']);
    exit;
}

if ($row['status'] !== 'pending') {
    $pesanErr = 'Pesanan tidak dapat dibatalkan karena sudah ' .
        ($row['status'] === 'diproses' ? 'dikonfirmasi penjual dan sedang diproses' :
        ($row['status'] === 'selesai'  ? 'selesai' : 'dibatalkan sebelumnya'));
    echo json_encode(['status'=>'error','msg'=>$pesanErr]);
    exit;
}

// Update status → batal & kembalikan stok
mysqli_begin_transaction($konek);
try {
    $upd = mysqli_prepare($konek,
        "UPDATE transaksi SET status='batal' WHERE id_transaksi=? AND id_user=?");
    mysqli_stmt_bind_param($upd, "ii", $id_transaksi, $id_user);
    mysqli_stmt_execute($upd);

    // kembalikan stok dan terjual
    $rst = mysqli_prepare($konek,
        "UPDATE menu_req SET stok=stok+?, terjual=GREATEST(0, terjual-?) WHERE id=?");
    mysqli_stmt_bind_param($rst, "iii", $row['jumlah'], $row['jumlah'], $row['id_menu']);
    mysqli_stmt_execute($rst);

    mysqli_commit($konek);
    echo json_encode(['status'=>'ok','msg'=>'Pesanan berhasil dibatalkan']);
} catch (Exception $e) {
    mysqli_rollback($konek);
    echo json_encode(['status'=>'error','msg'=>'Gagal membatalkan: '.$e->getMessage()]);
}
