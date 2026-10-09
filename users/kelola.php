<?php
session_start();
include "database.php";


if (!isset($_SESSION['username'])) {
  header("Location: admin_login.php");
  exit;
}

$pesan_sukses = "";
$pesan_error  = "";

/* =========================
   APPROVE (status = 1)
========================= */
if (isset($_GET['approve'])) {
    $id = intval($_GET['approve']);
    mysqli_query($db, "UPDATE users SET status=1 WHERE id_user='$id'");
    header("Location: admin_kelola_pembeli.php?sukses=approve");
    exit;
}

/* =========================
   HAPUS / NONAKTIFKAN (status = 3)
========================= */
if (isset($_GET['hapus'])) {
    $id = intval($_GET['hapus']);
    mysqli_query($db, "UPDATE users SET status=3 WHERE id_user='$id'");
    header("Location: admin_kelola_pembeli.php?sukses=hapus");
    exit;
}

/* =========================
   AKTIFKAN KEMBALI (status = 1)
========================= */
if (isset($_GET['aktifkan'])) {
    $id = intval($_GET['aktifkan']);
    mysqli_query($db, "UPDATE users SET status=1 WHERE id_user='$id'");
    header("Location: admin_kelola_pembeli.php?sukses=aktifkan");
    exit;
}

/* =========================
   EDIT (nama, email, password)
========================= */
if (isset($_POST['edit'])) {
    $id    = intval($_POST['edit_id']);
    $nama  = mysqli_real_escape_string($db, $_POST['edit_nama']);
    $email = mysqli_real_escape_string($db, $_POST['edit_email']);
    $pw    = trim($_POST['edit_password']);

    if ($pw !== "") {
        $hashed = md5($pw);
        mysqli_query($db, "UPDATE users SET nama='$nama', email='$email', password='$hashed' WHERE id_user='$id'");
    } else {
        mysqli_query($db, "UPDATE users SET nama='$nama', email='$email' WHERE id_user='$id'");
    }
    header("Location: admin_kelola_pembeli.php?sukses=edit");
    exit;
}

/* =========================
   PESAN
========================= */
if (isset($_GET['sukses'])) {
    if ($_GET['sukses'] == 'approve') $pesan_sukses = "✅ Akun pembeli berhasil di-approve";
    if ($_GET['sukses'] == 'hapus')   $pesan_sukses = "🚫 Akun pembeli berhasil dinonaktifkan";
    if ($_GET['sukses'] == 'edit')    $pesan_sukses = "✏️ Data pembeli berhasil diperbarui";
    if ($_GET['sukses'] == 'aktifkan') $pesan_sukses = "✅ Akun pembeli berhasil diaktifkan kembali";
}

/* =========================
   AMBIL DATA
========================= */
// Menunggu approval (status = 0)
$qPending  = mysqli_query($db, "SELECT * FROM users WHERE status = 0 ORDER BY id_user DESC");
// Sudah approved (status = 1)
$qApproved = mysqli_query($db, "SELECT * FROM users WHERE status = 1 ORDER BY id_user DESC");
// Dinonaktifkan (status = 3)
$qNonaktif = mysqli_query($db, "SELECT * FROM users WHERE status = 3 ORDER BY id_user DESC");
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Kelola Akun Pembeli</title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>

  body.dark{
    --bg:#0e0e16;--bg2:#13131f;--card:#1a1a2b;--card2:#1f1f33;
    --header:rgba(14,14,22,0.92);--text:#f0f0f8;--text-sub:#8888aa;
    --border:rgba(255,255,255,0.07);--primary:#ff7a3d;--primary-soft:rgba(255,122,61,0.15);
    --shadow:rgba(0,0,0,0.5);--nav-hover:rgba(255,255,255,0.06);
    --tr-hover:#1e2a45;--input:#1d2235;--sub:#8888aa;
    --modal-bg:rgba(0,0,0,0.7);
    --pending-bg:#1a1a0e;--pending-border:rgba(234,179,8,0.2);
    --approved-bg:#0e1a0e;--approved-border:rgba(34,197,94,0.2);
  }
  body.light{
    --bg:#f5f5fa;--bg2:#ededf5;--card:#ffffff;--card2:#fafafa;
    --header:rgba(255,255,255,0.95);--text:#111122;--text-sub:#666680;
    --border:rgba(0,0,0,0.07);--primary:#ff6a1f;--primary-soft:rgba(255,106,31,0.1);
    --shadow:rgba(0,0,0,0.07);--nav-hover:rgba(0,0,0,0.05);
    --tr-hover:#fafafa;--input:#ffffff;--sub:#666680;
    --modal-bg:rgba(0,0,0,0.45);
    --pending-bg:#fefce8;--pending-border:rgba(234,179,8,0.3);
    --approved-bg:#f0fdf4;--approved-border:rgba(34,197,94,0.3);
  }

  *{box-sizing:border-box;margin:0;padding:0;font-family:'Plus Jakarta Sans',sans-serif;}
  body{background:var(--bg);min-height:100vh;color:var(--text);transition:background .3s,color .3s;}

  @keyframes fadeUp{from{opacity:0;transform:translateY(16px);}to{opacity:1;transform:translateY(0);}}
  @keyframes fadeDown{from{opacity:0;transform:translateY(-8px);}to{opacity:1;transform:translateY(0);}}
  @keyframes modalIn{from{opacity:0;transform:scale(.95) translateY(12px);}to{opacity:1;transform:scale(1) translateY(0);}}

  /* HEADER */
  header{
    background:var(--header);padding:14px 20px;
    display:flex;justify-content:space-between;align-items:center;
    border-bottom:1px solid var(--border);backdrop-filter:blur(20px);
    -webkit-backdrop-filter:blur(20px);position:sticky;top:0;z-index:50;
  }
  .header-left,.header-right{display:flex;align-items:center;gap:10px;}
  .hamburger{display:none;flex-direction:column;gap:5px;cursor:pointer;padding:7px;border-radius:10px;background:none;border:none;}
  .hamburger span{display:block;width:20px;height:2px;background:var(--primary);border-radius:2px;transition:.3s;}
  .logo{display:flex;align-items:center;gap:9px;font-weight:800;font-size:18px;color:var(--text);letter-spacing:-0.3px;}
  .logo-box{width:34px;height:34px;background:var(--primary);border-radius:9px;display:flex;align-items:center;justify-content:center;color:white;font-size:16px;box-shadow:0 4px 12px rgba(255,106,31,0.35);}
  .nav-menu{display:flex;gap:4px;}
  .nav-menu a{text-decoration:none;color:var(--text-sub);font-size:13.5px;font-weight:500;padding:7px 13px;border-radius:8px;transition:.2s;}
  .nav-menu a:hover{background:var(--nav-hover);color:var(--text);}
  .nav-menu a.active{background:var(--primary-soft);color:var(--primary);}
  .dark-toggle{background:none;border:1.5px solid var(--border);border-radius:20px;padding:6px 14px;cursor:pointer;font-size:12.5px;font-weight:600;color:var(--text-sub);transition:.2s;}
  .dark-toggle:hover{border-color:var(--primary);color:var(--primary);}
  .profile-btn{width:34px;height:34px;background:var(--card2);border-radius:9px;display:flex;align-items:center;justify-content:center;font-size:16px;cursor:pointer;border:1px solid var(--border);transition:.2s;position:relative;}
  .profile-btn:hover{border-color:var(--primary);}
  .profile-dropdown{position:absolute;top:46px;right:0;background:var(--card);width:190px;border-radius:14px;box-shadow:0 12px 36px var(--shadow);padding:14px;display:none;border:1px solid var(--border);z-index:60;animation:fadeDown .25s ease;}
  .profile-name{font-size:13px;font-weight:600;color:var(--text);margin-bottom:3px;}
  .profile-email{font-size:12px;color:var(--text-sub);margin-bottom:12px;word-break:break-all;}
  .logout-btn{display:block;text-decoration:none;background:linear-gradient(135deg,#ff4d4d,#ff6a6a);color:white;padding:8px 12px;border-radius:9px;font-size:13px;font-weight:600;text-align:center;transition:.2s;}
  .logout-btn:hover{opacity:.85;}

  /* MOBILE NAV */
  .nav-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:98;backdrop-filter:blur(3px);}
  .nav-overlay.open{display:block;}
  .mobile-nav{position:fixed;top:0;left:-290px;width:270px;height:100%;background:var(--card);z-index:99;transition:left .3s cubic-bezier(.4,0,.2,1);box-shadow:8px 0 40px rgba(0,0,0,0.25);border-right:1px solid var(--border);overflow-y:auto;display:flex;flex-direction:column;}
  .mobile-nav.open{left:0;}
  .mn-top{padding:20px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;}
  .mn-logo{display:flex;align-items:center;gap:9px;font-weight:800;font-size:17px;color:var(--text);}
  .mn-logo-box{width:32px;height:32px;background:var(--primary);border-radius:8px;display:flex;align-items:center;justify-content:center;color:white;font-size:15px;}
  .mn-close{width:30px;height:30px;border-radius:8px;background:var(--card2);border:1px solid var(--border);color:var(--text-sub);font-size:16px;cursor:pointer;display:flex;align-items:center;justify-content:center;transition:.2s;}
  .mn-close:hover{background:var(--primary-soft);color:var(--primary);}
  .mn-user{margin:16px;padding:14px 16px;background:var(--primary-soft);border-radius:14px;display:flex;align-items:center;gap:11px;border:1px solid rgba(255,106,31,0.15);}
  .mn-avatar{width:38px;height:38px;border-radius:10px;background:var(--primary);display:flex;align-items:center;justify-content:center;font-size:18px;color:white;flex-shrink:0;}
  .mn-user-name{font-size:13.5px;font-weight:700;color:var(--text);}
  .mn-user-role{font-size:11.5px;color:var(--primary);font-weight:500;}
  .mn-section{padding:8px 16px 4px;font-size:10.5px;font-weight:700;letter-spacing:.8px;color:var(--text-sub);text-transform:uppercase;}
  .mn-links{padding:0 12px;}
  .mobile-nav a{display:flex;align-items:center;gap:10px;text-decoration:none;color:var(--text-sub);padding:11px 12px;border-radius:11px;margin-bottom:3px;font-size:14px;font-weight:500;transition:.2s;}
  .mobile-nav a .mn-ico{font-size:18px;}
  .mobile-nav a:hover{background:var(--nav-hover);color:var(--text);}
  .mobile-nav a.active{background:var(--primary-soft);color:var(--primary);font-weight:600;}
  .mn-bottom{margin-top:auto;padding:16px;border-top:1px solid var(--border);display:flex;flex-direction:column;gap:8px;}
  .mn-theme{width:100%;padding:11px;border:1.5px solid var(--border);border-radius:11px;background:none;color:var(--text-sub);font-weight:600;font-size:13.5px;cursor:pointer;transition:.2s;font-family:inherit;}
  .mn-theme:hover{border-color:var(--primary);color:var(--primary);}
  .mn-logout{display:flex;align-items:center;justify-content:center;gap:8px;background:linear-gradient(135deg,#ff4d4d,#ff6a6a);color:white !important;padding:11px;border-radius:11px;font-size:13.5px;font-weight:600;text-decoration:none;transition:.2s;}
  .mn-logout:hover{opacity:.85;}

  /* CONTENT */
  .container{max-width:1100px;margin:auto;padding:30px;animation:fadeUp .6s ease;}
  .title{font-size:24px;font-weight:800;margin-bottom:6px;color:var(--text);letter-spacing:-0.5px;}
  .subtitle{font-size:13.5px;color:var(--text-sub);margin-bottom:24px;}

  /* SECTION CARDS */
  .section-card{
    border-radius:18px;padding:22px;margin-bottom:24px;
    box-shadow:0 8px 20px var(--shadow);
    border:1px solid var(--border);
    background:var(--card);
  }
  .section-card.pending{border-top:3px solid #eab308;}
  .section-card.approved{border-top:3px solid #22c55e;}

  .section-header{
    display:flex;justify-content:space-between;align-items:center;
    margin-bottom:16px;flex-wrap:wrap;gap:8px;
  }
  .section-title{font-size:15px;font-weight:800;color:var(--text);display:flex;align-items:center;gap:8px;}
  .badge{
    padding:4px 12px;border-radius:20px;font-size:12px;font-weight:700;
  }
  .badge-pending{background:#fef9c3;color:#854d0e;}
  body.dark .badge-pending{background:rgba(234,179,8,0.15);color:#fcd34d;}
  .badge-approved{background:#dcfce7;color:#166534;}
  body.dark .badge-approved{background:rgba(34,197,94,0.15);color:#4ade80;}

  /* TABLE */
  .table-wrapper{width:100%;overflow-x:auto;-webkit-overflow-scrolling:touch;}
  table{width:100%;border-collapse:collapse;min-width:520px;}
  th{background:var(--primary);color:white;padding:11px 12px;font-size:13px;white-space:nowrap;font-weight:600;}
  th:first-child{border-radius:10px 0 0 0;}th:last-child{border-radius:0 10px 0 0;}
  td{padding:11px 12px;border-bottom:1px solid var(--border);font-size:13.5px;color:var(--text);}
  tr:hover td{background:var(--tr-hover);}
  tr:last-child td{border-bottom:none;}

  /* ACTION BUTTONS */
  .action-wrap{display:flex;gap:6px;flex-wrap:wrap;}
  .btn-approve{
    background:linear-gradient(135deg,#22c55e,#16a34a);color:white;
    padding:5px 11px;border-radius:8px;text-decoration:none;
    font-size:12px;font-weight:700;white-space:nowrap;transition:.2s;
    display:inline-flex;align-items:center;gap:4px;
  }
  .btn-approve:hover{opacity:.85;transform:translateY(-1px);}
  .btn-edit{
    background:linear-gradient(135deg,#3b82f6,#2563eb);color:white;
    padding:5px 11px;border-radius:8px;text-decoration:none;
    font-size:12px;font-weight:700;white-space:nowrap;transition:.2s;
    display:inline-flex;align-items:center;gap:4px;cursor:pointer;border:none;font-family:inherit;
  }
  .btn-edit:hover{opacity:.85;transform:translateY(-1px);}
  .btn-hapus{
    background:linear-gradient(135deg,#ff4d4d,#ff6a6a);color:white;
    padding:5px 11px;border-radius:8px;text-decoration:none;
    font-size:12px;font-weight:700;white-space:nowrap;transition:.2s;
    display:inline-flex;align-items:center;gap:4px;
  }
  .btn-hapus:hover{opacity:.85;transform:translateY(-1px);}

  /* EMPTY STATE */
  .empty-state{text-align:center;padding:30px 20px;color:var(--text-sub);}
  .empty-state .es-icon{font-size:36px;margin-bottom:10px;}
  .empty-state p{font-size:13.5px;}

  /* PESAN */
  .pesan-sukses{background:#d1fae5;color:#065f46;padding:12px 16px;border-radius:12px;margin-bottom:20px;font-size:13px;font-weight:600;}
  body.dark .pesan-sukses{background:rgba(34,197,94,0.12);color:#4ade80;}

  /* MODAL */
  .modal-overlay{display:none;position:fixed;inset:0;background:var(--modal-bg);z-index:200;align-items:center;justify-content:center;backdrop-filter:blur(4px);padding:16px;}
  .modal-overlay.open{display:flex;}
  .modal-box{background:var(--card);border-radius:20px;padding:28px;width:100%;max-width:480px;border:1px solid var(--border);box-shadow:0 24px 60px var(--shadow);animation:modalIn .3s ease;}
  .modal-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:22px;}
  .modal-title{font-size:17px;font-weight:800;color:var(--text);}
  .modal-close{width:32px;height:32px;border-radius:9px;background:var(--card2);border:1px solid var(--border);color:var(--text-sub);font-size:17px;cursor:pointer;display:flex;align-items:center;justify-content:center;transition:.2s;}
  .modal-close:hover{background:var(--primary-soft);color:var(--primary);}
  .form-group{margin-bottom:16px;}
  label{display:block;margin-bottom:7px;font-size:13px;font-weight:700;color:var(--sub);}
  input{width:100%;padding:11px 14px;border-radius:12px;border:1.5px solid var(--border);background:var(--input);color:var(--text);font-size:14px;font-family:inherit;transition:border-color .2s;}
  input:focus{outline:none;border-color:var(--primary);}
  .form-submit-btn{margin-top:6px;width:100%;border:none;background:linear-gradient(135deg,#3b82f6,#2563eb);color:white;padding:12px;border-radius:13px;font-size:14px;font-weight:700;cursor:pointer;font-family:inherit;transition:.2s;}
  .form-submit-btn:hover{opacity:.9;}
  .hint{font-size:11.5px;color:var(--text-sub);margin-top:5px;}

  .btn-kembali{display:inline-block;margin-top:8px;text-decoration:none;color:var(--text-sub);font-size:13.5px;transition:.2s;}
  .btn-kembali:hover{color:var(--primary);}

  @media(max-width:768px){
    header{padding:12px 16px;}
    .hamburger{display:flex;}
    .nav-menu,.dark-toggle,.profile-btn{display:none !important;}
    .container{padding:16px;}
    .title{font-size:20px;}
    .section-card{padding:16px;}
    th,td{padding:9px 8px;font-size:12.5px;}
  }
  @media(max-width:400px){.logo span{display:none;}}

</style>
</head>
<body class="light">

<div class="nav-overlay" id="navOverlay"></div>

<div class="mobile-nav" id="mobileNav">
  <div class="mn-top">
    <div class="mn-logo"><div class="mn-logo-box">🏠</div> E-Canteen</div>
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
    <a href="admin_beranda.php"><span class="mn-ico">🏠</span> Dashboard</a>
    <a href="admin_kelola_penjual.php"><span class="mn-ico">🛒</span> Penjual</a>
    <a href="admin_kelola_pembeli.php" class="active"><span class="mn-ico">👥</span> Pembeli</a>
    <a href="admin_kelola_makanan.php"><span class="mn-ico">🍔</span> Makanan</a>
  </div>
  <div class="mn-bottom">
    <button class="mn-theme" onclick="toggleDark()" id="toggleBtnMobile">🌙 Dark Mode</button>
    <a href="admin_login.php" class="mn-logout">🚪 Logout</a>
  </div>
</div>

<header>
  <div class="header-left">
    <button class="hamburger" onclick="openNav()"><span></span><span></span><span></span></button>
    <div class="logo"><div class="logo-box">🏠</div><span>E-Canteen</span></div>
  </div>
  <div class="header-right">
    <div class="nav-menu">
      <a href="admin_beranda.php">Dashboard</a>
      <a href="admin_kelola_penjual.php">Kelola Penjual</a>
      <a href="admin_kelola_pembeli.php" class="active">Kelola Pembeli</a>
      <a href="admin_kelola_makanan.php">Kelola Makanan</a>
    </div>
    <button class="dark-toggle" onclick="toggleDark()" id="toggleBtn">🌙 Dark</button>
    <div class="profile-btn" onclick="toggleProfile()" style="position:relative;">
      👤
      <div class="profile-dropdown" id="profileBox">
        <div class="profile-name"><?php echo $_SESSION['username']; ?></div>
        <div class="profile-email">Administrator</div>
        <a href="admin_login.php" class="logout-btn">Logout</a>
      </div>
    </div>
  </div>
</header>

<div class="container">

  <div class="title">Kelola Akun Pembeli</div>
  <div class="subtitle">Approve pendaftaran baru dan kelola akun pembeli aktif</div>

  <?php if($pesan_sukses): ?>
    <div class="pesan-sukses"><?= $pesan_sukses ?></div>
  <?php endif; ?>

  <!-- ===== TABEL MENUNGGU APPROVAL ===== -->
  <div class="section-card pending">
    <div class="section-header">
      <div class="section-title">
        ⏳ Menunggu Approval
      </div>
      <span class="badge badge-pending">
        <?php
        $cntP = mysqli_num_rows($qPending);
        echo $cntP . " Pembeli";
        ?>
      </span>
    </div>
    <div class="table-wrapper">
      <table>
        <tr>
          <th>No</th>
          <th>Nama</th>
          <th>Username</th>
          <th>Email</th>
          <th>Aksi</th>
        </tr>
        <?php if ($cntP == 0): ?>
        <tr><td colspan="5">
          <div class="empty-state">
            <div class="es-icon">🎉</div>
            <p>Tidak ada akun yang menunggu approval.</p>
          </div>
        </td></tr>
        <?php else: $no=1; while($row=mysqli_fetch_assoc($qPending)): ?>
        <tr>
          <td><?= $no++ ?></td>
          <td><?= htmlspecialchars($row['nama']) ?></td>
          <td><?= htmlspecialchars($row['username']) ?></td>
          <td><?= htmlspecialchars($row['email']) ?></td>
          <td>
            <div class="action-wrap">
              <a class="btn-approve"
                 href="?approve=<?= $row['id_user'] ?>"
                 onclick="return confirm('Approve akun <?= addslashes($row['nama']) ?>?')">
                ✅ Approve
              </a>
              <a class="btn-hapus"
                 href="?hapus=<?= $row['id_user'] ?>"
                 onclick="return confirm('Tolak & nonaktifkan akun ini?')">
                ❌ Tolak
              </a>
            </div>
          </td>
        </tr>
        <?php endwhile; endif; ?>
      </table>
    </div>
  </div>

  <!-- ===== TABEL SUDAH DIAPPROVE ===== -->
  <div class="section-card approved">
    <div class="section-header">
      <div class="section-title">
        ✅ Sudah Di-Approve
      </div>
      <span class="badge badge-approved">
        <?php
        $cntA = mysqli_num_rows($qApproved);
        echo $cntA . " Pembeli";
        ?>
      </span>
    </div>
    <div class="table-wrapper">
      <table>
        <tr>
          <th>No</th>
          <th>Nama</th>
          <th>Username</th>
          <th>Email</th>
          <th>Aksi</th>
        </tr>
        <?php if ($cntA == 0): ?>
        <tr><td colspan="5">
          <div class="empty-state">
            <div class="es-icon">👥</div>
            <p>Belum ada pembeli yang di-approve.</p>
          </div>
        </td></tr>
        <?php else: $no=1; while($row=mysqli_fetch_assoc($qApproved)): ?>
        <tr>
          <td><?= $no++ ?></td>
          <td><?= htmlspecialchars($row['nama']) ?></td>
          <td><?= htmlspecialchars($row['username']) ?></td>
          <td><?= htmlspecialchars($row['email']) ?></td>
          <td>
            <div class="action-wrap">
              <button class="btn-edit"
                onclick="bukaEdit(<?= $row['id_user'] ?>, '<?= addslashes(htmlspecialchars($row['nama'])) ?>', '<?= addslashes(htmlspecialchars($row['email'])) ?>')">
                ✏️ Edit
              </button>
              <a class="btn-hapus"
                 href="?hapus=<?= $row['id_user'] ?>"
                 onclick="return confirm('Nonaktifkan akun <?= addslashes($row['nama']) ?>?\n\nData tidak akan dihapus dari database.')">
                🚫 Nonaktifkan
              </a>
            </div>
          </td>
        </tr>
        <?php endwhile; endif; ?>
      </table>
    </div>
  </div>

  <a class="btn-kembali" href="admin_beranda.php">⬅ Kembali ke Dashboard</a>

  <!-- ===== TABEL DINONAKTIFKAN ===== -->
  <div class="section-card" style="border-top:3px solid #ef4444;margin-top:24px;">
    <div class="section-header">
      <div class="section-title">
        🚫 Akun Dinonaktifkan
      </div>
      <span class="badge" style="background:#fee2e2;color:#991b1b;">
        <?php
        $cntN = mysqli_num_rows($qNonaktif);
        echo $cntN . " Pembeli";
        ?>
      </span>
    </div>
    <div class="table-wrapper">
      <table>
        <tr>
          <th>No</th>
          <th>Nama</th>
          <th>Username</th>
          <th>Email</th>
          <th>Aksi</th>
        </tr>
        <?php if ($cntN == 0): ?>
        <tr><td colspan="5">
          <div class="empty-state">
            <div class="es-icon">✔️</div>
            <p>Tidak ada akun yang dinonaktifkan.</p>
          </div>
        </td></tr>
        <?php else: $no=1; while($row=mysqli_fetch_assoc($qNonaktif)): ?>
        <tr>
          <td><?= $no++ ?></td>
          <td><?= htmlspecialchars($row['nama']) ?></td>
          <td><?= htmlspecialchars($row['username']) ?></td>
          <td><?= htmlspecialchars($row['email']) ?></td>
          <td>
            <div class="action-wrap">
              <a class="btn-approve"
                 href="?aktifkan=<?= $row['id_user'] ?>"
                 onclick="return confirm('Aktifkan kembali akun <?= addslashes($row['nama']) ?>?')">
                ✅ Aktifkan
              </a>
            </div>
          </td>
        </tr>
        <?php endwhile; endif; ?>
      </table>
    </div>
  </div>

</div>

<!-- ===== MODAL EDIT ===== -->
<div class="modal-overlay" id="modalEdit">
  <div class="modal-box">
    <div class="modal-header">
      <div class="modal-title">✏️ Edit Data Pembeli</div>
      <button class="modal-close" onclick="tutupEdit()">✕</button>
    </div>
    <form method="post">
      <input type="hidden" name="edit_id" id="edit_id">
      <div class="form-group">
        <label>Nama Lengkap</label>
        <input type="text" name="edit_nama" id="edit_nama" placeholder="Nama lengkap" required>
      </div>
      <div class="form-group">
        <label>Email</label>
        <input type="email" name="edit_email" id="edit_email" placeholder="Email" required>
      </div>
      <div class="form-group">
        <label>Password Baru <span style="color:var(--text-sub);font-weight:400;">(kosongkan jika tidak diubah)</span></label>
        <input type="password" name="edit_password" id="edit_password" placeholder="Password baru...">
        <div class="hint">💡 Biarkan kosong jika password tidak ingin diubah.</div>
      </div>
      <button type="submit" name="edit" class="form-submit-btn">💾 Simpan Perubahan</button>
    </form>
  </div>
</div>

<script>
/* THEME */
function toggleDark(){
  const body=document.body,btn=document.getElementById('toggleBtn'),btnM=document.getElementById('toggleBtnMobile');
  if(body.classList.contains('light')){
    body.classList.replace('light','dark');
    if(btn)btn.textContent='☀️ Light';if(btnM)btnM.textContent='☀️ Light Mode';
    localStorage.setItem('theme','dark');
  }else{
    body.classList.replace('dark','light');
    if(btn)btn.textContent='🌙 Dark';if(btnM)btnM.textContent='🌙 Dark Mode';
    localStorage.setItem('theme','light');
  }
}
window.onload=function(){
  const saved=localStorage.getItem('theme');
  if(saved==='dark'){
    document.body.classList.replace('light','dark');
    const btn=document.getElementById('toggleBtn'),btnM=document.getElementById('toggleBtnMobile');
    if(btn)btn.textContent='☀️ Light';if(btnM)btnM.textContent='☀️ Light Mode';
  }
}

/* PROFILE */
function toggleProfile(){const box=document.getElementById('profileBox');box.style.display=box.style.display==='block'?'none':'block';}
window.addEventListener('click',function(e){if(!e.target.closest('.profile-btn')){const box=document.getElementById('profileBox');if(box&&box.style.display==='block')box.style.display='none';}});

/* MOBILE NAV */
function openNav(){document.getElementById('mobileNav').classList.add('open');document.getElementById('navOverlay').classList.add('open');document.body.style.overflow='hidden';}
function closeNav(){document.getElementById('mobileNav').classList.remove('open');document.getElementById('navOverlay').classList.remove('open');document.body.style.overflow='';}
document.getElementById('navOverlay').addEventListener('click',closeNav);

/* MODAL EDIT */
function bukaEdit(id,nama,email){
  document.getElementById('edit_id').value=id;
  document.getElementById('edit_nama').value=nama;
  document.getElementById('edit_email').value=email;
  document.getElementById('edit_password').value='';
  document.getElementById('modalEdit').classList.add('open');
  document.body.style.overflow='hidden';
}
function tutupEdit(){
  document.getElementById('modalEdit').classList.remove('open');
  document.body.style.overflow='';
}
document.getElementById('modalEdit').addEventListener('click',function(e){if(e.target===this)tutupEdit();});
</script>
</body>
</html>