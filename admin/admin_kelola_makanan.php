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

// ===== AKSI =====
if (isset($_GET['nonaktif'])) {
    $id = (int)$_GET['nonaktif'];
    $stmt = mysqli_prepare($db, "UPDATE menu_req SET aktif=0 WHERE id=?");
    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);
    header("Location: admin_kelola_makanan.php");
    exit;
}
if (isset($_GET['aktifkan'])) {
    $id = (int)$_GET['aktifkan'];
    $stmt = mysqli_prepare($db, "UPDATE menu_req SET aktif=1 WHERE id=?");
    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);
    header("Location: admin_kelola_makanan.php");
    exit;
}

// ===== HAPUS = SOFT DELETE (aktif=0, deleted_at=NOW()) =====
// Menu TIDAK benar-benar dihapus dari DB agar rekap transaksi historis tetap bisa dilihat,
// namun tidak akan ditampilkan lagi di halaman admin manapun.
if (isset($_GET['hapus'])) {
    $id = (int)$_GET['hapus'];

    // Pastikan menu ada dan belum di-soft-delete
    $stmt_cek = mysqli_prepare($db, "SELECT id FROM menu_req WHERE id=? AND deleted_at IS NULL");
    mysqli_stmt_bind_param($stmt_cek, "i", $id);
    mysqli_stmt_execute($stmt_cek);
    $res_cek = mysqli_stmt_get_result($stmt_cek);

    if (mysqli_num_rows($res_cek) > 0) {
        // Soft delete: tandai deleted_at dan nonaktifkan
        $stmt_del = mysqli_prepare($db, "UPDATE menu_req SET aktif=0, deleted_at=NOW() WHERE id=?");
        mysqli_stmt_bind_param($stmt_del, "i", $id);
        mysqli_stmt_execute($stmt_del);

        // Hapus dari keranjang agar pembeli tidak bisa checkout menu ini
        $stmt_keranjang = mysqli_prepare($db, "DELETE FROM keranjang WHERE id_menu=?");
        mysqli_stmt_bind_param($stmt_keranjang, "i", $id);
        mysqli_stmt_execute($stmt_keranjang);
    }

    header("Location: admin_kelola_makanan.php");
    exit;
}

// ===== DAFTAR PENJUAL AKTIF urut berdasarkan id_kantin =====
$q_penjual = mysqli_query($db, "
  SELECT p.id_penjual, p.nama AS nama_penjual, p.id_kantin, k.nama_kantin
  FROM penjual p
  LEFT JOIN kantin k ON p.id_kantin = k.id_kantin
  WHERE p.status = 1 AND p.id_kantin IS NOT NULL
  ORDER BY p.id_kantin ASC, p.nama ASC
");
$penjual_list  = [];
$penjual_order = [];
while ($row = mysqli_fetch_assoc($q_penjual)) {
    $pid = $row['id_penjual'];
    $penjual_list[$pid] = $row;
    $penjual_order[] = $pid;
}

// ===== AMBIL SEMUA MENU (aktif & nonaktif, BELUM di-soft-delete) dikelompokkan per id_penjual =====
$q_aktif = mysqli_query($db, "
  SELECT menu_req.*, penjual.nama AS nama_penjual
  FROM menu_req
  LEFT JOIN penjual ON menu_req.id_penjual = penjual.id_penjual
  WHERE menu_req.aktif = 1 AND menu_req.deleted_at IS NULL
  ORDER BY menu_req.nama_makanan ASC
");
$data_aktif = [];
while ($row = mysqli_fetch_assoc($q_aktif)) {
    $pid = $row['id_penjual'];
    $data_aktif[$pid][] = $row;
}

$q_nonaktif = mysqli_query($db, "
  SELECT menu_req.*, penjual.nama AS nama_penjual
  FROM menu_req
  LEFT JOIN penjual ON menu_req.id_penjual = penjual.id_penjual
  WHERE menu_req.aktif = 0 AND menu_req.deleted_at IS NULL
  ORDER BY menu_req.nama_makanan ASC
");
$data_nonaktif = [];
while ($row = mysqli_fetch_assoc($q_nonaktif)) {
    $pid = $row['id_penjual'];
    $data_nonaktif[$pid][] = $row;
}

// ===== REKAP PER MENU (indexed by id_menu) =====
$rekap_query = mysqli_query($db, "
  SELECT
    t.id_menu,
    SUM(t.jumlah) AS total_terjual,
    SUM(t.total) AS total_pendapatan,
    COUNT(t.id_transaksi) AS jumlah_transaksi,
    SUM(CASE WHEN t.status='batal' THEN 1 ELSE 0 END) AS total_batal,
    SUM(CASE WHEN t.status='ditolak' THEN 1 ELSE 0 END) AS total_ditolak
  FROM transaksi t
  WHERE t.status = 'lunas'
  GROUP BY t.id_menu
");
$rekap_by_id = [];
while ($r = mysqli_fetch_assoc($rekap_query)) {
    $rekap_by_id[$r['id_menu']] = $r;
}

// Rating per menu
$rating_query = mysqli_query($db, "
  SELECT id_menu, AVG(bintang) AS rata_bintang, COUNT(*) AS jumlah_ulasan
  FROM ulasan
  GROUP BY id_menu
");
$rating_by_id = [];
while ($r = mysqli_fetch_assoc($rating_query)) {
    $rating_by_id[$r['id_menu']] = $r;
}

// ===== VARIAN PER MENU =====
$varian_query = mysqli_query($db, "
  SELECT id, id_menu, nama, foto_varian, harga_tambah, stok
  FROM varian_menu
  ORDER BY id_menu, id
");
$varian_by_menu = [];
while ($v = mysqli_fetch_assoc($varian_query)) {
    $varian_by_menu[$v['id_menu']][] = $v;
}

// ===== REKAP TERJUAL PER VARIAN (parse dari kolom catatan) =====
$varian_rekap_query = mysqli_query($db, "
  SELECT id_menu,
         TRIM(SUBSTRING_INDEX(SUBSTRING_INDEX(catatan, 'Varian:', -1), '|', 1)) AS nama_varian,
         SUM(jumlah) AS terjual,
         SUM(total) AS pendapatan,
         COUNT(*) AS jumlah_transaksi
  FROM transaksi
  WHERE status = 'lunas'
    AND catatan LIKE '%Varian:%'
    AND id_menu IS NOT NULL
  GROUP BY id_menu, nama_varian
");
$varian_rekap_by_menu = [];
while ($vr = mysqli_fetch_assoc($varian_rekap_query)) {
    $varian_rekap_by_menu[$vr['id_menu']][] = $vr;
}

// ===== KANTIN DINONAKTIFKAN =====
$kantin_nonaktif_query = mysqli_query($db, "
  SELECT p.*, k.nama_kantin
  FROM penjual p
  LEFT JOIN kantin k ON p.nonaktif_dari_kantin = k.id_kantin
  WHERE p.status = 0 AND p.nonaktif_dari_kantin IS NOT NULL
  ORDER BY p.nama ASC
");
$data_kantin_nonaktif = [];
while ($row = mysqli_fetch_assoc($kantin_nonaktif_query)) {
    $data_kantin_nonaktif[] = $row;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Kelola Makanan</title>

  <link rel="stylesheet" href="../custom_confirm.css">
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <style>
    body.dark {
      --bg:#0e0e16;--bg2:#13131f;--card:#1a1a2b;--card2:#1f1f33;
      --header:rgba(14,14,22,0.92);--text:#f0f0f8;--text-sub:#8888aa;
      --border:rgba(255,255,255,0.07);--primary:#ff7a3d;
      --primary-soft:rgba(255,122,61,0.15);--shadow:rgba(0,0,0,0.5);
      --nav-hover:rgba(255,255,255,0.06);
      --menu-card-bg:#1a2236;--menu-card-nonaktif:#1f1618;
      --tr-hover:#1e2a45;--modal-bg:#1a1a2b;
      --divider-bg:rgba(220,53,69,0.08);
    }
    body.light {
      --bg:#f5f5fa;--bg2:#ededf5;--card:#ffffff;--card2:#fafafa;
      --header:rgba(255,255,255,0.95);--text:#111122;--text-sub:#666680;
      --border:rgba(0,0,0,0.07);--primary:#ff6a1f;
      --primary-soft:rgba(255,106,31,0.1);--shadow:rgba(0,0,0,0.07);
      --nav-hover:rgba(0,0,0,0.05);
      --menu-card-bg:#fafafa;--menu-card-nonaktif:#fff5f5;
      --tr-hover:#f8f8fc;--modal-bg:#ffffff;
      --divider-bg:rgba(220,53,69,0.04);
    }

    *{box-sizing:border-box;margin:0;padding:0;font-family:'Plus Jakarta Sans',sans-serif;}
    body{background:var(--bg);min-height:100vh;color:var(--text);transition:background .3s,color .3s;}

    @keyframes fadeUp{from{opacity:0;transform:translateY(16px);}to{opacity:1;transform:translateY(0);}}
    @keyframes fadeDown{from{opacity:0;transform:translateY(-8px);}to{opacity:1;transform:translateY(0);}}
    @keyframes fadeScale{from{opacity:0;transform:scale(0.95);}to{opacity:1;transform:scale(1);}}
    @keyframes modalIn{from{opacity:0;transform:scale(0.94) translateY(18px);}to{opacity:1;transform:scale(1) translateY(0);}}

    /* HEADER */
    header{background:var(--header);padding:14px 20px;display:flex;justify-content:space-between;align-items:center;border-bottom:1px solid var(--border);backdrop-filter:blur(20px);position:sticky;top:0;z-index:50;}
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
    .profile-email{font-size:12px;color:var(--text-sub);margin-bottom:12px;}
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

    /* CONTAINER */
    .container{max-width:1100px;margin:auto;padding:30px;animation:fadeUp .6s ease;}
    .title{font-size:24px;font-weight:800;color:var(--text);letter-spacing:-0.5px;margin-bottom:6px;}
    .subtitle{font-size:13px;color:var(--text-sub);margin-bottom:22px;display:flex;align-items:center;gap:6px;}

    /* KANTIN BOX */
    .kantin-box{background:var(--card);border-radius:18px;margin-bottom:28px;box-shadow:0 8px 20px var(--shadow);border:1px solid var(--border);overflow:hidden;}
    .kantin-box.kantin-nonaktif-box{background:#fff8f0;border:1.5px solid rgba(230,126,34,0.3);}
    body.dark .kantin-box.kantin-nonaktif-box{background:#1f1a12;border-color:rgba(230,126,34,0.25);}

    .kantin-head{padding:16px 20px;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:8px;}
    .kantin-head-title{font-size:15px;font-weight:700;color:var(--primary);}
    .kantin-head-title.kantin-nonaktif-title{color:#e67e22;}
    .badge{display:inline-flex;align-items:center;gap:4px;padding:3px 10px;border-radius:20px;font-size:11px;font-weight:700;letter-spacing:.3px;}
    .badge-nonaktif-menu{background:rgba(220,53,69,0.12);color:#dc3545;border:1px solid rgba(220,53,69,0.2);}
    .badge-kantin-off{background:rgba(230,126,34,0.12);color:#e67e22;border:1px solid rgba(230,126,34,0.2);}

    /* SECTION AKTIF & NONAKTIF di dalam box */
    .menu-section{padding:16px 20px;}
    .menu-section-divider{
      display:flex;align-items:center;gap:10px;
      padding:10px 20px 0;
      margin-bottom:0;
    }
    .divider-line{flex:1;height:1px;background:rgba(220,53,69,0.25);}
    .divider-label{
      font-size:11px;font-weight:800;letter-spacing:.7px;text-transform:uppercase;
      color:#dc3545;white-space:nowrap;
      display:flex;align-items:center;gap:5px;
    }
    .nonaktif-section{
      background:var(--divider-bg);
      padding:14px 20px 18px;
      border-top:1px dashed rgba(220,53,69,0.2);
      margin-top:4px;
    }

    /* MENU GRID */
    .menu-grid{display:flex;gap:14px;overflow-x:auto;padding-bottom:6px;scrollbar-width:thin;}
    .menu-grid::-webkit-scrollbar{height:5px;}
    .menu-grid::-webkit-scrollbar-thumb{background:rgba(0,0,0,0.15);border-radius:10px;}

    /* MENU CARD */
    .menu-card{
      background:var(--menu-card-bg);
      padding:13px;border-radius:14px;text-align:center;
      box-shadow:0 3px 10px var(--shadow);
      transition:.25s;min-width:188px;max-width:188px;
      flex-shrink:0;animation:fadeScale .35s ease;
      border:1px solid var(--border);
    }
    .menu-card:hover{transform:translateY(-5px);box-shadow:0 10px 22px var(--shadow);}
    .menu-card.card-nonaktif{
      background:var(--menu-card-nonaktif);
      opacity:.82;filter:grayscale(20%);
      border:1px dashed rgba(220,53,69,0.3);
    }

    .menu-card img{width:100%;height:136px;object-fit:cover;border-radius:10px;margin-bottom:9px;}
    .menu-nama{font-weight:700;margin-bottom:3px;color:var(--text);font-size:13.5px;line-height:1.3;}
    .food-desc{font-size:11.5px;color:var(--text-sub);margin-bottom:4px;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;line-height:1.4;}
    .menu-harga{font-size:13px;color:var(--primary);font-weight:700;margin-bottom:3px;}
    .menu-stok{font-size:11.5px;color:var(--text-sub);margin-bottom:3px;}
    .menu-terjual{font-size:11.5px;color:#28a745;font-weight:700;margin-bottom:9px;}

    /* TOMBOL */
    .btn-group{display:flex;gap:5px;justify-content:center;flex-wrap:wrap;}
    .btn{display:inline-flex;align-items:center;gap:3px;padding:5px 10px;border-radius:7px;font-size:11px;font-weight:700;text-decoration:none;transition:.2s;cursor:pointer;border:none;white-space:nowrap;}
    .btn-nonaktif{background:linear-gradient(135deg,#fd7e14,#ffc107);color:white;}
    .btn-nonaktif:hover{opacity:.82;}
    .btn-aktifkan{background:linear-gradient(135deg,#28a745,#20c997);color:white;}
    .btn-aktifkan:hover{opacity:.82;}
    .btn-hapus{background:linear-gradient(135deg,#dc3545,#ff4d4d);color:white;}
    .btn-hapus:hover{opacity:.82;}
    .btn-rekap{background:linear-gradient(135deg,#6c63ff,#8b5cf6);color:white;}
    .btn-rekap:hover{opacity:.82;}

    /* KANTIN NONAKTIF INFO */
    .kantin-nonaktif-info{padding:11px 15px;border-radius:11px;background:rgba(230,126,34,0.08);border:1px solid rgba(230,126,34,0.18);margin:10px 0 4px;font-size:12.5px;color:var(--text);line-height:1.6;}
    .kantin-nonaktif-info strong{color:#e67e22;}

    /* SECTION KANTIN NONAKTIF */
    .section-label{display:flex;align-items:center;gap:10px;font-size:12px;font-weight:800;text-transform:uppercase;letter-spacing:.8px;margin:30px 0 14px;color:#e67e22;}
    .section-label::after{content:'';flex:1;height:1px;background:rgba(230,126,34,0.25);}

    /* MODAL */
    .modal-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,0.68);z-index:200;backdrop-filter:blur(7px);align-items:center;justify-content:center;padding:16px;}
    .modal-overlay.open{display:flex;}
    .modal-box{background:var(--modal-bg);border-radius:20px;width:100%;max-width:580px;max-height:88vh;overflow:hidden;display:flex;flex-direction:column;box-shadow:0 24px 60px rgba(0,0,0,0.4);border:1px solid var(--border);animation:modalIn .3s ease;}
    .modal-head{padding:18px 22px;border-bottom:1px solid var(--border);display:flex;align-items:flex-start;justify-content:space-between;gap:12px;flex-shrink:0;}
    .modal-head-info{}
    .modal-head-info h2{font-size:16px;font-weight:800;color:var(--text);margin-bottom:2px;}
    .modal-head-info .modal-sub{font-size:12px;color:var(--text-sub);}
    .modal-close{width:30px;height:30px;border-radius:8px;background:var(--card2);border:1px solid var(--border);color:var(--text-sub);font-size:17px;cursor:pointer;display:flex;align-items:center;justify-content:center;transition:.2s;flex-shrink:0;}
    .modal-close:hover{background:rgba(220,53,69,0.1);color:#dc3545;border-color:rgba(220,53,69,0.3);}
    .modal-body{padding:18px 22px;overflow-y:auto;flex:1;}

    /* REKAP STATS */
    .rekap-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:11px;margin-bottom:18px;}
    .rstat{background:var(--card2);border-radius:13px;padding:14px 16px;border:1px solid var(--border);}
    .rstat-val{font-size:20px;font-weight:800;color:var(--primary);line-height:1;}
    .rstat-val.green{color:#28a745;}
    .rstat-val.purple{color:#8b5cf6;}
    .rstat-val.blue{color:#3b82f6;}
    .rstat-lbl{font-size:11px;color:var(--text-sub);margin-top:4px;font-weight:600;}

    /* REKAP DETAIL ROWS */
    .rekap-row{display:flex;align-items:center;justify-content:space-between;padding:10px 0;border-bottom:1px solid var(--border);font-size:13px;}
    .rekap-row:last-child{border-bottom:none;}
    .rekap-row-label{color:var(--text-sub);font-weight:500;}
    .rekap-row-val{font-weight:700;color:var(--text);}
    .rekap-row-val.green{color:#28a745;}

    /* STARS */
    .stars-display{display:flex;align-items:center;gap:4px;}
    .stars-display .star{font-size:13px;}
    .stars-display .star.on{color:#ffc107;}
    .stars-display .star.off{color:var(--border);}

    .empty-modal{text-align:center;padding:30px;color:var(--text-sub);font-size:14px;}
    .empty-modal .ico{font-size:36px;margin-bottom:10px;}

    /* EMPTY KANTIN (tidak ada menu) */
    .empty-kantin{display:flex;align-items:center;justify-content:center;gap:10px;padding:24px 20px;color:var(--text-sub);font-size:13.5px;font-weight:500;}

    /* EMPTY PAGE */
    .empty-state{text-align:center;padding:50px 20px;color:var(--text-sub);font-size:14px;}
    .empty-state .ico{font-size:44px;margin-bottom:12px;}

    /* MODAL DETAIL MAKANAN */
    .detail-img-wrap{position:relative;border-radius:14px;overflow:hidden;margin-bottom:16px;background:var(--card2);aspect-ratio:4/3;}
    .detail-img-wrap img{width:100%;height:100%;object-fit:cover;display:block;}
    .detail-badge-status{position:absolute;top:10px;right:10px;padding:4px 10px;border-radius:20px;font-size:11px;font-weight:700;}
    .detail-badge-aktif{background:rgba(40,167,69,0.9);color:white;}
    .detail-badge-nonaktif{background:rgba(220,53,69,0.9);color:white;}
    .detail-info-row{display:flex;justify-content:space-between;align-items:center;padding:8px 0;border-bottom:1px solid var(--border);font-size:13px;}
    .detail-info-row:last-child{border-bottom:none;}
    .detail-info-label{color:var(--text-sub);font-weight:500;}
    .detail-info-val{font-weight:700;color:var(--text);}
    .detail-desc{font-size:13px;color:var(--text-sub);line-height:1.6;padding:10px 14px;background:var(--card2);border-radius:10px;margin-bottom:14px;}
    .varian-section-title{font-size:12px;font-weight:800;text-transform:uppercase;letter-spacing:.6px;color:var(--primary);margin:14px 0 8px;}
    .varian-list{display:flex;flex-direction:column;gap:8px;}
    .varian-item{display:flex;align-items:center;gap:10px;padding:9px 12px;background:var(--card2);border-radius:11px;border:1px solid var(--border);transition:.2s;}
    .varian-item:hover{border-color:var(--primary);background:var(--primary-soft);}
    .varian-img{width:48px;height:48px;border-radius:8px;object-fit:cover;flex-shrink:0;background:var(--border);}
    .varian-img-placeholder{width:48px;height:48px;border-radius:8px;background:var(--border);display:flex;align-items:center;justify-content:center;font-size:20px;flex-shrink:0;}
    .varian-info{flex:1;}
    .varian-nama{font-size:13px;font-weight:700;color:var(--text);}
    .varian-detail{font-size:11.5px;color:var(--text-sub);margin-top:2px;}
    .varian-stok{font-size:11.5px;font-weight:600;color:#28a745;white-space:nowrap;}
    .menu-card{cursor:pointer;}

    /* REKAP VARIAN TABLE */
    .varian-rekap-table{width:100%;border-collapse:collapse;font-size:12.5px;margin-top:4px;}
    .varian-rekap-table th{text-align:left;padding:7px 10px;font-size:11px;font-weight:800;text-transform:uppercase;letter-spacing:.5px;color:var(--text-sub);border-bottom:2px solid var(--border);}
    .varian-rekap-table td{padding:8px 10px;border-bottom:1px solid var(--border);color:var(--text);}
    .varian-rekap-table tr:last-child td{border-bottom:none;}
    .varian-rekap-table tr:hover td{background:var(--tr-hover);}
    .varian-rekap-table td.green{color:#28a745;font-weight:700;}
    .varian-rekap-section{margin-top:18px;padding-top:14px;border-top:1px solid var(--border);}
    .varian-rekap-section-title{font-size:12px;font-weight:800;text-transform:uppercase;letter-spacing:.6px;color:var(--primary);margin-bottom:10px;display:flex;align-items:center;gap:6px;}

    @media(max-width:768px){
      header{padding:12px 16px;}
      .hamburger{display:flex;}
      .nav-menu,.dark-toggle,.profile-btn{display:none !important;}
      .container{padding:14px;}
      .title{font-size:20px;}
      .menu-card{min-width:162px;max-width:162px;padding:11px;}
      .menu-card img{height:116px;}
      .menu-nama{font-size:13px;}
      .modal-box{border-radius:14px;}
      .modal-head{padding:14px 16px;}
      .modal-body{padding:14px 16px;}
      .rekap-grid{grid-template-columns:repeat(2,1fr);}
    }
    @media(max-width:400px){
      .logo span{display:none;}
      .menu-card{min-width:148px;max-width:148px;}
    }
  </style>
</head>
<body class="light">

<!-- ===== MODAL DETAIL MAKANAN ===== -->
<div class="modal-overlay" id="modalDetail" onclick="if(event.target===this)tutupModalDetail()">
  <div class="modal-box" style="max-width:460px;">
    <div class="modal-head">
      <div class="modal-head-info">
        <h2 id="modalDetailJudul">🍽️ Detail Makanan</h2>
        <div class="modal-sub" id="modalDetailSub"></div>
      </div>
      <button class="modal-close" onclick="tutupModalDetail()">✕</button>
    </div>
    <div class="modal-body" id="modalDetailBody"></div>
  </div>
</div>

<!-- ===== MODAL REKAP PER MENU ===== -->
<div class="modal-overlay" id="modalRekap" onclick="if(event.target===this)tutupModal()">
  <div class="modal-box">
    <div class="modal-head">
      <div class="modal-head-info">
        <h2 id="modalJudul">📊 Rekap Penjualan</h2>
        <div class="modal-sub" id="modalSub"></div>
      </div>
      <button class="modal-close" onclick="tutupModal()">✕</button>
    </div>
    <div class="modal-body" id="modalBody">
      <!-- diisi JS -->
    </div>
  </div>
</div>

<!-- REKAP DATA JSON untuk JS -->
<script>
const rekapData = <?php
  $js_rekap = [];
  $all_menu_q = mysqli_query($db, "
    SELECT m.id, m.nama_makanan, m.harga, m.stok, m.terjual, m.aktif, m.foto, m.deskripsi,
           p.nama AS nama_penjual
    FROM menu_req m
    LEFT JOIN penjual p ON m.id_penjual = p.id_penjual
    WHERE m.deleted_at IS NULL
  ");
  while ($am = mysqli_fetch_assoc($all_menu_q)) {
      $mid = $am['id'];
      $rek = $rekap_by_id[$mid] ?? null;
      $rat = $rating_by_id[$mid] ?? null;
      $js_rekap[$mid] = [
          'nama'             => $am['nama_makanan'],
          'harga'            => (int)$am['harga'],
          'stok'             => (int)$am['stok'],
          'terjual'          => (int)$am['terjual'],
          'aktif'            => (int)$am['aktif'],
          'foto'             => $am['foto'],
          'deskripsi'        => $am['deskripsi'],
          'nama_penjual'     => $am['nama_penjual'],
          'total_terjual'    => $rek ? (int)$rek['total_terjual'] : 0,
          'total_pendapatan' => $rek ? (float)$rek['total_pendapatan'] : 0,
          'jumlah_transaksi' => $rek ? (int)$rek['jumlah_transaksi'] : 0,
          'rata_bintang'     => $rat ? (float)$rat['rata_bintang'] : null,
          'jumlah_ulasan'    => $rat ? (int)$rat['jumlah_ulasan'] : 0,
      ];
  }
  echo json_encode($js_rekap);
?>;

const varianData = <?php
  $js_varian = [];
  foreach ($varian_by_menu as $mid => $variants) {
      $js_varian[$mid] = array_map(function($v) {
          return [
              'id'          => (int)$v['id'],
              'nama'        => $v['nama'],
              'foto_varian' => $v['foto_varian'] ?? '',
              'harga_tambah'=> (int)$v['harga_tambah'],
              'stok'        => (int)$v['stok'],
          ];
      }, $variants);
  }
  echo json_encode($js_varian);
?>;

const varianRekapData = <?php
  $js_vr = [];
  foreach ($varian_rekap_by_menu as $mid => $vr_list) {
      $js_vr[$mid] = array_map(function($vr) {
          return [
              'nama_varian'      => trim($vr['nama_varian']),
              'terjual'          => (int)$vr['terjual'],
              'pendapatan'       => (float)$vr['pendapatan'],
              'jumlah_transaksi' => (int)$vr['jumlah_transaksi'],
          ];
      }, $vr_list);
  }
  echo json_encode($js_vr);
?>;
</script>

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
    <a href="admin_beranda.php"><span class="mn-ico">🏠</span> Dashboard</a>
    <a href="admin_kelola_penjual.php"><span class="mn-ico">🛒</span> Penjual</a>
    <a href="admin_kelola_pembeli.php"><span class="mn-ico">👥</span> Pembeli</a>
    <a href="admin_kelola_makanan.php" class="active"><span class="mn-ico">🍔</span> Makanan</a>
  </div>
  <div class="mn-bottom">
    <button class="mn-theme" onclick="toggleDark()" id="toggleBtnMobile">🌙 Dark Mode</button>
    <a href="logout.php" class="mn-logout">🚪 Logout</a>
  </div>
</div>

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
      <a href="admin_kelola_pembeli.php">Kelola Pembeli</a>
      <a href="admin_kelola_makanan.php" class="active">Kelola Makanan</a>
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
  <div class="title">Kelola Menu Makanan</div>
  <div class="subtitle">
    <span>Manajemen menu aktif dan nonaktif per kantin</span>
  </div>

  <?php if (empty($penjual_order)): ?>
    <div class="empty-state">
      <div class="ico">🍽️</div>
      Belum ada penjual/kantin terdaftar.
    </div>
  <?php else: ?>

  <?php foreach ($penjual_order as $pid): ?>
    <?php
    $penjual_info   = $penjual_list[$pid];
    $nama_kantin    = $penjual_info['nama_penjual'];
    $no_kantin      = $penjual_info['id_kantin'];
    $menus_aktif    = $data_aktif[$pid]    ?? [];
    $menus_nonaktif = $data_nonaktif[$pid] ?? [];
    $ada_aktif      = !empty($menus_aktif);
    $ada_nonaktif   = !empty($menus_nonaktif);
    $ada_menu       = $ada_aktif || $ada_nonaktif;
    ?>

    <div class="kantin-box">

      <!-- HEADER KANTIN -->
      <div class="kantin-head">
        <span style="font-size:18px;">🍽️</span>
        <span class="kantin-head-title">
          Kantin <?php echo $no_kantin; ?> &mdash; <?php echo htmlspecialchars($nama_kantin); ?>
        </span>
        <?php if ($ada_nonaktif): ?>
          <span class="badge badge-nonaktif-menu" style="margin-left:4px;">
            🚫 <?php echo count($menus_nonaktif); ?> Menu Nonaktif
          </span>
        <?php endif; ?>
      </div>

      <!-- ===== MENU AKTIF ===== -->
      <?php if ($ada_aktif): ?>
      <div class="menu-section">
        <div class="menu-grid">
          <?php foreach ($menus_aktif as $menu): ?>
            <div class="menu-card" onclick="bukaDetail(<?php echo $menu['id']; ?>)">
              <img src="../penjual/uploads/<?php echo htmlspecialchars($menu['foto']); ?>"
                   alt="<?php echo htmlspecialchars($menu['nama_makanan']); ?>"
                   onerror="this.src='../gambar/no-image.png'">
              <div class="menu-nama"><?php echo htmlspecialchars($menu['nama_makanan']); ?></div>
              <p class="food-desc"><?php echo htmlspecialchars($menu['deskripsi']); ?></p>
              <div class="menu-harga">Rp <?php echo number_format($menu['harga']); ?></div>
              <div class="menu-stok">Stok : <?php echo $menu['stok']; ?></div>
              <div class="menu-terjual">🛒 Terjual : <?php echo number_format($menu['terjual']); ?></div>
              <div class="btn-group" onclick="event.stopPropagation()">
                <button class="btn btn-rekap"
                  onclick="bukaRekap(<?php echo $menu['id']; ?>)">📊 Rekap</button>
                <a class="btn btn-nonaktif"
                   href="?nonaktif=<?php echo $menu['id']; ?>"
                   onclick="confirmPopup({ judul:'Nonaktifkan Menu', ikon:'🚫', pesan:'Nonaktifkan menu ini?', poin:['Menu tidak akan tampil di halaman pembeli','Bisa diaktifkan kembali kapan saja'], tipe:'warning', labelOK:'Ya, Nonaktifkan', url:this.href }); return false;">🚫 Nonaktifkan</a>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>

      <!-- ===== MENU NONAKTIF ===== -->
      <?php if ($ada_nonaktif): ?>
        <?php if ($ada_aktif): ?>
        <div class="menu-section-divider">
          <div class="divider-line"></div>
          <div class="divider-label">🚫 Menu Dinonaktifkan</div>
          <div class="divider-line"></div>
        </div>
        <?php else: ?>
        <div class="menu-section-divider" style="padding:14px 20px 0;">
          <div class="divider-line"></div>
          <div class="divider-label">🚫 Semua Menu Dinonaktifkan</div>
          <div class="divider-line"></div>
        </div>
        <?php endif; ?>

        <div class="nonaktif-section">
          <div class="menu-grid">
            <?php foreach ($menus_nonaktif as $menu): ?>
              <div class="menu-card card-nonaktif" onclick="bukaDetail(<?php echo $menu['id']; ?>)">
                <img src="../penjual/uploads/<?php echo htmlspecialchars($menu['foto']); ?>"
                     alt="<?php echo htmlspecialchars($menu['nama_makanan']); ?>"
                     onerror="this.src='../gambar/no-image.png'">
                <div class="menu-nama"><?php echo htmlspecialchars($menu['nama_makanan']); ?></div>
                <p class="food-desc"><?php echo htmlspecialchars($menu['deskripsi']); ?></p>
                <div class="menu-harga">Rp <?php echo number_format($menu['harga']); ?></div>
                <div class="menu-stok">Stok : <?php echo $menu['stok']; ?></div>
                <div class="menu-terjual">🛒 Terjual : <?php echo number_format($menu['terjual']); ?></div>
                <div class="btn-group" onclick="event.stopPropagation()">
                  <button class="btn btn-rekap"
                    onclick="bukaRekap(<?php echo $menu['id']; ?>)">📊 Rekap</button>
                  <a class="btn btn-aktifkan"
                     href="?aktifkan=<?php echo $menu['id']; ?>"
                     onclick="confirmPopup({ judul:'Aktifkan Menu', ikon:'✅', pesan:'Aktifkan kembali menu ini?', poin:['Menu akan tampil kembali di halaman pembeli'], tipe:'success', labelOK:'Ya, Aktifkan', url:this.href }); return false;">✅ Aktifkan</a>
                  <a class="btn btn-hapus"
                     href="?hapus=<?php echo $menu['id']; ?>"
                     onclick="confirmPopup({ judul:'Hapus Menu', ikon:'🗑️', pesan:'Menu ini akan dihapus dari panel admin.', poin:['Data transaksi & rekap tetap tersimpan di database','Menu tidak akan tampil lagi di halaman admin','Tindakan ini tidak bisa dibatalkan'], tipe:'danger', labelOK:'Ya, Hapus', url:this.href }); return false;">🗑️ Hapus</a>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endif; ?>

      <!-- Kantin tanpa menu sama sekali -->
      <?php if (!$ada_menu): ?>
      <div class="empty-kantin">
        <span style="font-size:26px;">🍽️</span>
        <span>Belum ada menu di kantin ini</span>
      </div>
      <?php endif; ?>

    </div><!-- /kantin-box -->
  <?php endforeach; ?>

  <?php endif; ?>

  <!-- ===== KANTIN DINONAKTIFKAN ===== -->
  <?php if (!empty($data_kantin_nonaktif)): ?>
    <?php
    $kantin_grup = [];
    foreach ($data_kantin_nonaktif as $penjual) {
        $nk = $penjual['nama_kantin'] ?? 'Kantin tidak diketahui';
        $kantin_grup[$nk][] = $penjual;
    }
    ?>
    <div class="section-label">⚠️ Kantin Dinonaktifkan</div>

    <?php foreach ($kantin_grup as $nama_kantin => $penjual_nonaktif_list): ?>
      <div class="kantin-box kantin-nonaktif-box">
        <div class="kantin-head">
          <span style="font-size:18px;">⚠️</span>
          <span class="kantin-head-title kantin-nonaktif-title"><?php echo htmlspecialchars($nama_kantin); ?></span>
          <span class="badge badge-kantin-off">⛔ Kantin Nonaktif</span>
        </div>
        <div style="padding:14px 20px;">
          <?php foreach ($penjual_nonaktif_list as $p): ?>
            <div class="kantin-nonaktif-info">
              👤 <strong><?php echo htmlspecialchars($p['nama']); ?></strong>
              &nbsp;·&nbsp; 📧 <?php echo htmlspecialchars($p['email'] ?? '-'); ?>
              &nbsp;·&nbsp; 🔑 <?php echo htmlspecialchars($p['username']); ?>
              &nbsp;·&nbsp; <strong style="color:#dc3545;">Dinonaktifkan dari <?php echo htmlspecialchars($nama_kantin); ?></strong>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>

</div><!-- /container -->

<script>
// ===== DETAIL MAKANAN MODAL =====
function bukaDetail(id) {
  const d = rekapData[id];
  if (!d) return;

  const variants = varianData[id] || [];
  const fmt = n => 'Rp ' + Number(n).toLocaleString('id-ID');

  document.getElementById('modalDetailJudul').textContent = '🍽️ ' + d.nama;
  document.getElementById('modalDetailSub').textContent = 'Penjual: ' + (d.nama_penjual || '-');

  const statusBadge = d.aktif
    ? '<span class="detail-badge-status detail-badge-aktif">✅ Aktif</span>'
    : '<span class="detail-badge-status detail-badge-nonaktif">🚫 Nonaktif</span>';

  const imgSrc = d.foto ? `../penjual/uploads/${d.foto}` : '../gambar/no-image.png';

  let varianHtml = '';
  if (variants.length > 0) {
    varianHtml = `
      <div class="varian-section-title">🎛️ Varian Menu (${variants.length})</div>
      <div class="varian-list">
        ${variants.map(v => {
          const hargaTambah = v.harga_tambah > 0
            ? `<span style="color:var(--primary);">+${fmt(v.harga_tambah)}</span> · ` : '';
          const imgEl = v.foto_varian
            ? `<img class="varian-img" src="../penjual/uploads/${v.foto_varian}" alt="${v.nama}" onerror="this.src='../gambar/no-image.png'">`
            : `<div class="varian-img-placeholder">🍱</div>`;
          return `
            <div class="varian-item">
              ${imgEl}
              <div class="varian-info">
                <div class="varian-nama">${v.nama}</div>
                <div class="varian-detail">${hargaTambah}Stok: <span class="varian-stok">${v.stok} pcs</span></div>
              </div>
            </div>`;
        }).join('')}
      </div>`;
  }

  const descHtml = d.deskripsi
    ? `<div class="detail-desc">${d.deskripsi}</div>` : '';

  document.getElementById('modalDetailBody').innerHTML = `
    <div class="detail-img-wrap">
      <img src="${imgSrc}" alt="${d.nama}" onerror="this.src='../gambar/no-image.png'">
      ${statusBadge}
    </div>
    ${descHtml}
    <div class="detail-info-row">
      <span class="detail-info-label">Harga Dasar</span>
      <span class="detail-info-val" style="color:var(--primary);">${fmt(d.harga)}</span>
    </div>
    <div class="detail-info-row">
      <span class="detail-info-label">Stok Tersedia</span>
      <span class="detail-info-val">${Number(d.stok).toLocaleString('id-ID')} pcs</span>
    </div>
    <div class="detail-info-row">
      <span class="detail-info-label">Total Terjual</span>
      <span class="detail-info-val" style="color:#28a745;">🛒 ${Number(d.terjual).toLocaleString('id-ID')} pcs</span>
    </div>
    ${varianHtml}
  `;

  document.getElementById('modalDetail').classList.add('open');
  document.body.style.overflow = 'hidden';
}

function tutupModalDetail() {
  document.getElementById('modalDetail').classList.remove('open');
  document.body.style.overflow = '';
}

// ===== REKAP MODAL =====
function bukaRekap(id) {
  const d = rekapData[id];
  if (!d) return;

  document.getElementById('modalJudul').textContent = '📊 ' + d.nama;
  document.getElementById('modalSub').textContent = 'Kantin: ' + (d.nama_penjual || '-');

  const fmt = n => 'Rp ' + Number(n).toLocaleString('id-ID');
  const fmtN = n => Number(n).toLocaleString('id-ID');

  // Stars
  let starsHtml = '';
  if (d.rata_bintang !== null) {
    const bintang = Math.round(d.rata_bintang);
    for (let i = 1; i <= 5; i++) {
      starsHtml += `<span class="star ${i <= bintang ? 'on' : 'off'}">★</span>`;
    }
  }

  // Rekap per varian
  const varianRekap = varianRekapData[id] || [];
  let varianRekapHtml = '';
  if (varianRekap.length > 0) {
    const rows = varianRekap
      .sort((a, b) => b.terjual - a.terjual)
      .map(vr => `
        <tr>
          <td>${vr.nama_varian}</td>
          <td class="green">${fmtN(vr.terjual)} pcs</td>
          <td>${vr.jumlah_transaksi}x</td>
          <td>${fmt(vr.pendapatan)}</td>
        </tr>`).join('');

    varianRekapHtml = `
      <div class="varian-rekap-section">
        <div class="varian-rekap-section-title">🎛️ Rekap Terjual per Varian</div>
        <table class="varian-rekap-table">
          <thead>
            <tr>
              <th>Varian</th>
              <th>Terjual</th>
              <th>Transaksi</th>
              <th>Pendapatan</th>
            </tr>
          </thead>
          <tbody>${rows}</tbody>
        </table>
      </div>`;
  }

  const punya_penjualan = d.jumlah_transaksi > 0;

  document.getElementById('modalBody').innerHTML = punya_penjualan ? `
    <div class="rekap-grid">
      <div class="rstat">
        <div class="rstat-val green">${fmtN(d.total_terjual)}</div>
        <div class="rstat-lbl">Total Terjual (pcs)</div>
      </div>
      <div class="rstat">
        <div class="rstat-val" style="font-size:15px;">${fmt(d.total_pendapatan)}</div>
        <div class="rstat-lbl">Total Pendapatan</div>
      </div>
      <div class="rstat">
        <div class="rstat-val purple">${d.jumlah_transaksi}x</div>
        <div class="rstat-lbl">Transaksi Lunas</div>
      </div>
      <div class="rstat">
        <div class="rstat-val blue">${d.jumlah_ulasan}</div>
        <div class="rstat-lbl">Total Ulasan</div>
      </div>
    </div>
    <div>
      <div class="rekap-row">
        <span class="rekap-row-label">Harga Satuan</span>
        <span class="rekap-row-val">${fmt(d.harga)}</span>
      </div>
      <div class="rekap-row">
        <span class="rekap-row-label">Stok Saat Ini</span>
        <span class="rekap-row-val">${fmtN(d.stok)} pcs</span>
      </div>
      <div class="rekap-row">
        <span class="rekap-row-label">Total Penjualan (field terjual)</span>
        <span class="rekap-row-val green">${fmtN(d.terjual)} pcs</span>
      </div>
      <div class="rekap-row">
        <span class="rekap-row-label">Rata-rata Pendapatan / Transaksi</span>
        <span class="rekap-row-val">${d.jumlah_transaksi > 0 ? fmt(d.total_pendapatan / d.jumlah_transaksi) : '-'}</span>
      </div>
      <div class="rekap-row">
        <span class="rekap-row-label">Status Menu</span>
        <span class="rekap-row-val" style="color:${d.aktif ? '#28a745' : '#dc3545'}">
          ${d.aktif ? '✅ Aktif' : '🚫 Nonaktif'}
        </span>
      </div>
      ${d.rata_bintang !== null ? `
      <div class="rekap-row">
        <span class="rekap-row-label">Rating</span>
        <span class="rekap-row-val">
          <span class="stars-display">${starsHtml}</span>
          &nbsp;${Number(d.rata_bintang).toFixed(1)} / 5
          <span style="color:var(--text-sub);font-size:11px;">(${d.jumlah_ulasan} ulasan)</span>
        </span>
      </div>` : `
      <div class="rekap-row">
        <span class="rekap-row-label">Rating</span>
        <span class="rekap-row-val" style="color:var(--text-sub);">Belum ada ulasan</span>
      </div>`}
    </div>
    ${varianRekapHtml}
  ` : `
    <div class="empty-modal">
      <div class="ico">📭</div>
      <div>Belum ada transaksi lunas untuk menu ini.</div>
      <div style="margin-top:12px;">
        <div class="rekap-row">
          <span class="rekap-row-label">Harga Satuan</span>
          <span class="rekap-row-val">${fmt(d.harga)}</span>
        </div>
        <div class="rekap-row">
          <span class="rekap-row-label">Stok Saat Ini</span>
          <span class="rekap-row-val">${fmtN(d.stok)} pcs</span>
        </div>
        <div class="rekap-row">
          <span class="rekap-row-label">Status Menu</span>
          <span class="rekap-row-val" style="color:${d.aktif ? '#28a745' : '#dc3545'}">
            ${d.aktif ? '✅ Aktif' : '🚫 Nonaktif'}
          </span>
        </div>
      </div>
    </div>
  `;

  document.getElementById('modalRekap').classList.add('open');
  document.body.style.overflow = 'hidden';
}

function tutupModal() {
  document.getElementById('modalRekap').classList.remove('open');
  document.body.style.overflow = '';
}

document.addEventListener('keydown', e => { if (e.key === 'Escape') { tutupModal(); tutupModalDetail(); } });

// ===== DARK MODE =====
function toggleDark() {
  const body = document.body;
  const btn = document.getElementById('toggleBtn');
  const btnM = document.getElementById('toggleBtnMobile');
  if (body.classList.contains('light')) {
    body.classList.replace('light', 'dark');
    if (btn) btn.textContent = '☀️ Light';
    if (btnM) btnM.textContent = '☀️ Light Mode';
    localStorage.setItem('theme', 'dark');
  } else {
    body.classList.replace('dark', 'light');
    if (btn) btn.textContent = '🌙 Dark';
    if (btnM) btnM.textContent = '🌙 Dark Mode';
    localStorage.setItem('theme', 'light');
  }
}
window.onload = function () {
  const saved = localStorage.getItem('theme');
  if (saved === 'dark') {
    document.body.classList.replace('light', 'dark');
    const btn = document.getElementById('toggleBtn');
    const btnM = document.getElementById('toggleBtnMobile');
    if (btn) btn.textContent = '☀️ Light';
    if (btnM) btnM.textContent = '☀️ Light Mode';
  }
};

// ===== PROFILE =====
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

// ===== MOBILE NAV =====
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
</script>
<script src="../custom_confirm.js"></script>
</body>
</html>