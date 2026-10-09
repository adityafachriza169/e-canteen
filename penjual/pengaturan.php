<?php
// =============================================
// SESSION & AUTH CHECK
// =============================================
ob_start();
session_start();

if (!isset($_SESSION['username']) || (int)($_SESSION['role'] ?? 0) !== 1) {
    header('Location: ../users/login.php');
    exit;
}

// =============================================
// HANDLE POST — GANTI PASSWORD (JSON response)
// =============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pw_lama'])) {

    include "../users/koneksi.php";
    if (!isset($db) && isset($konek)) $db = $konek;

    ob_clean();
    header('Content-Type: application/json');

    $pw_lama  = trim($_POST['pw_lama']  ?? '');
    $pw_baru  = $_POST['pw_baru']  ?? '';
    $pw_ulang = $_POST['pw_ulang'] ?? '';

    // Validasi server-side
    if (!$pw_lama || !$pw_baru || !$pw_ulang) {
        echo json_encode(['success' => false, 'message' => 'Semua field wajib diisi.']);
        exit;
    }
    if (strlen($pw_baru) < 6) {
        echo json_encode(['success' => false, 'message' => 'Password baru minimal 6 karakter.']);
        exit;
    }
    if ($pw_baru !== $pw_ulang) {
        echo json_encode(['success' => false, 'message' => 'Password baru tidak cocok.']);
        exit;
    }

    // Ambil password lama dari DB
    $ses_username = $_SESSION['username'];
    $stmt = mysqli_prepare($db, "SELECT password FROM penjual WHERE username = ? LIMIT 1");
    mysqli_stmt_bind_param($stmt, "s", $ses_username);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);

    if (!$row) {
        echo json_encode(['success' => false, 'message' => 'Akun tidak ditemukan.']);
        exit;
    }

    // Cek password lama — pakai MD5 (sama dengan proses.php)
    $pw_db = $row['password'];
    $valid = ($pw_db === md5($pw_lama));

    if (!$valid) {
        echo json_encode(['success' => false, 'message' => 'Password lama tidak sesuai.']);
        exit;
    }

    // Simpan password baru pakai MD5 (sama dengan proses.php)
    $pw_hash = md5($pw_baru);
    $now     = date('Y-m-d H:i:s');

    $stmt2 = mysqli_prepare($db, "UPDATE penjual SET password = ?, update_pw_at = ? WHERE username = ?");
    mysqli_stmt_bind_param($stmt2, "sss", $pw_hash, $now, $ses_username);
    $ok = mysqli_stmt_execute($stmt2);
    mysqli_stmt_close($stmt2);

    if ($ok) {
        echo json_encode(['success' => true, 'update_pw_at' => $now]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Gagal menyimpan ke database.']);
    }
    exit;
}

// =============================================
// KONEKSI DATABASE
// =============================================
include "../users/koneksi.php";
if (!isset($db) && isset($konek)) $db = $konek;

// =============================================
// AMBIL DATA PENJUAL DARI SESSION USERNAME
// =============================================
$ses_username = $_SESSION['username'];

$stmt_p = mysqli_prepare($db, "
    SELECT p.id_penjual, p.id_kantin, p.nama, p.username, p.profil,
           p.update_pw_at,
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

$nama_penjual = $penjual_data['nama']        ?? 'Penjual';
$nama_kantin  = $penjual_data['nama_kantin'] ?? 'Kantin';
$id_kantin    = (int)($penjual_data['id_kantin'] ?? 0);
$update_pw_at = $penjual_data['update_pw_at'] ?? null;

// Format tanggal update_pw_at untuk ditampilkan
$update_pw_at_fmt = '';
if ($update_pw_at) {
    $dt = new DateTime($update_pw_at);
    $bulan = ['','Jan','Feb','Mar','Apr','Mei','Jun','Jul','Ags','Sep','Okt','Nov','Des'];
    $update_pw_at_fmt = $dt->format('d') . ' ' . $bulan[(int)$dt->format('n')] . ' ' . $dt->format('Y, H:i');
}

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
  <title>E-Canteen – Pengaturan</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css"/>
  <link href="https://fonts.googleapis.com/css2?family=Righteous&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <style>
/* =========================================
   RESET & VARS
========================================= */
*, *::before, *::after { margin:0; padding:0; box-sizing:border-box; }
:root {
  --orange:              #E8714A;
  --orange-deep:         #C84B11;
  --orange-pale:         #FEF0E0;
  --sidebar-bg:          #FDE8DC;
  --sidebar-active-bg:   #FFDABF;
  --sidebar-active-text: #E1715D;
  --sidebar-text:        #E8714A;
  --topbar-bg:           #b84a00;
  --main-bg:             #fdf6f0;
  --card-bg:             #fff;
  --cream:               #FDF6EE;
  --brown-dark:          #3A1A00;
}
a { text-decoration:none; color:inherit; }
body { font-family:'Plus Jakarta Sans',sans-serif; background:var(--cream); color:var(--brown-dark); overflow-x:hidden; }

/* ── TOPBAR ── */
.topbar {
  background:var(--topbar-bg);
  padding:0 52px; height:36px;
  display:flex; align-items:center; justify-content:space-between;
  font-size:12px; color:rgba(255,255,255,.8);
  position:fixed; top:0; left:0; width:100%; z-index:1000;
}
.top-right { display:flex; align-items:center; gap:20px; }
.top-right > a { color:rgba(255,255,255,.75); font-size:12px; display:flex; align-items:center; gap:5px; transition:.2s; }
.top-right > a:hover { color:#ffe0a0; }
.lang { position:relative; display:flex; align-items:center; gap:6px; cursor:pointer; color:rgba(255,255,255,.75); font-size:12px; }
.lang i.fa-chevron-down { font-size:8px; transition:.3s; }
.lang:hover { color:#ffe0a0; }
.lang:hover i.fa-chevron-down { transform:rotate(180deg); }
.lang-drop {
  position:absolute; top:calc(100% + 14px); right:0; width:190px;
  background:#fff; border-radius:10px; box-shadow:0 12px 40px rgba(0,0,0,.2);
  padding:6px 0; opacity:0; visibility:hidden; transform:translateY(8px); transition:.22s; z-index:999;
}
.lang:hover .lang-drop { opacity:1; visibility:visible; transform:translateY(0); }
.lang-drop a { display:flex; align-items:center; gap:10px; padding:9px 16px; color:#444; font-size:13px; transition:.15s; }
.lang-drop a:hover { background:#fff5ee; color:#e8650a; }
.lang-drop .active-lang { color:#e8650a; font-weight:600; }

/* ── NAVBAR ── */
.navbar {
  padding:0 52px; height:90px;
  display:flex; align-items:center; gap:24px;
  position:fixed; top:36px; left:0; width:100%; overflow:hidden; z-index:999;
  background:
    radial-gradient(ellipse 55% 130% at 85% 40%,rgba(255,220,100,.28) 0%,transparent 55%),
    radial-gradient(ellipse 45% 110% at 5% 60%,rgba(200,90,0,.3) 0%,transparent 60%),
    linear-gradient(112deg,#d45500 0%,#e86510 22%,#f57c1a 45%,#f98e20 65%,#fcaa38 82%,#ffc55a 100%);
}
.navbar::after {
  content:''; position:absolute; bottom:0; left:0; right:0; height:2.5px;
  background:linear-gradient(90deg,transparent,rgba(255,200,80,.6) 25%,rgba(255,250,200,.9) 50%,rgba(255,200,80,.6) 75%,transparent);
}
.navbar-texture {
  position:absolute; inset:0;
  background:repeating-linear-gradient(108deg,transparent 0,transparent 58px,rgba(255,255,255,.03) 58px,rgba(255,255,255,.03) 59px);
  pointer-events:none;
}
.orb1 { position:absolute; top:-90px; right:80px; width:280px; height:280px; border-radius:50%; background:radial-gradient(circle,rgba(255,220,100,.25) 0%,transparent 65%); pointer-events:none; }
.orb2 { position:absolute; bottom:-110px; left:220px; width:220px; height:220px; border-radius:50%; background:radial-gradient(circle,rgba(255,140,30,.2) 0%,transparent 65%); pointer-events:none; }

/* ── LOGO ── */
.logo { min-width:185px; position:relative; z-index:1; display:flex; align-items:center; gap:11px; flex-shrink:0; }
.logo-img-wrap { display:flex; align-items:center; }
.logo-img-wrap img { width:50px; height:50px; object-fit:contain; border-radius:50%; }
.logo-text { display:flex; flex-direction:column; }
.logo-title { font-family:'Righteous',cursive; font-size:24px; color:#fff; line-height:1.1; letter-spacing:.5px; }
.logo-sub { font-size:10px; color:rgba(255,240,200,.85); letter-spacing:2px; text-transform:uppercase; font-weight:600; }

/* ── SEARCH ── */
.search-wrap { flex:1; position:relative; z-index:1; }
.search-box { display:flex; background:rgba(255,255,255,.97); border-radius:10px; overflow:hidden; height:50px; border:1.5px solid rgba(255,255,255,.9); transition:.25s; box-shadow:0 6px 24px rgba(150,60,0,.2); }
.search-box:focus-within { box-shadow:0 6px 28px rgba(150,60,0,.25),0 0 0 3px rgba(255,170,60,.55); }
.search-box input { flex:1; border:none; outline:none; padding:0 18px; font-size:14px; color:#333; background:transparent; font-family:'Plus Jakarta Sans',sans-serif; }
.search-box input::placeholder { color:#bbb; }
.search-box button { width:52px; border:none; background:linear-gradient(145deg,#f98020,#e06010); color:#fff; cursor:pointer; font-size:16px; display:flex; align-items:center; justify-content:center; }

/* ── ICON ACTIONS ── */
.icon-actions { display:flex; align-items:center; gap:10px; position:relative; z-index:1; flex-shrink:0; }
.icon-btn { position:relative; width:52px; height:52px; display:flex; align-items:center; justify-content:center; color:#fff; font-size:21px; cursor:pointer; border-radius:12px; background:rgba(255,255,255,.15); border:1.5px solid rgba(255,255,255,.3); box-shadow:0 2px 8px rgba(150,60,0,.15); transition:all .22s; }
.icon-btn:hover { background:rgba(255,255,255,.28); transform:translateY(-2px); }
.icon-badge { position:absolute; top:-6px; right:-6px; min-width:20px; height:20px; background:#ef4444; color:#fff; border-radius:10px; display:flex; align-items:center; justify-content:center; font-size:10.5px; font-weight:700; font-family:'Plus Jakarta Sans',sans-serif; box-shadow:0 2px 8px rgba(0,0,0,.18); padding:0 5px; border:1.5px solid rgba(255,150,30,.2); letter-spacing:-.3px; }

/* ── HAMBURGER ── */
.hamburger-btn { display:none; flex-direction:column; justify-content:center; align-items:center; gap:5px; width:42px; height:42px; border:none; background:rgba(255,255,255,.15); border-radius:10px; cursor:pointer; position:relative; z-index:1; flex-shrink:0; transition:background .2s; }
.hamburger-btn span { display:block; width:22px; height:2.5px; background:#fff; border-radius:2px; transition:transform .3s,opacity .2s; }
.hamburger-btn.open span:nth-child(1) { transform:translateY(7.5px) rotate(45deg); }
.hamburger-btn.open span:nth-child(2) { opacity:0; }
.hamburger-btn.open span:nth-child(3) { transform:translateY(-7.5px) rotate(-45deg); }
.hamburger-btn:hover { background:rgba(255,255,255,.25); }

/* ── SIDEBAR OVERLAY ── */
.sidebar-overlay { display:none; position:fixed; inset:0; background:rgba(0,0,0,.5); z-index:1100; backdrop-filter:blur(2px); }
.sidebar-overlay.show { display:block; }

/* ── PAGE WRAPPER ── */
.page-wrapper { display:flex; padding-top:126px; min-height:100vh; }

/* ── SIDEBAR ── */
.sidebar { width:260px; min-width:220px; background:var(--sidebar-bg); border-right:1px solid rgba(232,119,34,.12); padding:28px 0 24px; display:flex; flex-direction:column; position:sticky; top:126px; height:calc(100vh - 126px); overflow-y:auto; flex-shrink:0; }
.sidebar-profile { display:flex; align-items:center; gap:13px; padding:0 22px 22px; border-bottom:1.5px solid rgba(232,119,34,.18); margin-bottom:10px; }
.sidebar-avatar { width:52px; height:52px; border-radius:50%; overflow:hidden; flex-shrink:0; border:2.5px solid var(--orange); background:linear-gradient(135deg,#ffd89b,#e87722); display:flex; align-items:center; justify-content:center; font-size:1.5rem; }
.sidebar-avatar img { width:100%; height:100%; object-fit:cover; display:block; }
.sidebar-profile-info h3 { font-size:.97rem; font-weight:800; color:#222; }
.sidebar-profile-info a { font-size:.78rem; color:#999; display:flex; align-items:center; gap:4px; margin-top:3px; transition:color .2s; }
.sidebar-profile-info a:hover { color:var(--orange); }
.sidebar-nav-area { padding:10px 12px; display:flex; flex-direction:column; gap:2px; }
.sidebar-nav-area a { display:flex; align-items:center; gap:12px; padding:12px 14px; border-radius:10px; color:var(--sidebar-text); font-size:14px; font-weight:700; transition:background .18s,color .18s; }
.sidebar-nav-area a svg { flex-shrink:0; color:var(--sidebar-text); transition:color .18s; }
.sidebar-nav-area a:hover { background:rgba(232,113,74,.12); }
.sidebar-nav-area a.active { background:var(--sidebar-active-bg); color:var(--sidebar-active-text); }
.sidebar-nav-area a.active svg { color:var(--sidebar-active-text); }
.sidebar-divider { height:1px; background:rgba(232,113,74,.15); margin:4px 18px; }
.sidebar-bottom { padding:10px 12px; display:flex; flex-direction:column; gap:2px; }
.sidebar-bottom a { display:flex; align-items:center; gap:12px; padding:12px 14px; border-radius:10px; color:var(--sidebar-text); font-size:14px; font-weight:700; transition:background .18s; }
.sidebar-bottom a svg { flex-shrink:0; color:var(--orange); }
.sidebar-bottom a:hover { background:rgba(232,113,74,.1); }
.sidebar-bottom a.active { background:var(--sidebar-active-bg); color:var(--sidebar-active-text); }
.sidebar-bottom a.active svg { color:var(--sidebar-active-text); }

/* ── MAIN ── */
.main { flex:1; padding:30px 36px; background:var(--main-bg); overflow-x:hidden; display:flex; flex-direction:column; gap:24px; }
.main-title { font-size:1.4rem; font-weight:800; color:var(--brown-dark); }

/* ── SETTINGS GRID ── */
.settings-grid { display:grid; grid-template-columns:repeat(2,1fr); gap:20px; }
.settings-card { background:var(--card-bg); border-radius:16px; border:1.5px solid #f0ddd0; overflow:hidden; transition:box-shadow .2s; }
.settings-card:hover { box-shadow:0 6px 24px rgba(232,100,10,.1); }
.settings-card.full-width { grid-column:1 / -1; }
.settings-card-header { display:flex; align-items:center; gap:14px; padding:18px 20px 14px; border-bottom:1px solid #fdf0e8; }
.header-icon { width:40px; height:40px; border-radius:11px; display:flex; align-items:center; justify-content:center; flex-shrink:0; }
.settings-card-header h3 { font-size:15px; font-weight:800; color:#1a0a00; margin-bottom:2px; }
.settings-card-header p { font-size:12px; color:#a07050; }
.settings-card-body { padding:8px 0; }

/* ── SETTING ROW ── */
.setting-row { display:flex; align-items:center; justify-content:space-between; gap:12px; padding:13px 20px; transition:background .15s; cursor:pointer; }
.setting-row:hover { background:#fffaf6; }
.setting-row-left { display:flex; align-items:center; gap:13px; flex:1; min-width:0; }
.setting-row-icon { width:36px; height:36px; border-radius:10px; background:#f5f5f5; display:flex; align-items:center; justify-content:center; font-size:14px; flex-shrink:0; }
.emoji-icon { font-size:18px; background:#fff5ee; }
.setting-row-text { min-width:0; }
.setting-row-text .label { font-size:13.5px; font-weight:700; color:#1a0a00; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.setting-row-text .sublabel { font-size:11.5px; color:#a07050; margin-top:2px; }
.notif-group-label { padding:10px 20px 4px; font-size:10.5px; font-weight:800; color:#c09070; letter-spacing:.6px; text-transform:uppercase; }

/* ── TOGGLE SWITCH ── */
.toggle-switch { position:relative; display:inline-block; width:44px; height:24px; flex-shrink:0; }
.toggle-switch input { opacity:0; width:0; height:0; }
.toggle-slider { position:absolute; inset:0; background:#e0e0e0; border-radius:24px; transition:.3s; cursor:pointer; }
.toggle-slider::before { content:''; position:absolute; width:18px; height:18px; left:3px; top:3px; background:#fff; border-radius:50%; transition:.3s; box-shadow:0 1px 4px rgba(0,0,0,.2); }
.toggle-switch input:checked + .toggle-slider { background:var(--orange); }
.toggle-switch input:checked + .toggle-slider::before { transform:translateX(20px); }

/* ── LANG ── */
.lang-check { font-size:16px; flex-shrink:0; }
.active-lang-row { background:#fffaf6; }

/* ── VERSION BADGE ── */
.version-badge { display:inline-flex; align-items:center; gap:5px; padding:4px 12px; background:#f0fdf4; border:1px solid #bbf7d0; border-radius:50px; font-size:12px; font-weight:700; color:#15803d; white-space:nowrap; }

/* ── PASSWORD DATE BADGE ── */
.pw-date-badge { display:inline-flex; align-items:center; gap:5px; padding:4px 11px; background:#f0f4ff; border:1px solid #c7d7fc; border-radius:50px; font-size:11.5px; font-weight:700; color:#3a5bd9; white-space:nowrap; }
.pw-date-badge i { font-size:10px; }

/* ── LOGOUT BTN ── */
.logout-btn { width:100%; display:flex; align-items:center; gap:16px; padding:16px 20px; background:transparent; border:none; cursor:pointer; text-align:left; transition:background .15s; font-family:'Plus Jakarta Sans',sans-serif; }
.logout-btn:hover { background:#fff5f5; }
.logout-icon { width:40px; height:40px; border-radius:11px; background:#fff1f1; display:flex; align-items:center; justify-content:center; color:#dc2626; font-size:16px; flex-shrink:0; }
.logout-label { font-size:14px; font-weight:700; color:#dc2626; }
.logout-sub { font-size:12px; color:#a07070; margin-top:2px; }

/* ── FAQ ── */
.faq-item { border-bottom:1px solid #fdf0e8; cursor:pointer; transition:background .15s; }
.faq-item:last-child { border-bottom:none; }
.faq-item:hover { background:#fffaf6; }
.faq-item-top { display:flex; align-items:center; justify-content:space-between; gap:12px; padding:14px 20px; }
.faq-item-title { display:flex; align-items:center; gap:10px; font-size:13.5px; font-weight:700; color:#1a0a00; }
.faq-item-title i { color:var(--orange); font-size:13px; flex-shrink:0; }
.faq-chevron { color:#c09070; font-size:11px; flex-shrink:0; transition:transform .3s; }
.faq-item.open .faq-chevron { transform:rotate(90deg); }
.faq-item-body { max-height:0; overflow:hidden; font-size:13px; color:#7a4a28; line-height:1.65; transition:max-height .35s ease,padding .3s; padding:0 20px; }
.faq-item.open .faq-item-body { max-height:200px; padding:0 20px 16px; }

/* ═══════════════════════════════════════
   MODAL — BASE
═══════════════════════════════════════ */
.modal-overlay { position:fixed; inset:0; background:rgba(0,0,0,.45); backdrop-filter:blur(4px); z-index:2000; display:none; align-items:center; justify-content:center; padding:16px; }
.modal-overlay.open { display:flex; }
.modal-box { background:#fff; border-radius:20px; padding:32px 28px 24px; max-width:380px; width:100%; text-align:center; box-shadow:0 20px 60px rgba(0,0,0,.2); animation:modalIn .3s cubic-bezier(.34,1.2,.64,1); }
@keyframes modalIn { from{opacity:0;transform:scale(.9) translateY(20px)} to{opacity:1;transform:scale(1) translateY(0)} }
.modal-icon { width:60px; height:60px; border-radius:50%; background:#fff1f1; display:flex; align-items:center; justify-content:center; font-size:24px; color:#dc2626; margin:0 auto 18px; }
.modal-box h3 { font-size:18px; font-weight:800; color:#1a0a00; margin-bottom:10px; }
.modal-box p { font-size:13.5px; color:#7a5a40; line-height:1.6; }
.modal-actions { display:flex; gap:12px; margin-top:22px; }
.btn-cancel { flex:1; padding:12px; border-radius:10px; border:1.5px solid #e8d8c8; background:transparent; color:#a07050; font-size:14px; font-weight:700; cursor:pointer; font-family:'Plus Jakarta Sans',sans-serif; transition:background .15s; }
.btn-cancel:hover { background:#fdf3ec; }
.btn-logout-modal { flex:1; padding:12px; border-radius:10px; border:none; background:#dc2626; color:#fff; font-size:14px; font-weight:700; cursor:pointer; font-family:'Plus Jakarta Sans',sans-serif; transition:background .15s; }
.btn-logout-modal:hover { background:#b91c1c; }

/* ═══════════════════════════════════════
   MODAL — GANTI PASSWORD
═══════════════════════════════════════ */
#gantiPasswordModal .modal-box {
  max-width:440px;
  text-align:left;
  padding:28px 28px 24px;
}
#gantiPasswordModal .modal-icon {
  background:#fff5ee;
  color:var(--orange);
}
#gantiPasswordModal h3 { text-align:center; margin-bottom:4px; }
.modal-subtitle { text-align:center; font-size:13px; color:#a07050; margin-bottom:22px; display:block; }

/* Form group */
.pw-form-group { display:flex; flex-direction:column; gap:5px; margin-bottom:14px; }
.pw-form-group:last-of-type { margin-bottom:0; }
.pw-form-label { font-size:12.5px; font-weight:700; color:#7a4a28; }

/* Input wrap */
.pw-input-wrap { position:relative; display:flex; align-items:center; }
.pw-input-wrap input {
  width:100%;
  padding:11px 44px 11px 14px;
  border:1.5px solid #e8d8c8;
  border-radius:10px;
  font-size:14px;
  font-family:'Plus Jakarta Sans',sans-serif;
  color:#1a0a00;
  background:#fff;
  outline:none;
  transition:border-color .2s,box-shadow .2s;
}
.pw-input-wrap input:focus { border-color:var(--orange); box-shadow:0 0 0 3px rgba(232,113,74,.15); }
.pw-input-wrap input.pw-error { border-color:#ef4444; box-shadow:0 0 0 3px rgba(239,68,68,.12); }

.pw-eye-btn { position:absolute; right:12px; background:none; border:none; cursor:pointer; color:#b0987a; font-size:14px; padding:0; display:flex; align-items:center; transition:color .2s; }
.pw-eye-btn:hover { color:var(--orange); }

/* Error text */
.pw-err-text { font-size:11.5px; color:#ef4444; min-height:15px; display:block; }

/* Strength bar */
.strength-track { height:5px; background:#f0e8e0; border-radius:5px; margin-top:6px; overflow:hidden; }
.strength-fill { height:100%; border-radius:5px; width:0; transition:width .35s,background .35s; }
.strength-label { font-size:11px; font-weight:700; margin-top:3px; min-height:15px; display:block; }

/* Divider */
.pw-divider { height:1px; background:#fdf0e8; margin:16px 0; }

/* Tombol simpan */
.btn-save-pw {
  flex:1; padding:12px; border-radius:10px; border:none;
  background:linear-gradient(135deg,#e8714a,#c84b11);
  color:#fff; font-size:14px; font-weight:700; cursor:pointer;
  font-family:'Plus Jakarta Sans',sans-serif;
  transition:opacity .2s,transform .15s;
  display:flex; align-items:center; justify-content:center; gap:7px;
}
.btn-save-pw:hover:not(:disabled) { opacity:.9; transform:translateY(-1px); }
.btn-save-pw:disabled { opacity:.6; cursor:not-allowed; }

/* ── TOAST ── */
.toast { position:fixed; bottom:24px; left:50%; transform:translateX(-50%) translateY(80px); background:#1a0a00; color:#fff; padding:12px 22px; border-radius:50px; font-size:13px; font-weight:600; display:flex; align-items:center; gap:8px; white-space:nowrap; z-index:3000; opacity:0; transition:transform .35s cubic-bezier(.34,1.2,.64,1),opacity .3s; box-shadow:0 8px 32px rgba(0,0,0,.25); }
.toast.show { opacity:1; transform:translateX(-50%) translateY(0); }
.toast i { color:#4ade80; font-size:14px; }
.toast i.err { color:#f87171; }

/* =========================================
   RESPONSIVE MOBILE <= 768px
========================================= */
@media (max-width: 768px) {
  .icon-actions { margin-left:auto; }
  .topbar { padding:0 16px; }
  .top-right > a span { display:none; }
  .navbar { padding:0 16px; height:64px; top:36px; gap:12px; }
  .hamburger-btn { display:flex; }
  .search-wrap { display:none; }
  .logo { min-width:unset; }
  .logo-title { font-size:20px; }
  .logo-sub { display:none; }
  .icon-btn { width:42px; height:42px; font-size:18px; }
  .sidebar { position:fixed; top:0; left:0; height:100vh; width:280px; z-index:1200; padding-top:36px; transform:translateX(-100%); transition:transform .32s cubic-bezier(.4,0,.2,1); }
  .sidebar.open { transform:translateX(0); }
  .page-wrapper { padding-top:100px; flex-direction:column; }
  .main { padding:20px 16px 32px; gap:16px; }
  .main-title { font-size:1.15rem; }
  .settings-grid { grid-template-columns:1fr; gap:14px; }
  .settings-card.full-width { grid-column:1; }
  .setting-row { padding:12px 16px; }
  .settings-card-header { padding:14px 16px 12px; gap:12px; }
  .settings-card-header h3 { font-size:14px; }
  .settings-card-body { padding:4px 0; }
  .notif-group-label { padding:10px 16px 4px; }
  .faq-item-top { padding:13px 16px; }
  .faq-item-body { padding:0 16px; }
  .faq-item.open .faq-item-body { padding:0 16px 14px; }
  .logout-btn { padding:14px 16px; }
  .setting-row-text .label { font-size:13px; }
  .modal-box { padding:24px 18px 20px; border-radius:16px; }
  .modal-actions { flex-direction:column; }
  .modal-box h3 { font-size:16px; }
  #gantiPasswordModal .modal-box { padding:24px 16px 20px; }
  .toast { bottom:16px; font-size:12px; padding:10px 18px; max-width:calc(100vw - 32px); white-space:normal; text-align:center; }
  .pw-date-badge { font-size:11px; padding:3px 9px; }
}
@media (max-width: 400px) {
  .logo-title { font-size:17px; }
  .hamburger-btn { width:38px; height:38px; }
}
/* ── MODAL TOLAK ── */
.modal-tolak-box {
  background:#fff;
  border-radius:20px;
  padding:28px;
  width:90%;
  max-width:400px;
  box-shadow:0 20px 60px rgba(0,0,0,.25);
  text-align:center;
}
.modal-tolak-icon {
  width:64px; height:64px;
  background:#fef2f2;
  border-radius:50%;
  display:flex; align-items:center; justify-content:center;
  margin:0 auto 16px;
  font-size:28px; color:#ef4444;
}
.modal-tolak-box h3 {
  font-size:18px; font-weight:800; color:#1e293b; margin-bottom:8px;
}
.modal-tolak-box p {
  font-size:13px; color:#64748b; margin-bottom:24px; line-height:1.6;
}
.btn-tolak-konfirm {
  flex:2; padding:12px;
  border-radius:10px; border:none;
  background:linear-gradient(135deg,#ef4444,#dc2626);
  color:#fff; font-size:14px; font-weight:700;
  cursor:pointer; transition:.2s;
  display:flex; align-items:center; justify-content:center; gap:6px;
  box-shadow:0 4px 12px rgba(239,68,68,.3);
  font-family:'Plus Jakarta Sans',sans-serif;
}
.btn-tolak-konfirm:hover { filter:brightness(1.08); }
  </style>
</head>
<body>

<!-- TOPBAR -->
<div class="topbar">
  <div class="top-left"></div>
  <div class="top-right">
    <a href="pesanan.php"><i class="fa-regular fa-bell"></i> <span>Notifikasi</span></a>
    <a href="suport.php"><i class="fa-regular fa-circle-question"></i> <span>Bantuan</span></a>
    <div class="lang">
      <i class="fa-solid fa-globe"></i>
      <span>Bahasa Indonesia</span>
      <i class="fa-solid fa-chevron-down"></i>
      <div class="lang-drop">
        <a href="#" class="active-lang"><i class="fa-solid fa-check"></i> Bahasa Indonesia</a>
        <a href="#">English</a>
      </div>
    </div>
  </div>
</div>

<!-- NAVBAR -->
<div class="navbar">
  <div class="navbar-texture"></div>
  <div class="orb1"></div>
  <div class="orb2"></div>

  <button class="hamburger-btn" id="hamburgerBtn" aria-label="Buka menu" onclick="toggleSidebar()">
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
    <div class="search-box">
      <input type="text" placeholder="Cari makanan dan minuman...">
      <button aria-label="Cari"><i class="fa-solid fa-magnifying-glass"></i></button>
    </div>
  </div>

  <div class="icon-actions">
    <button onclick="openChatPopup()" class="icon-btn">
      <i class="fa-solid fa-comment-dots"></i>
      <span class="icon-badge" id="chatBadge" style="display:none"></span>
    </button>
  </div>
</div>

<!-- OVERLAY -->
<div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>

<!-- PAGE WRAPPER -->
<div class="page-wrapper">

  <!-- SIDEBAR -->
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

    <nav class="sidebar-nav-area">
      <a href="dasbord.php">
        <svg xmlns="http://www.w3.org/2000/svg" width="19" height="19" viewBox="0 0 24 24"><path fill="currentColor" d="M6 19h3v-6h6v6h3v-9l-6-4.5L6 10zm-2 2V9l8-6l8 6v12h-7v-6h-2v6z"/></svg>
        Dashboard
      </a>
      <a href="produk.php">
        <svg xmlns="http://www.w3.org/2000/svg" width="19" height="19" viewBox="0 0 24 24"><path fill="currentColor" d="M19.78 12.08v4.5H4.26v-4.51H4a6.4 6.4 0 0 1-3-.73v10.24a2 2 0 0 0 2 2h18a2 2 0 0 0 2-2V11.35a6.3 6.3 0 0 1-3 .73Zm-11 9.5a.75.75 0 0 1-1.5 0v-3a.75.75 0 0 1 1.5 0Zm8 0a.75.75 0 0 1-1.5 0v-3a.75.75 0 0 1 1.5 0Zm-3.04-21h-3.45L9.23 4.83h5.58zm9.51 4.25l-1.14-3.56a1.09 1.09 0 0 0-1-.69h-5.82l1.06 4.25Zm.48 1.5H.29L0 7.21a3 3 0 0 0 1.15 2.44l.12.1l.31.19l.13.07l.17.08H2l.16.07l.14.05l.18.06H4.2a4.13 4.13 0 0 0 3.21-1.19a.74.74 0 0 1 1.19 0c1.49 2 5.33 2 6.81 0a.74.74 0 0 1 1.19 0a4.17 4.17 0 0 0 3.24 1.5h.82a2.5 2.5 0 0 0 .38-.06h.15l.37-.11l.15-.05l.35-.16l.15-.08l.32-.2l.14-.1A3 3 0 0 0 24 7.22ZM8.74.58h-5.8a1.1 1.1 0 0 0-1 .69L.78 4.83h6.9Z"/></svg>
        Produk Saya
      </a>
      <a href="pesanan.php" style="position:relative;">
        <svg xmlns="http://www.w3.org/2000/svg" width="19" height="19" viewBox="0 0 24 24"><path fill="currentColor" d="M19 4h-2V3a1 1 0 0 0-2 0v1H9V3a1 1 0 0 0-2 0v1H5a3 3 0 0 0-3 3v12a3 3 0 0 0 3 3h14a3 3 0 0 0 3-3V7a3 3 0 0 0-3-3m1 15a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1v-7h16Zm0-9H4V7a1 1 0 0 1 1-1h2v1a1 1 0 0 0 2 0V6h6v1a1 1 0 0 0 2 0V6h2a1 1 0 0 1 1 1Z"/></svg>
        Pesanan Masuk
        <span id="sidebarPesananBadge" style="position:absolute;top:50%;right:12px;transform:translateY(-50%);background:#ef4444;color:#fff;border-radius:10px;font-size:11px;font-weight:800;min-width:20px;height:20px;display:none;align-items:center;justify-content:center;padding:0 5px;border:2px solid #fff;box-shadow:0 2px 8px rgba(239,68,68,.45);line-height:1;"></span>
      </a>
      <a href="pendapatan.php">
        <svg xmlns="http://www.w3.org/2000/svg" width="19" height="19" viewBox="0 0 512 512"><path fill="currentColor" d="M32 376a56 56 0 0 0 56 56h336a56 56 0 0 0 56-56V222H32Zm66-76a30 30 0 0 1 30-30h48a30 30 0 0 1 30 30v20a30 30 0 0 1-30 30h-48a30 30 0 0 1-30-30ZM424 80H88a56 56 0 0 0-56 56v26h448v-26a56 56 0 0 0-56-56"/></svg>
        Pendapatan
      </a>
    </nav>

    <div class="sidebar-divider"></div>

    <div class="sidebar-bottom">
      <a href="suport.php">
        <svg xmlns="http://www.w3.org/2000/svg" width="19" height="19" viewBox="0 0 24 24"><path fill="currentColor" d="M22 17.002a6 6 0 0 1-4.713 5.86l-.638-1.914A4 4 0 0 0 19.465 19H17a2 2 0 0 1-2-2v-4a2 2 0 0 1 2-2h2.938a8.001 8.001 0 0 0-15.876 0H7a2 2 0 0 1 2 2v4a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2v-5C2 6.477 6.477 2 12 2s10 4.477 10 10z"/></svg>
        Support
      </a>
      <a href="pengaturan.php" class="active">
        <svg xmlns="http://www.w3.org/2000/svg" width="19" height="19" viewBox="0 0 24 24"><path fill="currentColor" d="m9.25 22l-.4-3.2q-.325-.125-.612-.3t-.563-.375L4.7 19.375l-2.75-4.75l2.575-1.95Q4.5 12.5 4.5 12.338v-.675q0-.163.025-.338L1.95 9.375l2.75-4.75l2.975 1.25q.275-.2.575-.375t.6-.3l.4-3.2h5.5l.4 3.2q.325.125.613.3t.562.375l2.975-1.25l2.75 4.75l-2.575 1.95q.025.175.025.338v.674q0 .163-.05.338l2.575 1.95l-2.75 4.75l-2.95-1.25q-.275.2-.575.375t-.6.3l-.4 3.2z"/></svg>
        Pengaturan
      </a>
    </div>
  </aside>

  <!-- MAIN CONTENT -->
  <main class="main">
    <h2 class="main-title">Pengaturan</h2>

    <div class="settings-grid">

      <!-- NOTIFIKASI -->
      <div class="settings-card">
        <div class="settings-card-header">
          <div class="header-icon" style="background:#fff5ee;">
            <i class="fa-solid fa-bell" style="color:#e8650a;font-size:15px;"></i>
          </div>
          <div>
            <h3>Notifikasi</h3>
            <p>Atur jenis notifikasi yang kamu terima</p>
          </div>
        </div>
        <div class="settings-card-body">
          <div class="notif-group-label">Pesanan</div>
          <div class="setting-row">
            <div class="setting-row-left">
              <div class="setting-row-icon" style="background:#fff5ee;"><i class="fa-solid fa-bag-shopping" style="color:#e8650a;"></i></div>
              <div class="setting-row-text">
                <div class="label">Pesanan Masuk</div>
                <div class="sublabel">Notif saat ada pesanan baru</div>
              </div>
            </div>
            <label class="toggle-switch">
              <input type="checkbox" checked onchange="showToast(this.checked?'Notifikasi pesanan masuk diaktifkan':'Notifikasi pesanan masuk dimatikan')">
              <span class="toggle-slider"></span>
            </label>
          </div>
          <div class="setting-row">
            <div class="setting-row-left">
              <div class="setting-row-icon" style="background:#eafff0;"><i class="fa-solid fa-circle-check" style="color:#27ae60;"></i></div>
              <div class="setting-row-text">
                <div class="label">Pesanan Selesai</div>
                <div class="sublabel">Notif saat pesanan selesai</div>
              </div>
            </div>
            <label class="toggle-switch">
              <input type="checkbox" checked onchange="showToast(this.checked?'Notifikasi pesanan selesai diaktifkan':'Notifikasi pesanan selesai dimatikan')">
              <span class="toggle-slider"></span>
            </label>
          </div>
          <div class="notif-group-label">Keuangan</div>
          <div class="setting-row">
            <div class="setting-row-left">
              <div class="setting-row-icon" style="background:#f0f4ff;"><i class="fa-solid fa-credit-card" style="color:#3a5bd9;"></i></div>
              <div class="setting-row-text">
                <div class="label">Notifikasi Pembayaran</div>
                <div class="sublabel">Notif saat ada pembayaran masuk</div>
              </div>
            </div>
            <label class="toggle-switch">
              <input type="checkbox" checked onchange="showToast(this.checked?'Notifikasi pembayaran diaktifkan':'Notifikasi pembayaran dimatikan')">
              <span class="toggle-slider"></span>
            </label>
          </div>
          <div class="notif-group-label">Produk</div>
          <div class="setting-row">
            <div class="setting-row-left">
              <div class="setting-row-icon" style="background:#fff8e0;"><i class="fa-solid fa-triangle-exclamation" style="color:#f0a500;"></i></div>
              <div class="setting-row-text">
                <div class="label">Stok Menipis</div>
                <div class="sublabel">Ingatkan saat stok &lt; 5</div>
              </div>
            </div>
            <label class="toggle-switch">
              <input type="checkbox" onchange="showToast(this.checked?'Notifikasi stok menipis diaktifkan':'Notifikasi stok menipis dimatikan')">
              <span class="toggle-slider"></span>
            </label>
          </div>
        </div>
      </div>

      <!-- BAHASA -->
      <div class="settings-card">
        <div class="settings-card-header">
          <div class="header-icon" style="background:#f0f4ff;">
            <i class="fa-solid fa-globe" style="color:#3a5bd9;font-size:15px;"></i>
          </div>
          <div>
            <h3>Bahasa</h3>
            <p>Pilih bahasa tampilan aplikasi</p>
          </div>
        </div>
        <div class="settings-card-body">
          <div class="setting-row active-lang-row" id="lang-id" onclick="selectLang('id')">
            <div class="setting-row-left">
              <div class="setting-row-icon emoji-icon">🇮🇩</div>
              <div class="setting-row-text">
                <div class="label">Bahasa Indonesia</div>
                <div class="sublabel">Aktif saat ini</div>
              </div>
            </div>
            <i class="fa-solid fa-circle-check lang-check" id="check-id" style="color:#e8650a;"></i>
          </div>
          <div class="setting-row" id="lang-en" onclick="selectLang('en')">
            <div class="setting-row-left">
              <div class="setting-row-icon emoji-icon" style="background:#f0f4ff;">🇬🇧</div>
              <div class="setting-row-text">
                <div class="label">English</div>
                <div class="sublabel">Switch to English</div>
              </div>
            </div>
            <i class="fa-solid fa-circle-check lang-check" id="check-en" style="color:#ddd;"></i>
          </div>
        </div>
      </div>

      <!-- BANTUAN -->
      <div class="settings-card">
        <div class="settings-card-header">
          <div class="header-icon" style="background:#eafff0;">
            <i class="fa-solid fa-circle-question" style="color:#27ae60;font-size:15px;"></i>
          </div>
          <div>
            <h3>Bantuan</h3>
            <p>Panduan &amp; pertanyaan umum</p>
          </div>
        </div>
        <div class="settings-card-body">
          <div class="faq-item" onclick="toggleFaq(this)">
            <div class="faq-item-top">
              <div class="faq-item-title"><i class="fa-solid fa-box-open"></i> Cara menambah produk baru</div>
              <i class="fa-solid fa-chevron-right faq-chevron"></i>
            </div>
            <div class="faq-item-body">Buka menu <b>Produk Saya</b>, lalu klik tombol <b>+ Tambah Produk</b>. Isi nama, harga, stok, dan foto produk. Klik Simpan.</div>
          </div>
          <div class="faq-item" onclick="toggleFaq(this)">
            <div class="faq-item-top">
              <div class="faq-item-title"><i class="fa-solid fa-receipt"></i> Cara memproses pesanan masuk</div>
              <i class="fa-solid fa-chevron-right faq-chevron"></i>
            </div>
            <div class="faq-item-body">Buka menu <b>Pesanan Masuk</b>. Klik pesanan yang ingin diproses, lalu ubah statusnya menjadi <b>Diproses</b> atau <b>Siap</b>.</div>
          </div>
          <div class="faq-item" onclick="toggleFaq(this)">
            <div class="faq-item-top">
              <div class="faq-item-title"><i class="fa-solid fa-money-bill-trend-up"></i> Cara melihat laporan pendapatan</div>
              <i class="fa-solid fa-chevron-right faq-chevron"></i>
            </div>
            <div class="faq-item-body">Buka menu <b>Pendapatan</b>. Di sana kamu bisa melihat rekap penjualan harian, mingguan, dan bulanan beserta grafiknya.</div>
          </div>
          <div class="faq-item" onclick="toggleFaq(this)">
            <div class="faq-item-top">
              <div class="faq-item-title"><i class="fa-solid fa-headset"></i> Hubungi Tim Support</div>
              <i class="fa-solid fa-chevron-right faq-chevron"></i>
            </div>
            <div class="faq-item-body">Kamu bisa menghubungi admin kantin melalui menu <b>Support</b> di sidebar, atau email ke <b>support@esemkita.sch.id</b>.</div>
          </div>
        </div>
      </div>

      <!-- KEAMANAN AKUN -->
      <div class="settings-card">
        <div class="settings-card-header">
          <div class="header-icon" style="background:#f0f4ff;">
            <i class="fa-solid fa-shield-halved" style="color:#3a5bd9;font-size:15px;"></i>
          </div>
          <div>
            <h3>Keamanan Akun</h3>
            <p>Kelola password dan keamanan login</p>
          </div>
        </div>
        <div class="settings-card-body">
          <div class="setting-row" onclick="openGantiPassword()">
            <div class="setting-row-left">
              <div class="setting-row-icon" style="background:#f0f4ff;">
                <i class="fa-solid fa-lock" style="color:#3a5bd9;"></i>
              </div>
              <div class="setting-row-text">
                <div class="label">Ganti Password</div>
                <div class="sublabel">Ubah kata sandi akun kamu</div>
              </div>
            </div>
            <i class="fa-solid fa-chevron-right" style="color:#c09070;font-size:12px;"></i>
          </div>
          <div class="setting-row" id="pwLastChangedRow"
               style="cursor:default;<?= $update_pw_at ? '' : 'display:none;' ?>">
            <div class="setting-row-left">
              <div class="setting-row-icon" style="background:#eafff0;">
                <i class="fa-solid fa-clock-rotate-left" style="color:#27ae60;font-size:13px;"></i>
              </div>
              <div class="setting-row-text">
                <div class="label">Terakhir Ganti Password</div>
                <div class="sublabel">Tercatat di database</div>
              </div>
            </div>
            <span class="pw-date-badge">
              <i class="fa-solid fa-calendar-check"></i>
              <span id="pwLastChanged"><?= htmlspecialchars($update_pw_at_fmt ?: '-') ?></span>
            </span>
          </div>
        </div>
      </div>

      <!-- TENTANG & LOGOUT -->
      <div class="settings-card full-width">
        <div class="settings-card-header">
          <div class="header-icon" style="background:#fff5ee;">
            <i class="fa-solid fa-circle-info" style="color:#e8650a;font-size:15px;"></i>
          </div>
          <div>
            <h3>Tentang Aplikasi</h3>
            <p>Informasi versi &amp; keluar akun</p>
          </div>
        </div>
        <div class="settings-card-body">
          <div class="setting-row" style="cursor:default;">
            <div class="setting-row-left">
              <div class="setting-row-icon" style="background:#fff5ee;"><i class="fa-solid fa-tag" style="color:#e8650a;"></i></div>
              <div class="setting-row-text">
                <div class="label">Versi Aplikasi</div>
                <div class="sublabel">E-Canteen Esemkita</div>
              </div>
            </div>
            <span class="version-badge"><i class="fa-solid fa-circle" style="color:#27ae60;font-size:7px;"></i> v1.0.0</span>
          </div>
          <div style="border-top:1px solid #fdf0e8;">
            <button class="logout-btn" onclick="document.getElementById('logoutModal').classList.add('open')">
              <div class="logout-icon"><i class="fa-solid fa-right-from-bracket"></i></div>
              <div>
                <div class="logout-label">Keluar (Log Out)</div>
                <div class="logout-sub">Keluar dari akun <?= htmlspecialchars($nama_penjual) ?></div>
              </div>
            </button>
          </div>
        </div>
      </div>

    </div><!-- /settings-grid -->
  </main>
</div><!-- /page-wrapper -->

<!-- MODAL LOGOUT -->
<div class="modal-overlay" id="logoutModal" onclick="if(event.target===this)this.classList.remove('open')">
  <div class="modal-box">
    <div class="modal-icon"><i class="fa-solid fa-right-from-bracket"></i></div>
    <h3>Yakin ingin keluar?</h3>
    <p>Kamu akan keluar dari akun <b><?= htmlspecialchars($nama_penjual) ?></b>. Kamu perlu login ulang untuk mengakses dashboard penjual.</p>
    <div class="modal-actions">
      <button class="btn-cancel" onclick="document.getElementById('logoutModal').classList.remove('open')">Batal</button>
      <button class="btn-logout-modal" onclick="window.location.href='logout.php'">Ya, Keluar</button>
    </div>
  </div>
</div>

<!-- MODAL GANTI PASSWORD -->
<div class="modal-overlay" id="gantiPasswordModal">
  <div class="modal-box">
    <div class="modal-icon" style="background:#fff5ee;color:var(--orange);">
      <i class="fa-solid fa-lock"></i>
    </div>
    <h3>Ganti Password</h3>
    <span class="modal-subtitle">Pastikan password baru minimal 6 karakter</span>

    <form id="formGantiPassword" onsubmit="return false;" autocomplete="off">

      <!-- Password Lama -->
      <div class="pw-form-group">
        <label class="pw-form-label" for="pwLama">Password Lama</label>
        <div class="pw-input-wrap">
          <input type="password" id="pwLama" placeholder="Masukkan password saat ini" autocomplete="current-password">
          <button type="button" class="pw-eye-btn" onclick="togglePwEye('pwLama',this)" tabindex="-1">
            <i class="fa-solid fa-eye"></i>
          </button>
        </div>
        <span class="pw-err-text" id="pwLamaErr"></span>
      </div>

      <!-- Password Baru -->
      <div class="pw-form-group">
        <label class="pw-form-label" for="pwBaru">Password Baru</label>
        <div class="pw-input-wrap">
          <input type="password" id="pwBaru" placeholder="Minimal 6 karakter" autocomplete="new-password"
                 oninput="updateStrength(this.value)">
          <button type="button" class="pw-eye-btn" onclick="togglePwEye('pwBaru',this)" tabindex="-1">
            <i class="fa-solid fa-eye"></i>
          </button>
        </div>
        <div class="strength-track">
          <div class="strength-fill" id="strengthBar"></div>
        </div>
        <span class="strength-label" id="strengthLabel"></span>
        <span class="pw-err-text" id="pwBaruErr"></span>
      </div>

      <!-- Konfirmasi Password -->
      <div class="pw-form-group">
        <label class="pw-form-label" for="pwUlang">Konfirmasi Password Baru</label>
        <div class="pw-input-wrap">
          <input type="password" id="pwUlang" placeholder="Ulangi password baru" autocomplete="new-password">
          <button type="button" class="pw-eye-btn" onclick="togglePwEye('pwUlang',this)" tabindex="-1">
            <i class="fa-solid fa-eye"></i>
          </button>
        </div>
        <span class="pw-err-text" id="pwUlangErr"></span>
      </div>

    </form>

    <div class="modal-actions">
      <button class="btn-cancel" onclick="closeGantiPassword()">Batal</button>
      <button class="btn-save-pw" id="btnSimpanPw" onclick="submitGantiPassword()">
        <i class="fa-solid fa-floppy-disk"></i> Simpan Password
      </button>
    </div>
  </div>
</div>

<!-- MODAL KONFIRMASI TOLAK -->
<div class="modal-overlay" id="tolak-modal" onclick="if(event.target===this)tutupModalTolak()">
  <div class="modal-tolak-box">
    <div class="modal-tolak-icon">
      <i class="fa-solid fa-trash-xmark"></i>
    </div>
    <h3>Batalkan Pesanan?</h3>
    <p id="tolak-modal-desc">
      Pesanan dari <strong id="tolak-nama-pembeli">—</strong> akan dibatalkan.<br>
      Stok menu akan dikembalikan secara otomatis.
    </p>
    <div class="modal-btns">
      <button class="modal-cancel-btn" onclick="tutupModalTolak()">Tidak</button>
      <button class="btn-tolak-konfirm" id="tolak-konfirm-btn">
        <i class="fa-solid fa-xmark"></i> Ya, Batalkan
      </button>
    </div>
  </div>
</div>

<!-- TOAST -->
<div class="toast" id="toast">
  <i class="fa-solid fa-circle-check" id="toastIcon"></i>
  <span id="toastMsg"></span>
</div>

<script>
/* =========================================
   E-CANTEEN — pengaturan.js
========================================= */

/* SIDEBAR */
const sidebar        = document.getElementById('sidebar');
const hamburgerBtn   = document.getElementById('hamburgerBtn');
const sidebarOverlay = document.getElementById('sidebarOverlay');

function toggleSidebar() {
  sidebar.classList.contains('open') ? closeSidebar() : openSidebar();
}
function openSidebar() {
  sidebar.classList.add('open');
  hamburgerBtn.classList.add('open');
  sidebarOverlay.classList.add('show');
  document.body.style.overflow = 'hidden';
}
function closeSidebar() {
  sidebar.classList.remove('open');
  hamburgerBtn.classList.remove('open');
  sidebarOverlay.classList.remove('show');
  document.body.style.overflow = '';
}
window.addEventListener('resize', () => { if (window.innerWidth > 768) closeSidebar(); });
document.addEventListener('keydown', e => { if (e.key === 'Escape') closeSidebar(); });

/* TOAST */
let toastTimer = null;
function showToast(msg, isError = false) {
  const t    = document.getElementById('toast');
  const icon = t.querySelector('i');
  document.getElementById('toastMsg').textContent = msg;
  if (isError) {
    icon.className   = 'fa-solid fa-circle-xmark';
    icon.style.color = '#f87171';
  } else {
    icon.className   = 'fa-solid fa-circle-check';
    icon.style.color = '#4ade80';
  }
  t.classList.add('show');
  clearTimeout(toastTimer);
  toastTimer = setTimeout(() => t.classList.remove('show'), 3000);
}

/* FAQ */
function toggleFaq(el) { el.classList.toggle('open'); }

/* BAHASA */
function selectLang(lang) {
  const checkId = document.getElementById('check-id');
  const checkEn = document.getElementById('check-en');
  const rowId   = document.getElementById('lang-id');
  const rowEn   = document.getElementById('lang-en');
  if (lang === 'id') {
    checkId.style.color = '#e8650a'; checkEn.style.color = '#ddd';
    rowId.classList.add('active-lang-row'); rowEn.classList.remove('active-lang-row');
    showToast('Bahasa diubah ke Bahasa Indonesia');
  } else {
    checkEn.style.color = '#e8650a'; checkId.style.color = '#ddd';
    rowEn.classList.add('active-lang-row'); rowId.classList.remove('active-lang-row');
    showToast('Language changed to English');
  }
}

/* ═══════════════════════════════════════
   GANTI PASSWORD — Modal Logic
═══════════════════════════════════════ */

function openGantiPassword() {
  const modal = document.getElementById('gantiPasswordModal');
  modal.classList.add('open');
  document.getElementById('formGantiPassword').reset();
  clearPwErrors();
  const bar   = document.getElementById('strengthBar');
  const label = document.getElementById('strengthLabel');
  if (bar)   { bar.style.width = '0'; bar.style.background = ''; }
  if (label) { label.textContent = ''; }
  ['pwLama','pwBaru','pwUlang'].forEach(id => {
    const input = document.getElementById(id);
    const btn   = input?.parentElement.querySelector('.pw-eye-btn i');
    if (input) input.type = 'password';
    if (btn)   btn.className = 'fa-solid fa-eye';
  });
}

function closeGantiPassword() {
  document.getElementById('gantiPasswordModal').classList.remove('open');
}

function togglePwEye(inputId, btn) {
  const input = document.getElementById(inputId);
  const icon  = btn.querySelector('i');
  if (!input || !icon) return;
  if (input.type === 'password') {
    input.type     = 'text';
    icon.className = 'fa-solid fa-eye-slash';
  } else {
    input.type     = 'password';
    icon.className = 'fa-solid fa-eye';
  }
}

function updateStrength(val) {
  const bar   = document.getElementById('strengthBar');
  const label = document.getElementById('strengthLabel');
  if (!bar) return;
  if (!val) { bar.style.width = '0'; if (label) label.textContent = ''; return; }
  let score = 0;
  if (val.length >= 6)            score++;
  if (val.length >= 10)           score++;
  if (/[A-Z]/.test(val))         score++;
  if (/[0-9]/.test(val))         score++;
  if (/[^A-Za-z0-9]/.test(val)) score++;
  const levels = [
    { pct:'20%', color:'#ef4444', text:'Sangat lemah' },
    { pct:'40%', color:'#f97316', text:'Lemah'        },
    { pct:'60%', color:'#facc15', text:'Cukup'        },
    { pct:'80%', color:'#84cc16', text:'Kuat'         },
    { pct:'100%',color:'#22c55e', text:'Sangat kuat'  },
  ];
  const lv = levels[Math.max(0, score - 1)];
  bar.style.width      = lv.pct;
  bar.style.background = lv.color;
  if (label) { label.textContent = lv.text; label.style.color = lv.color; }
}

function clearPwErrors() {
  ['pwLamaErr','pwBaruErr','pwUlangErr'].forEach(id => {
    const el = document.getElementById(id);
    if (el) el.textContent = '';
  });
  ['pwLama','pwBaru','pwUlang'].forEach(id => {
    document.getElementById(id)?.classList.remove('pw-error');
  });
}

function setPwError(fieldId, msg) {
  const input = document.getElementById(fieldId);
  const errEl = document.getElementById(fieldId + 'Err');
  if (input) input.classList.add('pw-error');
  if (errEl) errEl.textContent = msg;
}

async function submitGantiPassword() {
  clearPwErrors();

  const pwLama  = document.getElementById('pwLama').value.trim();
  const pwBaru  = document.getElementById('pwBaru').value;
  const pwUlang = document.getElementById('pwUlang').value;
  const btn     = document.getElementById('btnSimpanPw');

  // Validasi client-side
  let valid = true;
  if (!pwLama)  { setPwError('pwLama',  'Password lama wajib diisi.');          valid = false; }
  if (!pwBaru)  { setPwError('pwBaru',  'Password baru wajib diisi.');           valid = false; }
  else if (pwBaru.length < 6) { setPwError('pwBaru', 'Minimal 6 karakter.');    valid = false; }
  if (!pwUlang) { setPwError('pwUlang', 'Konfirmasi password wajib diisi.');     valid = false; }
  else if (pwBaru && pwBaru !== pwUlang) { setPwError('pwUlang', 'Password tidak cocok.'); valid = false; }
  if (!valid) return;

  // Loading state
  if (btn) {
    btn.disabled  = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Menyimpan…';
  }

  try {
    const fd = new FormData();
    fd.append('pw_lama',  pwLama);
    fd.append('pw_baru',  pwBaru);
    fd.append('pw_ulang', pwUlang);

    const res = await fetch('pengaturan.php', { method: 'POST', body: fd });

    // Cek apakah response bisa di-parse sebagai JSON
    const text = await res.text();
    let data;
    try {
      data = JSON.parse(text);
    } catch {
      console.error('Response bukan JSON:', text);
      showToast('Server mengembalikan response tidak valid.', true);
      return;
    }

    if (data.success) {
      closeGantiPassword();
      showToast('Password berhasil diubah!');
      const elDate = document.getElementById('pwLastChanged');
      const elRow  = document.getElementById('pwLastChangedRow');
      if (elDate && data.update_pw_at) {
        const d = new Date(data.update_pw_at);
        elDate.textContent = d.toLocaleDateString('id-ID', {
          day:'2-digit', month:'long', year:'numeric', hour:'2-digit', minute:'2-digit'
        });
        if (elRow) elRow.style.display = 'flex';
      }
    } else {
      showToast(data.message || 'Gagal mengubah password.', true);
      if (data.message && data.message.toLowerCase().includes('lama')) {
        setPwError('pwLama', data.message);
      } else if (data.message && data.message.toLowerCase().includes('cocok')) {
        setPwError('pwUlang', data.message);
      }
    }
  } catch (err) {
    console.error('Fetch error:', err);
    showToast('Terjadi kesalahan jaringan.', true);
  } finally {
    if (btn) {
      btn.disabled  = false;
      btn.innerHTML = '<i class="fa-solid fa-floppy-disk"></i> Simpan Password';
    }
  }
}

/* Init */
document.addEventListener('DOMContentLoaded', () => {
  document.getElementById('gantiPasswordModal')
    ?.addEventListener('click', function(e) {
      if (e.target === this) closeGantiPassword();
    });
  document.getElementById('pwBaru')
    ?.addEventListener('input', e => updateStrength(e.target.value));
});

/* Chat badge polling */
async function loadNotifChat() {
  try {
    const res  = await fetch('../users/chat_api.php?action=jumlah_belum_baca');
    const data = await res.json();
    const badge = document.getElementById('chatBadge');
    if (data.belum_baca > 0) { badge.style.display = 'flex'; badge.textContent = data.belum_baca; }
    else badge.style.display = 'none';
  } catch {}
}
loadNotifChat();
setInterval(loadNotifChat, 10000);

/* Badge Pesanan Masuk sidebar */
function loadSidebarPesananBadge() {
  fetch('pesanan.php?action=get_notif_count')
    .then(r => r.json())
    .then(data => {
      const badge   = document.getElementById('sidebarPesananBadge');
      if (!badge) return;
      const pending = data.pending || 0;
      if (pending > 0) { badge.textContent = pending; badge.style.display = 'flex'; }
      else badge.style.display = 'none';
    }).catch(() => {});
}
loadSidebarPesananBadge();
setInterval(loadSidebarPesananBadge, 5000);
</script>

<?php include 'chat_popup_penjual.php'; ?>

</body>
</html>