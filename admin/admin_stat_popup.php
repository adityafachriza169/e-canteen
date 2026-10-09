<?php
session_start();
include "database.php";

if (!isset($_SESSION['username'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

header('Content-Type: application/json');
$type = $_GET['type'] ?? '';

if ($type === 'penjual') {
    $sql = "SELECT 
                p.id_penjual,
                p.nama,
                p.username,
                p.email,
                p.no_tlp,
                p.buka,
                k.nama_kantin,
                (SELECT COUNT(*) FROM menu_req m WHERE m.id_penjual = p.id_penjual AND m.aktif = 1 AND m.deleted_at IS NULL) as total_menu_aktif
            FROM penjual p
            LEFT JOIN kantin k ON p.id_kantin = k.id_kantin
            WHERE p.status = 1 AND p.deleted_at IS NULL
            ORDER BY k.nama_kantin, p.nama";
    $result = mysqli_query($db, $sql);
    $data = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $data[] = $row;
    }
    echo json_encode(['success' => true, 'data' => $data]);

} elseif ($type === 'pembeli') {
    $sql = "SELECT 
                u.id_user,
                u.nama,
                u.username,
                u.email,
                u.kelas,
                u.no_hp,
                u.gender,
                u.tanggal_lahir,
                (SELECT COUNT(*) FROM transaksi t WHERE t.id_user = u.id_user) as total_transaksi
            FROM users u
            WHERE u.status = 1 AND u.deleted_at IS NULL
            ORDER BY u.kelas, u.nama";
    $result = mysqli_query($db, $sql);
    $data = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $data[] = $row;
    }
    echo json_encode(['success' => true, 'data' => $data]);

} elseif ($type === 'menu') {
    $sql = "SELECT 
                m.id,
                m.nama_makanan,
                m.kategori,
                m.harga,
                m.stok,
                m.terjual,
                m.aktif,
                m.deleted_at,
                m.created_at,
                k.nama_kantin,
                p.nama as nama_penjual
            FROM menu_req m
            LEFT JOIN kantin k ON m.id_kantin = k.id_kantin
            LEFT JOIN penjual p ON m.id_penjual = p.id_penjual
            WHERE m.aktif = 1 AND m.deleted_at IS NULL
            ORDER BY k.nama_kantin, m.nama_makanan";
    $result = mysqli_query($db, $sql);
    $data = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $data[] = $row;
    }
    echo json_encode(['success' => true, 'data' => $data]);

} else {
    echo json_encode(['success' => false, 'message' => 'Invalid type']);
}
?>