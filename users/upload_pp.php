<?php
session_start();
include "koneksi.php";


header('Content-Type: application/json');

if (!isset($_SESSION['id_user'])) {
    echo json_encode([
        'status' => 'error',
        'msg' => 'Belum login'
    ]);
    exit;
}

if (!isset($_FILES['foto'])) {
    echo json_encode([
        'status' => 'error',
        'msg' => 'File tidak ditemukan'
    ]);
    exit;
}

$file = $_FILES['foto'];

if ($file['error'] !== 0) {
    echo json_encode([
        'status' => 'error',
        'msg' => 'Upload gagal'
    ]);
    exit;
}

$allowed = ['image/jpeg', 'image/png'];

if (!in_array($file['type'], $allowed)) {
    echo json_encode([
        'status' => 'error',
        'msg' => 'Format harus JPG/PNG'
    ]);
    exit;
}

if ($file['size'] > 1024 * 1024) {
    echo json_encode([
        'status' => 'error',
        'msg' => 'Ukuran maksimal 1MB'
    ]);
    exit;
}

$ext = pathinfo($file['name'], PATHINFO_EXTENSION);

$namaBaru = 'pp_' . time() . '_' . rand(1000,9999) . '.' . $ext;

$folder = "../gambar/profil/";

if (!is_dir($folder)) {
    mkdir($folder, 0777, true);
}

$pathSimpan = $folder . $namaBaru;

if (!move_uploaded_file($file['tmp_name'], $pathSimpan)) {
    echo json_encode([
        'status' => 'error',
        'msg' => 'Gagal menyimpan file'
    ]);
    exit;
}

$id_user = $_SESSION['id_user'];

$stmt = mysqli_prepare($konek, "UPDATE users SET pp=? WHERE id_user=?");
mysqli_stmt_bind_param($stmt, "si", $namaBaru, $id_user);
mysqli_stmt_execute($stmt);

echo json_encode([
    'status' => 'ok',
    'url' => '../gambar/profil/' . $namaBaru
]);