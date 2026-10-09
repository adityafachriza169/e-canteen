<?php
session_start();
include "database.php";
require_once 'cookie_cek_login.php';

if (!isset($_SESSION['username'])) {
    if (isset($_COOKIE['admin_remember']) && !empty($_COOKIE['admin_remember'])) {
        $token = $_COOKIE['admin_remember'];
        $stmt = mysqli_prepare($db, "SELECT username FROM akun_admin WHERE ingat = ?");
        mysqli_stmt_bind_param($stmt, "s", $token);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $row = mysqli_fetch_assoc($result);
        if ($row) {
            $_SESSION['username'] = $row['username'];
        } else {
            setcookie('admin_remember', '', time() - 3600, '/');
            header("Location: index.php");
            exit();
        }
    } else {
        header("Location: index.php");
        exit();
    }
}

$username = $_SESSION['username'];

$stmt = mysqli_prepare($db, "SELECT username FROM akun_admin WHERE username=?");
mysqli_stmt_bind_param($stmt, "s", $username);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$data_admin = mysqli_fetch_assoc($result);

$q_penjual = mysqli_query($db,"SELECT COUNT(*) as total_penjual FROM penjual WHERE status = 1");
$data_penjual = mysqli_fetch_assoc($q_penjual);
$total_penjual = $data_penjual['total_penjual'];

$q_pembeli = mysqli_query($db,"SELECT COUNT(*) as total_pembeli FROM users WHERE status = 1");
$data_pembeli = mysqli_fetch_assoc($q_pembeli);
$total_pembeli = $data_pembeli['total_pembeli'];

$q_menu = mysqli_query($db,"SELECT COUNT(id) as total_menu FROM menu_req WHERE aktif = 1");
$data_menu = mysqli_fetch_assoc($q_menu);
$total_menu = $data_menu['total_menu'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Beranda Admin</title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
<style>
body.dark{--bg:#0e0e16;--bg2:#13131f;--card:#1a1a2b;--card2:#1f1f33;--header:rgba(14,14,22,0.92);--text:#f0f0f8;--text-sub:#8888aa;--border:rgba(255,255,255,0.07);--primary:#ff7a3d;--primary-soft:rgba(255,122,61,0.15);--shadow:rgba(0,0,0,0.5);--nav-hover:rgba(255,255,255,0.06);--glass:rgba(255,255,255,0.04);--stat1:linear-gradient(135deg,#1e2a4a,#1a1f3a);--stat2:linear-gradient(135deg,#1e3a2a,#1a2f20);--stat3:linear-gradient(135deg,#2e1e3a,#241a30);--stat1-accent:#4f8af4;--stat2-accent:#2ec97e;--stat3-accent:#c47cf9;--modal-bg:#1a1a2b;--modal-header:#1f1f33;--table-stripe:rgba(255,255,255,0.03);--table-hover:rgba(255,255,255,0.06);--badge-aktif-bg:rgba(46,201,126,0.15);--badge-aktif-color:#2ec97e;--badge-tutup-bg:rgba(255,100,100,0.12);--badge-tutup-color:#ff7070}
body.light{--bg:#f5f5fa;--bg2:#ededf5;--card:#ffffff;--card2:#fafafa;--header:rgba(255,255,255,0.95);--text:#111122;--text-sub:#666680;--border:rgba(0,0,0,0.07);--primary:#ff6a1f;--primary-soft:rgba(255,106,31,0.1);--shadow:rgba(0,0,0,0.07);--nav-hover:rgba(0,0,0,0.05);--glass:rgba(255,255,255,0.7);--stat1:linear-gradient(135deg,#e8f0ff,#dde7ff);--stat2:linear-gradient(135deg,#e2f9ed,#d4f5e3);--stat3:linear-gradient(135deg,#f0e8ff,#e8d8ff);--stat1-accent:#3b6fd4;--stat2-accent:#1a9f5a;--stat3-accent:#8b45d4;--modal-bg:#ffffff;--modal-header:#f8f8ff;--table-stripe:rgba(0,0,0,0.02);--table-hover:rgba(0,0,0,0.04);--badge-aktif-bg:rgba(26,159,90,0.1);--badge-aktif-color:#1a9f5a;--badge-tutup-bg:rgba(220,50,50,0.09);--badge-tutup-color:#cc3333}
*{box-sizing:border-box;margin:0;padding:0;font-family:'Plus Jakarta Sans',sans-serif}
body{background:var(--bg);min-height:100vh;color:var(--text);transition:background .3s,color .3s}
header{background:var(--header);padding:14px 20px;display:flex;justify-content:space-between;align-items:center;border-bottom:1px solid var(--border);backdrop-filter:blur(20px);-webkit-backdrop-filter:blur(20px);position:sticky;top:0;z-index:50}
.header-left,.header-right{display:flex;align-items:center;gap:10px}
.hamburger{display:none;flex-direction:column;gap:5px;cursor:pointer;padding:7px;border-radius:10px;background:none;border:none}
.hamburger span{display:block;width:20px;height:2px;background:var(--primary);border-radius:2px;transition:.3s}
.logo{display:flex;align-items:center;gap:9px;font-weight:800;font-size:18px;color:var(--text);letter-spacing:-.3px}
.logo-box{width:48px;height:48px;border-radius:9px;display:flex;align-items:center;justify-content:center}
.logo-box img{width:48px;height:48px;object-fit:contain;border-radius:4px}
.nav-menu{display:flex;gap:4px}
.nav-menu a{text-decoration:none;color:var(--text-sub);font-size:13.5px;font-weight:500;padding:7px 13px;border-radius:8px;transition:.2s}
.nav-menu a:hover{background:var(--nav-hover);color:var(--text)}
.nav-menu a.active{background:var(--primary-soft);color:var(--primary)}
.dark-toggle{background:none;border:1.5px solid var(--border);border-radius:20px;padding:6px 14px;cursor:pointer;font-size:12.5px;font-weight:600;color:var(--text-sub);transition:.2s}
.dark-toggle:hover{border-color:var(--primary);color:var(--primary)}
.profile-btn{width:34px;height:34px;background:var(--card2);border-radius:9px;display:flex;align-items:center;justify-content:center;font-size:16px;cursor:pointer;border:1px solid var(--border);transition:.2s;position:relative}
.profile-btn:hover{border-color:var(--primary)}
.profile-dropdown{position:absolute;top:46px;right:0;background:var(--card);width:190px;border-radius:14px;box-shadow:0 12px 36px var(--shadow);padding:14px;display:none;border:1px solid var(--border);z-index:60;animation:fadeDown .25s ease}
@keyframes fadeDown{from{opacity:0;transform:translateY(-8px)}to{opacity:1;transform:translateY(0)}}
.profile-name{font-size:13px;font-weight:600;color:var(--text);margin-bottom:3px}
.profile-email{font-size:12px;color:var(--text-sub);margin-bottom:12px}
.logout-btn{display:block;text-decoration:none;background:linear-gradient(135deg,#ff4d4d,#ff6a6a);color:white;padding:8px 12px;border-radius:9px;font-size:13px;font-weight:600;text-align:center;transition:.2s}
.logout-btn:hover{opacity:.85}
.container{max-width:1080px;margin:auto;padding:28px 24px;animation:fadeUp .5s ease}
@keyframes fadeUp{from{opacity:0;transform:translateY(16px)}to{opacity:1;transform:translateY(0)}}
.page-header{margin-bottom:28px}
.title{font-size:24px;font-weight:800;letter-spacing:-.5px;color:var(--text)}
.subtitle{font-size:13.5px;color:var(--text-sub);margin-top:3px}
.stats{display:grid;grid-template-columns:repeat(3,1fr);gap:16px;margin-bottom:32px}
.stat-card{background:var(--card);padding:22px 20px;border-radius:18px;border:1px solid var(--border);position:relative;overflow:hidden;transition:transform .25s,box-shadow .25s;cursor:pointer}
.stat-card:hover{transform:translateY(-4px);box-shadow:0 12px 32px var(--shadow)}
.stat-card::after{content:'Klik untuk detail';position:absolute;bottom:10px;right:14px;font-size:10px;font-weight:600;color:var(--text-sub);opacity:0;transition:.2s;letter-spacing:.3px}
.stat-card:hover::after{opacity:1}
.stat-bg{position:absolute;inset:0;border-radius:inherit;opacity:1;z-index:0}
.stat-card:nth-child(1) .stat-bg{background:var(--stat1)}
.stat-card:nth-child(2) .stat-bg{background:var(--stat2)}
.stat-card:nth-child(3) .stat-bg{background:var(--stat3)}
.stat-inner{position:relative;z-index:1}
.stat-icon-wrap{width:42px;height:42px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:20px;margin-bottom:14px}
.stat-card:nth-child(1) .stat-icon-wrap{background:rgba(79,138,244,0.15)}
.stat-card:nth-child(2) .stat-icon-wrap{background:rgba(46,201,126,0.15)}
.stat-card:nth-child(3) .stat-icon-wrap{background:rgba(196,124,249,0.15)}
.stat-label{font-size:12.5px;font-weight:500;color:var(--text-sub);margin-bottom:6px;text-transform:uppercase;letter-spacing:.5px}
.stat-number{font-size:32px;font-weight:800;letter-spacing:-1px;color:var(--text)}
.stat-badge{display:inline-flex;align-items:center;gap:4px;margin-top:8px;font-size:11.5px;font-weight:600;padding:3px 9px;border-radius:20px}
.stat-card:nth-child(1) .stat-badge{background:rgba(79,138,244,0.15);color:var(--stat1-accent)}
.stat-card:nth-child(2) .stat-badge{background:rgba(46,201,126,0.15);color:var(--stat2-accent)}
.stat-card:nth-child(3) .stat-badge{background:rgba(196,124,249,0.15);color:var(--stat3-accent)}
.section-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:16px}
.section-title{font-size:16px;font-weight:700;color:var(--text)}
.menu-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:16px}
.menu-card{background:var(--card);border-radius:18px;padding:24px 18px;text-decoration:none;color:var(--text);border:1px solid var(--border);transition:transform .25s,border-color .25s,box-shadow .25s;display:flex;flex-direction:column;align-items:flex-start;gap:0;overflow:hidden;position:relative}
.menu-card:hover{transform:translateY(-5px);border-color:var(--primary);box-shadow:0 12px 32px var(--shadow)}
.menu-card:hover .menu-arrow{opacity:1;transform:translate(2px,-2px)}
.menu-icon{width:50px;height:50px;border-radius:14px;display:flex;align-items:center;justify-content:center;font-size:22px;margin-bottom:14px}
.icon1{background:rgba(59,130,246,0.12);box-shadow:0 0 0 1px rgba(59,130,246,0.2)}
.icon2{background:rgba(139,92,246,0.12);box-shadow:0 0 0 1px rgba(139,92,246,0.2)}
.icon3{background:rgba(34,197,94,0.12);box-shadow:0 0 0 1px rgba(34,197,94,0.2)}
.menu-card h4{font-size:14px;font-weight:700;color:var(--text);margin-bottom:5px}
.menu-card p{font-size:12.5px;color:var(--text-sub);line-height:1.5}
.menu-arrow{position:absolute;top:16px;right:16px;font-size:18px;color:var(--text-sub);opacity:0;transition:.2s}
.modal-overlay{position:fixed;inset:0;background:rgba(0,0,0,0.55);backdrop-filter:blur(6px);-webkit-backdrop-filter:blur(6px);z-index:200;display:flex;align-items:center;justify-content:center;padding:16px;opacity:0;pointer-events:none;transition:opacity .25s}
.modal-overlay.show{opacity:1;pointer-events:all}
.modal-box{background:var(--modal-bg);border-radius:22px;width:100%;max-width:900px;max-height:85vh;display:flex;flex-direction:column;border:1px solid var(--border);box-shadow:0 24px 60px rgba(0,0,0,0.35);transform:translateY(20px) scale(0.98);transition:transform .3s cubic-bezier(.34,1.56,.64,1);overflow:hidden}
.modal-overlay.show .modal-box{transform:translateY(0) scale(1)}
.modal-head{display:flex;align-items:center;justify-content:space-between;padding:20px 24px;background:var(--modal-header);border-bottom:1px solid var(--border);flex-shrink:0;gap:12px;flex-wrap:wrap}
.modal-head-left{display:flex;align-items:center;gap:10px;flex:1;min-width:0}
.modal-head-right{display:flex;align-items:center;gap:8px;flex-shrink:0}
.modal-title{display:flex;align-items:center;gap:10px;font-size:17px;font-weight:800;color:var(--text);letter-spacing:-.3px;min-width:0}
.modal-title-icon{width:38px;height:38px;border-radius:11px;display:flex;align-items:center;justify-content:center;font-size:18px;flex-shrink:0}
.modal-count{font-size:12px;font-weight:600;padding:3px 10px;border-radius:20px;background:var(--primary-soft);color:var(--primary);margin-left:8px;white-space:nowrap}
.modal-close{width:34px;height:34px;border-radius:9px;background:var(--card2);border:1px solid var(--border);color:var(--text-sub);font-size:18px;cursor:pointer;display:flex;align-items:center;justify-content:center;transition:.2s;line-height:1;flex-shrink:0}
.modal-close:hover{background:var(--primary-soft);color:var(--primary);border-color:var(--primary)}
.btn-export{display:inline-flex;align-items:center;gap:6px;padding:7px 14px;border-radius:10px;font-size:12.5px;font-weight:700;cursor:pointer;border:none;transition:.2s;white-space:nowrap;font-family:inherit}
.btn-export-green{background:linear-gradient(135deg,#1D6F42,#22924f);color:white;box-shadow:0 2px 8px rgba(29,111,66,0.3)}
.btn-export-green:hover{background:linear-gradient(135deg,#16573a,#1a7a40);box-shadow:0 4px 14px rgba(29,111,66,0.4);transform:translateY(-1px)}
.btn-row{display:inline-flex;align-items:center;gap:5px;padding:4px 10px;border-radius:7px;font-size:11px;font-weight:700;cursor:pointer;border:none;background:linear-gradient(135deg,#1D6F42,#22924f);color:white;transition:.2s;font-family:inherit;white-space:nowrap}
.btn-row:hover{background:linear-gradient(135deg,#16573a,#1a7a40);transform:translateY(-1px)}
.btn-loading{opacity:.7;pointer-events:none}
.modal-search{padding:16px 24px;border-bottom:1px solid var(--border);flex-shrink:0}
.modal-search input{width:100%;padding:10px 14px;border-radius:11px;border:1.5px solid var(--border);background:var(--card2);color:var(--text);font-size:13.5px;font-family:inherit;outline:none;transition:.2s}
.modal-search input:focus{border-color:var(--primary)}
.modal-search input::placeholder{color:var(--text-sub)}
.modal-body{overflow-y:auto;padding:20px 24px;flex:1}
.modal-body::-webkit-scrollbar{width:5px}
.modal-body::-webkit-scrollbar-track{background:transparent}
.modal-body::-webkit-scrollbar-thumb{background:var(--border);border-radius:10px}
.data-table{width:100%;border-collapse:collapse;font-size:13.5px}
.data-table th{text-align:left;padding:10px 12px;font-size:11px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--text-sub);border-bottom:1.5px solid var(--border);background:transparent;white-space:nowrap}
.data-table td{padding:11px 12px;border-bottom:1px solid var(--border);color:var(--text);vertical-align:middle}
.data-table tr:last-child td{border-bottom:none}
.data-table tr:nth-child(even) td{background:var(--table-stripe)}
.data-table tr:hover td{background:var(--table-hover)}
.badge{display:inline-flex;align-items:center;gap:4px;padding:3px 9px;border-radius:20px;font-size:11px;font-weight:700;white-space:nowrap}
.badge-aktif{background:var(--badge-aktif-bg);color:var(--badge-aktif-color)}
.badge-tutup{background:var(--badge-tutup-bg);color:var(--badge-tutup-color)}
.badge-makanan{background:rgba(255,160,40,0.13);color:#e08010}
.badge-minuman{background:rgba(59,130,246,0.12);color:#3b82f6}
.badge-snack{background:rgba(139,92,246,0.12);color:#8b5cf6}
.no-data{text-align:center;padding:40px 20px;color:var(--text-sub);font-size:14px}
.no-data .nd-icon{font-size:36px;margin-bottom:10px}
.skel{background:linear-gradient(90deg,var(--border) 25%,var(--card2) 50%,var(--border) 75%);background-size:200% 100%;animation:shimmer 1.4s infinite;border-radius:6px;height:14px}
@keyframes shimmer{0%{background-position:200% 0}100%{background-position:-200% 0}}
.nav-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:98;backdrop-filter:blur(3px)}
.nav-overlay.open{display:block}
.mobile-nav{position:fixed;top:0;left:-290px;width:270px;height:100%;background:var(--card);z-index:99;padding:0;transition:left .3s cubic-bezier(.4,0,.2,1);box-shadow:8px 0 40px rgba(0,0,0,0.25);border-right:1px solid var(--border);overflow-y:auto;display:flex;flex-direction:column}
.mobile-nav.open{left:0}
.mn-top{padding:20px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between}
.mn-logo{display:flex;align-items:center;gap:9px;font-weight:800;font-size:17px;color:var(--text)}
.mn-logo-box{width:48px;height:48px;border-radius:8px;display:flex;align-items:center;justify-content:center}
.mn-logo-box img{width:48px;height:48px;object-fit:contain;border-radius:4px}
.mn-close{width:30px;height:30px;border-radius:8px;background:var(--card2);border:1px solid var(--border);color:var(--text-sub);font-size:16px;cursor:pointer;display:flex;align-items:center;justify-content:center;transition:.2s}
.mn-close:hover{background:var(--primary-soft);color:var(--primary)}
.mn-user{margin:16px;padding:14px 16px;background:var(--primary-soft);border-radius:14px;display:flex;align-items:center;gap:11px;border:1px solid rgba(255,106,31,0.15)}
.mn-avatar{width:38px;height:38px;border-radius:10px;background:var(--primary);display:flex;align-items:center;justify-content:center;font-size:18px;color:white;flex-shrink:0}
.mn-user-name{font-size:13.5px;font-weight:700;color:var(--text)}
.mn-user-role{font-size:11.5px;color:var(--primary);font-weight:500}
.mn-section{padding:8px 16px 4px;font-size:10.5px;font-weight:700;letter-spacing:.8px;color:var(--text-sub);text-transform:uppercase}
.mn-links{padding:0 12px}
.mobile-nav a{display:flex;align-items:center;gap:10px;text-decoration:none;color:var(--text-sub);padding:11px 12px;border-radius:11px;margin-bottom:3px;font-size:14px;font-weight:500;transition:.2s}
.mobile-nav a .mn-ico{font-size:18px}
.mobile-nav a:hover{background:var(--nav-hover);color:var(--text)}
.mobile-nav a.active{background:var(--primary-soft);color:var(--primary);font-weight:600}
.mn-bottom{margin-top:auto;padding:16px;border-top:1px solid var(--border);display:flex;flex-direction:column;gap:8px}
.mn-theme{width:100%;padding:11px;border:1.5px solid var(--border);border-radius:11px;background:none;color:var(--text-sub);font-weight:600;font-size:13.5px;cursor:pointer;transition:.2s;font-family:inherit}
.mn-theme:hover{border-color:var(--primary);color:var(--primary)}
.mn-logout{display:flex;align-items:center;justify-content:center;gap:8px;background:linear-gradient(135deg,#ff4d4d,#ff6a6a);color:white !important;padding:11px;border-radius:11px;font-size:13.5px;font-weight:600;text-decoration:none;transition:.2s}
.mn-logout:hover{opacity:.85}
@media(max-width:768px){header{padding:12px 16px}.hamburger{display:flex}.nav-menu,.dark-toggle,.profile-btn{display:none !important}.container{padding:16px}.page-header{margin-bottom:20px}.title{font-size:20px}.stats{grid-template-columns:1fr 1fr;gap:10px;margin-bottom:24px}.stat-card:last-child{grid-column:span 2}.menu-grid{grid-template-columns:1fr;gap:10px}.menu-card{flex-direction:row;align-items:center;padding:15px 18px;gap:14px;border-radius:16px}.menu-icon{width:46px;height:46px;font-size:20px;margin-bottom:0;flex-shrink:0;border-radius:12px}.menu-text{flex:1}.menu-card h4{font-size:14px;margin-bottom:3px}.menu-arrow{opacity:.4;position:static;transform:none !important}.modal-box{max-height:90vh}.data-table th:nth-child(n+6),.data-table td:nth-child(n+6){display:none}.modal-head{padding:14px 16px}.btn-export span{display:none}}
@media(max-width:380px){.stat-number{font-size:22px}.logo span{display:none}}
</style>
</head>

<body class="light">

<div class="nav-overlay" id="navOverlay"></div>

<div class="mobile-nav" id="mobileNav">
  <div class="mn-top">
    <div class="mn-logo">
      <div class="mn-logo-box"><img src="../gambar/logo.png" alt="Logo"></div>
      E-Canteen
    </div>
    <button class="mn-close" onclick="closeNav()">✕</button>
  </div>
  <div class="mn-user">
    <div class="mn-avatar">👤</div>
    <div>
      <div class="mn-user-name"><?php echo $_SESSION['username']; ?></div>
      <div class="mn-user-role">Administrator</div>
    </div>
  </div>
  <div class="mn-section">Menu</div>
  <div class="mn-links">
    <a href="admin_beranda.php" class="active"><span class="mn-ico">🏠</span> Dashboard</a>
    <a href="admin_kelola_penjual.php"><span class="mn-ico">🛒</span> Penjual</a>
    <a href="admin_kelola_pembeli.php"><span class="mn-ico">👥</span> Pembeli</a>
    <a href="admin_kelola_makanan.php"><span class="mn-ico">🍔</span> Makanan</a>
  </div>
  <div class="mn-bottom">
    <button class="mn-theme" onclick="toggleDark()" id="toggleBtnMobile">🌙 Dark Mode</button>
    <a href="logout.php" class="mn-logout">🚪 Logout</a>
  </div>
</div>

<header>
  <div class="header-left">
    <button class="hamburger" onclick="openNav()">
      <span></span><span></span><span></span>
    </button>
    <div class="logo">
      <div class="logo-box"><img src="../gambar/logo.png" alt="Logo"></div>
      <span>E-Canteen</span>
    </div>
  </div>
  <div class="header-right">
    <div class="nav-menu">
      <a href="admin_beranda.php" class="active">Dashboard</a>
      <a href="admin_kelola_penjual.php">Kelola Penjual</a>
      <a href="admin_kelola_pembeli.php">Kelola Pembeli</a>
      <a href="admin_kelola_makanan.php">Kelola Makanan</a>
    </div>
    <button class="dark-toggle" onclick="toggleDark()" id="toggleBtn">🌙 Dark</button>
    <div class="profile-btn" onclick="toggleProfile()" style="position:relative;">
      👤
      <div class="profile-dropdown" id="profileBox">
        <div class="profile-name"><?php echo $_SESSION['username']; ?></div>
        <div class="profile-email">Administrator</div>
        <a href="logout.php" class="logout-btn">Logout</a>
      </div>
    </div>
  </div>
</header>

<!-- MODAL PENJUAL -->
<div class="modal-overlay" id="modalPenjual">
  <div class="modal-box">
    <div class="modal-head">
      <div class="modal-head-left">
        <div class="modal-title">
          <div class="modal-title-icon" style="background:rgba(79,138,244,0.15);">🛒</div>
          Daftar Penjual Aktif
          <span class="modal-count" id="countPenjual">–</span>
        </div>
      </div>
      <div class="modal-head-right">
        <button class="btn-export btn-export-green" id="btnExportAllPenjual" onclick="exportPenjualSemua(this)" title="Ekspor semua penjual ke Excel">
          📥 <span>Ekspor Data Penjual</span>
        </button>
        <button class="modal-close" onclick="closeModal('modalPenjual')">✕</button>
      </div>
    </div>
    <div class="modal-search">
      <input type="text" id="searchPenjual" placeholder="🔍  Cari nama, username, atau kantin..." oninput="filterTable('penjual')">
    </div>
    <div class="modal-body" id="bodyPenjual">
      <div id="skelPenjual">
        <?php for($i=0;$i<5;$i++): ?>
        <div style="display:flex;gap:12px;margin-bottom:14px;align-items:center;">
          <div class="skel" style="width:32px;height:32px;border-radius:8px;flex-shrink:0;"></div>
          <div style="flex:1"><div class="skel" style="width:60%;margin-bottom:6px;"></div><div class="skel" style="width:40%;height:10px;"></div></div>
          <div class="skel" style="width:60px;height:22px;border-radius:20px;"></div>
        </div>
        <?php endfor; ?>
      </div>
      <table class="data-table" id="tblPenjual" style="display:none;">
        <thead><tr><th>#</th><th>Nama Penjual</th><th>Username</th><th>Kantin</th><th>Status Buka</th><th>Menu Aktif</th><th style="text-align:center;">Unduh Data</th></tr></thead>
        <tbody id="tbodyPenjual"></tbody>
      </table>
      <div class="no-data" id="ndPenjual" style="display:none;"><div class="nd-icon">😕</div>Tidak ada data yang cocok</div>
    </div>
  </div>
</div>

<!-- MODAL PEMBELI -->
<div class="modal-overlay" id="modalPembeli">
  <div class="modal-box">
    <div class="modal-head">
      <div class="modal-head-left">
        <div class="modal-title">
          <div class="modal-title-icon" style="background:rgba(46,201,126,0.15);">👥</div>
          Daftar Pembeli Aktif
          <span class="modal-count" id="countPembeli">–</span>
        </div>
      </div>
      <div class="modal-head-right">
        <button class="btn-export btn-export-green" id="btnExportPembeli" onclick="exportPembeli(this)" title="Ekspor data pembeli ke Excel">
          📥 <span>Ekspor ke Excel</span>
        </button>
        <button class="modal-close" onclick="closeModal('modalPembeli')">✕</button>
      </div>
    </div>
    <div class="modal-search">
      <input type="text" id="searchPembeli" placeholder="🔍  Cari nama, username, atau kelas..." oninput="filterTable('pembeli')">
    </div>
    <div class="modal-body" id="bodyPembeli">
      <div id="skelPembeli">
        <?php for($i=0;$i<5;$i++): ?>
        <div style="display:flex;gap:12px;margin-bottom:14px;align-items:center;">
          <div class="skel" style="width:32px;height:32px;border-radius:8px;flex-shrink:0;"></div>
          <div style="flex:1"><div class="skel" style="width:55%;margin-bottom:6px;"></div><div class="skel" style="width:35%;height:10px;"></div></div>
          <div class="skel" style="width:50px;height:22px;border-radius:20px;"></div>
        </div>
        <?php endfor; ?>
      </div>
      <table class="data-table" id="tblPembeli" style="display:none;">
        <thead><tr><th>#</th><th>Nama</th><th>Username</th><th>Kelas</th><th>Email</th><th>Transaksi</th><th style="text-align:center;">Unduh Data</th></tr></thead>
        <tbody id="tbodyPembeli"></tbody>
      </table>
      <div class="no-data" id="ndPembeli" style="display:none;"><div class="nd-icon">😕</div>Tidak ada data yang cocok</div>
    </div>
  </div>
</div>

<!-- MODAL MENU -->
<div class="modal-overlay" id="modalMenu">
  <div class="modal-box">
    <div class="modal-head">
      <div class="modal-head-left">
        <div class="modal-title">
          <div class="modal-title-icon" style="background:rgba(196,124,249,0.15);">🍔</div>
          Daftar Menu Aktif
          <span class="modal-count" id="countMenu">–</span>
        </div>
      </div>
      <div class="modal-head-right">
        <button class="modal-close" onclick="closeModal('modalMenu')">✕</button>
      </div>
    </div>
    <div class="modal-search">
      <input type="text" id="searchMenu" placeholder="🔍  Cari nama menu, kategori, atau kantin..." oninput="filterTable('menu')">
    </div>
    <div class="modal-body" id="bodyMenu">
      <div id="skelMenu">
        <?php for($i=0;$i<5;$i++): ?>
        <div style="display:flex;gap:12px;margin-bottom:14px;align-items:center;">
          <div class="skel" style="width:32px;height:32px;border-radius:8px;flex-shrink:0;"></div>
          <div style="flex:1"><div class="skel" style="width:65%;margin-bottom:6px;"></div><div class="skel" style="width:40%;height:10px;"></div></div>
          <div class="skel" style="width:55px;height:22px;border-radius:20px;"></div>
        </div>
        <?php endfor; ?>
      </div>
      <table class="data-table" id="tblMenu" style="display:none;">
        <thead><tr><th>#</th><th>Nama Menu</th><th>Kategori</th><th>Kantin</th><th>Harga</th><th>Stok</th><th>Terjual</th><th style="text-align:center;">Unduh</th></tr></thead>
        <tbody id="tbodyMenu"></tbody>
      </table>
      <div class="no-data" id="ndMenu" style="display:none;"><div class="nd-icon">😕</div>Tidak ada data yang cocok</div>
    </div>
  </div>
</div>

<!-- MAIN CONTENT -->
<div class="container">
  <div class="page-header">
    <div class="title">Dashboard Admin</div>
    <div class="subtitle">Selamat datang kembali, <strong><?php echo $_SESSION['username']; ?></strong> 👋</div>
  </div>
  <div class="stats">
    <div class="stat-card" onclick="openModal('penjual')" title="Klik untuk lihat detail penjual aktif">
      <div class="stat-bg"></div>
      <div class="stat-inner">
        <div class="stat-icon-wrap">👥</div>
        <div class="stat-info">
          <div class="stat-label">Akun Penjual</div>
          <div class="stat-number"><?php echo $total_penjual; ?></div>
          <div class="stat-badge">↑ Aktif</div>
        </div>
      </div>
    </div>
    <div class="stat-card" onclick="openModal('pembeli')" title="Klik untuk lihat detail pembeli aktif">
      <div class="stat-bg"></div>
      <div class="stat-inner">
        <div class="stat-icon-wrap">📊</div>
        <div class="stat-info">
          <div class="stat-label">Akun Pembeli</div>
          <div class="stat-number"><?php echo $total_pembeli; ?></div>
          <div class="stat-badge">↑ Terdaftar</div>
        </div>
      </div>
    </div>
    <div class="stat-card" onclick="openModal('menu')" title="Klik untuk lihat detail menu aktif">
      <div class="stat-bg"></div>
      <div class="stat-inner">
        <div class="stat-icon-wrap">🍔</div>
        <div class="stat-info">
          <div class="stat-label">Total Menu Aktif</div>
          <div class="stat-number"><?php echo $total_menu; ?></div>
          <div class="stat-badge">↑ Tersedia</div>
        </div>
      </div>
    </div>
  </div>
  <div class="section-header">
    <div class="section-title">⚡ Aksi Cepat</div>
  </div>
  <div class="menu-grid">
    <a href="admin_kelola_penjual.php" class="menu-card">
      <div class="menu-icon icon1">🛒</div>
      <div class="menu-text"><h4>Kelola Penjual</h4><p>Manajemen akun penjual</p></div>
      <div class="menu-arrow">↗</div>
    </a>
    <a href="admin_kelola_pembeli.php" class="menu-card">
      <div class="menu-icon icon2">👥</div>
      <div class="menu-text"><h4>Kelola Pembeli</h4><p>Manajemen data pembeli</p></div>
      <div class="menu-arrow">↗</div>
    </a>
    <a href="admin_kelola_makanan.php" class="menu-card">
      <div class="menu-icon icon3">🍔</div>
      <div class="menu-text"><h4>Kelola Makanan</h4><p>Manajemen data makanan</p></div>
      <div class="menu-arrow">↗</div>
    </a>
  </div>
</div>

<script>
// ================================================================
//  CACHE & UTILS
// ================================================================
const cache = {};

function rupiah(n) {
  return 'Rp ' + parseInt(n || 0).toLocaleString('id-ID');
}
function cap(s) {
  return s.charAt(0).toUpperCase() + s.slice(1);
}
function capStr(s) {
  return s ? s.charAt(0).toUpperCase() + s.slice(1) : '-';
}
function esc(s) {
  const d = document.createElement('div');
  d.textContent = s || '';
  return d.innerHTML;
}
function escAttr(s) {
  return (s || '').replace(/'/g, "\\'").replace(/"/g, '&quot;');
}
function showSkel(type, show) {
  const s = document.getElementById('skel' + cap(type));
  if (s) s.style.display = show ? 'block' : 'none';
  const t = document.getElementById('tbl' + cap(type));
  if (t && show) t.style.display = 'none';
}
function buildSearchStr(type, row) {
  if (type === 'penjual') return [row.nama, row.username, row.email, row.nama_kantin].join(' ').toLowerCase();
  if (type === 'pembeli') return [row.nama, row.username, row.email, row.kelas].join(' ').toLowerCase();
  if (type === 'menu') return [row.nama_makanan, row.kategori, row.nama_kantin, row.nama_penjual].join(' ').toLowerCase();
  return '';
}
function setLoading(btn, loading) {
  if (!btn) return;
  if (loading) {
    btn.classList.add('btn-loading');
    btn._orig = btn.innerHTML;
    btn.innerHTML = '⏳ Memproses...';
  } else {
    btn.classList.remove('btn-loading');
    if (btn._orig) btn.innerHTML = btn._orig;
  }
}
function dateStr() {
  return new Date().toISOString().slice(0, 10);
}
function nowStr() {
  const d = new Date();
  return d.toLocaleDateString('id-ID', { day: '2-digit', month: 'long', year: 'numeric' })
    + ', ' + d.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' });
}
function rupiahStr(n) {
  return 'Rp ' + parseInt(n || 0).toLocaleString('id-ID');
}

// ================================================================
//  MODAL
// ================================================================
function openModal(type) {
  const ids = { penjual: 'modalPenjual', pembeli: 'modalPembeli', menu: 'modalMenu' };
  const overlay = document.getElementById(ids[type]);
  overlay.classList.add('show');
  document.body.style.overflow = 'hidden';
  const searchEl = document.getElementById('search' + cap(type));
  if (searchEl) searchEl.value = '';
  if (!cache[type]) fetchData(type);
  else renderData(type, cache[type]);
}
function closeModal(id) {
  document.getElementById(id).classList.remove('show');
  document.body.style.overflow = '';
}
document.querySelectorAll('.modal-overlay').forEach(el => {
  el.addEventListener('click', function (e) { if (e.target === this) closeModal(this.id); });
});
document.addEventListener('keydown', e => {
  if (e.key === 'Escape') document.querySelectorAll('.modal-overlay.show').forEach(el => closeModal(el.id));
});

function fetchData(type) {
  showSkel(type, true);
  fetch('admin_stat_popup.php?type=' + type)
    .then(r => r.json())
    .then(res => {
      if (res.success) { cache[type] = res.data; renderData(type, res.data); }
    })
    .catch(() => {
      showSkel(type, false);
      document.getElementById('body' + cap(type)).innerHTML = '<div class="no-data"><div class="nd-icon">❌</div>Gagal memuat data</div>';
    });
}

function renderData(type, data) {
  showSkel(type, false);
  const tbody = document.getElementById('tbody' + cap(type));
  const tbl   = document.getElementById('tbl'   + cap(type));
  const nd    = document.getElementById('nd'    + cap(type));
  const count = document.getElementById('count' + cap(type));
  count.textContent = data.length + ' data';
  if (data.length === 0) { tbl.style.display = 'none'; nd.style.display = 'block'; return; }
  tbl.style.display = 'table'; nd.style.display = 'none'; tbody.innerHTML = '';
  data.forEach((row, i) => {
    const tr = document.createElement('tr');
    tr.setAttribute('data-search', buildSearchStr(type, row));
    if (type === 'penjual') {
      const bukaLabel = row.buka == 1
        ? '<span class="badge badge-aktif">🟢 Buka</span>'
        : '<span class="badge badge-tutup">🔴 Tutup</span>';
      tr.innerHTML = `<td style="color:var(--text-sub);font-size:12px;">${i + 1}</td><td><strong>${esc(row.nama)}</strong></td><td style="color:var(--text-sub);">${esc(row.username)}</td><td>${row.nama_kantin ? esc(row.nama_kantin) : '<span style="color:var(--text-sub);font-size:12px;">-</span>'}</td><td>${bukaLabel}</td><td><span style="font-weight:700;color:var(--stat3-accent);">${row.total_menu_aktif}</span></td><td style="text-align:center;"><button class="btn-row" onclick="exportPenjualSatu(this,'${row.id_penjual}','${escAttr(row.nama)}')" title="Unduh data ${esc(row.nama)}">📥 Unduh Excel</button></td>`;
    } else if (type === 'pembeli') {
      tr.innerHTML = `<td style="color:var(--text-sub);font-size:12px;">${i + 1}</td><td><strong>${esc(row.nama)}</strong></td><td style="color:var(--text-sub);">${esc(row.username)}</td><td>${esc(row.kelas)}</td><td style="font-size:12px;color:var(--text-sub);">${esc(row.email || '-')}</td><td><span style="font-weight:700;color:var(--stat2-accent);">${row.total_transaksi}</span></td><td style="text-align:center;"><button class="btn-row" onclick="exportPembeliSatu(this,'${row.id_user}','${escAttr(row.nama)}')" title="Unduh data ${esc(row.nama)}">📥 Unduh Excel</button></td>`;
    } else if (type === 'menu') {
      const kat = row.kategori ? row.kategori.toUpperCase() : '';
      let katBadge = `<span class="badge badge-makanan">${esc(kat)}</span>`;
      if (kat === 'MINUMAN') katBadge = `<span class="badge badge-minuman">${esc(kat)}</span>`;
      else if (kat === 'SNACK') katBadge = `<span class="badge badge-snack">${esc(kat)}</span>`;
      tr.innerHTML = `<td style="color:var(--text-sub);font-size:12px;">${i + 1}</td><td><strong>${esc(row.nama_makanan)}</strong><div style="font-size:11px;color:var(--text-sub);margin-top:2px;">${esc(row.nama_penjual || '-')}</div></td><td>${katBadge}</td><td style="font-size:12.5px;">${esc(row.nama_kantin || '-')}</td><td style="font-weight:600;">${rupiah(row.harga)}</td><td>${row.stok}</td><td><span style="font-weight:700;color:var(--stat2-accent);">${row.terjual}</span></td><td style="text-align:center;"><button class="btn-row" onclick="exportMenuSatu(this,'${row.id}','${escAttr(row.nama_makanan)}')" title="Unduh detail menu ${esc(row.nama_makanan)}">📥 Unduh</button></td>`;
    }
    tbody.appendChild(tr);
  });
}

function filterTable(type) {
  const q = document.getElementById('search' + cap(type)).value.toLowerCase();
  const rows = document.querySelectorAll('#tbl' + cap(type) + ' tbody tr');
  const nd = document.getElementById('nd' + cap(type));
  let visible = 0;
  rows.forEach(tr => { const match = tr.getAttribute('data-search').includes(q); tr.style.display = match ? '' : 'none'; if (match) visible++; });
  nd.style.display = visible === 0 ? 'block' : 'none';
  document.getElementById('count' + cap(type)).textContent = visible + ' data';
}

// ================================================================
//  DARK MODE
// ================================================================
function toggleDark() {
  const body = document.body;
  const btn  = document.getElementById('toggleBtn');
  const btnM = document.getElementById('toggleBtnMobile');
  if (body.classList.contains('light')) {
    body.classList.replace('light', 'dark');
    if (btn)  btn.textContent  = '☀️ Light';
    if (btnM) btnM.textContent = '☀️ Light Mode';
    localStorage.setItem('theme', 'dark');
  } else {
    body.classList.replace('dark', 'light');
    if (btn)  btn.textContent  = '🌙 Dark';
    if (btnM) btnM.textContent = '🌙 Dark Mode';
    localStorage.setItem('theme', 'light');
  }
}
window.onload = function () {
  if (localStorage.getItem('theme') === 'dark') {
    document.body.classList.replace('light', 'dark');
    const btn  = document.getElementById('toggleBtn');
    const btnM = document.getElementById('toggleBtnMobile');
    if (btn)  btn.textContent  = '☀️ Light';
    if (btnM) btnM.textContent = '☀️ Light Mode';
  }
};

// ================================================================
//  PROFILE & SIDEBAR
// ================================================================
function toggleProfile() {
  const box = document.getElementById('profileBox');
  box.style.display = box.style.display === 'block' ? 'none' : 'block';
}
window.addEventListener('click', function (e) {
  if (!e.target.closest('.profile-btn')) {
    const box = document.getElementById('profileBox');
    if (box && box.style.display === 'block') box.style.display = 'none';
  }
});
function openNav() {
  document.getElementById('mobileNav').classList.add('open');
  document.getElementById('navOverlay').classList.add('open');
  document.body.style.overflow = 'hidden';
}
function closeNav() {
  document.getElementById('mobileNav').classList.remove('open');
  document.getElementById('navOverlay').classList.remove('open');
  document.body.style.overflow = '';
}
document.getElementById('navOverlay').addEventListener('click', closeNav);

// ================================================================
//  PALET WARNA — Desain Baru (Oranye / Navy / Putih)
// ================================================================
const C = {
  // Brand Oranye
  orange:      'FFEA580C',
  orangeMid:   'FFED6C02',
  orangeLight: 'FFFFF3E0',
  orangePale:  'FFFFF8F0',
  orangeDark:  'FFB84500',

  // Navy / Gelap
  navy:        'FF1B2A4A',
  navyMid:     'FF243358',
  navyLight:   'FFE8EDF5',
  navyPale:    'FFF5F7FB',

  // Hijau
  green:       'FF1A7A4A',
  greenMid:    'FF22924F',
  greenLight:  'FFD4EDDA',
  greenPale:   'FFF0FDF4',

  // Merah
  red:         'FFCC2222',
  redMid:      'FFDC2626',
  redLight:    'FFFECACA',
  redPale:     'FFFFF1F1',

  // Biru (QRIS / info)
  blue:        'FF1B4FBF',
  blueMid:     'FF2563EB',
  blueLight:   'FFDBEAFE',
  bluePale:    'FFEFF8FF',

  // Ungu
  purple:      'FF6D28D9',
  purpleLight: 'FFEDE9FE',

  // Netral
  white:       'FFFFFFFF',
  gray50:      'FFF9FAFB',
  gray100:     'FFF3F4F6',
  gray200:     'FFE5E7EB',
  gray300:     'FFD1D5DB',
  gray400:     'FF9CA3AF',
  gray500:     'FF6B7280',
  gray600:     'FF4B5563',
  gray700:     'FF374151',
  gray800:     'FF1F2937',
  gray900:     'FF111827',
  black:       'FF000000',
};

// ── HELPER BORDER ─────────────────────────────────────────────────
function bd(style, hex) {
  return { style: style || 'thin', color: { rgb: hex || C.gray200 } };
}
function allBd(style, hex) {
  const b = bd(style, hex);
  return { top: b, bottom: b, left: b, right: b };
}
function noBd() {
  const n = bd('none', C.white);
  return { top: n, bottom: n, left: n, right: n };
}

// ── CELL BUILDER ─────────────────────────────────────────────────
function cell(value, opt) {
  opt = opt || {};
  const isNum = typeof value === 'number';
  return {
    v: value,
    t: isNum ? 'n' : 's',
    s: {
      font: {
        name:   opt.font   || 'Calibri',
        sz:     opt.sz     || 10,
        bold:   !!opt.bold,
        italic: !!opt.italic,
        color:  { rgb: opt.color || C.gray800 },
      },
      fill: {
        patternType: 'solid',
        fgColor: { rgb: opt.bg || C.white },
      },
      alignment: {
        horizontal: opt.h      || 'left',
        vertical:   opt.v      || 'center',
        wrapText:   !!opt.wrap,
        indent:     opt.indent || 0,
      },
      border: opt.border || allBd('thin', C.gray200),
    },
  };
}

// ── PRESET CELL TYPES ─────────────────────────────────────────────

/** Judul laporan utama — oranye penuh */
function JUDUL(v) {
  return cell(v, {
    sz: 16, bold: true,
    color: C.white, bg: C.orange,
    h: 'center', v: 'center',
    border: allBd('medium', C.orangeDark),
  });
}

/** Sub-judul tipis */
function SUBJUDUL_KIRI(v) {
  return cell(v, {
    sz: 9, italic: true,
    color: C.gray600, bg: C.white,
    h: 'left',
    border: noBd(),
  });
}
function SUBJUDUL_KANAN(v) {
  return cell(v, {
    sz: 9, italic: true,
    color: C.gray500, bg: C.white,
    h: 'right',
    border: noBd(),
  });
}

/** Header seksi — navy gelap */
function SEKSI(v) {
  return cell(v, {
    sz: 10, bold: true,
    color: C.white, bg: C.navy,
    h: 'left', indent: 1,
    border: allBd('medium', C.navyMid),
  });
}

/** Header kolom tabel — oranye */
function KOLOM(v, bg) {
  return cell(v, {
    sz: 9, bold: true,
    color: C.white,
    bg: bg || C.orange,
    h: 'center', v: 'center',
    border: allBd('medium', C.orangeDark),
  });
}

/** Label kiri info */
function LABEL(v) {
  return cell(v, {
    sz: 10, bold: true,
    color: C.navy, bg: C.navyLight,
    h: 'left', indent: 1,
    border: allBd('thin', C.gray300),
  });
}

/** Nilai teks */
function VAL(v, opt) {
  return cell(v, Object.assign({
    sz: 10, color: C.gray800, bg: C.white,
    h: 'left', indent: 1,
    border: allBd('thin', C.gray200),
  }, opt || {}));
}

/** Badge berwarna */
function BADGE(v, bg, fg) {
  return cell(v, {
    sz: 9, bold: true,
    color: fg || C.white, bg: bg || C.orange,
    h: 'center',
    border: allBd('thin', bg || C.orange),
  });
}

/** Nomor urut */
function NO(v, bg) {
  return cell(v, {
    sz: 9, color: C.gray400, bg: bg || C.white,
    h: 'center',
    border: allBd('thin', C.gray200),
  });
}

/** Footer total label */
function TOTAL_LBL(v) {
  return cell(v, {
    sz: 10, bold: true,
    color: C.white, bg: C.navy,
    h: 'right', indent: 1,
    border: allBd('medium', C.navyMid),
  });
}

/** Footer total nilai */
function TOTAL_VAL(v, opt) {
  return cell(v, Object.assign({
    sz: 12, bold: true,
    color: C.orange, bg: C.orangeLight,
    h: 'center',
    border: allBd('medium', C.orange),
  }, opt || {}));
}

/** Baris pemisah tipis */
function SEP(n, hex) {
  return Array(n).fill(cell('', {
    bg: hex || C.gray200,
    sz: 3, border: noBd(),
  }));
}

/** Baris kosong */
function KOSONG(n) {
  return Array(n).fill(cell('', { border: noBd() }));
}

// ── STAT BOX (kotak ringkasan) ─────────────────────────────────
function statHeader(label, bg) {
  return cell(label, {
    sz: 9, bold: true,
    color: bg === C.orange ? C.white : C.gray700,
    bg: bg || C.orangeLight,
    h: 'center',
    border: allBd('medium', bg || C.orange),
  });
}
function statValue(val, color, bg, bdr) {
  return cell(val, {
    sz: 22, bold: true,
    color: color || C.orange,
    bg: bg || C.white,
    h: 'center', v: 'center',
    border: allBd('medium', bdr || C.orange),
  });
}

// ── HELPER WARNA ──────────────────────────────────────────────────
function warnaStatus(sts) {
  const s = (sts || '').toLowerCase();
  if (s === 'lunas')                       return [C.greenLight, C.green,  '✔ Lunas'];
  if (s === 'ditolak' || s === 'batal')    return [C.redLight,   C.red,    '✘ ' + capStr(sts)];
  if (s === 'pending' || s === 'menunggu') return [C.orangeLight, C.orangeMid, '⏳ ' + capStr(sts)];
  if (s === 'diproses')                    return [C.blueLight,  C.blue,   '⚙ Diproses'];
  return [C.gray100, C.gray600, capStr(sts)];
}
function warnaBuka(buka) {
  return buka == 1
    ? [C.greenLight, C.green,  '● Buka']
    : [C.redLight,   C.red,    '● Tutup'];
}
function warnaKategori(kat) {
  const k = (kat || '').toUpperCase();
  if (k === 'MINUMAN') return [C.blueLight,   C.blue,   'Minuman'];
  if (k === 'SNACK')   return [C.purpleLight, C.purple, 'Snack'];
  return [C.orangeLight, C.orange, 'Makanan'];
}

// ── BUILDER WORKSHEET ─────────────────────────────────────────────
function buatSheet(wb, nama, aoa, merges, lebar) {
  const ws = XLSX.utils.aoa_to_sheet(aoa, { cellStyles: true });
  ws['!merges'] = merges || [];
  ws['!cols']   = lebar.map(w => ({ wch: w }));
  ws['!rows']   = aoa.map((_, i) => {
    if (i === 0) return { hpt: 40 };
    if (i === 1) return { hpt: 16 };
    return { hpt: 22 };
  });
  XLSX.utils.book_append_sheet(wb, ws, nama);
  return ws;
}
function simpanWb(wb, nama) {
  XLSX.writeFile(wb, nama);
}
function span(r, c, rEnd, cEnd) {
  return { s: { r, c }, e: { r: rEnd || r, c: cEnd || c } };
}

// ================================================================
//  1. EKSPOR SEMUA PENJUAL
// ================================================================
function exportPenjualSemua(btn) {
  const data = cache['penjual'];
  if (!data || data.length === 0) { alert('Data belum dimuat.'); return; }
  setLoading(btn, true);

  const COL = 8;
  const wb  = XLSX.utils.book_new();
  const aoa = [];
  const mrg = [];

  const totalBuka  = data.filter(p => p.buka == 1).length;
  const totalTutup = data.filter(p => p.buka != 1).length;
  const totalMenu  = data.reduce((s, p) => s + (parseInt(p.total_menu_aktif) || 0), 0);

  // ── HEADER ───────────────────────────────────────────────────
  aoa.push([JUDUL('LAPORAN DATA PENJUAL AKTIF — E-CANTEEN'), ...Array(COL - 1).fill(JUDUL(''))]);
  mrg.push(span(0, 0, 0, COL - 1));

  // Baris periode + cetak
  const rInfo = aoa.length;
  aoa.push([
    SUBJUDUL_KIRI('Periode: Seluruh Periode'),
    ...Array(COL - 2).fill(cell('', { border: noBd() })),
    SUBJUDUL_KANAN('Dicetak: ' + nowStr()),
  ]);
  mrg.push(span(rInfo, 0, rInfo, COL - 2));

  aoa.push(KOSONG(COL));

  // ── KOTAK STATISTIK ─────────────────────────────────────────
  const rH = aoa.length;
  aoa.push([
    statHeader('Total Penjual',    C.orange),      statHeader('', C.orange),
    statHeader('Sedang Buka',      C.greenMid),    statHeader('', C.greenMid),
    statHeader('Sedang Tutup',     C.redMid),      statHeader('', C.redMid),
    statHeader('Total Menu Aktif', C.navy),        statHeader('', C.navy),
  ]);
  aoa.push([
    statValue(data.length,  C.orange,   C.orangeLight, C.orange),
    cell('', { bg: C.orangeLight, border: allBd('medium', C.orange) }),
    statValue(totalBuka,    C.green,    C.greenLight,  C.greenMid),
    cell('', { bg: C.greenLight,  border: allBd('medium', C.greenMid) }),
    statValue(totalTutup,   C.red,      C.redLight,    C.redMid),
    cell('', { bg: C.redLight,    border: allBd('medium', C.redMid) }),
    statValue(totalMenu,    C.white,    C.navy,        C.navyMid),
    cell('', { bg: C.navy,        border: allBd('medium', C.navyMid) }),
  ]);
  // Sub-label di bawah nilai
  aoa.push([
    cell('akun aktif',   { sz:8, italic:true, color:C.orange,  bg:C.orangeLight, h:'center', border:allBd('thin', C.orange)  }),
    cell('', { bg:C.orangeLight, border:allBd('thin', C.orange) }),
    cell('sedang buka',  { sz:8, italic:true, color:C.green,   bg:C.greenLight,  h:'center', border:allBd('thin', C.greenMid) }),
    cell('', { bg:C.greenLight,  border:allBd('thin', C.greenMid) }),
    cell('sedang tutup', { sz:8, italic:true, color:C.red,     bg:C.redLight,    h:'center', border:allBd('thin', C.redMid) }),
    cell('', { bg:C.redLight,    border:allBd('thin', C.redMid) }),
    cell('menu tersedia',{ sz:8, italic:true, color:C.white,   bg:C.navy,        h:'center', border:allBd('thin', C.navyMid) }),
    cell('', { bg:C.navy,        border:allBd('thin', C.navyMid) }),
  ]);
  // Merge kotak statistik
  [[rH,0],[rH,2],[rH,4],[rH,6],[rH+1,0],[rH+1,2],[rH+1,4],[rH+1,6],[rH+2,0],[rH+2,2],[rH+2,4],[rH+2,6]]
    .forEach(([r, c]) => mrg.push(span(r, c, r, c + 1)));

  aoa.push(KOSONG(COL));

  // ── SEKSI TABEL ──────────────────────────────────────────────
  aoa.push([SEKSI('  REKAP PER PENJUAL'), ...Array(COL - 1).fill(SEKSI(''))]);
  mrg.push(span(aoa.length - 1, 0, aoa.length - 1, COL - 1));

  aoa.push([
    KOLOM('No'),   KOLOM('Nama Penjual'),  KOLOM('Username'),
    KOLOM('Kantin'), KOLOM('Email'),       KOLOM('Status Buka'),
    KOLOM('Menu Aktif'), KOLOM('Terdaftar'),
  ]);

  data.forEach((p, i) => {
    const alt   = i % 2 === 1;
    const rowBg = alt ? C.navyPale : C.white;
    const [bBg, bFg, bTxt] = warnaBuka(p.buka);
    const mc = parseInt(p.total_menu_aktif) || 0;
    aoa.push([
      NO(i + 1, rowBg),
      cell(p.nama || '-',        { sz:10, bold:true, color:C.navy,    bg:rowBg, indent:1, border:allBd('thin', C.gray200) }),
      cell(p.username || '-',    { sz:10, color:C.gray500,            bg:rowBg, indent:1, border:allBd('thin', C.gray200) }),
      cell(p.nama_kantin || '-', { sz:10, color:C.gray700,            bg:rowBg, indent:1, border:allBd('thin', C.gray200) }),
      cell(p.email || '-',       { sz:9,  color:C.gray400,            bg:rowBg, indent:1, border:allBd('thin', C.gray200) }),
      BADGE(bTxt, bBg, bFg),
      cell(mc, { sz:11, bold:mc>0, color:mc>0?C.orange:C.gray400,    bg:rowBg, h:'center', border:allBd('thin', C.gray200) }),
      cell(p.created_at ? p.created_at.slice(0,10) : '-', { sz:9, color:C.gray400, bg:rowBg, h:'center', border:allBd('thin', C.gray200) }),
    ]);
  });

  // Footer
  const rFoot = aoa.length;
  aoa.push([
    TOTAL_LBL('TOTAL'), TOTAL_LBL(''), TOTAL_LBL(''), TOTAL_LBL(''),
    TOTAL_LBL(''), TOTAL_LBL(''), TOTAL_VAL(data.length), TOTAL_LBL(''),
  ]);
  mrg.push(span(rFoot, 0, rFoot, 5));

  buatSheet(wb, 'Penjual Aktif', aoa, mrg, [5, 28, 16, 20, 30, 12, 12, 14]);
  simpanWb(wb, 'Laporan_Penjual_Aktif_' + dateStr() + '.xlsx');
  setLoading(btn, false);
}

// ================================================================
//  2. EKSPOR DETAIL 1 PENJUAL
// ================================================================
function exportPenjualSatu(btn, id, nama) {
  setLoading(btn, true);
  fetch('admin_export_data.php?type=penjual_detail&id=' + id)
    .then(r => r.json())
    .then(res => {
      if (!res.success) { alert('Gagal memuat data.'); setLoading(btn, false); return; }
      buatExcelPenjualDetail(res.penjual, res.menus);
      setLoading(btn, false);
    })
    .catch(() => { alert('Gagal menghubungi server.'); setLoading(btn, false); });
}

function buatExcelPenjualDetail(p, menus) {
  const COL = 9;
  const wb  = XLSX.utils.book_new();
  const aoa = [];
  const mrg = [];

  const aktif    = menus.filter(m => m.aktif == 1 && !m.deleted_at).length;
  const nonaktif = menus.filter(m => m.aktif == 0 && !m.deleted_at).length;
  const hapus    = menus.filter(m => m.deleted_at).length;
  const terjual  = menus.reduce((s, m) => s + (parseInt(m.terjual) || 0), 0);
  const [bBg, bFg, bTxt] = warnaBuka(p.buka);

  // HEADER
  aoa.push([JUDUL('PROFIL PENJUAL & DAFTAR MENU — E-CANTEEN'), ...Array(COL - 1).fill(JUDUL(''))]);
  mrg.push(span(0, 0, 0, COL - 1));
  const rI = aoa.length;
  aoa.push([SUBJUDUL_KIRI('Penjual: ' + (p.nama || '-')), ...Array(COL - 2).fill(cell('', { border: noBd() })), SUBJUDUL_KANAN('Dicetak: ' + nowStr())]);
  mrg.push(span(rI, 0, rI, COL - 2));
  aoa.push(KOSONG(COL));

  // PROFIL
  aoa.push([SEKSI('  PROFIL PENJUAL'), ...Array(COL - 1).fill(SEKSI(''))]);
  mrg.push(span(aoa.length - 1, 0, aoa.length - 1, COL - 1));

  [
    ['Nama Penjual',  p.nama        || '-'],
    ['Username',      p.username    || '-'],
    ['Email',         p.email       || '-'],
    ['No. Telepon',   p.no_tlp      || '-'],
    ['Kantin',        p.nama_kantin || '-'],
  ].forEach(([lb, vl]) => {
    const r = aoa.length;
    aoa.push([LABEL(lb), VAL(String(vl)), ...Array(COL - 2).fill(cell('', { bg:C.white, border:allBd('thin', C.gray200) }))]);
    mrg.push(span(r, 1, r, COL - 1));
  });
  { const r = aoa.length; aoa.push([LABEL('Status Buka'), BADGE(bTxt, bBg, bFg), ...Array(COL - 2).fill(cell('', { bg:C.white, border:allBd('thin', C.gray200) }))]); mrg.push(span(r, 1, r, COL - 1)); }

  aoa.push(KOSONG(COL));

  // STATISTIK MENU
  aoa.push([SEKSI('  STATISTIK MENU'), ...Array(COL - 1).fill(SEKSI(''))]);
  mrg.push(span(aoa.length - 1, 0, aoa.length - 1, COL - 1));
  aoa.push(KOSONG(COL));

  const rH = aoa.length;
  aoa.push([
    statHeader('Total Menu',    C.navy),
    statHeader('',              C.navy),
    statHeader('Aktif',         C.greenMid),
    statHeader('',              C.greenMid),
    statHeader('Nonaktif',      C.orangeMid),
    statHeader('',              C.orangeMid),
    statHeader('Dihapus',       C.redMid),
    statHeader('',              C.redMid),
    statHeader('Total Terjual', C.orange),
  ]);
  aoa.push([
    statValue(menus.length, C.white,  C.navy,        C.navyMid),
    cell('', { bg:C.navy,        border:allBd('medium', C.navyMid) }),
    statValue(aktif,        C.green,  C.greenLight,  C.greenMid),
    cell('', { bg:C.greenLight,  border:allBd('medium', C.greenMid) }),
    statValue(nonaktif,     C.orangeMid, C.orangeLight, C.orangeMid),
    cell('', { bg:C.orangeLight, border:allBd('medium', C.orangeMid) }),
    statValue(hapus,        C.red,    C.redLight,    C.redMid),
    cell('', { bg:C.redLight,    border:allBd('medium', C.redMid) }),
    statValue(terjual,      C.orange, C.orangePale,  C.orange),
  ]);
  [[rH,0],[rH,2],[rH,4],[rH,6],[rH+1,0],[rH+1,2],[rH+1,4],[rH+1,6]]
    .forEach(([r, c]) => mrg.push(span(r, c, r, c + 1)));

  aoa.push(KOSONG(COL));

  // TABEL MENU
  aoa.push([SEKSI('  DAFTAR SEMUA MENU'), ...Array(COL - 1).fill(SEKSI(''))]);
  mrg.push(span(aoa.length - 1, 0, aoa.length - 1, COL - 1));

  aoa.push([
    KOLOM('No'), KOLOM('Nama Menu'),  KOLOM('Kategori'),
    KOLOM('Harga'),  KOLOM('Stok'),   KOLOM('Terjual'),
    KOLOM('Status'), KOLOM('Keterangan'), KOLOM('Terdaftar'),
  ]);

  menus.forEach((m, i) => {
    const isDel = !!m.deleted_at;
    const isNon = !isDel && m.aktif == 0;
    const rowBg = isDel ? C.redPale : (isNon ? C.orangePale : (i % 2 === 1 ? C.navyPale : C.white));
    const stsTxt = isDel ? '✘ Dihapus' : (isNon ? '⊘ Nonaktif' : '✔ Aktif');
    const stsBg  = isDel ? C.redLight  : (isNon ? C.orangeLight  : C.greenLight);
    const stsFg  = isDel ? C.red       : (isNon ? C.orangeMid    : C.green);
    const ket    = isDel ? ('Dihapus: ' + m.deleted_at.slice(0,10)) : (isNon ? 'Dinonaktifkan' : (m.stok==0 ? 'Stok habis' : '—'));
    const [kBg, kFg, kLbl] = warnaKategori(m.kategori);

    aoa.push([
      NO(i + 1, rowBg),
      cell(m.nama_makanan || '-', { sz:10, bold:true, color:C.navy,       bg:rowBg, indent:1, border:allBd('thin', C.gray200) }),
      BADGE(kLbl, kBg, kFg),
      cell(rupiahStr(m.harga), { sz:10, bold:true, color:C.orange,        bg:rowBg, h:'right', indent:1, border:allBd('thin', C.gray200) }),
      cell(parseInt(m.stok)||0, { sz:10, color:m.stok==0?C.red:C.gray800, bg:rowBg, h:'center', border:allBd('thin', C.gray200) }),
      cell(parseInt(m.terjual)||0, { sz:10, bold:true, color:C.orange,    bg:rowBg, h:'center', border:allBd('thin', C.gray200) }),
      BADGE(stsTxt, stsBg, stsFg),
      cell(ket, { sz:9, italic:true, color:C.gray500, bg:rowBg, indent:1, border:allBd('thin', C.gray200) }),
      cell(m.created_at ? m.created_at.slice(0,10) : '-', { sz:9, color:C.gray400, bg:rowBg, h:'center', border:allBd('thin', C.gray200) }),
    ]);
  });

  const rFoot = aoa.length;
  aoa.push([
    TOTAL_LBL('TOTAL TERJUAL'), TOTAL_LBL(''), TOTAL_LBL(''), TOTAL_LBL(''),
    TOTAL_LBL(''), TOTAL_VAL(terjual), TOTAL_LBL(''), TOTAL_LBL(''), TOTAL_LBL(''),
  ]);
  mrg.push(span(rFoot, 0, rFoot, 4));
  mrg.push(span(rFoot, 6, rFoot, COL - 1));

  buatSheet(wb, 'Profil & Menu', aoa, mrg, [5, 28, 12, 16, 8, 10, 14, 24, 14]);
  simpanWb(wb, 'Penjual_' + (p.nama || '').replace(/[^a-zA-Z0-9]/g, '_') + '_' + dateStr() + '.xlsx');
}

// ================================================================
//  3. EKSPOR SEMUA PEMBELI
// ================================================================
function exportPembeli(btn) {
  const data = cache['pembeli'];
  if (!data || data.length === 0) { alert('Data belum dimuat.'); return; }
  setLoading(btn, true);

  const COL = 8;
  const wb  = XLSX.utils.book_new();
  const aoa = [];
  const mrg = [];

  const kelasMap  = {};
  data.forEach(u => { const k = u.kelas || '-'; kelasMap[k] = (kelasMap[k]||0)+1; });
  const totalTrx  = data.reduce((s, u) => s + (parseInt(u.total_transaksi)||0), 0);

  // HEADER
  aoa.push([JUDUL('LAPORAN DATA PEMBELI AKTIF — E-CANTEEN'), ...Array(COL - 1).fill(JUDUL(''))]);
  mrg.push(span(0, 0, 0, COL - 1));
  const rI = aoa.length;
  aoa.push([SUBJUDUL_KIRI('Periode: Seluruh Periode'), ...Array(COL - 2).fill(cell('', { border:noBd() })), SUBJUDUL_KANAN('Dicetak: ' + nowStr())]);
  mrg.push(span(rI, 0, rI, COL - 2));
  aoa.push(KOSONG(COL));

  // KOTAK STATISTIK
  const rH = aoa.length;
  aoa.push([
    statHeader('Total Pembeli',    C.orange),  statHeader('', C.orange),  statHeader('', C.orange),
    statHeader('Total Transaksi',  C.navy),    statHeader('', C.navy),
    statHeader('Jumlah Kelas',     C.greenMid),statHeader('', C.greenMid),statHeader('', C.greenMid),
  ]);
  aoa.push([
    statValue(data.length, C.orange,  C.orangeLight, C.orange),
    cell('', { bg:C.orangeLight, border:allBd('medium', C.orange) }),
    cell('', { bg:C.orangeLight, border:allBd('medium', C.orange) }),
    statValue(totalTrx,    C.white,   C.navy,        C.navyMid),
    cell('', { bg:C.navy,  border:allBd('medium', C.navyMid) }),
    statValue(Object.keys(kelasMap).length, C.green, C.greenLight, C.greenMid),
    cell('', { bg:C.greenLight, border:allBd('medium', C.greenMid) }),
    cell('', { bg:C.greenLight, border:allBd('medium', C.greenMid) }),
  ]);
  aoa.push([
    cell('siswa terdaftar', { sz:8, italic:true, color:C.orange, bg:C.orangeLight, h:'center', border:allBd('thin', C.orange) }),
    cell('', { bg:C.orangeLight, border:allBd('thin', C.orange) }),
    cell('', { bg:C.orangeLight, border:allBd('thin', C.orange) }),
    cell('transaksi',       { sz:8, italic:true, color:C.white,  bg:C.navy,        h:'center', border:allBd('thin', C.navyMid) }),
    cell('', { bg:C.navy,        border:allBd('thin', C.navyMid) }),
    cell('kelas berbeda',   { sz:8, italic:true, color:C.green,  bg:C.greenLight,  h:'center', border:allBd('thin', C.greenMid) }),
    cell('', { bg:C.greenLight,  border:allBd('thin', C.greenMid) }),
    cell('', { bg:C.greenLight,  border:allBd('thin', C.greenMid) }),
  ]);
  [[rH,0],[rH,3],[rH,5],[rH+1,0],[rH+1,3],[rH+1,5],[rH+2,0],[rH+2,3],[rH+2,5]]
    .forEach(([r, c]) => {
      const e = c === 3 ? 1 : 2;
      mrg.push(span(r, c, r, c + e));
    });

  aoa.push(KOSONG(COL));

  // DISTRIBUSI KELAS
  aoa.push([SEKSI('  DISTRIBUSI KELAS'), ...Array(COL - 1).fill(SEKSI(''))]);
  mrg.push(span(aoa.length - 1, 0, aoa.length - 1, COL - 1));

  aoa.push([
    KOLOM('Kelas', C.orange), KOLOM('Jumlah Siswa', C.orange),
    ...Array(COL - 2).fill(KOLOM('', C.orange)),
  ]);
  mrg.push(span(aoa.length - 1, 1, aoa.length - 1, COL - 1));

  Object.entries(kelasMap).sort().forEach(([kelas, jumlah], i) => {
    const rowBg = i % 2 === 1 ? C.navyPale : C.white;
    const r = aoa.length;
    aoa.push([
      BADGE(kelas, C.navyLight, C.navy),
      cell(jumlah + ' siswa', { sz:10, bold:true, color:C.orange, bg:rowBg, indent:1, border:allBd('thin', C.gray200) }),
      ...Array(COL - 2).fill(cell('', { bg:rowBg, border:allBd('thin', C.gray200) })),
    ]);
    mrg.push(span(r, 1, r, COL - 1));
  });

  aoa.push(KOSONG(COL));

  // TABEL PEMBELI
  aoa.push([SEKSI('  DAFTAR PEMBELI AKTIF'), ...Array(COL - 1).fill(SEKSI(''))]);
  mrg.push(span(aoa.length - 1, 0, aoa.length - 1, COL - 1));

  aoa.push([
    KOLOM('No'),    KOLOM('Nama Lengkap'),   KOLOM('Username'),
    KOLOM('Kelas'), KOLOM('Email'),          KOLOM('No. HP'),
    KOLOM('Jenis Kelamin'), KOLOM('Total Transaksi'),
  ]);

  data.forEach((u, i) => {
    const alt   = i % 2 === 1;
    const rowBg = alt ? C.navyPale : C.white;
    const trx   = parseInt(u.total_transaksi) || 0;
    aoa.push([
      NO(i + 1, rowBg),
      cell(u.nama || '-',     { sz:10, bold:true, color:C.navy,   bg:rowBg, indent:1, border:allBd('thin', C.gray200) }),
      cell(u.username || '-', { sz:10, color:C.gray500,           bg:rowBg, indent:1, border:allBd('thin', C.gray200) }),
      BADGE(u.kelas || '-',   C.navyLight, C.navy),
      cell(u.email || '-',    { sz:9,  color:C.gray400,           bg:rowBg, indent:1, border:allBd('thin', C.gray200) }),
      cell(u.no_hp  || '-',   { sz:10, color:C.gray700,           bg:rowBg, h:'center', border:allBd('thin', C.gray200) }),
      cell(u.gender || '-',   { sz:10, color:C.gray600,           bg:rowBg, h:'center', border:allBd('thin', C.gray200) }),
      cell(trx, { sz:11, bold:trx>0, color:trx>0?C.orange:C.gray400, bg:trx>0?C.orangeLight:rowBg, h:'center', border:allBd('thin', C.gray200) }),
    ]);
  });

  const rFoot = aoa.length;
  aoa.push([
    TOTAL_LBL('TOTAL PEMBELI'), TOTAL_LBL(''), TOTAL_LBL(''), TOTAL_LBL(''),
    TOTAL_LBL(''), TOTAL_LBL(''), TOTAL_LBL(''), TOTAL_VAL(data.length),
  ]);
  mrg.push(span(rFoot, 0, rFoot, COL - 2));

  buatSheet(wb, 'Pembeli Aktif', aoa, mrg, [5, 26, 16, 10, 28, 16, 14, 16]);
  simpanWb(wb, 'Laporan_Pembeli_Aktif_' + dateStr() + '.xlsx');
  setLoading(btn, false);
}

// ================================================================
//  4. EKSPOR DETAIL 1 PEMBELI
// ================================================================
function exportPembeliSatu(btn, id, nama) {
  setLoading(btn, true);
  fetch('admin_export_data.php?type=pembeli_detail&id=' + id)
    .then(r => r.json())
    .then(res => {
      if (!res.success) { alert('Gagal memuat data pembeli: ' + (res.message || '')); setLoading(btn, false); return; }
      buatExcelPembeliDetail(res.pembeli, res.transaksis);
      setLoading(btn, false);
    })
    .catch(() => { alert('Gagal menghubungi server.'); setLoading(btn, false); });
}

function buatExcelPembeliDetail(u, transaksis) {
  const COL = 7;
  const wb  = XLSX.utils.book_new();
  const aoa = [];
  const mrg = [];

  const totalBelanja = transaksis.reduce((s, t) => s + (parseFloat(t.total)||0), 0);
  const lunas        = transaksis.filter(t => (t.status||'').toLowerCase() === 'lunas').length;
  const gagal        = transaksis.filter(t => ['ditolak','batal'].includes((t.status||'').toLowerCase())).length;

  // HEADER
  aoa.push([JUDUL('DATA & RIWAYAT PEMBELI — E-CANTEEN'), ...Array(COL - 1).fill(JUDUL(''))]);
  mrg.push(span(0, 0, 0, COL - 1));
  const rI = aoa.length;
  aoa.push([SUBJUDUL_KIRI('Pembeli: ' + (u.nama || '-')), ...Array(COL - 2).fill(cell('', { border:noBd() })), SUBJUDUL_KANAN('Dicetak: ' + nowStr())]);
  mrg.push(span(rI, 0, rI, COL - 2));
  aoa.push(KOSONG(COL));

  // PROFIL
  aoa.push([SEKSI('  PROFIL PEMBELI'), ...Array(COL - 1).fill(SEKSI(''))]);
  mrg.push(span(aoa.length - 1, 0, aoa.length - 1, COL - 1));

  [
    ['Nama Lengkap',  u.nama     || '-'],
    ['Username',      u.username || '-'],
    ['Kelas',         u.kelas    || '-'],
    ['Email',         u.email    || '-'],
    ['No. HP',        u.no_hp    || '-'],
    ['Jenis Kelamin', u.gender   || '-'],
  ].forEach(([lb, vl]) => {
    const r = aoa.length;
    aoa.push([LABEL(lb), VAL(String(vl)), ...Array(COL - 2).fill(cell('', { bg:C.white, border:allBd('thin', C.gray200) }))]);
    mrg.push(span(r, 1, r, COL - 1));
  });

  aoa.push(KOSONG(COL));

  // RINGKASAN TRANSAKSI
  aoa.push([SEKSI('  RINGKASAN TRANSAKSI'), ...Array(COL - 1).fill(SEKSI(''))]);
  mrg.push(span(aoa.length - 1, 0, aoa.length - 1, COL - 1));
  aoa.push(KOSONG(COL));

  const rH = aoa.length;
  aoa.push([
    statHeader('Total Transaksi', C.navy),    statHeader('', C.navy),
    statHeader('Lunas',           C.greenMid),statHeader('', C.greenMid),
    statHeader('Gagal/Batal',     C.redMid),
    statHeader('Total Belanja',   C.orange),  statHeader('', C.orange),
  ]);
  aoa.push([
    statValue(transaksis.length, C.white,   C.navy,        C.navyMid),
    cell('', { bg:C.navy,        border:allBd('medium', C.navyMid) }),
    statValue(lunas,             C.green,   C.greenLight,  C.greenMid),
    cell('', { bg:C.greenLight,  border:allBd('medium', C.greenMid) }),
    statValue(gagal,             C.red,     C.redLight,    C.redMid),
    statValue(rupiahStr(totalBelanja), C.orange, C.orangeLight, C.orange),
    cell('', { bg:C.orangeLight, border:allBd('medium', C.orange) }),
  ]);
  [[rH,0],[rH,2],[rH,5],[rH+1,0],[rH+1,2],[rH+1,5]]
    .forEach(([r, c]) => mrg.push(span(r, c, r, c + 1)));

  aoa.push(KOSONG(COL));

  // RIWAYAT TRANSAKSI
  aoa.push([SEKSI('  RIWAYAT TRANSAKSI'), ...Array(COL - 1).fill(SEKSI(''))]);
  mrg.push(span(aoa.length - 1, 0, aoa.length - 1, COL - 1));

  aoa.push([
    KOLOM('No'), KOLOM('Tanggal'), KOLOM('Nama Menu'),
    KOLOM('Kantin'), KOLOM('Jumlah'), KOLOM('Total'), KOLOM('Status'),
  ]);

  if (!transaksis || transaksis.length === 0) {
    const r = aoa.length;
    aoa.push([cell('Belum ada riwayat transaksi', { sz:10, italic:true, color:C.gray400, h:'center', bg:C.gray50, border:allBd('thin', C.gray200) }), ...Array(COL - 1).fill(cell('', { bg:C.gray50, border:allBd('thin', C.gray200) }))]);
    mrg.push(span(r, 0, r, COL - 1));
  } else {
    transaksis.forEach((tr, i) => {
      const rowBg = i % 2 === 1 ? C.navyPale : C.white;
      const [sBg, sFg, sTxt] = warnaStatus(tr.status);
      aoa.push([
        NO(i + 1, rowBg),
        cell(tr.tanggal ? tr.tanggal.slice(0,10) : '-', { sz:10, color:C.gray500, bg:rowBg, h:'center', border:allBd('thin', C.gray200) }),
        cell(tr.nama_makanan || '-', { sz:10, bold:true, color:C.navy,   bg:rowBg, indent:1, border:allBd('thin', C.gray200) }),
        cell(tr.nama_kantin  || '-', { sz:10, color:C.gray700,           bg:rowBg, indent:1, border:allBd('thin', C.gray200) }),
        cell(parseInt(tr.jumlah)||0, { sz:10, bold:true, color:C.gray800, bg:rowBg, h:'center', border:allBd('thin', C.gray200) }),
        cell(rupiahStr(tr.total), { sz:10, bold:true, color:C.orange,    bg:rowBg, h:'right', indent:1, border:allBd('thin', C.gray200) }),
        BADGE(sTxt, sBg, sFg),
      ]);
    });
    const rFoot = aoa.length;
    aoa.push([
      TOTAL_LBL('TOTAL BELANJA'), TOTAL_LBL(''), TOTAL_LBL(''), TOTAL_LBL(''), TOTAL_LBL(''),
      TOTAL_VAL(rupiahStr(totalBelanja)), TOTAL_LBL(''),
    ]);
    mrg.push(span(rFoot, 0, rFoot, 4));
  }

  buatSheet(wb, 'Data Pembeli', aoa, mrg, [5, 14, 28, 18, 10, 18, 14]);
  simpanWb(wb, 'Pembeli_' + (u.nama || '').replace(/[^a-zA-Z0-9]/g, '_') + '_' + dateStr() + '.xlsx');
}

// ================================================================
//  5. EKSPOR DETAIL 1 MENU
// ================================================================
function exportMenuSatu(btn, id, nama) {
  setLoading(btn, true);
  fetch('admin_export_data.php?type=menu_detail&id=' + id)
    .then(r => r.json())
    .then(res => {
      if (!res.success) { alert('Gagal memuat data menu: ' + (res.message || '')); setLoading(btn, false); return; }
      buatExcelMenuDetail(res.menu, res.transaksis);
      setLoading(btn, false);
    })
    .catch(() => { alert('Gagal menghubungi server.'); setLoading(btn, false); });
}

function buatExcelMenuDetail(m, transaksis) {
  const COL = 7;
  const wb  = XLSX.utils.book_new();
  const aoa = [];
  const mrg = [];

  const isDel = !!m.deleted_at;
  const isNon = !isDel && m.aktif == 0;
  const stsTxt = isDel ? '✘ Dihapus'  : (isNon ? '⊘ Nonaktif' : '✔ Aktif');
  const stsBg  = isDel ? C.redLight   : (isNon ? C.orangeLight : C.greenLight);
  const stsFg  = isDel ? C.red        : (isNon ? C.orangeMid   : C.green);

  const totalPendapatan = transaksis.reduce((s, t) => s + (parseFloat(t.total)||0), 0);
  const lunas           = transaksis.filter(t => (t.status||'').toLowerCase() === 'lunas').length;
  const [kBg, kFg, kLbl] = warnaKategori(m.kategori);

  // HEADER
  aoa.push([JUDUL('DETAIL MENU & RIWAYAT PENJUALAN — E-CANTEEN'), ...Array(COL - 1).fill(JUDUL(''))]);
  mrg.push(span(0, 0, 0, COL - 1));
  const rI = aoa.length;
  aoa.push([SUBJUDUL_KIRI('Menu: ' + (m.nama_makanan || '-')), ...Array(COL - 2).fill(cell('', { border:noBd() })), SUBJUDUL_KANAN('Dicetak: ' + nowStr())]);
  mrg.push(span(rI, 0, rI, COL - 2));
  aoa.push(KOSONG(COL));

  // INFO MENU
  aoa.push([SEKSI('  INFORMASI MENU'), ...Array(COL - 1).fill(SEKSI(''))]);
  mrg.push(span(aoa.length - 1, 0, aoa.length - 1, COL - 1));

  // Nama menu baris besar
  {
    const r = aoa.length;
    aoa.push([
      LABEL('Nama Menu'),
      cell(m.nama_makanan || '-', { sz:14, bold:true, color:C.orange, bg:C.orangePale, indent:1, border:allBd('medium', C.orange) }),
      ...Array(COL - 2).fill(cell('', { bg:C.orangePale, border:allBd('medium', C.orange) })),
    ]);
    mrg.push(span(r, 1, r, COL - 1));
  }

  const infoMenu = [
    ['Kategori',       null, kLbl, kBg, kFg],
    ['Harga',          rupiahStr(m.harga)],
    ['Stok Saat Ini',  parseInt(m.stok)||0],
    ['Total Terjual',  parseInt(m.terjual)||0],
    ['Kantin',         m.nama_kantin || '-'],
    ['Penjual',        (m.nama_penjual||'-') + ' (@' + (m.username_penjual||'-') + ')'],
    ['Waktu Proses',   m.waktu_proses || '-'],
    ['Deskripsi',      m.deskripsi   || '-', null, null, null, true],
    ['Terdaftar',      m.created_at  ? m.created_at.slice(0,10) : '-'],
    ['Status',         null, stsTxt, stsBg, stsFg],
  ];

  infoMenu.forEach(([lb, vl, badgeTxt, badgeBg, badgeFg, wrap]) => {
    const r = aoa.length;
    const valC = badgeTxt
      ? BADGE(badgeTxt, badgeBg, badgeFg)
      : VAL(typeof vl === 'number' ? vl : String(vl), { wrap: !!wrap });
    aoa.push([LABEL(lb), valC, ...Array(COL - 2).fill(cell('', { bg:C.white, border:allBd('thin', C.gray200) }))]);
    mrg.push(span(r, 1, r, COL - 1));
  });

  if (isDel) {
    const r = aoa.length;
    aoa.push([LABEL('Dihapus Pada'), VAL(m.deleted_at.slice(0,10), { color:C.red }), ...Array(COL - 2).fill(cell('', { bg:C.redPale, border:allBd('thin', C.redMid) }))]);
    mrg.push(span(r, 1, r, COL - 1));
  }

  aoa.push(KOSONG(COL));

  // RINGKASAN PENJUALAN
  aoa.push([SEKSI('  RINGKASAN PENJUALAN'), ...Array(COL - 1).fill(SEKSI(''))]);
  mrg.push(span(aoa.length - 1, 0, aoa.length - 1, COL - 1));
  aoa.push(KOSONG(COL));

  const rH = aoa.length;
  aoa.push([
    statHeader('Total Transaksi', C.navy),    statHeader('', C.navy),
    statHeader('Status Selesai',  C.greenMid),statHeader('', C.greenMid),
    statHeader('Total Qty',       C.orangeMid),
    statHeader('Total Pendapatan',C.orange),  statHeader('', C.orange),
  ]);
  aoa.push([
    statValue(transaksis.length,           C.white,  C.navy,        C.navyMid),
    cell('', { bg:C.navy,        border:allBd('medium', C.navyMid) }),
    statValue(lunas,                       C.green,  C.greenLight,  C.greenMid),
    cell('', { bg:C.greenLight,  border:allBd('medium', C.greenMid) }),
    statValue(parseInt(m.terjual)||0,      C.white,  C.orangeMid,   C.orangeMid),
    statValue(rupiahStr(totalPendapatan),  C.orange, C.orangeLight, C.orange),
    cell('', { bg:C.orangeLight, border:allBd('medium', C.orange) }),
  ]);
  aoa.push([
    cell('Semua status', { sz:8, italic:true, color:C.white, bg:C.navy,        h:'center', border:allBd('thin', C.navyMid) }),
    cell('', { bg:C.navy,        border:allBd('thin', C.navyMid) }),
    cell('Semua lunas',  { sz:8, italic:true, color:C.green, bg:C.greenLight,  h:'center', border:allBd('thin', C.greenMid) }),
    cell('', { bg:C.greenLight,  border:allBd('thin', C.greenMid) }),
    cell('item terjual', { sz:8, italic:true, color:C.white, bg:C.orangeMid,   h:'center', border:allBd('thin', C.orangeMid) }),
    cell('dari transaksi lunas', { sz:8, italic:true, color:C.orange, bg:C.orangeLight, h:'center', border:allBd('thin', C.orange) }),
    cell('', { bg:C.orangeLight, border:allBd('thin', C.orange) }),
  ]);
  [[rH,0],[rH,2],[rH,5],[rH+1,0],[rH+1,2],[rH+1,5],[rH+2,0],[rH+2,2],[rH+2,5]]
    .forEach(([r, c]) => mrg.push(span(r, c, r, c + 1)));

  aoa.push(KOSONG(COL));

  // RIWAYAT TRANSAKSI
  aoa.push([SEKSI('  RIWAYAT TRANSAKSI'), ...Array(COL - 1).fill(SEKSI(''))]);
  mrg.push(span(aoa.length - 1, 0, aoa.length - 1, COL - 1));

  aoa.push([
    KOLOM('No'),  KOLOM('Nama Pembeli'),  KOLOM('Kelas'),
    KOLOM('Jumlah'), KOLOM('Total'),      KOLOM('Tanggal'), KOLOM('Status'),
  ]);

  if (!transaksis || transaksis.length === 0) {
    const r = aoa.length;
    aoa.push([cell('Belum ada riwayat transaksi', { sz:10, italic:true, color:C.gray400, h:'center', bg:C.gray50, border:allBd('thin', C.gray200) }), ...Array(COL - 1).fill(cell('', { bg:C.gray50, border:allBd('thin', C.gray200) }))]);
    mrg.push(span(r, 0, r, COL - 1));
  } else {
    transaksis.forEach((tr, i) => {
      const rowBg = i % 2 === 1 ? C.navyPale : C.white;
      const [sBg, sFg, sTxt] = warnaStatus(tr.status);
      aoa.push([
        NO(i + 1, rowBg),
        cell(tr.nama_pembeli || '-', { sz:10, bold:true, color:C.navy,    bg:rowBg, indent:1, border:allBd('thin', C.gray200) }),
        BADGE(tr.kelas || '-',       C.navyLight, C.navy),
        cell(parseInt(tr.jumlah)||0, { sz:10, bold:true, color:C.gray800, bg:rowBg, h:'center', border:allBd('thin', C.gray200) }),
        cell(rupiahStr(tr.total),    { sz:10, bold:true, color:C.orange,  bg:rowBg, h:'right', indent:1, border:allBd('thin', C.gray200) }),
        cell(tr.tanggal ? tr.tanggal.slice(0,10) : '-', { sz:10, color:C.gray500, bg:rowBg, h:'center', border:allBd('thin', C.gray200) }),
        BADGE(sTxt, sBg, sFg),
      ]);
    });
    const rFoot = aoa.length;
    aoa.push([
      TOTAL_LBL('TOTAL TERJUAL'), TOTAL_LBL(''), TOTAL_LBL(''),
      TOTAL_VAL(parseInt(m.terjual)||0), TOTAL_LBL(''), TOTAL_LBL(''), TOTAL_LBL(''),
    ]);
    mrg.push(span(rFoot, 0, rFoot, 2));
    mrg.push(span(rFoot, 4, rFoot, COL - 1));
  }

  buatSheet(wb, 'Detail Menu', aoa, mrg, [5, 28, 14, 10, 18, 14, 14]);
  simpanWb(wb, 'Menu_' + (m.nama_makanan || '').replace(/[^a-zA-Z0-9]/g, '_') + '_' + dateStr() + '.xlsx');
}
</script>
</body>
</html>