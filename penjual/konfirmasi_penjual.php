<?php
/**
 * konfirmasi_penjual.php
 * Dipakai di panel PENJUAL untuk:
 *   - aksi=terima    → ubah pending → diproses   (user tidak bisa batal lagi)
 *   - aksi=selesai   → ubah diproses → selesai   (100% selesai, user bisa nilai)
 *   - aksi=tolak     → ubah pending → batal      (penjual menolak, stok dikembalikan)
 *
 * Pastikan halaman panel penjual sudah mengecek role penjual (role != 1) sebelum request ini.
 */
session_start();
header('Content-Type: application/json');
include "../users/koneksi.php";


// Auth penjual
if (!isset($_SESSION['role']) || $_SESSION['role'] != 1) {
    echo json_encode(['status'=>'error','msg'=>'Unauthorized']);
    exit;
}

$aksi         = $_POST['aksi']         ?? '';
$id_transaksi = (int)($_POST['id_transaksi'] ?? 0);

if (!$id_transaksi || !in_array($aksi, ['terima','selesai','tolak'])) {
    echo json_encode(['status'=>'error','msg'=>'Parameter tidak valid']);
    exit;
}

// Ambil data transaksi
$cek = mysqli_prepare($konek,
    "SELECT id_transaksi, status, jumlah, id_menu FROM transaksi WHERE id_transaksi=?");
mysqli_stmt_bind_param($cek, "i", $id_transaksi);
mysqli_stmt_execute($cek);
$row = mysqli_fetch_assoc(mysqli_stmt_get_result($cek));

if (!$row) {
    echo json_encode(['status'=>'error','msg'=>'Transaksi tidak ditemukan']);
    exit;
}

mysqli_begin_transaction($konek);
try {
    if ($aksi === 'terima') {
        // pending → diproses
        if ($row['status'] !== 'pending') throw new Exception('Pesanan tidak dalam status pending');
        $upd = mysqli_prepare($konek, "UPDATE transaksi SET status='diproses' WHERE id_transaksi=?");
        mysqli_stmt_bind_param($upd, "i", $id_transaksi);
        mysqli_stmt_execute($upd);
        $msg = 'Pesanan diterima dan sedang diproses';

    } elseif ($aksi === 'selesai') {
        // diproses → selesai
        if ($row['status'] !== 'diproses') throw new Exception('Pesanan belum berstatus diproses');
        $upd = mysqli_prepare($konek, "UPDATE transaksi SET status='selesai' WHERE id_transaksi=?");
        mysqli_stmt_bind_param($upd, "i", $id_transaksi);
        mysqli_stmt_execute($upd);
        $msg = 'Pesanan telah diselesaikan';

    } elseif ($aksi === 'tolak') {
        // pending → batal, kembalikan stok
        if ($row['status'] !== 'pending') throw new Exception('Hanya pesanan pending yang bisa ditolak');
        $upd = mysqli_prepare($konek, "UPDATE transaksi SET status='batal' WHERE id_transaksi=?");
        mysqli_stmt_bind_param($upd, "i", $id_transaksi);
        mysqli_stmt_execute($upd);

        $rst = mysqli_prepare($konek,
            "UPDATE menu_req SET stok=stok+?, terjual=GREATEST(0,terjual-?) WHERE id=?");
        mysqli_stmt_bind_param($rst, "iii", $row['jumlah'], $row['jumlah'], $row['id_menu']);
        mysqli_stmt_execute($rst);
        $msg = 'Pesanan ditolak';
    }

    mysqli_commit($konek);
    echo json_encode(['status'=>'ok','msg'=>$msg]);

} catch (Exception $e) {
    mysqli_rollback($konek);
    echo json_encode(['status'=>'error','msg'=>$e->getMessage()]);
}
