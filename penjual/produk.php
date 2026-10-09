<?php
// =============================================
// SESSION & AUTH CHECK
// =============================================
session_start();

if (!isset($_SESSION['username']) || (int)($_SESSION['role'] ?? 0) !== 1) {
    header('Location: ../users/login.php');
    exit;
}

// =============================================
// KONEKSI
// =============================================
include "../users/koneksi.php";
if (!isset($db) && isset($konek)) $db = $konek;

// =============================================
// AMBIL DATA PENJUAL DARI SESSION USERNAME
// =============================================
$ses_username = $_SESSION['username'];

$stmt_p = mysqli_prepare($db, "
    SELECT p.id_penjual, p.id_kantin, p.nama, p.username, p.profil,
           k.nama_kantin
    FROM penjual p
    LEFT JOIN kantin k ON k.id_kantin = p.id_kantin
    WHERE p.username = ?
    LIMIT 1
");
mysqli_stmt_bind_param($stmt_p, "s", $ses_username);
mysqli_stmt_execute($stmt_p);
$penjual_data = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_p));
mysqli_stmt_close($stmt_p);

if (!$penjual_data) {
    session_destroy();
    header('Location: ../users/login.php');
    exit;
}

$id_kantin    = (int) $penjual_data['id_kantin'];
$nama_penjual = $penjual_data['nama']        ?? 'Penjual';
$nama_kantin  = $penjual_data['nama_kantin'] ?? 'Kantin';

// Foto profil
$foto_profil_file = $penjual_data['profil'] ?? '';
$kemungkinan_path = [
    'profil/' . $foto_profil_file,
    '../profil/' . $foto_profil_file,
    'uploads/' . $foto_profil_file,
    '../admin/uploads/' . $foto_profil_file,
];
$foto_profil = 'https://ui-avatars.com/api/?name=' . urlencode($nama_penjual) . '&background=e8650a&color=fff&size=80';
foreach ($kemungkinan_path as $_p) {
    if (!empty($foto_profil_file) && file_exists($_p)) { $foto_profil = $_p; break; }
}

// =============================================
// HAPUS PRODUK (soft delete — aktif = 0)
// =============================================
if (isset($_GET['hapus'])) {
    $id_hapus = (int) $_GET['hapus'];

    // Pastikan menu milik kantin ini sebelum dinonaktifkan
    $q = mysqli_query($db, "SELECT id FROM menu_req WHERE id = $id_hapus AND id_kantin = $id_kantin AND aktif = 1");
    if (mysqli_fetch_assoc($q)) {
        // Soft delete: set aktif = 0, data & foto tetap di DB
        mysqli_query($db, "UPDATE menu_req SET aktif = 0 WHERE id = $id_hapus AND id_kantin = $id_kantin");
    }
    header("Location: produk.php");
    exit;
}

// =============================================
// FILTER & QUERY
// =============================================
$filter_kategori = isset($_GET['kategori']) ? mysqli_real_escape_string($db, $_GET['kategori']) : '';
$search          = isset($_GET['search'])   ? mysqli_real_escape_string($db, $_GET['search'])   : '';

$where = "WHERE m.id_kantin = $id_kantin AND m.aktif = 1";
if ($filter_kategori) $where .= " AND m.kategori = '$filter_kategori'";
if ($search)          $where .= " AND m.nama_makanan LIKE '%$search%'";

$sql = "SELECT m.*, k.nama_kantin,
               COALESCE(r.rata_bintang, 0) AS rata_bintang,
               COALESCE(r.total_ulasan, 0) AS total_ulasan
        FROM menu_req m
        LEFT JOIN kantin k ON m.id_kantin = k.id_kantin
        LEFT JOIN (
            SELECT id_menu,
                   ROUND(AVG(bintang), 1) AS rata_bintang,
                   COUNT(*) AS total_ulasan
            FROM ulasan
            GROUP BY id_menu
        ) r ON m.id = r.id_menu
        $where
        ORDER BY m.created_at DESC";

$result = mysqli_query($db, $sql);
$menus  = mysqli_fetch_all($result, MYSQLI_ASSOC);
$total  = count($menus);

// =============================================
// AMBIL SEMUA VARIAN SEKALIGUS
// =============================================
$varian_map = [];
if (!empty($menus)) {
    $id_menu_list = array_column($menus, 'id');
    $id_in = implode(',', $id_menu_list);
    $qv = mysqli_query($db, "SELECT * FROM varian_menu WHERE id_menu IN ($id_in) ORDER BY id ASC");
    while ($rv = mysqli_fetch_assoc($qv)) {
        $varian_map[$rv['id_menu']][] = $rv;
    }
}

// Helper format rupiah
function rupiah($angka) {
    return 'Rp' . number_format((int)$angka, 0, ',', '.');
}

// Icon kategori
function iconKategori($kat) {
    $kat = strtolower($kat);
    if ($kat === 'minuman') return '<i class="fa-solid fa-mug-hot"></i>';
    if ($kat === 'snack')   return '<i class="fa-solid fa-cookie-bite"></i>';
    return '<i class="fa-solid fa-utensils"></i>';
}

// ── Jumlah pesanan pending ──
$pending_orders = 0;
{
    $__q = mysqli_prepare($db, "SELECT COUNT(*) AS jml FROM transaksi WHERE id_kantin = ? AND status = 'pending'");
    mysqli_stmt_bind_param($__q, 'i', $id_kantin);
    mysqli_stmt_execute($__q);
    $pending_orders = (int) mysqli_fetch_assoc(mysqli_stmt_get_result($__q))['jml'];
    mysqli_stmt_close($__q);
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>E-Canteen – Produk Saya</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Righteous&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="produk.css">
  <style>
    /* ── FILTER BAR ── */
    .filter-bar {
      display: flex; gap: 8px; flex-wrap: wrap;
      padding: 12px 20px 0;
    }
    .filter-chip {
      padding: 6px 14px; border-radius: 20px;
      background: #f1f1f1; color: #555;
      text-decoration: none; font-size: 13px;
      display: flex; align-items: center; gap: 5px;
      transition: all .2s;
    }
    .filter-chip.active, .filter-chip:hover {
      background: #ff6b00; color: #fff;
    }

    /* ── ALERT ── */
    .alert {
      margin: 12px 20px; padding: 12px 16px;
      border-radius: 10px; font-size: 14px;
      display: flex; align-items: center; gap: 8px;
    }
    .alert-sukses { background: #d1fae5; color: #065f46; }
    .alert-error  { background: #fee2e2; color: #991b1b; }

    /* ── BADGE ── */
    .badge-habis {
      position: absolute; top: 8px; left: 8px;
      background: #ef4444; color: #fff;
      font-size: 10px; padding: 2px 8px;
      border-radius: 20px; font-weight: 700;
    }
    .badge-varian {
      position: absolute; top: 8px; right: 8px;
      background: rgba(200, 75, 17, .85);
      backdrop-filter: blur(4px);
      color: #fff;
      font-size: 10px; padding: 3px 9px;
      border-radius: 20px; font-weight: 700;
      display: flex; align-items: center; gap: 4px;
    }

    /* ── BADGE STOK HAMPIR HABIS ── */
    .badge-warning {
      position: absolute; top: 8px; left: 8px;
      background: linear-gradient(135deg, #f59e0b, #ef4444);
      color: #fff;
      font-size: 10px; padding: 2px 8px;
      border-radius: 20px; font-weight: 700;
      display: flex; align-items: center; gap: 4px;
      animation: pulse-warning 2s infinite;
      z-index: 2;
    }
    @keyframes pulse-warning {
      0%, 100% { box-shadow: 0 0 0 0 rgba(245,158,11,.5); }
      50%       { box-shadow: 0 0 0 6px rgba(245,158,11,0); }
    }

    /* Outline card ketika stok hampir habis */
    .produk-card.stok-warning {
      outline: 2px solid #f59e0b;
      outline-offset: -2px;
    }

    /* ── NOTIFIKASI STOK PANEL ── */
    .stok-notif-btn {
      position: relative;
      cursor: pointer;
      background: none;
      border: none;
      padding: 0;
      display: flex;
      align-items: center;
      justify-content: center;
    }
    .stok-notif-badge {
      position: absolute; top: -4px; right: -4px;
      background: #f59e0b; color: #fff;
      font-size: 10px; font-weight: 800;
      min-width: 16px; height: 16px;
      border-radius: 10px; padding: 0 4px;
      display: none;
      align-items: center; justify-content: center;
      border: 2px solid #fff;
      box-shadow: 0 2px 6px rgba(245,158,11,.5);
      line-height: 1;
    }
    .stok-notif-badge.show { display: flex; }

    .stok-notif-panel {
      position: absolute;
      top: calc(100% + 10px);
      right: 0;
      width: 320px;
      background: #fff;
      border-radius: 16px;
      box-shadow: 0 12px 40px rgba(0,0,0,.18);
      border: 1px solid #f1e8e0;
      z-index: 9999;
      display: none;
      flex-direction: column;
      overflow: hidden;
      animation: dropIn .2s ease;
    }
    .stok-notif-panel.open { display: flex; }
    @keyframes dropIn {
      from { opacity: 0; transform: translateY(-8px); }
      to   { opacity: 1; transform: translateY(0); }
    }
    .stok-notif-head {
      padding: 14px 16px 10px;
      border-bottom: 1px solid #f5ede9;
      display: flex; align-items: center; justify-content: space-between;
    }
    .stok-notif-head-title {
      font-size: 13px; font-weight: 800; color: #1a0a00;
      display: flex; align-items: center; gap: 6px;
    }
    .stok-notif-head-title i { color: #f59e0b; font-size: 14px; }
    .stok-notif-head-close {
      background: none; border: none; cursor: pointer;
      color: #aaa; font-size: 14px; padding: 2px;
    }
    .stok-notif-head-close:hover { color: #ef4444; }
    .stok-notif-list {
      max-height: 280px; overflow-y: auto;
      padding: 8px 0;
    }
    .stok-notif-item {
      display: flex; align-items: center; gap: 10px;
      padding: 8px 16px;
      transition: background .15s;
      cursor: pointer;
    }
    .stok-notif-item:hover { background: #fff9f3; }
    .stok-notif-item-icon {
      width: 36px; height: 36px; border-radius: 10px;
      background: linear-gradient(135deg, #fef3c7, #fde68a);
      display: flex; align-items: center; justify-content: center;
      flex-shrink: 0;
      font-size: 16px;
    }
    .stok-notif-item-info { flex: 1; min-width: 0; }
    .stok-notif-item-nama {
      font-size: 13px; font-weight: 700; color: #1a0a00;
      white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
    }
    .stok-notif-item-stok {
      font-size: 11px; color: #ef4444; font-weight: 700;
      margin-top: 1px;
    }
    .stok-notif-item-stok.kritis { color: #dc2626; }
    .stok-notif-empty {
      text-align: center; padding: 24px 16px;
      color: #bbb; font-size: 13px;
    }
    .stok-notif-empty i { display: block; font-size: 28px; margin-bottom: 6px; color: #d1fae5; }
    .stok-notif-footer {
      padding: 10px 16px;
      border-top: 1px solid #f5ede9;
      text-align: center;
    }
    .stok-notif-footer a {
      font-size: 12px; color: #e8650a; font-weight: 700;
      text-decoration: none;
    }
    .stok-notif-footer a:hover { text-decoration: underline; }
    /* posisi relatif container ikon notif */
    .stok-notif-wrap {
      position: relative;
      display: flex;
      align-items: center;
    }

    /* ── EMPTY ── */
    .produk-kosong {
      grid-column: 1/-1;
      text-align: center; padding: 60px 20px; color: #aaa;
    }
    .produk-kosong i { font-size: 48px; margin-bottom: 12px; display: block; }

    /* ── OVERLAY ACTIONS ── */
    .btn-edit, .btn-hapus {
      display: inline-flex; align-items: center; gap: 5px;
      padding: 6px 12px; border-radius: 8px;
      font-size: 13px; cursor: pointer;
      text-decoration: none; border: none;
    }
    .btn-edit  { background: #fff; color: #333; }
    .btn-hapus { background: #ef4444; color: #fff; }

    /* ─────────────────────────────────────────
       VARIAN BOX DI CARD
    ───────────────────────────────────────── */
    .produk-varian-box {
      margin-top: 10px;
      padding-top: 10px;
      border-top: 1px solid rgba(232, 113, 74, .12);
    }
    .pv-label {
      font-size: 10px; font-weight: 700;
      color: #b08060; text-transform: uppercase;
      letter-spacing: .5px; margin-bottom: 6px;
      display: flex; align-items: center; gap: 5px;
    }
    .pv-label i { color: #E8714A; font-size: 10px; }
    .pv-chips {
      display: flex; flex-wrap: wrap; gap: 4px;
      margin-bottom: 8px;
    }
    .pv-chip {
      display: inline-flex; align-items: center; gap: 3px;
      padding: 3px 9px; border-radius: 20px;
      background: #fef0e0;
      border: 1px solid rgba(232, 113, 74, .22);
      font-size: 10px; font-weight: 600; color: #C84B11;
    }
    .pv-chip-plus {
      font-size: 9px; color: #E8714A; opacity: .85;
    }
    .pv-chip-more {
      background: rgba(200, 75, 17, .1);
      border-color: rgba(200, 75, 17, .2);
      color: #C84B11; font-style: italic;
    }

    /* ── TOMBOL LIHAT SEMUA VARIAN ── */
    .btn-lihat-varian {
      width: 100%;
      padding: 9px 0;
      border-radius: 12px;
      background: linear-gradient(135deg, #C84B11 0%, #E8714A 100%);
      color: #fff;
      font-size: 12px;
      font-weight: 700;
      cursor: pointer;
      font-family: inherit;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 7px;
      border: none;
      box-shadow: 0 4px 14px rgba(200, 75, 17, .28);
      transition: opacity .15s, transform .15s, box-shadow .15s;
    }
    .btn-lihat-varian:hover {
      opacity: .92;
      transform: translateY(-1px);
      box-shadow: 0 6px 18px rgba(200, 75, 17, .38);
    }
    .btn-lihat-varian:active {
      transform: scale(.97);
    }
    .btn-lihat-varian i { font-size: 11px; }

    /* ─────────────────────────────────────────
       MODAL VARIAN — FULL REDESIGN
    ───────────────────────────────────────── */
    .modal-varian-bg {
      display: none;
      position: fixed;
      inset: 0;
      background: rgba(0,0,0,.55);
      z-index: 9999;
      align-items: flex-end;
      justify-content: center;
      backdrop-filter: blur(6px);
    }
    .modal-varian-bg.open { display: flex; }

    .modal-varian {
      background: #fff;
      border-radius: 28px 28px 0 0;
      width: 100%;
      max-width: 560px;
      max-height: 90vh;
      overflow: hidden;
      display: flex;
      flex-direction: column;
      animation: mvSlideUp .28s cubic-bezier(.34,1.2,.64,1);
    }

    @keyframes mvSlideUp {
      from { transform: translateY(60px); opacity: 0; }
      to   { transform: translateY(0);    opacity: 1; }
    }

    /* ── Modal Handle ── */
    .mv-handle {
      width: 40px; height: 4px;
      background: #e5e7eb; border-radius: 99px;
      margin: 14px auto 0;
      flex-shrink: 0;
    }

    /* ── Modal Header ── */
    .mv-header {
      padding: 16px 20px 14px;
      border-bottom: 1px solid #f5ede9;
      display: flex;
      align-items: flex-start;
      justify-content: space-between;
      gap: 12px;
      flex-shrink: 0;
    }
    .mv-header-left { flex: 1; min-width: 0; }
    .mv-badge-kat {
      display: inline-flex; align-items: center; gap: 5px;
      background: #fff1ee; color: #ff5a36;
      font-size: 9px; font-weight: 700;
      padding: 2px 8px; border-radius: 6px;
      margin-bottom: 5px; text-transform: uppercase;
      letter-spacing: .5px;
    }
    .mv-title {
      font-size: 18px; font-weight: 800;
      color: #1a0a00; margin-bottom: 2px;
      white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
    }
    .mv-sub {
      font-size: 13px; color: #ff5722; font-weight: 700;
    }
    .mv-close-x {
      width: 34px; height: 34px; border-radius: 50%;
      background: #f5f5f5; border: none; cursor: pointer;
      display: flex; align-items: center; justify-content: center;
      color: #666; font-size: 14px; flex-shrink: 0;
      transition: background .15s, color .15s;
    }
    .mv-close-x:hover { background: #ffe0d0; color: #C84B11; }

    /* ── Modal Stats ── */
    .mv-stats {
      display: flex;
      gap: 0;
      padding: 12px 20px;
      border-bottom: 1px solid #f5ede9;
      background: #fff9f6;
      flex-shrink: 0;
    }
    .mv-stat {
      flex: 1;
      text-align: center;
    }
    .mv-stat + .mv-stat {
      border-left: 1px solid #f0e4df;
    }
    .mv-stat-val {
      font-size: 16px; font-weight: 800; color: #C84B11;
    }
    .mv-stat-lbl {
      font-size: 10px; font-weight: 600; color: #b08060;
      text-transform: uppercase; letter-spacing: .4px;
    }

    /* ── Modal List ── */
    .mv-list {
      overflow-y: auto;
      padding: 16px 20px;
      display: flex;
      flex-direction: column;
      gap: 10px;
      flex: 1;
    }

    /* ── Varian Item ── */
    .mv-item {
      display: flex;
      align-items: center;
      gap: 14px;
      padding: 12px 14px;
      border-radius: 16px;
      background: #fef9f6;
      border: 1px solid rgba(232, 113, 74, .14);
      transition: background .15s, transform .15s;
      cursor: default;
    }
    .mv-item:hover {
      background: #fef0e0;
      transform: translateX(3px);
    }

    .mv-item-foto-wrap {
      position: relative;
      flex-shrink: 0;
    }
    .mv-item-foto {
      width: 58px; height: 58px;
      border-radius: 12px;
      object-fit: cover;
      border: 2px solid rgba(232, 113, 74, .15);
      background: #f5e6d8;
      display: block;
    }
    .mv-item-foto-placeholder {
      width: 58px; height: 58px;
      border-radius: 12px;
      background: linear-gradient(135deg, #fef0e0, #fde8d4);
      border: 2px dashed rgba(232, 113, 74, .25);
      display: flex;
      align-items: center;
      justify-content: center;
    }
    .mv-item-foto-placeholder i {
      font-size: 22px;
      color: rgba(232, 113, 74, .35);
    }

    /* Nomor urut */
    .mv-item-num {
      position: absolute;
      top: -5px; left: -5px;
      width: 18px; height: 18px;
      background: #C84B11;
      color: #fff;
      border-radius: 50%;
      font-size: 9px;
      font-weight: 800;
      display: flex;
      align-items: center;
      justify-content: center;
      font-family: 'Plus Jakarta Sans', sans-serif;
    }

    .mv-item-info {
      flex: 1;
      min-width: 0;
    }
    .mv-item-nama {
      font-size: 14px; font-weight: 700;
      color: #1a0a00; margin-bottom: 4px;
      white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
    }
    .mv-item-price-row {
      display: flex;
      align-items: center;
      gap: 8px;
    }
    .mv-item-tambah {
      font-size: 12px; font-weight: 800;
      color: #C84B11;
      background: rgba(200, 75, 17, .1);
      padding: 2px 8px;
      border-radius: 6px;
    }
    .mv-item-total {
      font-size: 11px; font-weight: 600;
      color: #b08060;
    }
    .mv-item-harga-dasar {
      font-size: 12px; font-weight: 600;
      color: #b08060;
      background: #f5f0eb;
      padding: 2px 8px;
      border-radius: 6px;
    }

    /* ── Modal Footer ── */
    .mv-footer {
      padding: 12px 20px 20px;
      flex-shrink: 0;
      border-top: 1px solid #f5ede9;
      background: #fff;
    }
    .mv-close-btn {
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
      width: 100%;
      padding: 13px;
      border-radius: 14px;
      background: linear-gradient(135deg, #C84B11, #E8714A);
      color: #fff;
      font-size: 14px;
      font-weight: 700;
      border: none;
      cursor: pointer;
      font-family: inherit;
      transition: opacity .15s;
      box-shadow: 0 6px 20px rgba(200, 75, 17, .28);
    }
    .mv-close-btn:hover { opacity: .9; }

    /* ── Kosong state di modal ── */
    .mv-empty {
      text-align: center;
      padding: 40px 20px;
      color: #ccc;
    }
    .mv-empty i { font-size: 36px; display: block; margin-bottom: 10px; color: #f0c0a0; }
    .mv-empty p { font-size: 13px; font-weight: 600; color: #bbb; }

    /* ── Scrollbar modal ── */
    .mv-list::-webkit-scrollbar { width: 4px; }
    .mv-list::-webkit-scrollbar-track { background: transparent; }
    .mv-list::-webkit-scrollbar-thumb { background: rgba(232, 113, 74, .25); border-radius: 4px; }
  </style>
</head>
<body>

  <!-- ═══════════════ TOPBAR ═══════════════ -->
  <div class="topbar">
    <div class="top-left"></div>
    <div class="top-right">
      <a href="pesanan.php"><i class="fa-regular fa-bell"></i> Notifikasi</a>
      <a href="suport.php"><i class="fa-regular fa-circle-question"></i> Bantuan</a>
      <div class="lang">
        <i class="fa-solid fa-globe"></i>
        <span>Bahasa Indonesia</span>
        <i class="fa-solid fa-chevron-down"></i>
        <div class="lang-drop">
          <a href="#" class="active"><i class="fa-solid fa-check"></i> Bahasa Indonesia</a>
          <a href="#">English</a>
        </div>
      </div>
    </div>
  </div>
  <!-- ── NAVBAR ── -->
  <div class="navbar orange-bg">
    <div class="navbar-texture"></div>
    <div class="orb1"></div>
    <div class="orb2"></div>

    <button class="hamburger-btn" id="hamburgerBtn" aria-label="Buka menu">
      <span></span><span></span><span></span>
    </button>

    <div class="logo">
      <div class="logo-img-wrap">
        <img src="../gambar/logo.png" alt="Logo E-Canteen">
      </div>
      <div class="logo-text">
        <span class="logo-title">E-Canteen</span>
        <span class="logo-sub">Esemkita</span>
      </div>
    </div>

    <div class="search-wrap">
      <form method="GET" action="produk.php">
        <div class="search-box">
          <input type="text" name="search" id="navbarSearch"
                 value="<?= htmlspecialchars($search) ?>"
                 placeholder="Cari makanan dan minuman...">
          <button type="submit" aria-label="Cari"><i class="fa-solid fa-magnifying-glass"></i></button>
        </div>
      </form>
    </div>

    <div class="icon-actions">
      <div class="icon-btn" id="searchTrigger" aria-label="Cari" role="button" tabindex="0">
        <i class="fa-solid fa-magnifying-glass"></i>
      </div>

      <!-- Notifikasi Stok Hampir Habis -->
      <div class="stok-notif-wrap">
        <button class="icon-btn stok-notif-btn" id="stokNotifBtn" aria-label="Stok Hampir Habis" title="Stok Hampir Habis">
          <i class="fa-solid fa-triangle-exclamation" style="color:#f59e0b;font-size:15px;"></i>
          <span class="stok-notif-badge" id="stokNotifBadge"></span>
        </button>
        <div class="stok-notif-panel" id="stokNotifPanel">
          <div class="stok-notif-head">
            <div class="stok-notif-head-title">
              <i class="fa-solid fa-triangle-exclamation"></i> Stok Hampir Habis
            </div>
            <button class="stok-notif-head-close" onclick="closeStokPanel()"><i class="fa-solid fa-xmark"></i></button>
          </div>
          <div class="stok-notif-list" id="stokNotifList">
            <div class="stok-notif-empty"><i class="fa-solid fa-check-circle" style="color:#6ee7b7;"></i>Semua stok aman</div>
          </div>
          <div class="stok-notif-footer">
            <a href="produk.php">Lihat Semua Produk →</a>
          </div>
        </div>
      </div>

      <button onclick="openChatPopup()" class="icon-btn chat-trigger-btn notif-chat-btn">
    <i class="fa-solid fa-comment-dots"></i>
    <span class="icon-badge" id="chatBadge" style="display:none"></span>
</button>
    </div>
  </div>

  <!-- ── SIDEBAR OVERLAY ── -->
  <div class="sidebar-overlay" id="sidebarOverlay"></div>

  <!-- ── SEARCH OVERLAY ── -->
  <div class="search-overlay" id="searchOverlay" onclick="if(event.target===this)closeSearch()">
    <div class="search-sheet">
      <div class="search-sheet-handle"></div>
      <h3>Cari Makanan &amp; Minuman</h3>
      <div class="search-sheet-box">
        <input id="sheetInput" type="text" placeholder="Ketik nama menu...">
        <button id="sheetSearchBtn"><i class="fa-solid fa-magnifying-glass"></i></button>
      </div>
      <div class="search-suggestions">
        <p>Populer</p>
        <div class="suggestion-chips">
          <div class="chip" onclick="applyChip('Ayam')">Ayam Geprek</div>
          <div class="chip" onclick="applyChip('Mie')">Mie Ayam</div>
          <div class="chip" onclick="applyChip('Nasi')">Nasi Goreng</div>
          <div class="chip" onclick="applyChip('Burger')">Burger</div>
          <div class="chip" onclick="applyChip('Salad')">Salad</div>
          <div class="chip" onclick="applyChip('Pizza')">Pizza</div>
        </div>
      </div>
    </div>
  </div>

  <!-- ── PAGE WRAPPER ── -->
  <div class="page-wrapper">

    <!-- ── SIDEBAR ── -->
    <aside class="sidebar" id="sidebar">
      <div class="sidebar-profile">
        <div class="sidebar-avatar">
          <img src="<?= htmlspecialchars($foto_profil) ?>" alt="Avatar"
               onerror="this.src='https://ui-avatars.com/api/?name=<?= urlencode($nama_penjual) ?>&background=e8650a&color=fff&size=80'" />
        </div>
        <div class="sidebar-profile-info">
          <h3><?= htmlspecialchars($nama_penjual) ?></h3>
          <small style="color:#aaa;font-size:.75rem;"><?= htmlspecialchars($nama_kantin) ?></small><br>
          <a href="profil/profile.php">✏️ Ubah Profil</a>
        </div>
      </div>

      <div class="sidebar-nav-area">
        <a href="dasbord.php">
          <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24"><path fill="currentColor" d="M6 19h3v-6h6v6h3v-9l-6-4.5L6 10zm-2 2V9l8-6l8 6v12h-7v-6h-2v6zm8-8.75"/></svg>
          Dashboard
        </a>
        <a href="produk.php" class="active">
          <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24"><path fill="currentColor" d="M19.78 12.08v4.5H4.26v-4.51H4a6.4 6.4 0 0 1-3-.73v10.24a2 2 0 0 0 2 2h18a2 2 0 0 0 2-2V11.35a6.3 6.3 0 0 1-3 .73Zm-11 9.5a.75.75 0 0 1-1.5 0v-3a.75.75 0 0 1 1.5 0Zm8 0a.75.75 0 0 1-1.5 0v-3a.75.75 0 0 1 1.5 0Zm-3.04-21h-3.45L9.23 4.83h5.58zm9.51 4.25l-1.14-3.56a1.09 1.09 0 0 0-1-.69h-5.82l1.06 4.25Zm.48 1.5H.29L0 7.21a3 3 0 0 0 1.15 2.44l.12.1l.31.19l.13.07l.17.08H2l.16.07l.14.05l.18.06H4.2a4.13 4.13 0 0 0 3.21-1.19a.74.74 0 0 1 1.19 0c1.49 2 5.33 2 6.81 0a.74.74 0 0 1 1.19 0a4.17 4.17 0 0 0 3.24 1.5h.82a2.5 2.5 0 0 0 .38-.06h.15l.37-.11l.15-.05l.35-.16l.15-.08l.32-.2l.14-.1A3 3 0 0 0 24 7.22ZM8.74.58h-5.8a1.1 1.1 0 0 0-1 .69L.78 4.83h6.9Z"/></svg>
          Produk Saya
        </a>
        <a href="pesanan.php" style="position:relative;">
          <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24"><path fill="currentColor" d="M19 4h-2V3a1 1 0 0 0-2 0v1H9V3a1 1 0 0 0-2 0v1H5a3 3 0 0 0-3 3v12a3 3 0 0 0 3 3h14a3 3 0 0 0 3-3V7a3 3 0 0 0-3-3m1 15a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1v-7h16Zm0-9H4V7a1 1 0 0 1 1-1h2v1a1 1 0 0 0 2 0V6h6v1a1 1 0 0 0 2 0V6h2a1 1 0 0 1 1 1Z"/></svg>
          Pesanan Masuk
<span id="sidebarPesananBadge" style="
    position:absolute;top:50%;right:12px;transform:translateY(-50%);
    background:#ef4444;color:#fff;border-radius:10px;font-size:11px;font-weight:800;
    min-width:20px;height:20px;display:none;
    align-items:center;justify-content:center;
    padding:0 5px;border:2px solid #fff;
    box-shadow:0 2px 8px rgba(239,68,68,.45);line-height:1;">
  </span>
        </a>
        <a href="pendapatan.php">
          <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 512 512"><path fill="currentColor" d="M32 376a56 56 0 0 0 56 56h336a56 56 0 0 0 56-56V222H32Zm66-76a30 30 0 0 1 30-30h48a30 30 0 0 1 30 30v20a30 30 0 0 1-30 30h-48a30 30 0 0 1-30-30ZM424 80H88a56 56 0 0 0-56 56v26h448v-26a56 56 0 0 0-56-56"/></svg>
          Pendapatan
        </a>
      </div>

      <div class="sidebar-divider"></div>

      <div class="sidebar-bottom">
        <a href="suport.php">
          <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24"><path fill="currentColor" d="M22 17.002a6 6 0 0 1-4.713 5.86l-.638-1.914A4 4 0 0 0 19.465 19H17a2 2 0 0 1-2-2v-4a2 2 0 0 1 2-2h2.938a8.001 8.001 0 0 0-15.876 0H7a2 2 0 0 1 2 2v4a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2v-5C2 6.477 6.477 2 12 2s10 4.477 10 10zM20 17v-4h-3v4zM4 13v4h3v-4z"/></svg>
          Support
        </a>
        <a href="pengaturan.php">
          <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24"><path fill="currentColor" d="m9.25 22l-.4-3.2q-.325-.125-.612-.3t-.563-.375L4.7 19.375l-2.75-4.75l2.575-1.95Q4.5 12.5 4.5 12.338v-.675q0-.163.025-.338L1.95 9.375l2.75-4.75l2.975 1.25q.275-.2.575-.375t.6-.3l.4-3.2h5.5l.4 3.2q.325.125.613.3t.562.375l2.975-1.25l2.75 4.75l-2.575 1.95q.025.175.025.338v.674q0 .163-.05.338l2.575 1.95l-2.75 4.75l-2.95-1.25q-.275.2-.575.375t-.6.3l-.4 3.2zM11 20h1.975l.35-2.65q.775-.2 1.438-.587t1.212-.938l2.475 1.025l.975-1.7l-2.15-1.625q.125-.35.175-.737T17.5 12t-.05-.787t-.175-.738l2.15-1.625l-.975-1.7l-2.475 1.05q-.55-.575-1.212-.962t-1.438-.588L13 4h-1.975l-.35 2.65q-.775.2-1.437.588t-1.213.937L5.55 7.15l-.975 1.7l2.15 1.6q-.125.375-.175.75t-.05.8q0 .4.05.775t.175.75l-2.15 1.625l.975 1.7l2.475-1.05q.55.575 1.213.963t1.437.587zm1.05-4.5q1.45 0 2.475-1.025T15.55 12t-1.025-2.475T12.05 8.5q-1.475 0-2.488 1.025T8.55 12t1.013 2.475T12.05 15.5M12 12"/></svg>
          Pengaturan
        </a>
      </div>
    </aside>

    <!-- ── MAIN ── -->
    <main class="main">

      <!-- Notifikasi sukses/error -->
      <?php if (isset($_GET['sukses'])): ?>
        <div class="alert alert-sukses">
          <i class="fa-solid fa-circle-check"></i>
          Menu berhasil <?= $_GET['sukses'] === 'tambah' ? 'ditambahkan' : 'diperbarui' ?>!
        </div>
      <?php elseif (isset($_GET['error'])): ?>
        <div class="alert alert-error">
          <i class="fa-solid fa-circle-exclamation"></i>
          Terjadi kesalahan, coba lagi.
        </div>
      <?php endif; ?>

      <!-- Filter Kategori -->
      <div class="filter-bar">
        <a href="produk.php" class="filter-chip <?= !$filter_kategori ? 'active' : '' ?>">Semua</a>
        <a href="?kategori=MAKANAN" class="filter-chip <?= $filter_kategori === 'MAKANAN' ? 'active' : '' ?>">
          <i class="fa-solid fa-utensils"></i> Makanan
        </a>
        <a href="?kategori=MINUMAN" class="filter-chip <?= $filter_kategori === 'MINUMAN' ? 'active' : '' ?>">
          <i class="fa-solid fa-mug-hot"></i> Minuman
        </a>
        <a href="?kategori=SNACK" class="filter-chip <?= $filter_kategori === 'SNACK' ? 'active' : '' ?>">
          <i class="fa-solid fa-cookie-bite"></i> Snack
        </a>
      </div>

      <!-- Product search bar -->
      <div class="produk-search-bar">
        <form method="GET" action="produk.php" style="display:contents;">
          <?php if ($filter_kategori): ?>
            <input type="hidden" name="kategori" value="<?= htmlspecialchars($filter_kategori) ?>">
          <?php endif; ?>
          <div class="produk-search-input-wrap">
            <i class="fa-solid fa-magnifying-glass"></i>
            <input type="text" name="search" id="produkSearch" class="produk-search-input"
                   value="<?= htmlspecialchars($search) ?>"
                   placeholder="Cari produk berdasarkan nama..."
                   autocomplete="off">
          </div>
          <button type="submit" style="display:none;"></button>
        </form>
        <span class="produk-search-count" id="produkCount"><?= $total ?> produk</span>
      </div>

      <!-- Product grid -->
      <div class="produk-grid" id="produkGrid">

        <?php if ($total === 0): ?>
          <div class="produk-kosong">
            <i class="fa-solid fa-box-open"></i>
            <p>Belum ada produk<?= $search ? " dengan kata kunci \"$search\"" : '' ?>.</p>
          </div>
        <?php endif; ?>

        <?php foreach ($menus as $menu):
          $foto_src = (!empty($menu['foto']) && file_exists("uploads/" . $menu['foto']))
                      ? "uploads/" . htmlspecialchars($menu['foto'])
                      : "https://images.unsplash.com/photo-1504674900247-0877df9cc836?q=80&w=600";
          $nama      = htmlspecialchars($menu['nama_makanan']);
          $harga     = rupiah($menu['harga']);
          $harga_raw = (int) $menu['harga'];
          $stok      = (int) $menu['stok'];
          $terjual   = (int) ($menu['terjual'] ?? 0);
          $rating    = number_format((float)$menu['rata_bintang'], 1);
          $kantin    = htmlspecialchars($menu['nama_kantin'] ?? 'Kantin');
          $kategori  = htmlspecialchars($menu['kategori']);
          $deskripsi = htmlspecialchars($menu['deskripsi']);
          $id        = (int) $menu['id'];
          $varianMenu = $varian_map[$id] ?? [];
          $jml_varian = count($varianMenu);

          // Siapkan data varian untuk JS (JSON)
          $varian_js = array_map(function($v) use ($harga_raw) {
            return [
              'nama'        => $v['nama'],
              'harga_tambah'=> (int)$v['harga_tambah'],
              'harga_total' => $harga_raw + (int)$v['harga_tambah'],
              'foto'        => $v['foto_varian'] ?? '',
            ];
          }, $varianMenu);
        ?>
        <div class="produk-card <?= ($stok > 0 && $stok <= 10) ? 'stok-warning' : '' ?>" data-nama="<?= $nama ?>">
          <div class="produk-gambar">
            <img src="<?= $foto_src ?>" alt="<?= $nama ?>"
                 onerror="this.src='https://images.unsplash.com/photo-1504674900247-0877df9cc836?q=80&w=600'">
            <?php if ($stok == 0): ?>
              <span class="badge-habis">Habis</span>
            <?php elseif ($stok <= 10): ?>
              <span class="badge-warning"><i class="fa-solid fa-triangle-exclamation"></i> Sisa <?= $stok ?></span>
            <?php endif; ?>
            <?php if ($jml_varian > 0): ?>
              <span class="badge-varian"><i class="fa-solid fa-layer-group"></i> <?= $jml_varian ?> Varian</span>
            <?php endif; ?>
            <div class="produk-overlay">
              <p class="overlay-desc"><?= $deskripsi ?: 'Tidak ada deskripsi.' ?></p>
              <div class="overlay-actions">
                <a href="des.php?edit=<?= $id ?>" class="btn-edit">
                  <i class="fa-solid fa-pen"></i> Edit
                </a>
                <a href="produk.php?hapus=<?= $id ?>"
                   class="btn-hapus"
                   onclick="return confirm('Yakin hapus menu ini?')">
                  <i class="fa-solid fa-trash"></i> Hapus
                </a>
              </div>
            </div>
          </div>

          <div class="produk-isi">
            <div class="produk-kategori">
              <span class="produk-kantin"><?= $kantin ?></span>
              <div class="produk-sekat"></div>
              <?= iconKategori($kategori) ?>
              <span><?= $kategori ?></span>
            </div>
            <div class="produk-nama"><?= $nama ?></div>
            <div class="produk-harga"><?= $harga ?></div>
            <div class="produk-info">
              <div class="produk-box">
                <div class="produk-atas"><i class="fa-solid fa-star produk-star"></i><span><?= $rating ?></span></div>
                <div class="produk-bawah">Rating</div>
              </div>
              <div class="produk-garis"></div>
              <div class="produk-box">
                <div class="produk-atas"><i class="fa-solid fa-bag-shopping"></i><span><?= $terjual ?></span></div>
                <div class="produk-bawah">Terjual</div>
              </div>
              <div class="produk-garis"></div>
              <div class="produk-box">
                <div class="produk-atas"><i class="fa-solid fa-box"></i><span><?= $stok ?></span></div>
                <div class="produk-bawah">Stock</div>
              </div>
            </div>

            <?php if ($jml_varian > 0): ?>
            <div class="produk-varian-box">
              <div class="pv-label">
                <i class="fa-solid fa-layer-group"></i> Varian Menu
              </div>
              <!-- Preview chips max 3 -->
              <div class="pv-chips">
                <?php foreach (array_slice($varianMenu, 0, 3) as $v): ?>
                  <span class="pv-chip">
                    <?= htmlspecialchars($v['nama']) ?>
                    <?php if ((int)$v['harga_tambah'] > 0): ?>
                      <span class="pv-chip-plus">+<?= rupiah($v['harga_tambah']) ?></span>
                    <?php endif; ?>
                  </span>
                <?php endforeach; ?>
                <?php if ($jml_varian > 3): ?>
                  <span class="pv-chip pv-chip-more">+<?= $jml_varian - 3 ?> lagi</span>
                <?php endif; ?>
              </div>
              <!-- TOMBOL LIHAT SEMUA VARIAN -->
              <button
                class="btn-lihat-varian"
                onclick="openVarianModal(
                  <?= htmlspecialchars(json_encode($menu['nama_makanan']), ENT_QUOTES) ?>,
                  <?= htmlspecialchars(json_encode($harga), ENT_QUOTES) ?>,
                  <?= htmlspecialchars(json_encode($harga_raw), ENT_QUOTES) ?>,
                  <?= htmlspecialchars(json_encode($kategori), ENT_QUOTES) ?>,
                  <?= htmlspecialchars(json_encode($varian_js), ENT_QUOTES) ?>
                )"
              >
                <i class="fa-solid fa-list-ul"></i>
                Lihat Semua Varian (<?= $jml_varian ?>)
              </button>
            </div>
            <?php else: ?>
            <!-- Tidak ada varian: tampilkan info kecil -->
            <div class="produk-varian-box" style="border-top:1px solid rgba(232,113,74,.08); margin-top:10px; padding-top:8px;">
              <div class="pv-label" style="color:#ccc;">
                <i class="fa-solid fa-layer-group" style="color:#e0d0c8;"></i>
                Belum ada varian
              </div>
            </div>
            <?php endif; ?>

          </div>
        </div>
        <?php endforeach; ?>

        <!-- CARD TAMBAH -->
        <a href="des.php" class="produk-card card-tambah" id="cardTambah">
          <div class="tambah-isi">
            <div class="tambah-icon"><i class="fa-solid fa-plus"></i></div>
            <div class="tambah-text">Tambah Produk</div>
          </div>
        </a>

      </div><!-- /.produk-grid -->

    </main>
  </div><!-- /.page-wrapper -->

  <!-- ══════════════════════════════════════════════
       MODAL VARIAN — BOTTOM SHEET
  ══════════════════════════════════════════════ -->
  <div class="modal-varian-bg" id="modalVarianBg" onclick="if(event.target===this)closeVarianModal()">
    <div class="modal-varian" id="modalVarian">

      <!-- Handle -->
      <div class="mv-handle"></div>

      <!-- Header -->
      <div class="mv-header">
        <div class="mv-header-left">
          <div class="mv-badge-kat" id="mvKategori"></div>
          <div class="mv-title" id="mvNama"></div>
          <div class="mv-sub" id="mvHarga"></div>
        </div>
        <button class="mv-close-x" onclick="closeVarianModal()" aria-label="Tutup">
          <i class="fa-solid fa-xmark"></i>
        </button>
      </div>

      <!-- Stats bar -->
      <div class="mv-stats" id="mvStats">
        <div class="mv-stat">
          <div class="mv-stat-val" id="mvJmlVarian">0</div>
          <div class="mv-stat-lbl">Varian</div>
        </div>
        <div class="mv-stat">
          <div class="mv-stat-val" id="mvHargaMin">-</div>
          <div class="mv-stat-lbl">Harga Min</div>
        </div>
        <div class="mv-stat">
          <div class="mv-stat-val" id="mvHargaMax">-</div>
          <div class="mv-stat-lbl">Harga Max</div>
        </div>
      </div>

      <!-- List varian -->
      <div class="mv-list" id="mvList"></div>

      <!-- Footer -->
      <div class="mv-footer">
        <button class="mv-close-btn" onclick="closeVarianModal()">
          <i class="fa-solid fa-xmark"></i> Tutup
        </button>
      </div>

    </div>
  </div>

 

<script src="produk.js"></script>
<script>
// ══ STOK HAMPIR HABIS — NOTIFIKASI ══
(function() {
    var btn   = document.getElementById('stokNotifBtn');
    var panel = document.getElementById('stokNotifPanel');
    var badge = document.getElementById('stokNotifBadge');
    var list  = document.getElementById('stokNotifList');

    function openStokPanel()  { panel.classList.add('open'); }
    window.closeStokPanel = function() { panel.classList.remove('open'); };

    // Tutup panel kalau klik di luar
    document.addEventListener('click', function(e) {
        if (!panel.contains(e.target) && !btn.contains(e.target)) {
            panel.classList.remove('open');
        }
    });

    btn.addEventListener('click', function(e) {
        e.stopPropagation();
        panel.classList.toggle('open');
    });

    function loadStokWarning() {
        fetch('stok_warning.php')
            .then(function(r) { return r.json(); })
            .then(function(data) {
                var items = data.items || [];
                var count = items.length;

                // Update badge
                if (count > 0) {
                    badge.textContent = count > 99 ? '99+' : count;
                    badge.classList.add('show');
                } else {
                    badge.classList.remove('show');
                }

                // Render list
                if (count === 0) {
                    list.innerHTML = '<div class="stok-notif-empty"><i class="fa-solid fa-check-circle" style="color:#6ee7b7;"></i>Semua stok aman</div>';
                } else {
                    var html = '';
                    items.forEach(function(item) {
                        var warna = item.stok <= 3 ? 'kritis' : '';
                        var emoji = item.stok <= 3 ? '🔴' : '⚠️';
                        html += '<div class="stok-notif-item" onclick="window.location.href=\'produk.php\'">' +
                            '<div class="stok-notif-item-icon">' + emoji + '</div>' +
                            '<div class="stok-notif-item-info">' +
                                '<div class="stok-notif-item-nama">' + item.nama + '</div>' +
                                '<div class="stok-notif-item-stok ' + warna + '">Sisa stok: ' + item.stok + ' tersisa</div>' +
                            '</div>' +
                        '</div>';
                    });
                    list.innerHTML = html;
                }
            })
            .catch(function() {});
    }

    loadStokWarning();
    setInterval(loadStokWarning, 15000); // refresh tiap 15 detik
})();
</script>
  <script>
async function loadNotifChat() {
    try {
        const res = await fetch('../users/chat_api.php?action=jumlah_belum_baca');
        const data = await res.json();

        const badge = document.getElementById('chatBadge');

        if (data.belum_baca > 0) {
            badge.style.display = 'flex';
            badge.textContent = data.belum_baca;
        } else {
            badge.style.display = 'none';
        }

    } catch (err) {
        console.error('Gagal ambil notif chat:', err);
    }
}

loadNotifChat();
setInterval(loadNotifChat, 1000);
</script>
  <?php include 'chat_popup_penjual.php'; ?>

<script>
// ── Badge Pesanan Masuk sidebar (poll tiap 5 detik) ──
function loadSidebarPesananBadge() {
    fetch('pesanan.php?action=get_notif_count')
        .then(r => r.json())
        .then(data => {
            const badge = document.getElementById('sidebarPesananBadge');
            if (!badge) return;
            const pending = data.pending || 0;
            if (pending > 0) {
                badge.textContent   = pending;
                badge.style.display = 'flex';
            } else {
                badge.style.display = 'none';
            }
        })
        .catch(() => {});
}
loadSidebarPesananBadge();
setInterval(loadSidebarPesananBadge, 5000);
</script>

</body>
</html>