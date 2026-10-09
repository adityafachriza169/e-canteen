<?php
/**
 * admin_export_data.php
 * Endpoint AJAX — mengembalikan JSON untuk SheetJS di browser.
 *
 * Struktur DB yang digunakan:
 * - transaksi: id_transaksi, id_user, tanggal, total, metode,
 *              nama_makanan, nama_kantin, id_kantin, jumlah,
 *              id_menu, status, antrian, catatan
 * - TIDAK ADA tabel detail_transaksi
 *
 * GET params:
 *   type = penjual_detail  → data 1 penjual + semua menunya
 *   type = menu_detail     → data 1 menu + riwayat transaksi (maks 100)
 *   type = pembeli_detail  → data 1 pembeli + riwayat transaksi
 *   id   = id record
 */
session_start();
include "database.php";
if (!isset($_SESSION['username'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}
header('Content-Type: application/json; charset=utf-8');
$type = $_GET['type'] ?? '';
$id   = (int)($_GET['id'] ?? 0);
if (!$id) {
    echo json_encode(['success' => false, 'message' => 'ID tidak valid']);
    exit();
}
// ─── DETAIL PENJUAL + SEMUA MENU ────────────────────────────────────────────
if ($type === 'penjual_detail') {
    $stmt = mysqli_prepare($db,
        "SELECT p.id_penjual, p.nama, p.username, p.email, p.no_tlp, p.buka,
                k.nama_kantin
         FROM penjual p
         LEFT JOIN kantin k ON p.id_kantin = k.id_kantin
         WHERE p.id_penjual = ? AND p.status = 1 AND p.deleted_at IS NULL");
    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);
    $penjual = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    if (!$penjual) {
        echo json_encode(['success' => false, 'message' => 'Penjual tidak ditemukan']);
        exit();
    }
    $stmt2 = mysqli_prepare($db,
        "SELECT m.id, m.nama_makanan, m.kategori, m.harga, m.stok, m.terjual,
                m.aktif, m.deleted_at, m.created_at, m.deskripsi, m.waktu_proses,
                k.nama_kantin
         FROM menu_req m
         LEFT JOIN kantin k ON m.id_kantin = k.id_kantin
         WHERE m.id_penjual = ?
         ORDER BY (m.deleted_at IS NULL) DESC, m.aktif DESC, m.nama_makanan ASC");
    mysqli_stmt_bind_param($stmt2, "i", $id);
    mysqli_stmt_execute($stmt2);
    $menus = mysqli_fetch_all(mysqli_stmt_get_result($stmt2), MYSQLI_ASSOC);
    echo json_encode([
        'success' => true,
        'penjual' => $penjual,
        'menus'   => $menus,
    ], JSON_UNESCAPED_UNICODE);
// ─── DETAIL MENU + RIWAYAT TRANSAKSI ────────────────────────────────────────
} elseif ($type === 'menu_detail') {
    // Data menu
    $stmt = mysqli_prepare($db,
        "SELECT m.id, m.nama_makanan, m.kategori, m.harga, m.stok, m.terjual,
                m.aktif, m.deleted_at, m.created_at, m.deskripsi, m.waktu_proses,
                k.nama_kantin,
                p.nama     AS nama_penjual,
                p.username AS username_penjual
         FROM menu_req m
         LEFT JOIN kantin  k ON k.id_kantin  = m.id_kantin
         LEFT JOIN penjual p ON p.id_penjual = m.id_penjual
         WHERE m.id = ?");
    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);
    $menu = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    if (!$menu) {
        echo json_encode(['success' => false, 'message' => 'Menu tidak ditemukan']);
        exit();
    }
    // Riwayat transaksi — langsung dari tabel transaksi (tidak ada detail_transaksi)
    // Kolom: jumlah, total (bukan total_harga), status (bukan status_pesanan)
    $stmt2 = mysqli_prepare($db,
        "SELECT t.jumlah,
                t.total,
                t.tanggal,
                t.status,
                t.catatan,
                u.nama  AS nama_pembeli,
                u.kelas
         FROM transaksi t
         LEFT JOIN users u ON u.id_user = t.id_user
         WHERE t.id_menu = ?
         ORDER BY t.tanggal DESC
         LIMIT 100");
    mysqli_stmt_bind_param($stmt2, "i", $id);
    mysqli_stmt_execute($stmt2);
    $transaksis = mysqli_fetch_all(mysqli_stmt_get_result($stmt2), MYSQLI_ASSOC);
    echo json_encode([
        'success'    => true,
        'menu'       => $menu,
        'transaksis' => $transaksis,
    ], JSON_UNESCAPED_UNICODE);
// ─── DETAIL PEMBELI + RIWAYAT TRANSAKSI ─────────────────────────────────────
} elseif ($type === 'pembeli_detail') {
    // Data profil pembeli
    $stmt = mysqli_prepare($db,
        "SELECT u.id_user, u.nama, u.username, u.email, u.kelas,
                u.no_hp, u.gender,
                COUNT(t.id_transaksi) AS total_transaksi
         FROM users u
         LEFT JOIN transaksi t ON t.id_user = u.id_user
         WHERE u.id_user = ? AND u.status = 1
         GROUP BY u.id_user");
    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);
    $pembeli = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    if (!$pembeli) {
        echo json_encode(['success' => false, 'message' => 'Pembeli tidak ditemukan']);
        exit();
    }
    // Riwayat transaksi — langsung dari tabel transaksi
    // Setiap baris = 1 item pesanan (tidak ada detail_transaksi)
    $stmt2 = mysqli_prepare($db,
        "SELECT t.id_transaksi,
                t.tanggal,
                t.nama_makanan,
                t.nama_kantin,
                t.jumlah,
                t.total,
                t.metode,
                t.status,
                t.catatan,
                t.antrian
         FROM transaksi t
         WHERE t.id_user = ?
         ORDER BY t.tanggal DESC, t.id_transaksi DESC
         LIMIT 200");
    mysqli_stmt_bind_param($stmt2, "i", $id);
    mysqli_stmt_execute($stmt2);
    $transaksis = mysqli_fetch_all(mysqli_stmt_get_result($stmt2), MYSQLI_ASSOC);
    echo json_encode([
        'success'    => true,
        'pembeli'    => $pembeli,
        'transaksis' => $transaksis,
    ], JSON_UNESCAPED_UNICODE);
} else {
    echo json_encode(['success' => false, 'message' => 'Tipe tidak dikenali']);
}
?>
