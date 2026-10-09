<?php
ob_start();
session_start();
include "koneksi.php";

ob_clean();
header('Content-Type: application/json');

if (!isset($_SESSION['id_user'])) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Session expired, silakan login ulang'
    ]);
    exit;
}

$id_user  = (int)$_SESSION['id_user'];
$id_menu  = isset($_POST['id_menu'])  ? intval($_POST['id_menu'])  : 0;
$id_varian = isset($_POST['id_varian']) ? intval($_POST['id_varian']) : null;

if ($id_menu <= 0) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Menu tidak valid'
    ]);
    exit;
}

// Ambil harga_tambah dari varian (jika ada)
$harga_tambah = 0;
if ($id_varian) {
    $q_varian = mysqli_prepare($konek, "SELECT harga_tambah FROM varian_menu WHERE id=? AND id_menu=?");
    mysqli_stmt_bind_param($q_varian, "ii", $id_varian, $id_menu);
    mysqli_stmt_execute($q_varian);
    $row_varian = mysqli_fetch_assoc(mysqli_stmt_get_result($q_varian));
    if ($row_varian) {
        $harga_tambah = (int)$row_varian['harga_tambah'];
    } else {
        $id_varian = null; // varian tidak valid, abaikan
    }
}

// Ambil stok
$cek_stok = mysqli_prepare($konek, "SELECT stok FROM menu_req WHERE id=?");
mysqli_stmt_bind_param($cek_stok, "i", $id_menu);
mysqli_stmt_execute($cek_stok);

$stok_row = mysqli_fetch_assoc(mysqli_stmt_get_result($cek_stok));

if (!$stok_row) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Menu tidak ditemukan'
    ]);
    exit;
}

$stok = (int)$stok_row['stok'];

// Cek keranjang (per varian — varian berbeda = row berbeda)
if ($id_varian) {
    $cek = mysqli_prepare($konek,
        "SELECT id, jumlah FROM keranjang WHERE id_user=? AND id_menu=? AND id_varian=?"
    );
    mysqli_stmt_bind_param($cek, "iii", $id_user, $id_menu, $id_varian);
} else {
    $cek = mysqli_prepare($konek,
        "SELECT id, jumlah FROM keranjang WHERE id_user=? AND id_menu=? AND (id_varian IS NULL OR id_varian=0)"
    );
    mysqli_stmt_bind_param($cek, "ii", $id_user, $id_menu);
}
mysqli_stmt_execute($cek);

$existing = mysqli_fetch_assoc(mysqli_stmt_get_result($cek));

if ($existing) {

    // Jika jumlah sudah sama dengan stok
    if ($existing['jumlah'] >= $stok) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Stok tidak cukup! Maksimal ' . $stok . ' porsi'
        ]);
        exit;
    }

    $jumlah_baru = $existing['jumlah'] + 1;

    $update = mysqli_prepare($konek,
        "UPDATE keranjang SET jumlah=? WHERE id=?"
    );

    mysqli_stmt_bind_param($update, "ii", $jumlah_baru, $existing['id']);
    mysqli_stmt_execute($update);

    // ID keranjang lama
    $id_keranjang_baru = $existing['id'];

} else {

    // Jika stok habis
    if ($stok < 1) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Stok habis!'
        ]);
        exit;
    }

    $insert = mysqli_prepare($konek,
        "INSERT INTO keranjang (id_user, id_menu, id_varian, jumlah)
         VALUES (?,?,?,1)"
    );

    mysqli_stmt_bind_param($insert, "iii", $id_user, $id_menu, $id_varian);
    mysqli_stmt_execute($insert);

    // Ambil ID hasil insert
    $id_keranjang_baru = mysqli_insert_id($konek);
}

// Hitung total keranjang
$total = mysqli_prepare($konek,
    "SELECT SUM(jumlah) as total
     FROM keranjang
     WHERE id_user=?"
);

mysqli_stmt_bind_param($total, "i", $id_user);
mysqli_stmt_execute($total);

$total_row = mysqli_fetch_assoc(mysqli_stmt_get_result($total));

// Response akhir
echo json_encode([
    'status' => 'success',
    'message' => 'Berhasil ditambahkan ke keranjang!',
    'total_keranjang' => (int)$total_row['total'],
    'id_keranjang' => $id_keranjang_baru,
    'stok' => $stok,
    'harga_tambah' => $harga_tambah,
]);