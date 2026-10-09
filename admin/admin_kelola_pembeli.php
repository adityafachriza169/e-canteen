<?php
// ══════════════════════════════════════════════════════════════════════════
// PATCH: Tambah Akun Pembeli
// Tambahkan kode di bawah ini ke file admin_kelola_pembeli.php Anda.
// ══════════════════════════════════════════════════════════════════════════

session_start();
include "database.php";
require_once 'cookie_cek_login.php';

// Cek cookie jika session tidak ada
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

// ── AJAX: ambil transaksi per user ──────────────────────────────────────────
if (isset($_GET['ajax_transaksi'])) {
    $uid = intval($_GET['ajax_transaksi']);
    $q = mysqli_query($db,
        "SELECT t.*, u.nama AS nama_user, u.username
         FROM transaksi t
         JOIN users u ON u.id_user = t.id_user
         WHERE t.id_user = '$uid'
         ORDER BY t.tanggal DESC"
    );
    $rows = [];
    $total_semua = 0;
    $total_lunas = 0;
    while ($r = mysqli_fetch_assoc($q)) {
        $rows[] = $r;
        $total_semua += $r['total'];
        if ($r['status'] === 'lunas') $total_lunas += $r['total'];
    }
    header('Content-Type: application/json');
    echo json_encode([
        'rows'        => $rows,
        'total_semua' => $total_semua,
        'total_lunas' => $total_lunas,
        'jumlah'      => count($rows),
    ]);
    exit;
}
// ─────────────────────────────────────────────────────────────────────────────

$pesan_sukses = "";
$pesan_error  = "";

// ── [BARU] Tambah Pembeli ────────────────────────────────────────────────────
if (isset($_POST['tambah_pembeli'])) {
    $nama          = mysqli_real_escape_string($db, trim($_POST['tb_nama']));
    $username      = mysqli_real_escape_string($db, trim($_POST['tb_username']));
    $email         = mysqli_real_escape_string($db, trim($_POST['tb_email']));
    $pw            = trim($_POST['tb_password']);
    $kelas         = mysqli_real_escape_string($db, trim($_POST['tb_kelas']));
    $no_hp         = mysqli_real_escape_string($db, trim($_POST['tb_no_hp']));
    $gender        = mysqli_real_escape_string($db, $_POST['tb_gender']);
    $tanggal_lahir = mysqli_real_escape_string($db, trim($_POST['tb_tanggal_lahir']));
    $hashed        = md5($pw);

    // Cek duplikat username
    $cekUser = mysqli_query($db, "SELECT id_user FROM users WHERE username='$username'");
    if (mysqli_num_rows($cekUser) > 0) {
        $pesan_error = "❌ Username '<b>$username</b>' sudah digunakan. Silakan pilih username lain.";
    } else {
        $q = mysqli_query($db,
            "INSERT INTO users (username, nama, email, password, role, kelas, no_hp, gender, tanggal_lahir, status)
             VALUES ('$username','$nama','$email','$hashed', 2, '$kelas','$no_hp','$gender',
             " . ($tanggal_lahir ? "'$tanggal_lahir'" : "NULL") . ", 1)"
        );
        if ($q) {
            header("Location: admin_kelola_pembeli.php?sukses=tambah");
            exit;
        } else {
            $pesan_error = "❌ Gagal menambah pembeli. Silakan coba lagi.";
        }
    }
}
// ─────────────────────────────────────────────────────────────────────────────

// Approve
if (isset($_GET['approve'])) {
    $id = intval($_GET['approve']);
    mysqli_query($db, "UPDATE users SET status=1, tanggal_nonaktif=NULL WHERE id_user='$id'");
    header("Location: admin_kelola_pembeli.php?sukses=approve");
    exit;
}

// Nonaktifkan — catat tanggal_nonaktif
if (isset($_GET['hapus'])) {
    $id  = intval($_GET['hapus']);
    $now = date('Y-m-d H:i:s');
    mysqli_query($db, "UPDATE users SET status=0, tanggal_nonaktif='$now' WHERE id_user='$id'");
    header("Location: admin_kelola_pembeli.php?sukses=hapus");
    exit;
}

// Hapus Permanen (soft-delete)
if (isset($_GET['hapus_permanen'])) {
    $id  = intval($_GET['hapus_permanen']);
    $now = date('Y-m-d H:i:s');
    mysqli_query($db, "UPDATE users SET deleted_at='$now' WHERE id_user='$id'");
    header("Location: admin_kelola_pembeli.php?sukses=hapus_permanen");
    exit;
}

// Edit
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

if (isset($_GET['sukses'])) {
    if ($_GET['sukses'] == 'approve')         $pesan_sukses = "✅ Akun pembeli berhasil di-approve";
    if ($_GET['sukses'] == 'hapus')           $pesan_sukses = "🚫 Akun pembeli berhasil dinonaktifkan";
    if ($_GET['sukses'] == 'edit')            $pesan_sukses = "✏️ Data pembeli berhasil diperbarui";
    if ($_GET['sukses'] == 'hapus_permanen')  $pesan_sukses = "🗑️ Akun berhasil dihapus permanen (tersembunyi dari admin)";
    // [BARU]
    if ($_GET['sukses'] == 'tambah')          $pesan_sukses = "➕ Akun pembeli baru berhasil ditambahkan";
}

// Semua query wajib tambah WHERE deleted_at IS NULL
$qPending  = mysqli_query($db, "SELECT * FROM users WHERE (status IS NULL OR status = 2) AND (deleted_at IS NULL) ORDER BY id_user DESC");
$qApproved = mysqli_query($db, "SELECT * FROM users WHERE status = 1 AND (deleted_at IS NULL) ORDER BY id_user DESC");
$qNonaktif = mysqli_query($db, "SELECT * FROM users WHERE status = 0 AND (deleted_at IS NULL) ORDER BY id_user DESC");

$cntP = mysqli_num_rows($qPending);
$cntA = mysqli_num_rows($qApproved);
$cntN = mysqli_num_rows($qNonaktif);

// Kumpulkan semua kelas unik
$qKelas = mysqli_query($db, "SELECT DISTINCT kelas FROM users WHERE kelas IS NOT NULL AND kelas != '' AND deleted_at IS NULL ORDER BY kelas ASC");
$daftarKelas = [];
while ($rk = mysqli_fetch_assoc($qKelas)) {
    $daftarKelas[] = $rk['kelas'];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Kelola Akun Pembeli</title>
<link rel="stylesheet" href="../custom_confirm.css">
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
    --nonaktif-bg:#1a0e0e;--nonaktif-border:rgba(239,68,68,0.2);
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
    --nonaktif-bg:#fff1f2;--nonaktif-border:rgba(239,68,68,0.3);
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
  .logo-box{width:48px;height:48px;border-radius:9px;display:flex;align-items:center;justify-content:center;}
  .logo-box img{width:48px;height:48px;object-fit:contain;border-radius:4px;}
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
  .mn-logo-box{width:48px;height:48px;border-radius:8px;display:flex;align-items:center;justify-content:center;}
  .mn-logo-box img{width:48px;height:48px;object-fit:contain;border-radius:4px;}
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
  .container{max-width:1200px;margin:auto;padding:30px;animation:fadeUp .6s ease;}
  .title{font-size:24px;font-weight:800;margin-bottom:6px;color:var(--text);letter-spacing:-0.5px;}
  .subtitle{font-size:13.5px;color:var(--text-sub);margin-bottom:24px;}

  /* PAGE HEADER ROW */
  .page-header-row{display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:24px;flex-wrap:wrap;gap:12px;}
  .page-header-row .title{margin-bottom:4px;}
  .btn-tambah-pembeli{
    display:inline-flex;align-items:center;gap:8px;
    background:linear-gradient(135deg,#ff7a3d,#ff6a1f);
    color:white;padding:10px 20px;border-radius:12px;
    font-size:13.5px;font-weight:700;border:none;cursor:pointer;
    font-family:inherit;transition:.2s;white-space:nowrap;
    box-shadow:0 4px 14px rgba(255,122,61,0.35);
  }
  .btn-tambah-pembeli:hover{opacity:.88;transform:translateY(-1px);box-shadow:0 6px 18px rgba(255,122,61,0.45);}

  /* FILTER BAR */
  .filter-bar{
    background:var(--card);border:1px solid var(--border);border-radius:16px;
    padding:16px 20px;margin-bottom:24px;
    box-shadow:0 4px 16px var(--shadow);
    display:flex;flex-wrap:wrap;gap:12px;align-items:flex-end;
  }
  .filter-bar-title{
    width:100%;font-size:12px;font-weight:700;color:var(--text-sub);
    text-transform:uppercase;letter-spacing:.7px;margin-bottom:-4px;
    display:flex;align-items:center;gap:6px;
  }
  .filter-item{display:flex;flex-direction:column;gap:6px;flex:1;min-width:180px;}
  .filter-item label{font-size:12px;font-weight:700;color:var(--text-sub);}
  .search-input-wrap{position:relative;}
  .search-input-wrap .search-ico{position:absolute;left:12px;top:50%;transform:translateY(-50%);font-size:14px;pointer-events:none;color:var(--text-sub);}
  .search-input-wrap input{width:100%;padding:10px 12px 10px 36px;border-radius:11px;border:1.5px solid var(--border);background:var(--input);color:var(--text);font-size:13.5px;font-family:inherit;transition:border-color .2s;}
  .search-input-wrap input:focus{outline:none;border-color:var(--primary);}
  .search-input-wrap input::placeholder{color:var(--text-sub);}
  .filter-select{width:100%;padding:10px 12px;border-radius:11px;border:1.5px solid var(--border);background:var(--input);color:var(--text);font-size:13.5px;font-family:inherit;cursor:pointer;transition:border-color .2s;appearance:none;background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%23888' stroke-width='2.5'%3E%3Cpath d='M6 9l6 6 6-6'/%3E%3C/svg%3E");background-repeat:no-repeat;background-position:right 12px center;padding-right:32px;}
  .filter-select:focus{outline:none;border-color:var(--primary);}
  .filter-actions{display:flex;gap:8px;align-items:flex-end;flex-shrink:0;}
  .btn-reset-filter{padding:10px 16px;border-radius:11px;border:1.5px solid var(--border);background:none;color:var(--text-sub);font-size:13px;font-weight:600;cursor:pointer;font-family:inherit;transition:.2s;white-space:nowrap;display:flex;align-items:center;gap:6px;}
  .btn-reset-filter:hover{border-color:var(--primary);color:var(--primary);}
  .result-chip{display:inline-flex;align-items:center;gap:6px;background:var(--primary-soft);color:var(--primary);padding:4px 12px;border-radius:20px;font-size:12px;font-weight:700;transition:.3s;}

  /* SECTION CARDS */
  .section-card{border-radius:18px;padding:22px;margin-bottom:24px;box-shadow:0 8px 20px var(--shadow);border:1px solid var(--border);background:var(--card);}
  .section-card.pending{border-top:3px solid #eab308;}
  .section-card.approved{border-top:3px solid #22c55e;}
  .section-card.nonaktif{border-top:3px solid #ef4444;}
  .section-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;flex-wrap:wrap;gap:8px;}
  .section-title{font-size:15px;font-weight:800;color:var(--text);display:flex;align-items:center;gap:8px;}
  .section-count{font-size:12px;font-weight:700;padding:4px 12px;border-radius:20px;}
  .sc-pending{background:#fef9c3;color:#854d0e;}
  body.dark .sc-pending{background:rgba(234,179,8,0.15);color:#fcd34d;}
  .sc-approved{background:#dcfce7;color:#166534;}
  body.dark .sc-approved{background:rgba(34,197,94,0.15);color:#4ade80;}
  .sc-nonaktif{background:#fee2e2;color:#991b1b;}
  body.dark .sc-nonaktif{background:rgba(239,68,68,0.15);color:#fca5a5;}

  /* TABLE */
  .table-wrapper{width:100%;overflow-x:auto;-webkit-overflow-scrolling:touch;}
  table{width:100%;border-collapse:collapse;min-width:620px;}
  th{background:var(--primary);color:white;padding:11px 12px;font-size:13px;white-space:nowrap;font-weight:600;}
  th:first-child{border-radius:10px 0 0 0;}th:last-child{border-radius:0 10px 0 0;}
  td{padding:11px 12px;border-bottom:1px solid var(--border);font-size:13.5px;color:var(--text);}
  tr:hover td{background:var(--tr-hover);}
  tr:last-child td{border-bottom:none;}
  .no-result-row td{text-align:center;padding:30px;color:var(--text-sub);font-size:13.5px;}
  .no-result-row .nr-ico{font-size:30px;display:block;margin-bottom:8px;}

  /* Tanggal nonaktif cell */
  .tgl-nonaktif{font-size:12px;color:var(--text-sub);white-space:nowrap;}
  .tgl-nonaktif.ada{color:#ef4444;font-weight:600;}
  body.dark .tgl-nonaktif.ada{color:#fca5a5;}

  /* HIGHLIGHT */
  mark{background:rgba(255,122,61,0.25);color:var(--primary);border-radius:3px;padding:0 2px;font-weight:700;}
  body.light mark{background:rgba(255,106,31,0.15);}

  /* STATUS BADGE */
  .status-aktif{display:inline-flex;align-items:center;gap:4px;background:#dcfce7;color:#166534;padding:3px 10px;border-radius:20px;font-size:11.5px;font-weight:700;}
  .status-nonaktif{display:inline-flex;align-items:center;gap:4px;background:#fee2e2;color:#991b1b;padding:3px 10px;border-radius:20px;font-size:11.5px;font-weight:700;}
  .status-pending{display:inline-flex;align-items:center;gap:4px;background:#fef9c3;color:#854d0e;padding:3px 10px;border-radius:20px;font-size:11.5px;font-weight:700;}
  body.dark .status-aktif{background:rgba(34,197,94,0.15);color:#4ade80;}
  body.dark .status-nonaktif{background:rgba(239,68,68,0.15);color:#fca5a5;}
  body.dark .status-pending{background:rgba(234,179,8,0.15);color:#fcd34d;}

  /* ACTION BUTTONS */
  .action-wrap{display:flex;gap:6px;flex-wrap:wrap;}
  .btn-approve{background:linear-gradient(135deg,#22c55e,#16a34a);color:white;padding:5px 11px;border-radius:8px;text-decoration:none;font-size:12px;font-weight:700;white-space:nowrap;transition:.2s;display:inline-flex;align-items:center;gap:4px;}
  .btn-approve:hover{opacity:.85;transform:translateY(-1px);}
  .btn-edit{background:linear-gradient(135deg,#3b82f6,#2563eb);color:white;padding:5px 11px;border-radius:8px;text-decoration:none;font-size:12px;font-weight:700;white-space:nowrap;transition:.2s;display:inline-flex;align-items:center;gap:4px;cursor:pointer;border:none;font-family:inherit;}
  .btn-edit:hover{opacity:.85;transform:translateY(-1px);}
  .btn-hapus{background:linear-gradient(135deg,#ff4d4d,#ff6a6a);color:white;padding:5px 11px;border-radius:8px;text-decoration:none;font-size:12px;font-weight:700;white-space:nowrap;transition:.2s;display:inline-flex;align-items:center;gap:4px;}
  .btn-hapus:hover{opacity:.85;transform:translateY(-1px);}
  .btn-transaksi{background:linear-gradient(135deg,#8b5cf6,#7c3aed);color:white;padding:5px 11px;border-radius:8px;border:none;font-size:12px;font-weight:700;white-space:nowrap;transition:.2s;display:inline-flex;align-items:center;gap:4px;cursor:pointer;font-family:inherit;}
  .btn-transaksi:hover{opacity:.85;transform:translateY(-1px);}
  .btn-reaktif{background:linear-gradient(135deg,#22c55e,#16a34a);color:white;padding:5px 11px;border-radius:8px;text-decoration:none;font-size:12px;font-weight:700;white-space:nowrap;transition:.2s;display:inline-flex;align-items:center;gap:4px;}
  .btn-reaktif:hover{opacity:.85;transform:translateY(-1px);}
  .btn-hapus-permanen{
    background:linear-gradient(135deg,#7f1d1d,#b91c1c);
    color:white;padding:5px 11px;border-radius:8px;text-decoration:none;
    font-size:12px;font-weight:700;white-space:nowrap;transition:.2s;
    display:inline-flex;align-items:center;gap:4px;
  }
  .btn-hapus-permanen:hover{opacity:.85;transform:translateY(-1px);}

  /* EMPTY STATE */
  .empty-state{text-align:center;padding:30px 20px;color:var(--text-sub);}
  .empty-state .es-icon{font-size:36px;margin-bottom:10px;}
  .empty-state p{font-size:13.5px;}

  /* PESAN */
  .pesan-sukses{background:#d1fae5;color:#065f46;padding:12px 16px;border-radius:12px;margin-bottom:20px;font-size:13px;font-weight:600;}
  body.dark .pesan-sukses{background:rgba(34,197,94,0.12);color:#4ade80;}
  .pesan-error{background:#fee2e2;color:#991b1b;padding:12px 16px;border-radius:12px;margin-bottom:20px;font-size:13px;font-weight:600;}
  body.dark .pesan-error{background:rgba(239,68,68,0.12);color:#fca5a5;}

  /* MODAL EDIT */
  .modal-overlay{display:none;position:fixed;inset:0;background:var(--modal-bg);z-index:200;align-items:center;justify-content:center;backdrop-filter:blur(4px);padding:16px;}
  .modal-overlay.open{display:flex;}
  .modal-box{background:var(--card);border-radius:20px;padding:28px;width:100%;max-width:480px;border:1px solid var(--border);box-shadow:0 24px 60px var(--shadow);animation:modalIn .3s ease;}
  .modal-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:22px;}
  .modal-title{font-size:17px;font-weight:800;color:var(--text);}
  .modal-close{width:32px;height:32px;border-radius:9px;background:var(--card2);border:1px solid var(--border);color:var(--text-sub);font-size:17px;cursor:pointer;display:flex;align-items:center;justify-content:center;transition:.2s;}
  .modal-close:hover{background:var(--primary-soft);color:var(--primary);}
  .form-group{margin-bottom:16px;}
  label{display:block;margin-bottom:7px;font-size:13px;font-weight:700;color:var(--sub);}
  input[type=text],input[type=email],input[type=password],input[type=date],input[type=tel],select.form-select{width:100%;padding:11px 14px;border-radius:12px;border:1.5px solid var(--border);background:var(--input);color:var(--text);font-size:14px;font-family:inherit;transition:border-color .2s;}
  input:focus,select.form-select:focus{outline:none;border-color:var(--primary);}
  select.form-select{appearance:none;background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%23888' stroke-width='2.5'%3E%3Cpath d='M6 9l6 6 6-6'/%3E%3C/svg%3E");background-repeat:no-repeat;background-position:right 14px center;padding-right:36px;cursor:pointer;}
  .form-submit-btn{margin-top:6px;width:100%;border:none;background:linear-gradient(135deg,#3b82f6,#2563eb);color:white;padding:12px;border-radius:13px;font-size:14px;font-weight:700;cursor:pointer;font-family:inherit;transition:.2s;}
  .form-submit-btn:hover{opacity:.9;}
  .hint{font-size:11.5px;color:var(--text-sub);margin-top:5px;}
  .btn-kembali{display:inline-block;margin-top:8px;text-decoration:none;color:var(--text-sub);font-size:13.5px;transition:.2s;}
  .btn-kembali:hover{color:var(--primary);}

  /* MODAL TAMBAH — scrollable, 2 kolom grid */
  .modal-box-wide{
    background:var(--card);border-radius:20px;padding:0;width:100%;max-width:680px;
    border:1px solid var(--border);box-shadow:0 24px 60px var(--shadow);
    animation:modalIn .3s ease;display:flex;flex-direction:column;
    max-height:90vh;overflow:hidden;
  }
  .mb-head{padding:22px 26px;border-bottom:1px solid var(--border);display:flex;justify-content:space-between;align-items:center;flex-shrink:0;}
  .mb-body{padding:22px 26px;overflow-y:auto;flex:1;}
  .mb-foot{padding:16px 26px;border-top:1px solid var(--border);flex-shrink:0;}
  .form-grid{display:grid;grid-template-columns:1fr 1fr;gap:16px 20px;}
  .form-grid .full{grid-column:1/-1;}
  .form-divider{grid-column:1/-1;height:1px;background:var(--border);margin:4px 0;}
  .form-section-label{grid-column:1/-1;font-size:11px;font-weight:800;letter-spacing:.8px;text-transform:uppercase;color:var(--text-sub);margin-bottom:-4px;}
  .btn-simpan-tambah{width:100%;border:none;background:linear-gradient(135deg,#ff7a3d,#ff6a1f);color:white;padding:13px;border-radius:13px;font-size:14.5px;font-weight:700;cursor:pointer;font-family:inherit;transition:.2s;box-shadow:0 4px 14px rgba(255,122,61,0.3);}
  .btn-simpan-tambah:hover{opacity:.9;}
  .required-star{color:#ef4444;margin-left:2px;}
  .pw-toggle-wrap{position:relative;}
  .pw-toggle-wrap input{padding-right:42px;}
  .pw-eye{position:absolute;right:13px;top:50%;transform:translateY(-50%);cursor:pointer;font-size:16px;color:var(--text-sub);user-select:none;}

  /* ── MODAL KONFIRMASI SIMPAN ── */
  .modal-konfirm-overlay{
    display:none;position:fixed;inset:0;
    background:rgba(0,0,0,0.6);z-index:500;
    align-items:center;justify-content:center;
    backdrop-filter:blur(6px);padding:16px;
  }
  .modal-konfirm-overlay.open{display:flex;}
  @keyframes konfirmIn{
    from{opacity:0;transform:scale(.85) translateY(20px);}
    to{opacity:1;transform:scale(1) translateY(0);}
  }
  .konfirm-box{
    background:var(--card);border-radius:24px;
    width:100%;max-width:400px;
    border:1px solid rgba(255,122,61,0.3);
    box-shadow:0 0 0 4px rgba(255,122,61,0.1), 0 24px 60px rgba(0,0,0,0.4);
    animation:konfirmIn .35s cubic-bezier(.34,1.56,.64,1);
    overflow:hidden;
  }
  .konfirm-top{
    background:linear-gradient(135deg,#ff7a3d,#ff6a1f);
    padding:28px 24px 24px;text-align:center;position:relative;
  }
  .konfirm-icon-wrap{
    width:64px;height:64px;border-radius:50%;
    background:rgba(255,255,255,0.2);
    display:flex;align-items:center;justify-content:center;
    margin:0 auto 14px;font-size:30px;
    border:3px solid rgba(255,255,255,0.35);
    box-shadow:0 4px 16px rgba(0,0,0,0.15);
  }
  .konfirm-judul{font-size:18px;font-weight:800;color:white;margin-bottom:4px;}
  .konfirm-sub{font-size:13px;color:rgba(255,255,255,0.85);font-weight:500;}
  .konfirm-body{padding:22px 24px;}
  .konfirm-info{
    background:var(--primary-soft);border:1px solid rgba(255,122,61,0.2);
    border-radius:14px;padding:14px 16px;margin-bottom:18px;
  }
  .konfirm-row{display:flex;justify-content:space-between;align-items:center;padding:5px 0;font-size:13px;}
  .konfirm-row:not(:last-child){border-bottom:1px solid rgba(255,122,61,0.12);}
  .konfirm-label{color:var(--text-sub);font-weight:600;}
  .konfirm-val{color:var(--text);font-weight:700;text-align:right;max-width:200px;word-break:break-word;}
  .konfirm-warn{
    display:flex;align-items:center;gap:8px;
    background:rgba(234,179,8,0.1);border:1px solid rgba(234,179,8,0.25);
    border-radius:10px;padding:10px 13px;margin-bottom:18px;
    font-size:12px;color:#92400e;font-weight:600;
  }
  body.dark .konfirm-warn{color:#fcd34d;background:rgba(234,179,8,0.08);}
  .konfirm-actions{display:flex;gap:10px;}
  .btn-konfirm-batal{
    flex:1;padding:12px;border-radius:13px;
    border:1.5px solid var(--border);background:none;
    color:var(--text-sub);font-size:14px;font-weight:700;
    cursor:pointer;font-family:inherit;transition:.2s;
  }
  .btn-konfirm-batal:hover{border-color:var(--primary);color:var(--primary);}
  .btn-konfirm-ok{
    flex:2;padding:12px;border-radius:13px;border:none;
    background:linear-gradient(135deg,#ff7a3d,#ff6a1f);
    color:white;font-size:14px;font-weight:700;
    cursor:pointer;font-family:inherit;transition:.2s;
    box-shadow:0 4px 14px rgba(255,122,61,0.4);
  }
  .btn-konfirm-ok:hover{opacity:.9;transform:translateY(-1px);}

  /* MODAL TRANSAKSI */
  #modalTrx{z-index:300;}
  .trx-box{background:var(--card);border-radius:20px;padding:0;width:100%;max-width:860px;max-height:90vh;border:1px solid var(--border);box-shadow:0 24px 60px var(--shadow);animation:modalIn .3s ease;display:flex;flex-direction:column;overflow:hidden;}
  .trx-header{padding:20px 24px;border-bottom:1px solid var(--border);display:flex;justify-content:space-between;align-items:center;flex-shrink:0;background:var(--card);}
  .trx-title{font-size:17px;font-weight:800;color:var(--text);}
  .trx-subtitle{font-size:12px;color:var(--text-sub);margin-top:2px;}
  .trx-body{padding:20px 24px;overflow-y:auto;flex:1;}
  .trx-summary{display:grid;grid-template-columns:repeat(3,1fr);gap:12px;margin-bottom:20px;}
  .trx-stat{background:var(--card2);border:1px solid var(--border);border-radius:14px;padding:14px 16px;}
  .trx-stat-label{font-size:11.5px;color:var(--text-sub);font-weight:600;margin-bottom:4px;}
  .trx-stat-value{font-size:18px;font-weight:800;color:var(--text);}
  .trx-stat-value.green{color:#22c55e;}
  .trx-stat-value.purple{color:#8b5cf6;}
  .trx-table-wrap{overflow-x:auto;}
  table.trx-tbl{min-width:620px;}
  table.trx-tbl th{font-size:12px;padding:9px 10px;}
  table.trx-tbl td{font-size:12.5px;padding:9px 10px;}
  .ts{display:inline-block;padding:2px 9px;border-radius:20px;font-size:11px;font-weight:700;}
  .ts-lunas{background:#dcfce7;color:#166534;}
  .ts-batal{background:#fee2e2;color:#991b1b;}
  .ts-ditolak{background:#fef9c3;color:#854d0e;}
  .ts-pending{background:#dbeafe;color:#1e40af;}
  .ts-dimasak{background:#fce7f3;color:#9d174d;}
  body.dark .ts-lunas{background:rgba(34,197,94,0.15);color:#4ade80;}
  body.dark .ts-batal{background:rgba(239,68,68,0.15);color:#fca5a5;}
  body.dark .ts-ditolak{background:rgba(234,179,8,0.15);color:#fcd34d;}
  body.dark .ts-pending{background:rgba(59,130,246,0.15);color:#93c5fd;}
  body.dark .ts-dimasak{background:rgba(236,72,153,0.15);color:#f9a8d4;}
  .metode-cash{display:inline-block;padding:2px 8px;background:var(--primary-soft);color:var(--primary);border-radius:6px;font-size:11px;font-weight:700;}
  .metode-qris{display:inline-block;padding:2px 8px;background:rgba(139,92,246,0.12);color:#8b5cf6;border-radius:6px;font-size:11px;font-weight:700;}
  .trx-loading{text-align:center;padding:40px;color:var(--text-sub);}
  .trx-loading .ld-ico{font-size:32px;margin-bottom:10px;animation:spin 1s linear infinite;display:inline-block;}
  @keyframes spin{to{transform:rotate(360deg);}}
  .trx-empty{text-align:center;padding:40px;color:var(--text-sub);}
  .trx-empty .te-ico{font-size:40px;margin-bottom:10px;}

  @media(max-width:768px){
    header{padding:12px 16px;}
    .hamburger{display:flex;}
    .nav-menu,.dark-toggle,.profile-btn{display:none !important;}
    .container{padding:16px;}
    .title{font-size:20px;}
    .section-card{padding:16px;}
    th,td{padding:9px 8px;font-size:12.5px;}
    .trx-summary{grid-template-columns:1fr 1fr;}
    .trx-box{max-height:95vh;}
    .trx-header,.trx-body{padding:16px;}
    .filter-bar{padding:14px 16px;}
    .filter-item{min-width:140px;}
    .form-grid{grid-template-columns:1fr;}
    .form-grid .full{grid-column:1;}
    .form-section-label{grid-column:1;}
    .form-divider{grid-column:1;}
    .modal-box-wide{max-width:100%;}
    .mb-head,.mb-body,.mb-foot{padding:16px;}
    .page-header-row{gap:8px;}
    .btn-tambah-pembeli{font-size:12.5px;padding:9px 14px;}
  }
  @media(max-width:400px){.logo span{display:none;}}
</style>
</head>
<body class="light">

<div class="nav-overlay" id="navOverlay"></div>

<!-- MOBILE NAV -->
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
    <a href="admin_beranda.php"><span class="mn-ico">🏠</span> Dashboard</a>
    <a href="admin_kelola_penjual.php"><span class="mn-ico">🛒</span> Penjual</a>
    <a href="admin_kelola_pembeli.php" class="active"><span class="mn-ico">👥</span> Pembeli</a>
    <a href="admin_kelola_makanan.php"><span class="mn-ico">🍔</span> Makanan</a>
  </div>
  <div class="mn-bottom">
    <button class="mn-theme" onclick="toggleDark()" id="toggleBtnMobile">🌙 Dark Mode</button>
    <a href="logout.php" class="mn-logout">🚪 Logout</a>
  </div>
</div>

<!-- HEADER -->
<header>
  <div class="header-left">
    <button class="hamburger" onclick="openNav()"><span></span><span></span><span></span></button>
    <div class="logo">
      <div class="logo-box"><img src="../gambar/logo.png" alt="Logo"></div>
      <span>E-Canteen</span>
    </div>
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
        <a href="logout.php" class="logout-btn">Logout</a>
      </div>
    </div>
  </div>
</header>

<div class="container">

  <!-- PAGE HEADER ROW (title + tombol tambah) -->
  <div class="page-header-row">
    <div>
      <div class="title">Kelola Akun Pembeli</div>
      <div class="subtitle">Approve pendaftaran baru dan kelola akun pembeli aktif</div>
    </div>
    <button class="btn-tambah-pembeli" onclick="bukaTambah()">➕ Tambah Pembeli</button>
  </div>

  <?php if($pesan_sukses): ?>
    <div class="pesan-sukses"><?= $pesan_sukses ?></div>
  <?php endif; ?>
  <?php if($pesan_error): ?>
    <div class="pesan-error"><?= $pesan_error ?></div>
  <?php endif; ?>

  <!-- SEARCH & FILTER BAR -->
  <div class="filter-bar">
    <div class="filter-bar-title">🔍 Cari & Filter Pembeli</div>
    <div class="filter-item">
      <label for="searchNama">Nama / Username</label>
      <div class="search-input-wrap">
        <input type="text" id="searchNama" placeholder="Ketik nama atau username..." oninput="applyFilter()">
      </div>
    </div>
    <div class="filter-item" style="max-width:180px;">
      <label for="filterKelas">Kelas</label>
      <select id="filterKelas" class="filter-select" onchange="applyFilter()">
        <option value="">Semua Kelas</option>
        <?php foreach($daftarKelas as $kls): ?>
          <option value="<?= htmlspecialchars($kls) ?>"><?= htmlspecialchars($kls) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="filter-item" style="max-width:180px;">
      <label for="filterStatus">Status</label>
      <select id="filterStatus" class="filter-select" onchange="applyFilter()">
        <option value="">Semua Status</option>
        <option value="pending">⏳ Menunggu Approval</option>
        <option value="approved">✅ Aktif / Approved</option>
        <option value="nonaktif">🚫 Nonaktif</option>
      </select>
    </div>
    <div class="filter-actions">
      <button class="btn-reset-filter" onclick="resetFilter()">↺ Reset</button>
    </div>
    <div style="display:flex;align-items:flex-end;">
      <span class="result-chip" id="resultChip">👥 Menghitung...</span>
    </div>
  </div>

  <!-- ═══ TABEL MENUNGGU APPROVAL ═══ -->
  <div class="section-card pending" id="sectionPending">
    <div class="section-header">
      <div class="section-title">⏳ Menunggu Approval</div>
      <span class="section-count sc-pending" id="countPending">0 Pembeli</span>
    </div>
    <div class="table-wrapper">
      <table id="tablePending">
        <tr>
          <th>No</th><th>Nama</th><th>Username</th><th>Email</th><th>Kelas</th><th>Status</th><th>Aksi</th>
        </tr>
        <?php if ($cntP == 0): ?>
        <tr class="empty-orig"><td colspan="7">
          <div class="empty-state"><div class="es-icon">🎉</div><p>Tidak ada akun yang menunggu approval.</p></div>
        </td></tr>
        <?php else: $no=1; while($row=mysqli_fetch_assoc($qPending)): ?>
        <tr
          data-nama="<?= strtolower(htmlspecialchars($row['nama'])) ?>"
          data-username="<?= strtolower(htmlspecialchars($row['username'])) ?>"
          data-kelas="<?= strtolower(htmlspecialchars($row['kelas'] ?? '')) ?>"
          data-status="pending"
        >
          <td class="col-no"><?= $no++ ?></td>
          <td class="col-nama"><?= htmlspecialchars($row['nama']) ?></td>
          <td class="col-username"><?= htmlspecialchars($row['username']) ?></td>
          <td><?= htmlspecialchars($row['email']) ?></td>
          <td class="col-kelas"><?= htmlspecialchars($row['kelas'] ?? '-') ?></td>
          <td><span class="status-pending">⏳ Menunggu</span></td>
          <td>
            <div class="action-wrap">
              <a class="btn-approve" href="?approve=<?= $row['id_user'] ?>"
                 onclick="confirmPopup({ judul:'Approve Akun', ikon:'✅', pesan:'Approve akun <?= addslashes($row['nama']) ?>?', poin:['Pembeli dapat login dan memesan'], tipe:'success', labelOK:'Ya, Approve', url:this.href }); return false;">✅ Approve</a>
              <button class="btn-transaksi" onclick="lihatTrx(<?= $row['id_user'] ?>, '<?= addslashes(htmlspecialchars($row['nama'])) ?>', '<?= addslashes(htmlspecialchars($row['username'])) ?>')">📊 Transaksi</button>
              <a class="btn-hapus" href="?hapus=<?= $row['id_user'] ?>"
                 onclick="confirmPopup({ judul:'Tolak Akun', ikon:'❌', pesan:'Tolak & nonaktifkan akun ini?', poin:['Pembeli tidak dapat login'], tipe:'danger', labelOK:'Ya, Tolak', url:this.href }); return false;">❌ Tolak</a>
            </div>
          </td>
        </tr>
        <?php endwhile; endif; ?>
      </table>
    </div>
  </div>

  <!-- ═══ TABEL SUDAH DIAPPROVE ═══ -->
  <div class="section-card approved" id="sectionApproved">
    <div class="section-header">
      <div class="section-title">✅ Sudah Di-Approve</div>
      <span class="section-count sc-approved" id="countApproved">0 Pembeli</span>
    </div>
    <div class="table-wrapper">
      <table id="tableApproved">
        <tr>
          <th>No</th><th>Nama</th><th>Username</th><th>Email</th><th>Kelas</th><th>Status</th><th>Aksi</th>
        </tr>
        <?php if ($cntA == 0): ?>
        <tr class="empty-orig"><td colspan="7">
          <div class="empty-state"><div class="es-icon">👥</div><p>Belum ada pembeli yang di-approve.</p></div>
        </td></tr>
        <?php else: $no=1; while($row=mysqli_fetch_assoc($qApproved)): ?>
        <tr
          data-nama="<?= strtolower(htmlspecialchars($row['nama'])) ?>"
          data-username="<?= strtolower(htmlspecialchars($row['username'])) ?>"
          data-kelas="<?= strtolower(htmlspecialchars($row['kelas'] ?? '')) ?>"
          data-status="approved"
        >
          <td class="col-no"><?= $no++ ?></td>
          <td class="col-nama"><?= htmlspecialchars($row['nama']) ?></td>
          <td class="col-username"><?= htmlspecialchars($row['username']) ?></td>
          <td><?= htmlspecialchars($row['email']) ?></td>
          <td class="col-kelas"><?= htmlspecialchars($row['kelas'] ?? '-') ?></td>
          <td><span class="status-aktif">✅ Aktif</span></td>
          <td>
            <div class="action-wrap">
              <button class="btn-edit"
                onclick="bukaEdit(<?= $row['id_user'] ?>, '<?= addslashes(htmlspecialchars($row['nama'])) ?>', '<?= addslashes(htmlspecialchars($row['email'])) ?>')">
                ✏️ Edit
              </button>
              <button class="btn-transaksi" onclick="lihatTrx(<?= $row['id_user'] ?>, '<?= addslashes(htmlspecialchars($row['nama'])) ?>', '<?= addslashes(htmlspecialchars($row['username'])) ?>')">📊 Transaksi</button>
              <a class="btn-hapus" href="?hapus=<?= $row['id_user'] ?>"
                 onclick="confirmPopup({ judul:'Nonaktifkan Akun', ikon:'🚫', pesan:'Nonaktifkan akun <?= addslashes($row['nama']) ?>?', poin:['Pembeli tidak bisa login sementara','Data tidak akan dihapus dari database','Bisa diaktifkan kembali kapan saja'], tipe:'warning', labelOK:'Ya, Nonaktifkan', url:this.href }); return false;">
                🚫 Nonaktifkan
              </a>
            </div>
          </td>
        </tr>
        <?php endwhile; endif; ?>
      </table>
    </div>
  </div>

  <!-- ═══ TABEL NONAKTIF ═══ -->
  <div class="section-card nonaktif" id="sectionNonaktif">
    <div class="section-header">
      <div class="section-title">🚫 Akun Nonaktif</div>
      <span class="section-count sc-nonaktif" id="countNonaktif">0 Pembeli</span>
    </div>
    <div class="table-wrapper">
      <table id="tableNonaktif">
        <tr>
          <th>No</th><th>Nama</th><th>Username</th><th>Email</th><th>Kelas</th><th>Tgl Nonaktif</th><th>Status</th><th>Aksi</th>
        </tr>
        <?php if ($cntN == 0): ?>
        <tr class="empty-orig"><td colspan="8">
          <div class="empty-state"><div class="es-icon">🎉</div><p>Tidak ada akun yang nonaktif.</p></div>
        </td></tr>
        <?php else: $no=1; while($row=mysqli_fetch_assoc($qNonaktif)): ?>
        <?php
          $tglNonaktif = '-';
          $tglClass    = 'tgl-nonaktif';
          if (!empty($row['tanggal_nonaktif'])) {
              $tglNonaktif = date('d M Y H:i', strtotime($row['tanggal_nonaktif']));
              $tglClass    = 'tgl-nonaktif ada';
          }
        ?>
        <tr
          data-nama="<?= strtolower(htmlspecialchars($row['nama'])) ?>"
          data-username="<?= strtolower(htmlspecialchars($row['username'])) ?>"
          data-kelas="<?= strtolower(htmlspecialchars($row['kelas'] ?? '')) ?>"
          data-status="nonaktif"
        >
          <td class="col-no"><?= $no++ ?></td>
          <td class="col-nama"><?= htmlspecialchars($row['nama']) ?></td>
          <td class="col-username"><?= htmlspecialchars($row['username']) ?></td>
          <td><?= htmlspecialchars($row['email']) ?></td>
          <td class="col-kelas"><?= htmlspecialchars($row['kelas'] ?? '-') ?></td>
          <td><span class="<?= $tglClass ?>"><?= $tglNonaktif ?></span></td>
          <td><span class="status-nonaktif">🚫 Nonaktif</span></td>
          <td>
            <div class="action-wrap">
              <a class="btn-reaktif" href="?approve=<?= $row['id_user'] ?>"
                 onclick="confirmPopup({ judul:'Aktifkan Kembali', ikon:'🔄', pesan:'Aktifkan kembali akun <?= addslashes($row['nama']) ?>?', poin:['Pembeli dapat login dan memesan kembali'], tipe:'success', labelOK:'Ya, Aktifkan', url:this.href }); return false;">🔄 Aktifkan</a>
              <button class="btn-transaksi" onclick="lihatTrx(<?= $row['id_user'] ?>, '<?= addslashes(htmlspecialchars($row['nama'])) ?>', '<?= addslashes(htmlspecialchars($row['username'])) ?>')">📊 Transaksi</button>
              <a class="btn-hapus-permanen" href="?hapus_permanen=<?= $row['id_user'] ?>"
                 onclick="confirmPopup({ judul:'Hapus Permanen Akun', ikon:'🗑️', pesan:'Hapus permanen akun <?= addslashes($row['nama']) ?>?', poin:['Akun akan hilang dari panel admin','Data di database tetap tersimpan (soft-delete)','Tindakan ini tidak bisa dibatalkan'], tipe:'danger', labelOK:'Ya, Hapus Permanen', url:this.href }); return false;">
                🗑️ Hapus Permanen
              </a>
            </div>
          </td>
        </tr>
        <?php endwhile; endif; ?>
      </table>
    </div>
  </div>

  <a class="btn-kembali" href="admin_beranda.php">⬅ Kembali ke Dashboard</a>

</div>

<!-- ═══════════════════════════════════════════════════
     MODAL EDIT
═══════════════════════════════════════════════════ -->
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

<!-- ═══════════════════════════════════════════════════
     MODAL TAMBAH PEMBELI BARU
═══════════════════════════════════════════════════ -->
<div class="modal-overlay" id="modalTambah">
  <div class="modal-box-wide">
    <!-- Header -->
    <div class="mb-head">
      <div>
        <div class="modal-title">➕ Tambah Akun Pembeli</div>
        <div style="font-size:12px;color:var(--text-sub);margin-top:3px;">Akun akan langsung aktif (status: Approved)</div>
      </div>
      <button class="modal-close" onclick="tutupTambah()">✕</button>
    </div>

    <!-- Body / Form -->
    <div class="mb-body">
      <form method="post" id="formTambah">
        <div class="form-grid">

          <!-- ─ SECTION: Informasi Akun ─ -->
          <div class="form-section-label">🔐 Informasi Akun</div>
          <div class="form-divider"></div>

          <!-- Nama -->
          <div class="form-group full">
            <label>Nama Lengkap <span class="required-star">*</span></label>
            <input type="text" name="tb_nama" placeholder="Contoh: Budi Santoso" required>
          </div>

          <!-- Username -->
          <div class="form-group">
            <label>Username <span class="required-star">*</span></label>
            <input type="text" name="tb_username" placeholder="Contoh: budi123" required
                   pattern="[a-zA-Z0-9_]+" title="Hanya huruf, angka, dan underscore">
            <div class="hint">Hanya huruf, angka, dan _ (tanpa spasi)</div>
          </div>

          <!-- Email -->
          <div class="form-group">
            <label>Email <span class="required-star">*</span></label>
            <input type="email" name="tb_email" placeholder="contoh@email.com" required
                   pattern="[a-zA-Z0-9._%+\-]+@gmail\.com"
                   title="Email harus menggunakan @gmail.com, contoh: nama@gmail.com"
                   oninput="validateEmail(this)">
            <div class="hint" id="emailHint" style="display:none;color:#ef4444;">⚠️ Format email tidak valid (contoh: nama@gmail.com)</div>
          </div>

          <!-- Password -->
          <div class="form-group full">
            <label>Password <span class="required-star">*</span></label>
            <div class="pw-toggle-wrap">
              <input type="password" name="tb_password" id="tb_password" placeholder="Minimal 6 karakter" required minlength="6">
              <span class="pw-eye" onclick="togglePw('tb_password','eyeIcon1')" id="eyeIcon1">👁️</span>
            </div>
          </div>

          <!-- ─ SECTION: Data Pribadi ─ -->
          <div class="form-section-label" style="margin-top:8px;">👤 Data Pribadi</div>
          <div class="form-divider"></div>

          <!-- Kelas -->
          <div class="form-group">
            <label>Kelas</label>
            <select name="tb_kelas" class="form-select">
              <option value="">-- Pilih Kelas --</option>
              <optgroup label="── Kelas 10 ──">
                <option value="10 TKI 1">10 TKI 1</option>
                <option value="10 TKI 2">10 TKI 2</option>
                <option value="10 RPL 1">10 RPL 1</option>
                <option value="10 RPL 2">10 RPL 2</option>
                <option value="10 ULW">10 ULW</option>
                <option value="10 MP 1">10 MP 1</option>
                <option value="10 MP 2">10 MP 2</option>
                <option value="10 MP 3">10 MP 3</option>
                <option value="10 MP 4">10 MP 4</option>
                <option value="10 AK 1">10 AK 1</option>
                <option value="10 AK 2">10 AK 2</option>
                <option value="10 AK 3">10 AK 3</option>
                <option value="10 AK 4">10 AK 4</option>
                <option value="10 DKV 1">10 DKV 1</option>
                <option value="10 DKV 2">10 DKV 2</option>
                <option value="10 PSPT 1">10 PSPT 1</option>
                <option value="10 PSPT 2">10 PSPT 2</option>
                <option value="10 TKJ 1">10 TKJ 1</option>
                <option value="10 TKJ 2">10 TKJ 2</option>
                <option value="10 Animasi 1">10 Animasi 1</option>
                <option value="10 Animasi 2">10 Animasi 2</option>
                <option value="10 BD 1">10 BD 1</option>
                <option value="10 BD 2">10 BD 2</option>
                <option value="10 BD 3">10 BD 3</option>
              </optgroup>
              <optgroup label="── Kelas 11 ──">
                <option value="11 TKI 1">11 TKI 1</option>
                <option value="11 TKI 2">11 TKI 2</option>
                <option value="11 RPL 1">11 RPL 1</option>
                <option value="11 RPL 2">11 RPL 2</option>
                <option value="11 ULW">11 ULW</option>
                <option value="11 MP 1">11 MP 1</option>
                <option value="11 MP 2">11 MP 2</option>
                <option value="11 MP 3">11 MP 3</option>
                <option value="11 MP 4">11 MP 4</option>
                <option value="11 AK 1">11 AK 1</option>
                <option value="11 AK 2">11 AK 2</option>
                <option value="11 AK 3">11 AK 3</option>
                <option value="11 AK 4">11 AK 4</option>
                <option value="11 DKV 1">11 DKV 1</option>
                <option value="11 DKV 2">11 DKV 2</option>
                <option value="11 PSPT 1">11 PSPT 1</option>
                <option value="11 PSPT 2">11 PSPT 2</option>
                <option value="11 TKJ 1">11 TKJ 1</option>
                <option value="11 TKJ 2">11 TKJ 2</option>
                <option value="11 Animasi 1">11 Animasi 1</option>
                <option value="11 Animasi 2">11 Animasi 2</option>
                <option value="11 BD 1">11 BD 1</option>
                <option value="11 BD 2">11 BD 2</option>
                <option value="11 BD 3">11 BD 3</option>
              </optgroup>
              <optgroup label="── Lainnya ──">
                <option value="Guru">Guru</option>
                <option value="Staf Karyawan">Staf Karyawan</option>
              </optgroup>
            </select>
          </div>

          <!-- No HP -->
          <div class="form-group">
            <label>No. HP</label>
            <input type="tel" name="tb_no_hp" placeholder="Contoh: 081234567890"
                   pattern="[0-9]{9,15}" inputmode="numeric"
                   oninput="this.value=this.value.replace(/[^0-9]/g,'')"
                   title="Nomor HP hanya boleh angka (9–15 digit)">
          </div>

          <!-- Gender -->
          <div class="form-group">
            <label>Jenis Kelamin</label>
            <select name="tb_gender" class="form-select">
              <option value="">-- Pilih --</option>
              <option value="Laki-laki">Laki-laki</option>
              <option value="Perempuan">Perempuan</option>
            </select>
          </div>

          <!-- Tanggal Lahir -->
          <div class="form-group">
            <label>Tanggal Lahir</label>
            <input type="date" name="tb_tanggal_lahir" id="tb_tanggal_lahir"
                   oninput="validateTglLahir(this)">
            <div class="hint" id="tglLahirHint" style="display:none;color:#ef4444;">⚠️ Usia minimal 14 tahun untuk mendaftar.</div>
          </div>

        </div><!-- /form-grid -->
      </form>
    </div>

    <!-- Footer -->
    <div class="mb-foot">
      <button type="button" onclick="bukKonfirm()" class="btn-simpan-tambah">
        💾 Simpan & Tambah Pembeli
      </button>
      <div style="text-align:center;margin-top:10px;">
        <button onclick="tutupTambah()" style="background:none;border:none;color:var(--text-sub);font-size:13px;cursor:pointer;font-family:inherit;">Batal</button>
      </div>
    </div>
  </div>
</div>

<!-- ═══════════════════════════════════════════════════
     MODAL KONFIRMASI SIMPAN PEMBELI
═══════════════════════════════════════════════════ -->
<div class="modal-konfirm-overlay" id="modalKonfirm">
  <div class="konfirm-box">
    <div class="konfirm-top">
      <div class="konfirm-icon-wrap">💾</div>
      <div class="konfirm-judul">Konfirmasi Tambah Pembeli</div>
      <div class="konfirm-sub">Pastikan data sudah benar sebelum disimpan</div>
    </div>
    <div class="konfirm-body">
      <div class="konfirm-info" id="konfirmInfo">
        <!-- diisi JS -->
      </div>
      <div class="konfirm-warn">
        ⚠️ Data yang disimpan akan langsung aktif dan bisa digunakan untuk login.
      </div>
      <div class="konfirm-actions">
        <button class="btn-konfirm-batal" onclick="tutupKonfirm()">✕ Batal</button>
        <button class="btn-konfirm-ok" onclick="submitTambah()">✅ Ya, Simpan Sekarang</button>
      </div>
    </div>
  </div>
</div>

<!-- ═══════════════════════════════════════════════════
     MODAL TRANSAKSI
═══════════════════════════════════════════════════ -->
<div class="modal-overlay" id="modalTrx">
  <div class="trx-box">
    <div class="trx-header">
      <div>
        <div class="trx-title" id="trxTitle">📊 Rekap Transaksi</div>
        <div class="trx-subtitle" id="trxSubtitle"></div>
      </div>
      <button class="modal-close" onclick="tutupTrx()">✕</button>
    </div>
    <div class="trx-body" id="trxBody">
      <div class="trx-loading"><div class="ld-ico">⏳</div><br>Memuat data transaksi...</div>
    </div>
  </div>
</div>

<script>
/* ── VALIDASI EMAIL REAL-TIME ── */
function validateEmail(input) {
  const re = /^[a-zA-Z0-9._%+\-]+@gmail\.com$/;
  const hint = document.getElementById('emailHint');
  if (input.value && !re.test(input.value)) {
    input.style.borderColor = '#ef4444';
    if (hint) { hint.style.display = 'block'; hint.textContent = '⚠️ Email harus menggunakan @gmail.com'; }
  } else {
    input.style.borderColor = '';
    if (hint) hint.style.display = 'none';
  }
}

/* ── VALIDASI TANGGAL LAHIR (min usia 14 tahun) ── */
function getMaxTglLahir() {
  const today = new Date();
  today.setFullYear(today.getFullYear() - 14);
  return today.toISOString().split('T')[0];
}
function validateTglLahir(input) {
  const hint = document.getElementById('tglLahirHint');
  const max  = getMaxTglLahir();
  if (input.value && input.value > max) {
    input.style.borderColor = '#ef4444';
    if (hint) hint.style.display = 'block';
    input.setCustomValidity('Usia minimal 14 tahun.');
  } else {
    input.style.borderColor = '';
    if (hint) hint.style.display = 'none';
    input.setCustomValidity('');
  }
}

/* ── DARK MODE ── */
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
  applyFilter();

  // Jika ada error dari POST tambah pembeli, buka modal kembali
  <?php if($pesan_error): ?>
  bukaTambah();
  <?php endif; ?>
}

/* ── PROFILE ── */
function toggleProfile(){const box=document.getElementById('profileBox');box.style.display=box.style.display==='block'?'none':'block';}
window.addEventListener('click',function(e){if(!e.target.closest('.profile-btn')){const box=document.getElementById('profileBox');if(box&&box.style.display==='block')box.style.display='none';}});

/* ── MOBILE NAV ── */
function openNav(){document.getElementById('mobileNav').classList.add('open');document.getElementById('navOverlay').classList.add('open');document.body.style.overflow='hidden';}
function closeNav(){document.getElementById('mobileNav').classList.remove('open');document.getElementById('navOverlay').classList.remove('open');document.body.style.overflow='';}
document.getElementById('navOverlay').addEventListener('click',closeNav);

/* ── MODAL EDIT ── */
function bukaEdit(id,nama,email){
  document.getElementById('edit_id').value=id;
  document.getElementById('edit_nama').value=nama;
  document.getElementById('edit_email').value=email;
  document.getElementById('edit_password').value='';
  document.getElementById('modalEdit').classList.add('open');
  document.body.style.overflow='hidden';
}
function tutupEdit(){document.getElementById('modalEdit').classList.remove('open');document.body.style.overflow='';}
document.getElementById('modalEdit').addEventListener('click',function(e){if(e.target===this)tutupEdit();});

/* ── MODAL KONFIRMASI TAMBAH PEMBELI ── */
function bukKonfirm() {
  const form = document.getElementById('formTambah');
  // Jalankan validasi HTML5 native dulu
  if (!form.checkValidity()) { form.reportValidity(); return; }

  const nama    = form.querySelector('[name=tb_nama]').value.trim();
  const user    = form.querySelector('[name=tb_username]').value.trim();
  const email   = form.querySelector('[name=tb_email]').value.trim();
  const kelas   = form.querySelector('[name=tb_kelas]').value || '-';
  const nohp    = form.querySelector('[name=tb_no_hp]').value.trim() || '-';
  const gender  = form.querySelector('[name=tb_gender]').value || '-';

  document.getElementById('konfirmInfo').innerHTML = `
    <div class="konfirm-row"><span class="konfirm-label">👤 Nama</span><span class="konfirm-val">${nama}</span></div>
    <div class="konfirm-row"><span class="konfirm-label">🔑 Username</span><span class="konfirm-val">${user}</span></div>
    <div class="konfirm-row"><span class="konfirm-label">📧 Email</span><span class="konfirm-val">${email}</span></div>
    <div class="konfirm-row"><span class="konfirm-label">🏫 Kelas</span><span class="konfirm-val">${kelas}</span></div>
    <div class="konfirm-row"><span class="konfirm-label">📱 No. HP</span><span class="konfirm-val">${nohp}</span></div>
    <div class="konfirm-row"><span class="konfirm-label">🚻 Gender</span><span class="konfirm-val">${gender}</span></div>
  `;
  document.getElementById('modalKonfirm').classList.add('open');
}
function tutupKonfirm() {
  document.getElementById('modalKonfirm').classList.remove('open');
}
function submitTambah() {
  tutupKonfirm();
  // Tambahkan hidden input name tambah_pembeli lalu submit
  const form = document.getElementById('formTambah');
  const hidden = document.createElement('input');
  hidden.type = 'hidden'; hidden.name = 'tambah_pembeli'; hidden.value = '1';
  form.appendChild(hidden);
  form.submit();
}
document.getElementById('modalKonfirm').addEventListener('click', function(e){ if(e.target===this) tutupKonfirm(); });

/* ── MODAL TAMBAH PEMBELI ── */
function bukaTambah(){
  document.getElementById('modalTambah').classList.add('open');
  document.body.style.overflow='hidden';
  // Set max tanggal lahir = hari ini - 14 tahun
  const tglInput = document.getElementById('tb_tanggal_lahir');
  if (tglInput) tglInput.max = getMaxTglLahir();
}
function tutupTambah(){
  document.getElementById('modalTambah').classList.remove('open');
  document.body.style.overflow='';
}
document.getElementById('modalTambah').addEventListener('click',function(e){if(e.target===this)tutupTambah();});

/* ── TOGGLE PASSWORD VISIBILITY ── */
function togglePw(inputId, iconId){
  const input = document.getElementById(inputId);
  const icon  = document.getElementById(iconId);
  if(input.type === 'password'){
    input.type = 'text';
    icon.textContent = '🙈';
  } else {
    input.type = 'password';
    icon.textContent = '👁️';
  }
}

/* ── FORMAT RUPIAH ── */
function rupiah(n){return'Rp '+parseFloat(n).toLocaleString('id-ID');}

/* ── STATUS BADGE ── */
function statusBadge(s){
  const map={
    'lunas':  '<span class="ts ts-lunas">✅ Lunas</span>',
    'batal':  '<span class="ts ts-batal">❌ Batal</span>',
    'ditolak':'<span class="ts ts-ditolak">⛔ Ditolak</span>',
    'pending':'<span class="ts ts-pending">⏳ Pending</span>',
    'dimasak':'<span class="ts ts-dimasak">🍳 Dimasak</span>',
  };
  return map[s]||`<span class="ts">${s}</span>`;
}

/* ═══════════════════════════════════════════════════
   SEARCH & FILTER ENGINE
═══════════════════════════════════════════════════ */
function highlight(text, keyword) {
  if (!keyword) return text;
  const escaped = keyword.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
  const re = new RegExp('(' + escaped + ')', 'gi');
  return text.replace(re, '<mark>$1</mark>');
}
function noResultRow(colspan, msg) {
  return `<tr class="no-result-row"><td colspan="${colspan}"><span class="nr-ico">🔍</span>${msg}</td></tr>`;
}

function applyFilter() {
  const keyword  = document.getElementById('searchNama').value.trim().toLowerCase();
  const kelas    = document.getElementById('filterKelas').value.toLowerCase();
  const statusF  = document.getElementById('filterStatus').value;

  const showPending  = !statusF || statusF === 'pending';
  const showApproved = !statusF || statusF === 'approved';
  const showNonaktif = !statusF || statusF === 'nonaktif';

  document.getElementById('sectionPending').style.display  = showPending  ? '' : 'none';
  document.getElementById('sectionApproved').style.display = showApproved ? '' : 'none';
  document.getElementById('sectionNonaktif').style.display = showNonaktif ? '' : 'none';

  let totalVisible = 0;

  function filterTable(tableId, countId, colspan, emptyMsg) {
    const table    = document.getElementById(tableId);
    const rows     = table.querySelectorAll('tr[data-status]');
    const noResRow = table.querySelector('.no-result-row');
    const emptyOrig= table.querySelector('.empty-orig');
    if (noResRow) noResRow.remove();

    let visible = 0, rowNum = 1;
    rows.forEach(tr => {
      const nama     = tr.dataset.nama     || '';
      const username = tr.dataset.username || '';
      const rowKelas = tr.dataset.kelas    || '';
      const matchKeyword = !keyword || nama.includes(keyword) || username.includes(keyword);
      const matchKelas   = !kelas   || rowKelas === kelas;
      if (matchKeyword && matchKelas) {
        tr.style.display = '';
        const colNo = tr.querySelector('.col-no');
        if (colNo) colNo.textContent = rowNum++;
        const colNama = tr.querySelector('.col-nama');
        if (colNama) colNama.innerHTML = highlight(colNama.textContent, keyword);
        const colUser = tr.querySelector('.col-username');
        if (colUser) colUser.innerHTML = highlight(colUser.textContent, keyword);
        const colKelas = tr.querySelector('.col-kelas');
        if (colKelas) colKelas.innerHTML = highlight(colKelas.textContent, keyword.replace(/-/g,' '));
        visible++;
      } else {
        tr.style.display = 'none';
        const colNama = tr.querySelector('.col-nama');
        if (colNama) colNama.textContent = colNama.textContent;
      }
    });
    if (emptyOrig) emptyOrig.style.display = rows.length === 0 ? '' : 'none';
    if (rows.length > 0 && visible === 0) {
      const hdr = table.querySelector('tr:first-child');
      hdr.insertAdjacentHTML('afterend', noResultRow(colspan, emptyMsg));
    }
    document.getElementById(countId).textContent = visible + ' Pembeli';
    return visible;
  }

  if (showPending)  totalVisible += filterTable('tablePending',  'countPending',  7, 'Tidak ada hasil untuk pencarian ini.');
  if (showApproved) totalVisible += filterTable('tableApproved', 'countApproved', 7, 'Tidak ada hasil untuk pencarian ini.');
  if (showNonaktif) totalVisible += filterTable('tableNonaktif', 'countNonaktif', 8, 'Tidak ada hasil untuk pencarian ini.');

  const chip = document.getElementById('resultChip');
  const isFiltering = keyword || kelas || statusF;
  chip.textContent = isFiltering ? `🔍 ${totalVisible} ditemukan` : `👥 ${totalVisible} pembeli`;
}

function resetFilter() {
  document.getElementById('searchNama').value = '';
  document.getElementById('filterKelas').value = '';
  document.getElementById('filterStatus').value = '';
  applyFilter();
}

/* ── MODAL TRANSAKSI ── */
function lihatTrx(id, nama, username){
  document.getElementById('trxTitle').textContent = '📊 Rekap Transaksi — '+nama;
  document.getElementById('trxSubtitle').textContent = '@'+username;
  document.getElementById('trxBody').innerHTML = '<div class="trx-loading"><div class="ld-ico">⏳</div><br>Memuat data transaksi...</div>';
  document.getElementById('modalTrx').classList.add('open');
  document.body.style.overflow = 'hidden';

  fetch('admin_kelola_pembeli.php?ajax_transaksi='+id)
    .then(r => r.json())
    .then(data => {
      let html = '';
      html += `<div class="trx-summary">
        <div class="trx-stat"><div class="trx-stat-label">Total Transaksi</div><div class="trx-stat-value purple">${data.jumlah}</div></div>
        <div class="trx-stat"><div class="trx-stat-label">Total Belanja</div><div class="trx-stat-value">${rupiah(data.total_semua)}</div></div>
        <div class="trx-stat"><div class="trx-stat-label">Total Lunas</div><div class="trx-stat-value green">${rupiah(data.total_lunas)}</div></div>
      </div>`;
      if (data.jumlah === 0) {
        html += `<div class="trx-empty"><div class="te-ico">🛒</div><p>Belum ada transaksi untuk pembeli ini.</p></div>`;
      } else {
        html += `<div class="trx-table-wrap"><table class="trx-tbl">
          <tr><th>No</th><th>Tanggal</th><th>Nama Makanan</th><th>Kantin</th><th>Jml</th><th>Total</th><th>Metode</th><th>Status</th><th>Catatan</th></tr>`;
        data.rows.forEach((r, i) => {
          const tgl = new Date(r.tanggal).toLocaleString('id-ID',{day:'2-digit',month:'short',year:'numeric',hour:'2-digit',minute:'2-digit'});
          const metode = r.metode === 'CASH'
            ? `<span class="metode-cash">💵 CASH</span>`
            : `<span class="metode-qris">📱 QRIS</span>`;
          const catatan = r.catatan ? `<span style="font-size:11px;color:var(--text-sub)">${r.catatan}</span>` : '-';
          html += `<tr>
            <td>${i+1}</td>
            <td style="white-space:nowrap">${tgl}</td>
            <td>${r.nama_makanan}</td>
            <td style="white-space:nowrap">${r.nama_kantin}</td>
            <td style="text-align:center">${r.jumlah}</td>
            <td style="white-space:nowrap;font-weight:700">${rupiah(r.total)}</td>
            <td>${metode}</td>
            <td>${statusBadge(r.status)}</td>
            <td>${catatan}</td>
          </tr>`;
        });
        html += `</table></div>`;
      }
      document.getElementById('trxBody').innerHTML = html;
    })
    .catch(() => {
      document.getElementById('trxBody').innerHTML = '<div class="trx-empty"><div class="te-ico">⚠️</div><p>Gagal memuat data transaksi.</p></div>';
    });
}
function tutupTrx(){
  document.getElementById('modalTrx').classList.remove('open');
  document.body.style.overflow = '';
}
document.getElementById('modalTrx').addEventListener('click', function(e){ if(e.target===this) tutupTrx(); });
</script>
<script src="../custom_confirm.js"></script>
</body>
</html>