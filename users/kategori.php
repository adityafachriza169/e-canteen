<?php
// ============================================================
// kategori.php — Halaman Toko per Kantin
// URL: kategori.php?id_kantin=X  atau  kategori.php?id_penjual=X
// ============================================================
session_start();
require 'koneksi.php';

if (!isset($_SESSION['id_user'])) {
    header('Location: login.php'); exit;
}

$id_user       = (int)$_SESSION['id_user'];
$username_user = $_SESSION['username'] ?? 'Tamu';

// Ambil pp_user langsung dari database (sama seperti user.php)
$stmt_pp = mysqli_prepare($konek,
    "SELECT pp FROM users WHERE id_user = ? LIMIT 1");
mysqli_stmt_bind_param($stmt_pp, 'i', $id_user);
mysqli_stmt_execute($stmt_pp);
$row_pp  = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_pp));
$pp_user = $row_pp['pp'] ?? null;

if (!empty($_GET['id_kantin']) && (int)$_GET['id_kantin'] > 0) {
    $id_kantin = (int)$_GET['id_kantin'];
} elseif (!empty($_GET['id_penjual']) && (int)$_GET['id_penjual'] > 0) {
    $tmp = mysqli_query($konek,
        "SELECT id_kantin FROM penjual WHERE id_penjual=".(int)$_GET['id_penjual']." LIMIT 1");
    $row = mysqli_fetch_assoc($tmp);
    $id_kantin = (int)($row['id_kantin'] ?? 0);
} else {
    $id_kantin = 0;
}
if ($id_kantin <= 0) { header('Location: menu.php'); exit; }

$resK = mysqli_query($konek,
    "SELECT id_kantin, nama_kantin FROM kantin WHERE id_kantin=$id_kantin LIMIT 1");
if (!$resK || mysqli_num_rows($resK) === 0) { header('Location: menu.php'); exit; }
$kantin = mysqli_fetch_assoc($resK);

$stmtP = mysqli_prepare($konek,
    "SELECT id_penjual, nama, profil, buka FROM penjual WHERE id_kantin=? AND status=1 LIMIT 1");
mysqli_stmt_bind_param($stmtP, 'i', $id_kantin);
mysqli_stmt_execute($stmtP);
$dataPenjual = mysqli_fetch_assoc(mysqli_stmt_get_result($stmtP));
$idPenjual   = (int)($dataPenjual['id_penjual'] ?? 0);
$namaPenjual = $dataPenjual['nama']   ?? $kantin['nama_kantin'];
$fotoPenjual = $dataPenjual['profil'] ?? '';
$isOpen      = (int)($dataPenjual['buka'] ?? 1);

$resMenu = mysqli_query($konek, "
    SELECT
        m.id, m.nama_makanan, m.foto, m.harga, m.stok,
        m.terjual, m.kategori, m.deskripsi, m.waktu_proses,
        COALESCE(ROUND(AVG(u.bintang),1), 0) AS rata_bintang,
        COALESCE(COUNT(DISTINCT u.id), 0)    AS total_ulasan
    FROM menu_req m
    LEFT JOIN ulasan u ON u.id_menu = m.id
    WHERE m.id_kantin = $id_kantin AND m.aktif = 1
    GROUP BY m.id
    ORDER BY m.terjual DESC, m.id DESC
");
$semuaMenu = [];
while ($row = mysqli_fetch_assoc($resMenu)) { $semuaMenu[] = $row; }

$totalMenu      = count($semuaMenu);
$totalPenilaian = (int)array_sum(array_column($semuaMenu, 'total_ulasan'));
$ratings        = array_filter(array_column($semuaMenu, 'rata_bintang'));
$avgRating      = count($ratings) > 0 ? number_format(array_sum($ratings)/count($ratings),1) : '–';
$totalTerjual   = array_sum(array_column($semuaMenu, 'terjual'));

$qCart = mysqli_prepare($konek,
    "SELECT COALESCE(SUM(jumlah),0) AS total FROM keranjang WHERE id_user=?");
mysqli_stmt_bind_param($qCart, 'i', $id_user);
mysqli_stmt_execute($qCart);
$totalKeranjang = (int)mysqli_fetch_assoc(mysqli_stmt_get_result($qCart))['total'];

$countAll = $totalMenu;
$countMak = count(array_filter($semuaMenu, fn($m) => in_array(strtolower($m['kategori']),['makanan'])));
$countMin = count(array_filter($semuaMenu, fn($m) => in_array(strtolower($m['kategori']),['minuman'])));
$countSnk = count(array_filter($semuaMenu, fn($m) => in_array(strtolower($m['kategori']),['snack','camilan','cemilan'])));

setcookie("last_page", $_SERVER['REQUEST_URI'], time()+86400*30, "/");
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title><?= htmlspecialchars($kantin['nama_kantin']) ?> — E-Canteen</title>
<link href="https://fonts.googleapis.com/css2?family=Righteous&family=Plus+Jakarta+Sans:ital,wght@0,400;0,500;0,600;0,700;0,800;1,400&display=swap" rel="stylesheet"/>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css"/>
<style>
/* ============================================
   RESET & BASE
   ============================================ */
*, *::before, *::after { margin:0; padding:0; box-sizing:border-box; }
a { text-decoration:none; color:inherit; }
html { overflow-x:hidden; }
body {
  padding-top: 126px;
  font-family: 'Plus Jakarta Sans', sans-serif;
  background: #FDF6EE;
  color: #3A1A00;
  overflow-x: hidden;
  width: 100%;
}

/* ============================================
   ORANGE GRADIENT
   ============================================ */
.orange-bg {
  background:
    radial-gradient(ellipse 55% 130% at 85% 40%, rgba(255,220,100,.28) 0%, transparent 55%),
    radial-gradient(ellipse 45% 110% at  5% 60%, rgba(200,90,0,.30) 0%, transparent 60%),
    linear-gradient(112deg, #d45500 0%, #e86510 22%, #f57c1a 45%, #f98e20 65%, #fcaa38 82%, #ffc55a 100%);
}

/* ============================================
   TOPBAR
   ============================================ */
.topbar {
  position:fixed; top:0; left:0;
  width:100%; z-index:1000;
  height:36px; padding:0 52px;
  background:#b84a00;
  display:flex; align-items:center; justify-content:space-between;
  font-size:12px; color:rgba(255,255,255,.8);
}
.top-left { display:flex; align-items:center; }
.top-left a { padding:0 12px; color:rgba(255,255,255,.75); font-size:12px; letter-spacing:.3px; transition:color .2s; }
.top-left a:first-child { padding-left:0; }
.top-left a:hover { color:#ffe0a0; }
.sep { color:rgba(255,255,255,.2); user-select:none; }
.top-right { display:flex; align-items:center; gap:20px; }
.top-right > a { display:flex; align-items:center; gap:5px; color:rgba(255,255,255,.75); font-size:12px; transition:color .2s; }
.top-right > a i { font-size:11px; }
.top-right > a:hover { color:#ffe0a0; }
.lang { position:relative; display:flex; align-items:center; gap:6px; cursor:pointer; color:rgba(255,255,255,.75); font-size:12px; }
.lang i.fa-chevron-down { font-size:8px; transition:transform .3s; }
.lang:hover { color:#ffe0a0; }
.lang:hover i.fa-chevron-down { transform:rotate(180deg); }
.lang-drop {
  position:absolute; top:calc(100% + 14px); right:0; width:190px;
  background:#fff; border-radius:10px; box-shadow:0 12px 40px rgba(0,0,0,.2);
  padding:6px 0; opacity:0; visibility:hidden; transform:translateY(8px);
  transition:opacity .22s, visibility .22s, transform .22s; z-index:999;
}
.lang:hover .lang-drop { opacity:1; visibility:visible; transform:translateY(0); }
.lang-drop a { display:flex; align-items:center; gap:10px; padding:9px 16px; color:#444; font-size:13px; transition:background .15s, color .15s; }
.lang-drop a:hover { background:#fff5ee; color:#e8650a; }
.lang-drop .active { color:#e8650a; font-weight:600; }
.profile { position:relative; display:flex; align-items:center; gap:7px; cursor:pointer; }
.profile img { width:26px; height:26px; border-radius:50%; object-fit:cover; border:1.5px solid rgba(255,255,255,.5); }
.profile > span { font-size:12px; color:rgba(255,255,255,.8); font-weight:600; }
.profile-drop {
  position:absolute; top:calc(100% + 14px); right:0; width:210px;
  background:#fff; border-radius:12px; box-shadow:0 12px 40px rgba(0,0,0,.18);
  padding:8px 0; opacity:0; visibility:hidden; transform:translateY(8px);
  transition:opacity .22s, visibility .22s, transform .22s; z-index:999;
}
.profile:hover .profile-drop { opacity:1; visibility:visible; transform:translateY(0); }
.profile-drop a { display:flex; align-items:center; gap:12px; padding:10px 18px; color:#333; font-size:13px; transition:background .15s, color .15s; }
.profile-drop a i { width:16px; text-align:center; color:#999; font-size:13px; }
.profile-drop a:hover { background:#fff5ee; color:#e8650a; }
.profile-drop a:hover i { color:#e8650a; }
.drop-line { height:1px; background:#f2f2f2; margin:5px 0; }
.logout { color:#e53935 !important; }
.logout i { color:#e53935 !important; }

/* ============================================
   NAVBAR DESKTOP
   ============================================ */
.navbar {
  position:fixed; top:36px; left:0;
  width:100%; z-index:999; height:90px; padding:0 52px;
  display:flex; align-items:center; gap:24px; overflow:hidden;
}
.navbar::before {
  content:''; position:absolute; top:0; left:0; right:0; height:1px;
  background:linear-gradient(90deg, transparent, rgba(255,240,180,.6) 50%, transparent);
}
.navbar::after {
  content:''; position:absolute; bottom:0; left:0; right:0; height:2.5px;
  background:linear-gradient(90deg, transparent, rgba(255,200,80,.6) 25%, rgba(255,250,200,.9) 50%, rgba(255,200,80,.6) 75%, transparent);
}
.navbar-texture {
  position:absolute; inset:0; pointer-events:none;
  background:repeating-linear-gradient(108deg, transparent 0, transparent 58px, rgba(255,255,255,.03) 58px, rgba(255,255,255,.03) 59px);
}
.orb1 { position:absolute; top:-90px; right:80px; width:280px; height:280px; border-radius:50%; pointer-events:none; background:radial-gradient(circle, rgba(255,220,100,.25) 0%, transparent 65%); }
.orb2 { position:absolute; bottom:-110px; left:220px; width:220px; height:220px; border-radius:50%; pointer-events:none; background:radial-gradient(circle, rgba(255,140,30,.2) 0%, transparent 65%); }
.logo { position:relative; z-index:1; min-width:185px; display:flex; align-items:center; gap:11px; flex-shrink:0; }
.logo-img-wrap { display:flex; align-items:center; transition:transform .25s; }
.logo-img-wrap:hover { transform:translateY(-2px); }
.logo-img-wrap img { width:72px; filter:drop-shadow(0 3px 10px rgba(0,0,0,.3)); }
.logo-text { display:flex; flex-direction:column; }
.logo-title { font-family:'Righteous',cursive; font-size:24px; color:#fff; text-shadow:0 2px 12px rgba(150,60,0,.4); line-height:1.1; letter-spacing:.5px; }
.logo-sub { font-size:10px; color:rgba(255,240,200,.85); letter-spacing:2px; text-transform:uppercase; font-weight:600; }
.search-wrap { flex:1; position:relative; z-index:1; }
.search-box {
  display:flex; height:50px; background:rgba(255,255,255,.97);
  border:1.5px solid rgba(255,255,255,.9); border-radius:10px; overflow:hidden;
  box-shadow:0 2px 0 rgba(255,255,255,.5) inset, 0 6px 24px rgba(150,60,0,.2);
  transition:box-shadow .25s, border-color .25s;
}
.search-box:focus-within { border-color:#fff; box-shadow:0 2px 0 rgba(255,255,255,.5) inset, 0 6px 28px rgba(150,60,0,.25), 0 0 0 3px rgba(255,170,60,.55); }
.search-box input { flex:1; border:none; outline:none; padding:0 18px; font-family:'Plus Jakarta Sans',sans-serif; font-size:14px; color:#333; background:transparent; }
.search-box input::placeholder { color:#bbb; }
.search-scope { display:flex; align-items:center; padding:0 4px; border-left:1px solid #eee; }
.search-scope select { border:none; outline:none; background:transparent; cursor:pointer; font-family:'Plus Jakarta Sans',sans-serif; font-size:13px; font-weight:600; color:#666; padding:0 28px 0 12px; height:100%; appearance:none; background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='10' viewBox='0 0 24 24' fill='none' stroke='%23888' stroke-width='2.5'%3E%3Cpath d='M6 9l6 6 6-6'/%3E%3C/svg%3E"); background-repeat:no-repeat; background-position:right 8px center; }
.search-box button { width:52px; border:none; background:linear-gradient(145deg,#f98020,#e06010); color:#fff; font-size:16px; cursor:pointer; display:flex; align-items:center; justify-content:center; transition:background .2s; }
.search-box button:hover { background:linear-gradient(145deg,#ffa030,#f07020); }
.icon-actions { position:relative; z-index:1; display:flex; align-items:center; gap:10px; flex-shrink:0; }
.icon-btn {
  position:relative; width:52px; height:52px;
  display:flex; align-items:center; justify-content:center;
  color:#fff; font-size:21px; cursor:pointer; border-radius:12px;
  background:rgba(255,255,255,.15); border:1.5px solid rgba(255,255,255,.3);
  box-shadow:0 2px 8px rgba(150,60,0,.15), inset 0 1px 0 rgba(255,255,255,.25);
  transition:all .22s cubic-bezier(.34,1.56,.64,1);
}
.icon-btn:hover { background:rgba(255,255,255,.28); border-color:rgba(255,255,255,.55); transform:translateY(-2px); }
.icon-btn:active { transform:scale(.94); }
.icon-badge {
  position:absolute; top:-6px; right:-6px;
  min-width:20px; height:20px; padding:0 5px;
  background:#fff; color:#e8650a; border:1.5px solid rgba(255,150,30,.2);
  border-radius:10px; display:flex; align-items:center; justify-content:center;
  font-family:'Plus Jakarta Sans',sans-serif; font-size:10.5px; font-weight:700;
  letter-spacing:-.3px; box-shadow:0 2px 8px rgba(0,0,0,.18);
}

/* ============================================
   MOBILE NAV
   ============================================ */
.mobile-nav     { display:none; }
.mob-bottom-nav { display:none; }
.mob-bottom-spacer { display:none; }

/* ============================================
   PAGE WRAPPER
   ============================================ */
.page { max-width:1380px; margin:0 auto; padding:28px 28px 64px; }

/* ============================================
   STORE CARD — PROFIL PENJUAL
   ============================================ */
.store-card {
  position:relative; overflow:hidden;
  background:#fff; border:1.5px solid rgba(232,113,74,.15);
  border-radius:16px; box-shadow:0 2px 16px rgba(232,113,74,.1);
  margin-bottom:22px; padding:24px 28px 0;
}
.store-card::after {
  content:''; position:absolute; right:0; top:0; bottom:0; width:35%;
  pointer-events:none;
  background:radial-gradient(ellipse at 90% 55%, #fde8d4 0%, transparent 65%);
}
.store-head {
  position:relative; z-index:1;
  display:flex; align-items:flex-start; gap:18px;
}
.store-logo {
  flex-shrink:0; width:80px; height:80px; border-radius:18px;
  background:linear-gradient(135deg,#fde8dc,#ffd0b0);
  border:2px solid rgba(232,113,74,.2);
  display:flex; align-items:center; justify-content:center;
  font-size:36px; overflow:hidden;
  box-shadow:0 4px 16px rgba(232,113,74,.2);
}
.store-logo img { width:100%; height:100%; object-fit:cover; }
.store-info { flex:1; min-width:0; }
.store-kantin { color:#e8714a; font-size:13px; font-weight:800; margin-bottom:3px; letter-spacing:.3px; }
.store-name { font-size:28px; font-weight:900; color:#3A1A00; margin-bottom:12px; line-height:1.1; }
.store-meta { display:flex; flex-wrap:wrap; gap:20px; font-size:13px; color:#888; font-weight:600; }
.store-meta-item { display:flex; align-items:center; gap:6px; }
.store-meta-item i { font-size:12px; color:#e8714a; }
.dot-live {
  display:inline-block; width:8px; height:8px; border-radius:50%;
  background:#22c55e; box-shadow:0 0 0 2px rgba(34,197,94,.25);
  animation:pulse 2s infinite;
}
.dot-closed { background:#ef4444; box-shadow:0 0 0 2px rgba(239,68,68,.25); }
@keyframes pulse { 0%,100%{box-shadow:0 0 0 2px rgba(34,197,94,.25)}50%{box-shadow:0 0 0 5px rgba(34,197,94,.1)} }

.store-right { display:flex; align-items:flex-start; gap:14px; }
.store-stats { flex-shrink:0; display:flex; gap:12px; align-self:flex-start; padding-top:2px; }
.stat-box {
  min-width:100px; padding:16px 24px;
  background:#fdf6f0; border:1.5px solid rgba(232,113,74,.15);
  border-radius:14px; text-align:center;
  box-shadow:0 2px 8px rgba(232,113,74,.08);
}
.stat-box .s-icon { display:block; font-size:22px; margin-bottom:5px; }
.stat-box .s-num  { font-size:26px; font-weight:900; color:#3A1A00; line-height:1; }
.stat-box .s-lbl  { font-size:12px; font-weight:600; color:#aaa; margin-top:3px; }
.chat-btn {
  display:flex; align-items:center; justify-content:center; gap:7px;
  padding:11px 22px;
  background:#fff; border:2px solid #e8714a; border-radius:12px;
  color:#e8714a; font-family:'Plus Jakarta Sans',sans-serif;
  font-size:14px; font-weight:800; white-space:nowrap; cursor:pointer;
  box-shadow:0 2px 10px rgba(232,113,74,.15); transition:all .18s;
}
.chat-btn:hover { background:#e8714a; color:#fff; transform:translateY(-1px); box-shadow:0 6px 18px rgba(232,113,74,.35); }

/* Tabs */
.store-tabs {
  display:flex; flex-wrap:wrap; gap:8px;
  padding:16px 0 18px; margin-top:16px;
  border-top:1px solid rgba(232,113,74,.1);
}
.tab-btn {
  display:flex; align-items:center; gap:7px; padding:9px 22px;
  background:#fff; border:1.5px solid #e8e0d8; border-radius:50px;
  font-family:'Plus Jakarta Sans',sans-serif; font-size:13px; font-weight:700;
  color:#888; cursor:pointer; box-shadow:0 1px 4px rgba(0,0,0,.04); transition:all .18s;
}
.tab-btn.active { background:linear-gradient(135deg,#e8714a,#f5a87b); border-color:transparent; color:#fff; box-shadow:0 4px 14px rgba(232,113,74,.35); }
.tab-btn:hover:not(.active) { border-color:#e8714a; color:#e8714a; background:#fff8f4; }
.tab-badge { background:rgba(255,255,255,.3); padding:1px 7px; border-radius:20px; font-size:11px; }
.tab-btn:not(.active) .tab-badge { background:rgba(232,113,74,.12); color:#e8714a; }

/* ============================================
   CONTENT LAYOUT
   ============================================ */
.content-wrap {
  display:grid;
  grid-template-columns:210px 1fr;
  gap:20px;
}

/* ============================================
   CATEGORY SIDEBAR
   ============================================ */
.cat-sidebar {
  height:fit-content; padding:20px 14px;
  background:#fff; border:1.5px solid rgba(232,113,74,.12);
  border-radius:16px; box-shadow:0 2px 12px rgba(232,113,74,.08);
  position:sticky; top:140px;
}
.cat-title {
  display:flex; align-items:center; gap:8px;
  padding:0 6px; margin-bottom:16px;
  font-size:15px; font-weight:900; color:#3A1A00;
}
.cat-title i { font-size:14px; color:#e8714a; }
.cat-list { list-style:none; display:flex; flex-direction:column; gap:2px; }
.cat-item {
  display:flex; align-items:center; padding:10px 12px;
  border-radius:10px; font-size:14px; font-weight:700; color:#888;
  cursor:pointer; transition:all .18s; gap:8px;
}
.cat-item i { width:18px; font-size:13px; color:#ccc; transition:color .18s; flex-shrink:0; }
.cat-item:hover { background:#fff5ee; color:#e8714a; }
.cat-item:hover i { color:#e8714a; }
.cat-item.active { background:#ffdabf; color:#c84b11; font-weight:800; }
.cat-item.active i { color:#c84b11; }
.cat-sep { height:1px; background:rgba(232,113,74,.12); margin:6px 4px; }
.cat-count { margin-left:auto; font-size:11px; font-weight:700; background:rgba(232,113,74,.1); color:#e8714a; padding:2px 7px; border-radius:20px; }
.cat-item.active .cat-count { background:rgba(200,75,17,.2); color:#c84b11; }

/* ============================================
   PRODUCTS PANEL
   ============================================ */
.products-panel { min-width:0; }

/* Sort Bar */
.sort-bar {
  display:flex; align-items:center; flex-wrap:wrap; gap:8px;
  padding:12px 18px; margin-bottom:16px;
  background:#fff; border:1.5px solid rgba(232,113,74,.12);
  border-radius:14px; box-shadow:0 2px 12px rgba(232,113,74,.07);
}
.sort-lbl { font-size:13px; font-weight:700; color:#aaa; margin-right:2px; }
.sort-btn {
  padding:7px 18px; background:#fff; border:1.5px solid #e8e0d8; border-radius:50px;
  font-family:'Plus Jakarta Sans',sans-serif; font-size:13px; font-weight:700;
  color:#888; cursor:pointer; transition:all .18s;
}
.sort-btn.active { background:linear-gradient(135deg,#e8714a,#f5a87b); border-color:transparent; color:#fff; box-shadow:0 3px 12px rgba(232,113,74,.3); }
.sort-btn:hover:not(.active) { border-color:#e8714a; color:#e8714a; }
.sort-select {
  padding:7px 28px 7px 14px; background:#fff; border:1.5px solid #e8e0d8;
  border-radius:50px; font-family:'Plus Jakarta Sans',sans-serif;
  font-size:13px; font-weight:700; color:#888; outline:none; cursor:pointer; appearance:none;
  background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='10' viewBox='0 0 24 24' fill='none' stroke='%23aaa' stroke-width='2.5'%3E%3Cpath d='M6 9l6 6 6-6'/%3E%3C/svg%3E");
  background-repeat:no-repeat; background-position:right 10px center;
}
.sort-right { margin-left:auto; display:flex; align-items:center; gap:7px; font-size:13px; font-weight:700; color:#bbb; }
.pg-btn {
  width:32px; height:32px; display:flex; align-items:center; justify-content:center;
  background:#fff; border:1.5px solid #e8e0d8; border-radius:8px;
  font-size:14px; color:#888; cursor:pointer; transition:all .18s;
}
.pg-btn:hover:not(:disabled) { border-color:#e8714a; color:#e8714a; background:#fff8f4; }
.pg-btn:disabled { opacity:.35; cursor:default; }
.pg-info { font-size:13px; font-weight:700; color:#bbb; }

/* ── Search inside sort bar ── */
.sort-search {
  position:relative; flex:1; min-width:160px;
}
.sort-search input {
  width:100%; padding:7px 14px 7px 34px;
  border-radius:50px; border:1.5px solid #e8e0d8;
  background:#fdf8f5; font-size:13px; font-family:'Plus Jakarta Sans',sans-serif;
  color:#3A1A00; outline:none; transition:border .2s, background .2s;
}
.sort-search input:focus { border-color:#e8714a; background:#fff; }
.sort-search i { position:absolute; left:12px; top:50%; transform:translateY(-50%); color:#ccc; font-size:12px; }

/* ============================================
   PRODUCT GRID
   ============================================ */
.product-grid {
  display:grid;
  grid-template-columns:repeat(auto-fill, minmax(180px,1fr));
  gap:14px;
}
.product-card {
  background:#fff; border:1.5px solid rgba(232,113,74,.1);
  border-radius:14px; overflow:hidden; cursor:pointer;
  box-shadow:0 2px 10px rgba(232,113,74,.07);
  transition:transform .2s, box-shadow .2s, border-color .2s;
  display:flex; flex-direction:column;
}
.product-card:hover { transform:translateY(-4px); box-shadow:0 10px 28px rgba(232,113,74,.18); border-color:rgba(232,113,74,.3); }
.card-img {
  position:relative; width:100%; padding-top:66%;
  background:#fdf0e8; overflow:hidden; flex-shrink:0;
}
.card-img img { position:absolute; inset:0; width:100%; height:100%; object-fit:cover; transition:transform .3s; }
.product-card:hover .card-img img { transform:scale(1.06); }
.card-placeholder {
  position:absolute; inset:0;
  display:flex; flex-direction:column; align-items:center; justify-content:center; gap:6px;
  background:linear-gradient(135deg,#fde8dc,#fdf0e8);
}
.card-placeholder .ph-icon { font-size:36px; }
.card-placeholder .ph-txt  { font-size:11px; font-weight:700; color:#e8714a; opacity:.6; }
.card-cat-badge {
  position:absolute; top:8px; left:8px;
  background:rgba(58,26,0,.6); backdrop-filter:blur(4px);
  color:#fff; font-size:10px; font-weight:700;
  padding:3px 8px; border-radius:20px;
  display:flex; align-items:center; gap:4px;
  text-transform:uppercase; letter-spacing:.04em;
}
.card-stok-badge {
  position:absolute; top:8px; right:8px;
  background:#ea580c; color:#fff;
  font-size:10px; font-weight:700;
  padding:3px 7px; border-radius:20px;
}
.card-body { padding:13px 14px 14px; flex:1; display:flex; flex-direction:column; }
.card-meta { display:flex; align-items:center; flex-wrap:wrap; gap:3px; margin-bottom:4px; font-size:11px; font-weight:700; color:#e8714a; }
.card-meta span { color:#ccc; font-weight:600; }
.card-name { margin-bottom:6px; font-size:14px; font-weight:800; color:#3A1A00; line-height:1.35; display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; overflow:hidden; }
.card-sub { display:flex; align-items:center; flex-wrap:wrap; gap:6px; margin-bottom:8px; }
.card-rating { display:inline-flex; align-items:center; gap:3px; font-size:11.5px; font-weight:700; color:#e8714a; }
.card-terjual { display:inline-flex; align-items:center; gap:3px; font-size:11px; font-weight:600; color:#aaa; }
.card-foot { display:flex; align-items:center; justify-content:space-between; margin-top:auto; }
.card-price { font-size:15px; font-weight:900; color:#c84b11; min-width:0; }
.add-btn {
  flex-shrink:0; width:32px; height:32px;
  display:flex; align-items:center; justify-content:center;
  background:#fff; border:2px solid #e8714a; border-radius:9px;
  color:#e8714a; font-size:20px; font-weight:700; line-height:1; cursor:pointer;
  box-shadow:0 2px 6px rgba(232,113,74,.15); transition:all .18s;
}
.add-btn:hover { background:linear-gradient(135deg,#e8714a,#f5a87b); border-color:transparent; color:#fff; box-shadow:0 4px 12px rgba(232,113,74,.4); }
.empty { grid-column:1/-1; padding:56px 20px; text-align:center; font-size:15px; font-weight:700; color:#e8c4a8; }
.empty i { display:block; font-size:40px; margin-bottom:12px; }

/* ============================================
   TOAST
   ============================================ */
#cart-toast {
  position:fixed; bottom:90px; left:50%; z-index:99999;
  padding:11px 24px; background:rgba(58,26,0,.92); color:#fff;
  border-radius:50px; font-family:'Plus Jakarta Sans',sans-serif;
  font-size:13px; font-weight:700; white-space:nowrap; pointer-events:none;
  opacity:0; transform:translateX(-50%) translateY(14px);
  box-shadow:0 4px 18px rgba(0,0,0,.25); transition:opacity .25s, transform .25s;
}
#cart-toast.show { opacity:1; transform:translateX(-50%) translateY(0); }
@keyframes badgeBounce { 0%{transform:scale(1)}30%{transform:scale(1.55)}60%{transform:scale(.88)}100%{transform:scale(1)} }
.badge-bounce { animation:badgeBounce .45s ease forwards; }

/* Chat badge */
.notif-chat-btn { position:relative; }
.chat-badge {
  position:absolute; top:-5px; right:-5px;
  min-width:18px; height:18px; padding:0 5px;
  background:#ef4444; color:#fff;
  border-radius:999px; font-size:10px; font-weight:700;
  display:flex; align-items:center; justify-content:center;
  border:2px solid #fff; line-height:1;
}

/* ============================================
   RESPONSIVE — TABLET ≤1100px
   ============================================ */
@media (max-width:1100px) {
  .content-wrap { grid-template-columns:180px 1fr; }
  .product-grid { grid-template-columns:repeat(auto-fill,minmax(160px,1fr)); }
  .store-stats .stat-box { min-width:80px; padding:12px 16px; }
  .stat-box .s-num { font-size:20px; }
}

/* ============================================
   RESPONSIVE — MOBILE ≤768px
   ============================================ */
@media (max-width:768px) {
  body { padding-top:0; }
  .topbar, .navbar { display:none !important; }

  /* Mobile top nav */
  .mobile-nav { display:block; position:sticky; top:0; z-index:200; width:100%; }
  .mob-top {
    position:relative; overflow:hidden;
    display:flex; align-items:center; justify-content:space-between;
    padding:10px 16px; min-height:58px;
  }
  .mob-orb { position:absolute; top:-70px; right:30px; width:200px; height:200px; border-radius:50%; pointer-events:none; background:radial-gradient(circle, rgba(255,220,100,.22) 0%, transparent 65%); }
  .mob-logo { position:relative; z-index:1; display:flex; align-items:center; gap:10px; }
  .mob-logo-text { display:flex; flex-direction:column; }
  .mob-logo-title { font-family:'Righteous',cursive; font-size:17px; color:#fff; line-height:1.1; }
  .mob-logo-sub { font-size:9px; color:rgba(255,240,200,.8); letter-spacing:1.5px; text-transform:uppercase; font-weight:600; }
  .mob-icons { position:relative; z-index:1; display:flex; align-items:center; gap:8px; }
  .mob-icon-btn { position:relative; width:42px; height:42px; display:flex; align-items:center; justify-content:center; border-radius:11px; background:rgba(255,255,255,.18); border:1.5px solid rgba(255,255,255,.3); color:#fff; font-size:18px; cursor:pointer; }
  .mob-badge { position:absolute; top:-5px; right:-5px; min-width:18px; height:18px; padding:0 4px; background:#fff; color:#e8650a; border-radius:9px; display:flex; align-items:center; justify-content:center; font-size:10px; font-weight:700; box-shadow:0 2px 6px rgba(0,0,0,.2); }

  /* Page */
  .page { padding:10px 10px 20px; }

  /* Store card */
  .store-card { padding:14px 14px 0; border-radius:14px; margin-bottom:12px; }
  .store-card::after { display:none; }
  .store-head { flex-direction:row; align-items:flex-start; gap:12px; flex-wrap:wrap; }
  .store-logo { width:56px; height:56px; min-width:56px; font-size:22px; border-radius:13px; }
  .store-info { flex:1; min-width:0; }
  .store-kantin { font-size:11px; }
  .store-name { font-size:18px; margin-bottom:6px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
  .store-meta { flex-direction:column; gap:4px; font-size:12px; }
  .store-right { flex-direction:column; width:100%; gap:8px; }
  .store-stats { display:grid; grid-template-columns:repeat(3,1fr); width:100%; gap:8px; padding-top:0; }
  .stat-box { min-width:0; padding:10px 8px; }
  .stat-box .s-icon { font-size:18px; margin-bottom:3px; }
  .stat-box .s-num { font-size:18px; }
  .stat-box .s-lbl { font-size:11px; }
  .chat-btn { width:100%; justify-content:center; padding:10px; font-size:13px; border-radius:10px; }

  /* Tabs */
  .store-tabs { display:flex; flex-wrap:nowrap; overflow-x:auto; -webkit-overflow-scrolling:touch; scrollbar-width:none; gap:8px; padding:12px 0 14px; border-top:1px solid rgba(232,113,74,.1); margin-top:12px; }
  .store-tabs::-webkit-scrollbar { display:none; }
  .tab-btn { flex:0 0 auto; padding:8px 16px; font-size:12px; white-space:nowrap; }

  /* Content layout */
  .content-wrap { display:block; }
  .cat-sidebar { display:none; }
  .products-panel { width:100%; min-width:0; }

  /* Sort bar */
  .sort-bar { display:flex; flex-wrap:nowrap; overflow-x:auto; -webkit-overflow-scrolling:touch; scrollbar-width:none; gap:6px; padding:10px 12px; margin-bottom:10px; border-radius:12px; }
  .sort-bar::-webkit-scrollbar { display:none; }
  .sort-lbl { flex-shrink:0; font-size:12px; white-space:nowrap; }
  .sort-btn { flex:0 0 auto; font-size:12px; padding:6px 14px; white-space:nowrap; }
  .sort-select { flex:0 0 auto; font-size:12px; padding:6px 24px 6px 12px; }
  .sort-right { display:none; }
  .sort-search { display:none; }

  /* Product grid — 2 kolom */
  .product-grid { grid-template-columns:repeat(2,minmax(0,1fr)); gap:8px; }
  .product-card { border-radius:12px; }
  .card-img { padding-top:70%; }
  .card-placeholder .ph-icon { font-size:28px; }
  .card-placeholder .ph-txt  { font-size:10px; }
  .card-body { padding:9px 10px 10px; }
  .card-meta { font-size:10px; margin-bottom:3px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
  .card-name { font-size:12px; line-height:1.3; margin-bottom:5px; }
  .card-sub { gap:4px; margin-bottom:6px; }
  .card-rating { font-size:10.5px; }
  .card-terjual { font-size:10px; }
  .card-price { font-size:13px; }
  .add-btn { width:28px; height:28px; min-width:28px; font-size:17px; border-radius:8px; }

  /* Toast */
  #cart-toast { bottom:82px; }

  /* Mobile bottom nav */
  .mob-bottom-nav {
    display:flex; position:fixed; bottom:0; left:0; right:0; z-index:300;
    background:#fff; border-top:1px solid #f0e8e0;
    box-shadow:0 -4px 20px rgba(58,26,0,.08);
    padding-bottom:env(safe-area-inset-bottom,0); height:60px;
  }
  .mob-bottom-spacer { display:block; height:70px; }
  .mob-nav-link { flex:1; display:flex; justify-content:center; align-items:center; text-decoration:none; color:inherit; }
  .mob-nav-item { display:flex; flex-direction:column; align-items:center; justify-content:center; padding:8px 0 6px; gap:3px; font-size:10px; font-weight:700; color:#bbb; cursor:pointer; transition:color .18s; width:100%; }
  .mob-nav-item i { font-size:20px; }
  .mob-nav-item.active, .mob-nav-item:hover { color:#e8714a; }
  .mob-nav-search { flex:1; display:flex; flex-direction:column; align-items:center; justify-content:flex-end; padding-bottom:4px; gap:2px; font-size:10px; font-weight:700; color:#e8714a; cursor:pointer; }
  .mob-nav-search-btn { width:50px; height:50px; border-radius:50%; background:linear-gradient(135deg,#e8714a,#f5a87b); display:flex; align-items:center; justify-content:center; color:#fff; font-size:20px; box-shadow:0 4px 16px rgba(232,113,74,.45); border:3px solid #fff; margin-top:-18px; transition:transform .18s; }
  .mob-nav-search-btn:active { transform:scale(.93); }
}

/* ============================================
   RESPONSIVE — SMALL MOBILE ≤400px
   ============================================ */
@media (max-width:400px) {
  .store-name  { font-size:16px; }
  .card-name   { font-size:11px; }
  .card-price  { font-size:12px; }
  .product-grid { gap:6px; }
  .card-body   { padding:8px; }
  .tab-btn     { padding:7px 12px; font-size:11px; }
}

/* ── Info row (rating | terjual | stok) ── */
.card-info-row {
  display: flex;
  align-items: center;
  gap: 0;
  background: #fff8f4;
  border-radius: 8px;
  border: 1px solid #ffe8d6;
  padding: 6px 8px;
  margin-bottom: 10px;
}
.card-info-box {
  flex: 1;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 2px;
}
.card-info-val {
  font-size: 11px;
  font-weight: 700;
  color: #3a1a00;
  display: flex;
  align-items: center;
  gap: 3px;
}
.card-info-lbl {
  font-size: 9.5px;
  color: #bbb;
  font-weight: 600;
}
.card-info-sep {
  width: 1px;
  height: 28px;
  background: #ffe0c8;
  flex-shrink: 0;
}
.card-info-habis .card-info-val {
  color: #dc2626;
}

/* ── Overlay habis di gambar ── */
.card-habis-overlay {
  position: absolute;
  inset: 0;
  background: rgba(0,0,0,0.45);
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: inherit;
}
.card-habis-overlay span {
  background: #dc2626;
  color: #fff;
  font-size: 12px;
  font-weight: 800;
  padding: 6px 16px;
  border-radius: 50px;
  display: flex;
  align-items: center;
  gap: 6px;
}

/* ── Card out of stock ── */
.card-out-of-stock {
  opacity: 0.75;
  cursor: default;
}
.card-out-of-stock .card-name,
.card-out-of-stock .card-price {
  color: #aaa;
}

/* ── Tombol habis pengganti add-btn ── */
.add-btn-habis {
  flex-shrink: 0;
  width: 32px; height: 32px;
  display: flex; align-items: center; justify-content: center;
  background: #f5f5f5;
  border: 2px solid #e0e0e0;
  border-radius: 9px;
  color: #ccc;
  font-size: 13px;
  cursor: not-allowed;
}
</style>
</head>
<body>

<!-- ════ TOPBAR ════ -->
<div class="topbar">
  <div class="top-left">
    <a href="user.php">Home</a>
    <span class="sep">|</span>
    <a href="menu.php">Menu</a>
    <span class="sep">|</span>
    <a href="pesan.php?tab=pesanan">Pesanan</a>
  </div>
  <div class="top-right">
    <a href="pesan.php?tab=notif"><i class="fa-regular fa-bell"></i> Notifikasi</a>
    <a href=""><i class="fa-regular fa-circle-question"></i> Bantuan</a>
    <div class="lang">
      <i class="fa-solid fa-globe"></i><span>Bahasa Indonesia</span>
      <i class="fa-solid fa-chevron-down"></i>
      <div class="lang-drop">
        <a href="#" class="active"><i class="fa-solid fa-check" style="color:#e8650a;font-size:11px"></i> Bahasa Indonesia</a>
        <a href="#">English</a>
      </div>
    </div>
    <div class="profile">
      <?php if (!empty($pp_user)): ?>
        <img src="../gambar/profil/<?= htmlspecialchars($pp_user) ?>"
             alt="<?= htmlspecialchars($username_user) ?>"
             onerror="this.src='https://ui-avatars.com/api/?name=<?= urlencode($username_user) ?>&background=e8650a&color=fff&size=32'; this.onerror=null;">
      <?php else: ?>
        <img src="https://ui-avatars.com/api/?name=<?= urlencode($username_user) ?>&background=e8650a&color=fff&size=32"
             alt="<?= htmlspecialchars($username_user) ?>">
      <?php endif; ?>
      <span><?= htmlspecialchars($username_user) ?></span>
      <div class="profile-drop">
        <a href="pesan.php?tab=akun"><i class="fa-regular fa-user"></i> Akun Saya</a>
        <div class="drop-line"></div>
        <a href="logout.php" class="logout"><i class="fa-solid fa-right-from-bracket"></i> Log Out</a>
      </div>
    </div>
  </div>
</div>

<!-- ════ NAVBAR DESKTOP ════ -->
<nav class="navbar orange-bg">
  <div class="navbar-texture"></div>
  <div class="orb1"></div><div class="orb2"></div>
  <div class="logo">
    <div class="logo-img-wrap"><img src="../gambar/logo.png" alt="Logo"></div>
    <div class="logo-text">
      <span class="logo-title">E-Canteen</span>
      <span class="logo-sub">Esemkita</span>
    </div>
  </div>
  <div class="search-wrap">
    <div class="search-box">
      <input type="text" id="searchDesktop" placeholder="Cari di <?= htmlspecialchars($kantin['nama_kantin']) ?>…"/>
      <div class="search-scope">
        <select id="searchScope">
          <option value="toko">Di toko ini</option>
          <option value="semua">Semua toko</option>
        </select>
      </div>
      <button id="searchDesktopBtn"><i class="fa-solid fa-magnifying-glass"></i></button>
    </div>
  </div>
  <div class="icon-actions">
    <a href="keranjang.php" style="text-decoration:none;color:inherit">
      <div class="icon-btn" title="Keranjang">
        <i class="fa-solid fa-bag-shopping"></i>
        <span class="icon-badge" id="cartBadge" style="<?= $totalKeranjang>0?'':'display:none' ?>"><?= $totalKeranjang?:'' ?></span>
      </div>
    </a>
    <button onclick="openChatPopup()" class="icon-btn notif-chat-btn" style="background:none;border:none;cursor:pointer;">
      <i class="fa-solid fa-comment-dots"></i>
      <span class="chat-badge" id="chatBadgeNav" style="display:none"></span>
    </button>
  </div>
</nav>

<!-- ════ MOBILE TOP NAV ════ -->
<div class="mobile-nav">
  <div class="mob-top orange-bg">
    <div class="mob-orb"></div>
    <div class="mob-logo">
      <img src="../gambar/logo.png" alt="Logo" style="height:36px">
      <div class="mob-logo-text">
        <span class="mob-logo-title">E-Canteen</span>
        <span class="mob-logo-sub">Esemkita</span>
      </div>
    </div>
    <div class="mob-icons">
      <button onclick="openChatPopup()" class="icon-btn notif-chat-btn" style="background:none;border:none;cursor:pointer;width:42px;height:42px;">
        <i class="fa-solid fa-comment-dots"></i>
        <span class="chat-badge" id="chatBadgeMob" style="display:none"></span>
      </button>
      <a href="keranjang.php" style="text-decoration:none;color:inherit">
        <div class="mob-icon-btn">
          <i class="fa-solid fa-bag-shopping"></i>
          <?php if ($totalKeranjang>0): ?>
            <span class="mob-badge"><?= $totalKeranjang ?></span>
          <?php endif; ?>
        </div>
      </a>
    </div>
  </div>
</div>

<!-- ════ PAGE ════ -->
<div class="page">

  <!-- ══ STORE CARD — PROFIL PENJUAL ══ -->
  <div class="store-card">
    <div class="store-head">

      <!-- Logo/Avatar -->
      <div class="store-logo">
        <?php if (!empty($fotoPenjual)): ?>
          <img src="../admin/uploads/<?= htmlspecialchars($fotoPenjual) ?>"
               alt="<?= htmlspecialchars($namaPenjual) ?>"
               onerror="this.src='https://ui-avatars.com/api/?name=<?= urlencode($namaPenjual) ?>&background=e8714a&color=fff&size=80'; this.onerror=null;">
        <?php else: ?>
          <img src="https://ui-avatars.com/api/?name=<?= urlencode($namaPenjual) ?>&background=e8714a&color=fff&size=80"
               alt="<?= htmlspecialchars($namaPenjual) ?>">
        <?php endif; ?>
      </div>

      <!-- Info -->
      <div class="store-info">
        <div class="store-kantin">
          <i class="fa-solid fa-store" style="font-size:11px"></i>
          <?= htmlspecialchars($kantin['nama_kantin']) ?>
        </div>
        <div class="store-name"><?= htmlspecialchars($namaPenjual) ?></div>
        <div class="store-meta">
          <div class="store-meta-item">
            <?php if ($isOpen): ?>
              <span class="dot-live"></span> Sedang Buka
            <?php else: ?>
              <span class="dot-live dot-closed"></span> Tutup
            <?php endif; ?>
          </div>
          <div class="store-meta-item">
            <i class="fa-regular fa-clock"></i> 07.00 – 15.00
          </div>
          <div class="store-meta-item">
            <i class="fa-solid fa-location-dot"></i> Esemkita, Area Kantin
          </div>
          <div class="store-meta-item">
            <i class="fa-solid fa-star" style="color:#f59e0b"></i>
            <?= $avgRating ?> rating · <?= $totalPenilaian ?> ulasan
          </div>
        </div>
      </div>

      <!-- Kanan: stats + chat -->
      <div class="store-right">
        <div class="store-stats">
          <div class="stat-box">
            <span class="s-icon">⭐</span>
            <div class="s-num"><?= $avgRating ?></div>
            <div class="s-lbl">Rating</div>
          </div>
          <div class="stat-box">
            <span class="s-icon">📋</span>
            <div class="s-num"><?= $totalMenu ?></div>
            <div class="s-lbl">Menu</div>
          </div>
          <div class="stat-box">
            <span class="s-icon">🛍️</span>
            <div class="s-num"><?= $totalTerjual >= 1000 ? number_format($totalTerjual/1000,1).'rb' : $totalTerjual ?></div>
            <div class="s-lbl">Terjual</div>
          </div>
        </div>
        <button class="chat-btn"
          onclick="chatToko(<?= $idPenjual ?>,'<?= htmlspecialchars($namaPenjual,ENT_QUOTES) ?>','<?= htmlspecialchars($kantin['nama_kantin'],ENT_QUOTES) ?>','<?= htmlspecialchars($fotoPenjual,ENT_QUOTES) ?>')">
          <i class="fa-regular fa-comment-dots"></i>
          Chat Penjual
        </button>
      </div>

    </div><!-- /store-head -->

    <!-- Category Tabs -->
    <div class="store-tabs" id="storeTabs">
      <button class="tab-btn active" data-cat="all">
        <i class="fa-solid fa-border-all"></i> Semua
        <span class="tab-badge"><?= $countAll ?></span>
      </button>
      <?php if ($countMak>0): ?>
      <button class="tab-btn" data-cat="makanan">
        <i class="fa-solid fa-bowl-food"></i> Makanan
        <span class="tab-badge"><?= $countMak ?></span>
      </button>
      <?php endif; ?>
      <?php if ($countMin>0): ?>
      <button class="tab-btn" data-cat="minuman">
        <i class="fa-solid fa-mug-hot"></i> Minuman
        <span class="tab-badge"><?= $countMin ?></span>
      </button>
      <?php endif; ?>
      <?php if ($countSnk>0): ?>
      <button class="tab-btn" data-cat="snack">
        <i class="fa-solid fa-cookie-bite"></i> Snack
        <span class="tab-badge"><?= $countSnk ?></span>
      </button>
      <?php endif; ?>
    </div>

  </div><!-- /store-card -->

  <!-- ══ CONTENT ══ -->
  <div class="content-wrap">

    <!-- Category Sidebar -->
    <aside class="cat-sidebar">
      <div class="cat-title"><i class="fa-solid fa-layer-group"></i> Kategori</div>
      <ul class="cat-list" id="catSidebar">
        <li class="cat-item active" data-cat="all">
          <i class="fa-solid fa-border-all"></i> Semua
          <span class="cat-count"><?= $countAll ?></span>
        </li>
        <li class="cat-sep"></li>
        <?php if ($countMak>0): ?>
        <li class="cat-item" data-cat="makanan">
          <i class="fa-solid fa-bowl-food"></i> Makanan
          <span class="cat-count"><?= $countMak ?></span>
        </li>
        <?php endif; ?>
        <?php if ($countMin>0): ?>
        <li class="cat-item" data-cat="minuman">
          <i class="fa-solid fa-mug-hot"></i> Minuman
          <span class="cat-count"><?= $countMin ?></span>
        </li>
        <?php endif; ?>
        <?php if ($countSnk>0): ?>
        <li class="cat-item" data-cat="snack">
          <i class="fa-solid fa-cookie-bite"></i> Snack
          <span class="cat-count"><?= $countSnk ?></span>
        </li>
        <?php endif; ?>
      </ul>
    </aside>

    <!-- Products Panel -->
    <div class="products-panel">

      <!-- Sort Bar -->
      <div class="sort-bar">
        <!-- Search -->
        <div class="sort-search">
          <i class="fa-solid fa-magnifying-glass"></i>
          <input type="text" id="searchInput"
                 placeholder="Cari menu di <?= htmlspecialchars($kantin['nama_kantin']) ?>…">
        </div>
        <span class="sort-lbl">Urut:</span>
        <button class="sort-btn active" data-sort="terlaris">
          <i class="fa-solid fa-fire" style="color:#ff6b2b;margin-right:4px"></i>Terlaris
        </button>
        <button class="sort-btn" data-sort="terbaru">
          <i class="fa-solid fa-clock-rotate-left" style="margin-right:4px"></i>Terbaru
        </button>
        <button class="sort-btn" data-sort="rating">
          <i class="fa-solid fa-star" style="color:#f59e0b;margin-right:4px"></i>Rating
        </button>
        <select class="sort-select" id="priceSort">
          <option value="">💰 Harga</option>
          <option value="asc">Terendah</option>
          <option value="desc">Tertinggi</option>
        </select>
        <div class="sort-right">
          <span class="pg-info" id="pgInfo">1 / 1</span>
          <button class="pg-btn" id="prevPage" disabled>
            <i class="fa-solid fa-chevron-left"></i>
          </button>
          <button class="pg-btn" id="nextPage" disabled>
            <i class="fa-solid fa-chevron-right"></i>
          </button>
        </div>
      </div>

      <!-- Grid -->
      <div class="product-grid" id="productGrid"></div>

      <!-- Mobile pagination -->
      <div style="display:flex;align-items:center;justify-content:center;gap:10px;margin-top:20px;padding:0 4px" id="mobPagination">
        <button class="pg-btn" id="prevPageMob" disabled style="width:36px;height:36px;border-radius:50%">
          <i class="fa-solid fa-chevron-left"></i>
        </button>
        <span class="pg-info" id="pgInfoMob" style="font-size:13px;font-weight:700;color:#aaa">1 / 1</span>
        <button class="pg-btn" id="nextPageMob" disabled style="width:36px;height:36px;border-radius:50%">
          <i class="fa-solid fa-chevron-right"></i>
        </button>
      </div>

    </div><!-- /products-panel -->
  </div><!-- /content-wrap -->

  <div class="mob-bottom-spacer"></div>
</div><!-- /page -->

<!-- ════ MOBILE BOTTOM NAV ════ -->
<nav class="mob-bottom-nav">
  <a href="user.php" class="mob-nav-link">
    <div class="mob-nav-item"><i class="fa-solid fa-house"></i><span>Home</span></div>
  </a>
  <a href="menu.php" class="mob-nav-link">
    <div class="mob-nav-item"><i class="fa-solid fa-bowl-food"></i><span>Menu</span></div>
  </a>
  <div class="mob-nav-search"
       onclick="document.getElementById('searchInput').focus();window.scrollTo({top:200,behavior:'smooth'})">
    <div class="mob-nav-search-btn"><i class="fa-solid fa-magnifying-glass"></i></div>
    <span>Cari</span>
  </div>
  <a href="pesan.php?tab=pesanan" class="mob-nav-link">
    <div class="mob-nav-item"><i class="fa-solid fa-bag-shopping"></i><span>Pesanan</span></div>
  </a>
  <a href="pesan.php?tab=akun" class="mob-nav-link">
    <div class="mob-nav-item"><i class="fa-regular fa-user"></i><span>Akun</span></div>
  </a>
</nav>

<div id="cart-toast"></div>

<!-- ══ DATA & LOGIC JS ══ -->
<script>
const PRODUCTS = <?= json_encode(array_map(function($m){
    $raw = strtolower(trim($m['kategori'] ?? ''));
    if($raw==='minuman') $cat='minuman';
    elseif(in_array($raw,['snack','camilan','cemilan'])) $cat='snack';
    else $cat='makanan';
    return[
        'id'     =>(int)$m['id'],
        'name'   =>(string)$m['nama_makanan'],
        'price'  =>(int)$m['harga'],
        'cat'    =>$cat,
        'foto'   =>(string)($m['foto']??''),
        'terjual'=>(int)$m['terjual'],
        'stok'   =>(int)$m['stok'],
        'rating' =>(float)$m['rata_bintang'],
        'ulasan' =>(int)$m['total_ulasan'],
    ];
},$semuaMenu),JSON_UNESCAPED_UNICODE) ?>;

let filterCat='all', sortMode='terlaris', priceSort='', searchQ='', page=1;
const PER = 20;

const rp = n => 'Rp' + parseInt(n).toLocaleString('id-ID');
const catIcon  = {makanan:'fa-bowl-food', minuman:'fa-mug-hot', snack:'fa-cookie-bite'};
const catLabel = {makanan:'Makanan', minuman:'Minuman', snack:'Snack'};
const esc = s => String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');

function getList(){
  let d = [...PRODUCTS];
  if(filterCat !== 'all') d = d.filter(p => p.cat === filterCat);
  if(searchQ) d = d.filter(p => p.name.toLowerCase().includes(searchQ));
  if(priceSort === 'asc')        d.sort((a,b) => a.price - b.price);
  else if(priceSort === 'desc')  d.sort((a,b) => b.price - a.price);
  else if(sortMode === 'terlaris') d.sort((a,b) => b.terjual - a.terjual);
  else if(sortMode === 'terbaru')  d.reverse();
  else if(sortMode === 'rating')   d.sort((a,b) => b.rating - a.rating);
  return d;
}
function render(){
  const list  = getList();
  const total = list.length;
  const pages = Math.max(1, Math.ceil(total / PER));
  if(page > pages) page = 1;

  ['pgInfo','pgInfoMob'].forEach(id => {
    const el = document.getElementById(id);
    if(el) el.textContent = page + ' / ' + pages;
  });
  ['prevPage','prevPageMob'].forEach(id => {
    const el = document.getElementById(id);
    if(el) el.disabled = page <= 1;
  });
  ['nextPage','nextPageMob'].forEach(id => {
    const el = document.getElementById(id);
    if(el) el.disabled = page >= pages;
  });

  const slice = list.slice((page-1)*PER, page*PER);
  const grid  = document.getElementById('productGrid');

  if(!slice.length){
    grid.innerHTML = '<div class="empty"><i class="fa-regular fa-face-sad-tear"></i>Menu tidak ditemukan</div>';
    return;
  }

  grid.innerHTML = slice.map(function(p) {
    var img = p.foto
      ? '<img src="../penjual/uploads/' + p.foto + '" alt="' + esc(p.name) + '" loading="lazy" onerror="this.onerror=null;this.parentElement.innerHTML=\'<div class=card-placeholder><div class=ph-icon>&#x1F37D;&#xFE0F;</div><div class=ph-txt>Foto tidak tersedia</div></div>\'">'
      : '<div class="card-placeholder"><div class="ph-icon"><i class="fa-solid ' + (catIcon[p.cat]||'fa-utensils') + '"></i></div><div class="ph-txt">Belum ada foto</div></div>';

    var stars = p.rating > 0
      ? '<span class="card-rating"><i class="fa-solid fa-star" style="font-size:10px"></i> ' + p.rating.toFixed(1) + '</span> <span class="card-terjual">· ' + p.ulasan + ' ulasan</span>'
      : '<span class="card-terjual">Belum ada ulasan</span>';

    var stokBadge = (p.stok > 0 && p.stok <= 10)
      ? '<div class="card-stok-badge">Sisa ' + p.stok + '</div>' : '';

    var habisOverlay = p.stok === 0
      ? '<div class="card-habis-overlay"><span><i class="fa-solid fa-ban"></i> Habis</span></div>' : '';

    var stokVal  = p.stok === 0 ? 'Habis' : p.stok;
    var stokCls  = p.stok === 0 ? 'card-info-habis' : '';
    var ratingVal = p.rating > 0 ? p.rating.toFixed(1) : '0.0';

    var infoRow = '<div class="card-info-row">'
      + '<div class="card-info-box">'
      +   '<span class="card-info-val"><i class="fa-solid fa-star" style="color:#f59e0b;font-size:9px"></i> ' + ratingVal + '</span>'
      +   '<span class="card-info-lbl">Rating</span>'
      + '</div>'
      + '<div class="card-info-sep"></div>'
      + '<div class="card-info-box">'
      +   '<span class="card-info-val"><i class="fa-solid fa-bag-shopping" style="font-size:9px"></i> ' + p.terjual + '</span>'
      +   '<span class="card-info-lbl">Terjual</span>'
      + '</div>'
      + '<div class="card-info-sep"></div>'
      + '<div class="card-info-box ' + stokCls + '">'
      +   '<span class="card-info-val"><i class="fa-solid fa-box" style="font-size:9px"></i> ' + stokVal + '</span>'
      +   '<span class="card-info-lbl">Stok</span>'
      + '</div>'
      + '</div>';

    var actionBtn = p.stok > 0
      ? '<button class="add-btn" onclick="event.stopPropagation();addCart(' + p.id + ')" title="Tambah ke keranjang">+</button>'
      : '<span class="add-btn-habis"><i class="fa-solid fa-ban"></i></span>';

    var cardCls     = p.stok === 0 ? 'product-card card-out-of-stock' : 'product-card';
    var onclickAttr = p.stok > 0  ? 'onclick="goDetail(' + p.id + ')"' : '';

    return '<div class="' + cardCls + '" ' + onclickAttr + '>'
      + '<div class="card-img">'
      +   img
      +   '<div class="card-cat-badge"><i class="fa-solid ' + (catIcon[p.cat]||'fa-utensils') + '" style="font-size:9px"></i> ' + (catLabel[p.cat]||p.cat) + '</div>'
      +   stokBadge
      +   habisOverlay
      + '</div>'
      + '<div class="card-body">'
      +   '<div class="card-name">' + esc(p.name) + '</div>'
      +   '<div class="card-sub">' + stars + '</div>'
      +   infoRow
      +   '<div class="card-foot">'
      +     '<div class="card-price">' + rp(p.price) + '</div>'
      +     actionBtn
      +   '</div>'
      + '</div>'
      + '</div>';
  }).join('');
}

function goDetail(id){ window.location.href = 'klik.php?id=' + id; }

/* ── Category Tabs (store-card) ── */
document.querySelectorAll('#storeTabs .tab-btn').forEach(btn => {
  btn.addEventListener('click', () => {
    document.querySelectorAll('#storeTabs .tab-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    filterCat = btn.dataset.cat; page = 1;
    syncSidebar(); render();
  });
});

/* ── Category Sidebar ── */
document.querySelectorAll('#catSidebar .cat-item').forEach(item => {
  item.addEventListener('click', () => {
    document.querySelectorAll('#catSidebar .cat-item').forEach(i => i.classList.remove('active'));
    item.classList.add('active');
    filterCat = item.dataset.cat; page = 1;
    syncTabs(); render();
  });
});

function syncSidebar(){
  document.querySelectorAll('#catSidebar .cat-item').forEach(i => i.classList.remove('active'));
  const m = document.querySelector(`#catSidebar .cat-item[data-cat="${filterCat}"]`);
  if(m) m.classList.add('active');
}
function syncTabs(){
  document.querySelectorAll('#storeTabs .tab-btn').forEach(b => b.classList.remove('active'));
  const m = document.querySelector(`#storeTabs .tab-btn[data-cat="${filterCat}"]`);
  if(m) m.classList.add('active');
}

/* ── Sort Buttons ── */
document.querySelectorAll('.sort-btn').forEach(btn => {
  btn.addEventListener('click', () => {
    document.querySelectorAll('.sort-btn').forEach(c => c.classList.remove('active'));
    btn.classList.add('active');
    sortMode = btn.dataset.sort; priceSort = '';
    document.getElementById('priceSort').value = '';
    page = 1; render();
  });
});
document.getElementById('priceSort').addEventListener('change', function(){
  priceSort = this.value;
  if(priceSort) document.querySelectorAll('.sort-btn').forEach(c => c.classList.remove('active'));
  page = 1; render();
});

/* ── Search ── */
document.getElementById('searchInput').addEventListener('input', function(){
  searchQ = this.value.toLowerCase().trim(); page = 1; render();
});
document.getElementById('searchDesktop').addEventListener('input', function(){
  if(document.getElementById('searchScope').value === 'toko'){
    searchQ = this.value.toLowerCase().trim(); page = 1; render();
  }
});
document.getElementById('searchDesktopBtn').addEventListener('click', function(){
  const q = document.getElementById('searchDesktop').value.trim();
  if(document.getElementById('searchScope').value === 'semua' && q)
    window.location.href = 'search.php?q=' + encodeURIComponent(q);
});

/* ── Pagination ── */
['prevPage','prevPageMob'].forEach(id => {
  document.getElementById(id).addEventListener('click', () => {
    if(page > 1){ page--; render(); window.scrollTo({top:200,behavior:'smooth'}); }
  });
});
['nextPage','nextPageMob'].forEach(id => {
  document.getElementById(id).addEventListener('click', () => {
    if(page < Math.ceil(getList().length / PER)){ page++; render(); window.scrollTo({top:200,behavior:'smooth'}); }
  });
});

/* ── Add to Cart ── */
function addCart(id){
  const p = PRODUCTS.find(x => x.id === id); if(!p) return;
  fetch('tambah_keranjang.php', {
    method: 'POST',
    headers: {'Content-Type':'application/x-www-form-urlencoded'},
    body: 'id_menu=' + id + '&jumlah=1'
  })
  .then(r => r.json())
  .then(data => {
    if(data.status === 'success'){
      const el = document.getElementById('cartBadge');
      if(el){ el.textContent = data.total_keranjang; el.style.display = data.total_keranjang > 0 ? '' : 'none'; el.classList.add('badge-bounce'); setTimeout(() => el.classList.remove('badge-bounce'), 500); }
      toast('✅ ' + p.name + ' ditambahkan!');
    } else toast('❌ ' + (data.message || 'Gagal'));
  })
  .catch(() => toast('❌ Gagal terhubung'));
}

/* ── Toast ── */
function toast(msg){
  const t = document.getElementById('cart-toast');
  t.textContent = msg; t.classList.add('show');
  clearTimeout(t._t); t._t = setTimeout(() => t.classList.remove('show'), 2600);
}

/* ── Chat Badge ── */
async function loadChatBadge(){
  try{
    const r = await fetch('chat_api.php?action=jumlah_belum_baca');
    const d = await r.json();
    document.querySelectorAll('.chat-badge').forEach(b => {
      b.style.display = d.belum_baca > 0 ? 'flex' : 'none';
      b.textContent   = d.belum_baca || '';
    });
  }catch(e){}
}
loadChatBadge(); setInterval(loadChatBadge, 10000);

/* ── Chat Toko ── */
function chatToko(idPenjual, namaPenjual, namaKantin, fotoPenjual){
  if(!idPenjual){ alert('Penjual tidak ditemukan.'); return; }
  openChatPopup();
  const tryOpen = n => {
    if(n > 30) return;
    if(typeof _cpOpenRoomByPenjual === 'function')
      _cpOpenRoomByPenjual(idPenjual, namaPenjual, namaKantin, fotoPenjual);
    else setTimeout(() => tryOpen(n+1), 100);
  };
  tryOpen(0);
}

localStorage.setItem('last_page', window.location.href);
render();
</script>

<?php include 'chat_popup.php'; ?>
</body>
</html>