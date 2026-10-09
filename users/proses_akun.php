<?php
session_start();
require 'koneksi.php';
header('Content-Type: application/json');

if (!isset($_SESSION['id_user'])) {
    echo json_encode(['status' => 'error', 'msg' => 'Sesi habis, silakan login ulang.']);
    exit;
}

$id_user = $_SESSION['id_user'];
$aksi    = $_POST['aksi'] ?? '';

// ============================================================
// AKSI: GANTI PASSWORD
// ============================================================
if ($aksi === 'ganti_password') {

    $pw_lama  = $_POST['pw_lama']  ?? '';
    $pw_baru  = $_POST['pw_baru']  ?? '';
    $konfirm  = $_POST['konfirm']  ?? '';

    if (!$pw_lama || !$pw_baru || !$konfirm) {
        echo json_encode(['status' => 'error', 'msg' => 'Semua field wajib diisi.']);
        exit;
    }
    if (strlen($pw_baru) < 8) {
        echo json_encode(['status' => 'error', 'msg' => 'Password baru minimal 8 karakter.']);
        exit;
    }
    if ($pw_baru !== $konfirm) {
        echo json_encode(['status' => 'error', 'msg' => 'Konfirmasi password tidak cocok.']);
        exit;
    }

    // Ambil password dari DB
    $q   = mysqli_query($konek, "SELECT password FROM users WHERE id_user='$id_user'");
    $row = mysqli_fetch_assoc($q);

    if (!$row) {
        echo json_encode(['status' => 'error', 'msg' => 'User tidak ditemukan.']);
        exit;
    }

    // Cek password lama (MD5)
    if ($row['password'] !== md5($pw_lama)) {
        echo json_encode(['status' => 'error', 'msg' => 'Password lama tidak sesuai.']);
        exit;
    }

    // Pastikan password baru tidak sama dengan lama
    if ($pw_lama === $pw_baru) {
        echo json_encode(['status' => 'error', 'msg' => 'Password baru tidak boleh sama dengan password lama.']);
        exit;
    }

    $hash = md5($pw_baru);
    mysqli_query($konek, "UPDATE users SET password='$hash' WHERE id_user='$id_user'");

    echo json_encode(['status' => 'ok', 'msg' => 'Password berhasil diubah.']);
    exit;
}

// Aksi tidak dikenal
echo json_encode(['status' => 'error', 'msg' => 'Aksi tidak valid.']);