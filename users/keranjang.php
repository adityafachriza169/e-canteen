<?php
ob_start();
session_start();
include "koneksi.php";
include 'chat_popup.php';


if (!isset($_SESSION['id_user']) || (int) ($_SESSION['role'] ?? -1) !== 2) {
    header("Location: login.php");
    exit;
}

$id_user = (int) $_SESSION['id_user'];

/* ===============================
   HAPUS ITEM
================================*/
if (isset($_GET['hapus'])) {
    $id_keranjang = (int) $_GET['hapus'];
    $del = mysqli_prepare($konek, "DELETE FROM keranjang WHERE id=?");
    mysqli_stmt_bind_param($del, "i", $id_keranjang);
    mysqli_stmt_execute($del);
    header("Location: keranjang.php");
    exit;
}

/* ===============================
   UPDATE JUMLAH (AJAX)
================================*/
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    ob_clean();

    $id_keranjang = (int) $_POST['id_keranjang'];
    $action = $_POST['action'];

    // ── SET MANUAL (dari input keyboard) ──
    if ($action === 'set') {
        $jumlah = max(1, (int) $_POST['jumlah']);
        $upd = mysqli_prepare($konek, "UPDATE keranjang SET jumlah=? WHERE id=? AND id_user=?");
        mysqli_stmt_bind_param($upd, "iii", $jumlah, $id_keranjang, $id_user);
        mysqli_stmt_execute($upd);
        echo json_encode(['status' => 'ok', 'jumlah' => $jumlah]);
        exit;
    }

    // ── TAMBAH / KURANG (dari tombol +/-) ──
    $cek = mysqli_prepare($konek, "SELECT jumlah FROM keranjang WHERE id=? AND id_user=?");
    mysqli_stmt_bind_param($cek, "ii", $id_keranjang, $id_user);
    mysqli_stmt_execute($cek);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($cek));

    if ($row) {
        $jumlah = $action === 'tambah' ? $row['jumlah'] + 1 : max(1, $row['jumlah'] - 1);
        $upd = mysqli_prepare($konek, "UPDATE keranjang SET jumlah=? WHERE id=?");
        mysqli_stmt_bind_param($upd, "ii", $jumlah, $id_keranjang);
        mysqli_stmt_execute($upd);
        echo json_encode(['status' => 'ok', 'jumlah' => $jumlah]);
    } else {
        echo json_encode(['status' => 'error']);
    }
    exit;
}

/* ===============================
   AMBIL DATA KERANJANG
================================*/
$query = mysqli_query($konek, "
    SELECT 
        keranjang.id AS id_keranjang,
        keranjang.jumlah,
        keranjang.id_varian,

        menu_req.id AS id_menu,
        menu_req.nama_makanan,
        menu_req.harga,
        menu_req.foto,
        menu_req.stok,
        menu_req.deskripsi,

        kantin.nama_kantin,
        kantin.id_kantin,

        varian_menu.nama,
        varian_menu.foto_varian,
        varian_menu.harga_tambah,

        (menu_req.harga + COALESCE(varian_menu.harga_tambah, 0)) AS harga_total

    FROM keranjang

    JOIN menu_req 
        ON keranjang.id_menu = menu_req.id

    JOIN kantin   
        ON menu_req.id_kantin = kantin.id_kantin

    LEFT JOIN varian_menu
        ON keranjang.id_varian = varian_menu.id

    WHERE keranjang.id_user = $id_user

    ORDER BY kantin.nama_kantin ASC, keranjang.id ASC
");
$data_kantin = [];
$total_semua = 0;

while ($row = mysqli_fetch_assoc($query)) {
    $data_kantin[$row['nama_kantin']][] = $row;
    $total_semua += $row['harga'] * $row['jumlah'];
}

$total_item = 0;
foreach ($data_kantin as $menus)
    foreach ($menus as $m)
        $total_item += $m['jumlah'];


$id_user = $_SESSION['id_user'];

$query = mysqli_query($konek, "SELECT username, pp FROM users WHERE id_user = '$id_user'");

$user = mysqli_fetch_assoc($query);

$username_user = $user['username'];
$pp_user = $user['pp'];

// Simpan halaman terakhir
$secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
setcookie("last_page", $_SERVER['REQUEST_URI'], time() + (30 * 24 * 60 * 60), "/", "", $secure, false);
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Keranjang Belanja — E-Canteen Esemkita</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
    <link rel="stylesheet" href="../custom_confirm.css">
    <link
        href="https://fonts.googleapis.com/css2?family=Righteous&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap"
        rel="stylesheet">
    <style>
        *,
        *::before,
        *::after {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        a {
            text-decoration: none;
            color: inherit;
        }

        :root {
            --orange: #e8650a;
            --orange-dark: #c45500;
            --orange-light: #fff5ee;
            --orange-mid: #ffd8bd;
            --text-main: #222;
            --text-muted: #888;
            --border: #ececec;
            --bg-page: #f4f4f4;
            --bg-white: #fff;
            --radius-sm: 8px;
            --radius-md: 12px;
            --radius-lg: 16px;
        }

        .notif-chat-btn {
            position: relative;
        }

        .chat-badge {
            position: absolute;
            top: -0.3125rem;
            right: -0.3125rem;
            min-width: 1.125rem;
            height: 1.125rem;
            padding: 0 0.3125rem;
            background: #ef4444;
            color: #fff;
            border-radius: 62.4375rem;
            font-size: 0.625rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 2px solid #fff;
            line-height: 1;
        }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: var(--bg-page);
            color: var(--text-main);
            min-height: 100vh;
            padding-top: 116px;
            padding-bottom: 110px;
        }

        .orange-bg {
            background: linear-gradient(112deg, #d45500 0%, #e86510 30%, #f98e20 65%, #ffc55a 100%);
        }

        /* ══════════════════════════════════════
           TOPBAR
        ══════════════════════════════════════ */
        .topbar {
            background: #b84a00;
            padding: 0 52px;
            height: 36px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-size: 12px;
            color: rgba(255, 255, 255, .8);
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            z-index: 1000;
        }

        .top-left {
            display: flex;
            align-items: center;
        }

        .top-left a {
            color: rgba(255, 255, 255, .75);
            padding: 0 12px;
            font-size: 12px;
            transition: .2s;
        }

        .top-left a:first-child {
            padding-left: 0;
        }

        .top-left a:hover {
            color: #ffe0a0;
        }

        .sep {
            color: rgba(255, 255, 255, .2);
            user-select: none;
        }

        .top-right {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .top-right>a {
            color: rgba(255, 255, 255, .75);
            font-size: 12px;
            display: flex;
            align-items: center;
            gap: 5px;
            transition: .2s;
        }

        .top-right>a:hover {
            color: #ffe0a0;
        }

        .lang {
            position: relative;
            display: flex;
            align-items: center;
            gap: 6px;
            cursor: pointer;
            color: rgba(255, 255, 255, .75);
            font-size: 12px;
        }

        .lang i.fa-chevron-down {
            font-size: 8px;
            transition: .3s;
        }

        .lang:hover {
            color: #ffe0a0;
        }

        .lang:hover i.fa-chevron-down {
            transform: rotate(180deg);
        }

        .lang-drop {
            position: absolute;
            top: calc(100% + 14px);
            right: 0;
            width: 190px;
            background: #fff;
            border-radius: 10px;
            box-shadow: 0 12px 40px rgba(0, 0, 0, .2);
            padding: 6px 0;
            opacity: 0;
            visibility: hidden;
            transform: translateY(8px);
            transition: .22s;
            z-index: 999;
        }

        .lang:hover .lang-drop {
            opacity: 1;
            visibility: visible;
            transform: translateY(0);
        }

        .lang-drop a {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 9px 16px;
            color: #444;
            font-size: 13px;
            transition: .15s;
        }

        .lang-drop a:hover {
            background: var(--orange-light);
            color: var(--orange);
        }

        .lang-drop .active {
            color: var(--orange);
            font-weight: 600;
        }

        .profile {
            position: relative;
            display: flex;
            align-items: center;
            gap: 7px;
            cursor: pointer;
        }

        .profile img {
            width: 26px;
            height: 26px;
            border-radius: 50%;
            object-fit: cover;
            border: 1.5px solid rgba(255, 255, 255, .5);
        }

        .profile span {
            font-size: 12px;
            color: rgba(255, 255, 255, .8);
        }

        .profile-drop {
            position: absolute;
            top: calc(100% + 14px);
            right: 0;
            width: 210px;
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 12px 40px rgba(0, 0, 0, .18);
            padding: 8px 0;
            opacity: 0;
            visibility: hidden;
            transform: translateY(8px);
            transition: .22s;
            z-index: 999;
        }

        .profile:hover .profile-drop {
            opacity: 1;
            visibility: visible;
            transform: translateY(0);
        }

        .profile-drop a {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px 18px;
            color: #333;
            font-size: 13px;
            transition: .15s;
        }

        .profile-drop a i {
            width: 16px;
            text-align: center;
            color: #999;
            font-size: 13px;
        }

        .profile-drop a:hover {
            background: var(--orange-light);
            color: var(--orange);
        }

        .profile-drop a:hover i {
            color: var(--orange);
        }

        .drop-line {
            height: 1px;
            background: #f2f2f2;
            margin: 5px 0;
        }

        .logout {
            color: #e53935 !important;
        }

        .logout i {
            color: #e53935 !important;
        }

        /* ══════════════════════════════════════
           NAVBAR
        ══════════════════════════════════════ */
        .navbar {
            padding: 0 52px;
            height: 80px;
            display: flex;
            align-items: center;
            gap: 16px;
            position: fixed;
            top: 36px;
            left: 0;
            width: 100%;
            z-index: 999;
        }

        .navbar::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            height: 2px;
            background: linear-gradient(90deg, transparent, rgba(255, 210, 100, .5) 30%, rgba(255, 250, 200, .8) 50%, rgba(255, 210, 100, .5) 70%, transparent);
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-shrink: 0;
        }

        .logo img {
            width: 64px;
            filter: drop-shadow(0 2px 8px rgba(0, 0, 0, .25));
        }

        .logo-text {
            display: flex;
            flex-direction: column;
        }

        .logo-title {
            font-family: 'Righteous', cursive;
            font-size: 22px;
            color: #fff;
            line-height: 1.1;
        }

        .logo-sub {
            font-size: 10px;
            color: rgba(255, 240, 200, .85);
            letter-spacing: 2px;
            text-transform: uppercase;
            font-weight: 600;
        }

        .nav-divider {
            width: 1.5px;
            height: 26px;
            background: rgba(255, 255, 255, .25);
            flex-shrink: 0;
        }

        .nav-label {
            font-family: 'Righteous', cursive;
            font-size: 17px;
            color: rgba(255, 255, 255, .9);
            letter-spacing: 1px;
            flex-shrink: 0;
        }

        .icon-actions {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-left: auto;
            position: relative;
            z-index: 1;
        }

        .icon-btn {
            position: relative;
            width: 44px;
            height: 44px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-size: 20px;
            cursor: pointer;
            border-radius: 12px;
            background: rgba(255, 255, 255, .15);
            border: 1.5px solid rgba(255, 255, 255, .3);
            transition: .2s;
        }

        .icon-btn:hover {
            background: rgba(255, 255, 255, .28);
            transform: translateY(-2px);
        }

        .icon-badge {
            position: absolute;
            top: -5px;
            right: -5px;
            min-width: 18px;
            height: 18px;
            background: #fff;
            color: var(--orange);
            border-radius: 9px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 10px;
            font-weight: 700;
            padding: 0 4px;
            box-shadow: 0 2px 6px rgba(0, 0, 0, .18);
        }

        /* ══════════════════════════════════════
           PAGE LAYOUT — CENTERED (FIX UTAMA)
        ══════════════════════════════════════ */
        .page-wrap {
            max-width: 860px;
            /* lebar konten yang lebih terfokus */
            margin: 24px auto;
            /* auto = centered horizontal */
            padding: 0 24px;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        /* ── EMPTY STATE ── */
        .kosong-wrap {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 60vh;
        }

        .kosong {
            text-align: center;
            padding: 48px 32px;
            background: var(--bg-white);
            border-radius: var(--radius-lg);
            box-shadow: 0 2px 12px rgba(0, 0, 0, .06);
        }

        .kosong-icon {
            font-size: 56px;
            color: #ddd;
            margin-bottom: 16px;
        }

        .kosong h3 {
            font-size: 20px;
            font-weight: 700;
            color: #333;
            margin-bottom: 8px;
        }

        .kosong p {
            font-size: 14px;
            color: var(--text-muted);
            margin-bottom: 24px;
        }

        .kosong a {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 12px 28px;
            background: linear-gradient(135deg, #f98020, var(--orange));
            color: #fff;
            border-radius: 10px;
            font-weight: 700;
            font-size: 14px;
        }

        /* ── TABLE HEADER ── */
        .cart-table-head {
            background: var(--bg-white);
            border-radius: var(--radius-sm);
            padding: 12px 18px;
            display: grid;
            grid-template-columns: 32px 1fr 120px 140px 120px 56px;
            gap: 12px;
            align-items: center;
            box-shadow: 0 1px 4px rgba(0, 0, 0, .05);
            font-size: 12px;
            color: var(--text-muted);
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: .4px;
        }

        /* ── SELECT ALL BAR ── */
        .select-all-bar {
            background: var(--bg-white);
            border-radius: var(--radius-sm);
            padding: 12px 18px;
            display: flex;
            align-items: center;
            gap: 10px;
            box-shadow: 0 1px 4px rgba(0, 0, 0, .05);
            font-size: 14px;
            font-weight: 600;
            color: #333;
        }

        .select-all-bar label {
            cursor: pointer;
        }

        .select-all-bar .info {
            margin-left: auto;
            font-size: 12px;
            color: #aaa;
            font-weight: 400;
        }

        input[type="checkbox"] {
            width: 16px;
            height: 16px;
            accent-color: var(--orange);
            cursor: pointer;
            flex-shrink: 0;
        }

        /* ── KANTIN CARD ── */
        .kantin-card {
            background: var(--bg-white);
            border-radius: var(--radius-md);
            box-shadow: 0 1px 6px rgba(0, 0, 0, .06);
            overflow: hidden;
        }

        .kantin-header {
            background: #fff8f3;
            padding: 10px 18px;
            display: flex;
            align-items: center;
            gap: 10px;
            border-bottom: 1px solid #ffe8d6;
        }

        .kantin-icon {
            width: 28px;
            height: 28px;
            background: linear-gradient(135deg, #f98020, #d45500);
            border-radius: 7px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-size: 13px;
            flex-shrink: 0;
        }

        .kantin-nama {
            font-weight: 700;
            font-size: 14px;
            color: var(--orange);
        }

        .kantin-badge {
            margin-left: auto;
            background: var(--orange-light);
            color: var(--orange);
            border-radius: 20px;
            padding: 2px 10px;
            font-size: 11px;
            font-weight: 700;
            border: 1px solid var(--orange-mid);
        }

        /* ── ITEM ROW (Desktop) ── */
        .item-row {
            display: grid;
            grid-template-columns: 32px 1fr 120px 140px 120px 56px;
            align-items: center;
            gap: 12px;
            padding: 16px 18px;
            border-bottom: 1px solid #f5f5f5;
            transition: background .15s;
        }

        .item-row:last-child {
            border-bottom: none;
        }

        .item-row:hover {
            background: #fffaf7;
        }

        .item-check-wrap {
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .item-product {
            display: flex;
            align-items: center;
            gap: 12px;
            min-width: 0;
        }

        .item-img {
            width: 76px;
            height: 76px;
            border-radius: 10px;
            object-fit: cover;
            flex-shrink: 0;
            border: 1px solid #f0f0f0;
            background: #fafafa;
        }

        .item-info {
            flex: 1;
            min-width: 0;
            display: flex;
            flex-direction: column;
            gap: 5px;
        }

        .item-nama {
            font-size: 14px;
            font-weight: 700;
            color: #222;
            line-height: 1.4;
            word-break: break-word;
        }

        .item-stok {
            font-size: 12px;
            color: var(--text-muted);
        }

        .cart-varian-wrap {
            margin-top: 4px;
        }

        .cart-varian {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: var(--orange-light);
            border: 1px solid var(--orange-mid);
            padding: 4px 10px;
            border-radius: 999px;
        }

        .cart-varian-img {
            width: 20px;
            height: 20px;
            border-radius: 50%;
            object-fit: cover;
            flex-shrink: 0;
        }

        .cart-varian-text {
            font-size: 12px;
            font-weight: 600;
            color: #c55e08;
            white-space: nowrap;
        }

        .item-harga-col {
            font-size: 14px;
            font-weight: 700;
            color: var(--orange);
            text-align: center;
            line-height: 1.4;
        }

        /* Qty stepper */
        .qty-wrap {
            display: inline-flex;
            align-items: center;
            border: 1.5px solid var(--border);
            border-radius: 10px;
            overflow: hidden;
            height: 40px;
        }

        .qty-btn {
            width: 36px;
            height: 40px;
            border: none;
            background: #fafafa;
            font-size: 20px;
            font-weight: 700;
            color: var(--orange);
            cursor: pointer;
            transition: background .15s;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .qty-btn:hover {
            background: var(--orange-light);
        }

        .qty-num {
            width: 44px;
            border: none;
            text-align: center;
            font-size: 15px;
            font-weight: 700;
            outline: none;
            background: transparent;
            font-family: inherit;
        }

        .qty-num::-webkit-inner-spin-button,
        .qty-num::-webkit-outer-spin-button {
            -webkit-appearance: none;
        }

        .qty-center {
            display: flex;
            justify-content: center;
        }

        /* Subtotal desktop */
        .item-subtotal-col {
            text-align: right;
        }

        .subtotal-label {
            font-size: 11px;
            color: #bbb;
            margin-bottom: 2px;
        }

        .subtotal-val {
            font-size: 15px;
            font-weight: 800;
            color: var(--orange);
        }

        /* Mobile subtotal inline — sembunyikan di desktop */
        .mob-subtotal-inline {
            display: none;
        }

        .item-actions {
            display: flex;
            justify-content: center;
        }

        .hapus-btn {
            width: 38px;
            height: 38px;
            border-radius: 10px;
            border: 1px solid #ffdede;
            background: #fff5f5;
            color: #e05555;
            font-size: 16px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: .15s;
        }

        .hapus-btn:hover {
            background: #ffe0e0;
            border-color: #f5b5b5;
        }

        /* ══════════════════════════════════════
           STICKY CHECKOUT BAR
        ══════════════════════════════════════ */
        .cart-right {
            position: fixed;
            bottom: 0;
            left: 0;
            width: 100%;
            background: rgba(255, 255, 255, .98);
            backdrop-filter: blur(16px);
            border-top: 1.5px solid var(--border);
            z-index: 998;
            padding: 12px 32px;
            box-shadow: 0 -4px 24px rgba(0, 0, 0, .09);
        }

        .ringkasan-card {
            max-width: 860px;
            margin: 0 auto;
            display: flex;
            align-items: center;
            gap: 24px;
        }

        .ringkasan-card h3 {
            display: none;
        }

        /* Info total kiri */
        .ringkasan-row {
            display: flex;
            flex-direction: column;
            gap: 2px;
            flex: 1;
            min-width: 0;
        }

        .ringkasan-row>span:first-child {
            font-size: 12px;
            color: var(--text-muted);
            font-weight: 500;
        }

        #total-harga-display {
            font-size: 26px;
            font-weight: 800;
            color: var(--orange);
            line-height: 1.1;
        }

        /* Divider vertikal antara total dan tombol */
        .ringkasan-divider {
            display: block;
            width: 1.5px;
            height: 40px;
            background: var(--border);
            flex-shrink: 0;
            border-radius: 2px;
        }

        .ringkasan-total {
            display: none;
        }

        .btn-checkout {
            height: 52px;
            padding: 0 36px;
            border: none;
            border-radius: 14px;
            background: linear-gradient(135deg, #f98020, var(--orange));
            color: #fff;
            font-size: 15px;
            font-weight: 700;
            font-family: inherit;
            cursor: pointer;
            transition: .2s;
            box-shadow: 0 6px 18px rgba(232, 101, 10, .3);
            white-space: nowrap;
            display: flex;
            align-items: center;
            gap: 8px;
            flex-shrink: 0;
        }

        .btn-checkout:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 24px rgba(232, 101, 10, .38);
        }

        .btn-checkout:active {
            transform: translateY(0);
        }

        /* ══════════════════════════════════════
           MOBILE
        ══════════════════════════════════════ */
        .mobile-nav {
            display: none;
        }

        .mob-bottom-nav {
            display: none;
        }

        .mob-back-btn {
            display: none;
        }

        /* Sembunyikan table header di layar sedang */
        @media (max-width: 900px) {
            .cart-table-head {
                display: none;
            }

            .item-row {
                grid-template-columns: 32px 1fr 100px 120px 100px 48px;
                gap: 8px;
            }
        }

        @media (max-width: 768px) {

            body {
                padding-top: 64px;
                padding-bottom: 88px;
            }

            .topbar {
                display: none;
            }

            .navbar {
                display: none;
            }

            .mob-bottom-nav {
                display: none !important;
            }

            /* Mobile top bar */
            .mobile-nav {
                display: block;
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                z-index: 1000;
            }

            .mob-top {
                height: 64px;
                padding: 0 14px;
                display: flex;
                align-items: center;
                gap: 10px;
            }

            .mob-back-btn {
                display: flex;
                align-items: center;
                justify-content: center;
                width: 36px;
                height: 36px;
                border-radius: 10px;
                background: rgba(255, 255, 255, .2);
                border: 1.5px solid rgba(255, 255, 255, .35);
                color: #fff;
                font-size: 15px;
                flex-shrink: 0;
                transition: background .18s, transform .18s;
                text-decoration: none;
            }

            .mob-back-btn:active {
                background: rgba(255, 255, 255, .35);
                transform: translateX(-2px);
            }

            .mob-logo {
                display: flex;
                align-items: center;
                gap: 9px;
                flex: 1;
                min-width: 0;
            }

            .mob-logo img {
                width: 40px;
                flex-shrink: 0;
                filter: drop-shadow(0 2px 6px rgba(0, 0, 0, .2));
            }

            .mob-logo-text {
                display: flex;
                flex-direction: column;
            }

            .mob-logo-title {
                font-family: 'Righteous', cursive;
                font-size: 18px;
                color: #fff;
                line-height: 1.1;
            }

            .mob-logo-sub {
                font-size: 8px;
                color: rgba(255, 240, 200, .85);
                letter-spacing: 1.5px;
                text-transform: uppercase;
                font-weight: 600;
            }

            .mob-icons {
                display: flex;
                gap: 8px;
                flex-shrink: 0;
            }

            .mob-icon-btn {
                position: relative;
                width: 38px;
                height: 38px;
                border-radius: 10px;
                background: rgba(255, 255, 255, .18);
                border: 1.5px solid rgba(255, 255, 255, .3);
                display: flex;
                align-items: center;
                justify-content: center;
                color: #fff;
                font-size: 17px;
                cursor: pointer;
            }

            .mob-badge {
                position: absolute;
                top: -4px;
                right: -4px;
                background: #fff;
                color: var(--orange);
                border-radius: 8px;
                font-size: 9px;
                font-weight: 700;
                padding: 1px 5px;
                min-width: 16px;
                text-align: center;
            }

            /* Checkout bar */
            .cart-right {
                bottom: 0;
                padding: 12px 16px 16px;
                box-shadow: 0 -6px 24px rgba(0, 0, 0, .1);
            }

            .ringkasan-card {
                gap: 10px;
            }

            #total-harga-display {
                font-size: 20px;
            }

            .ringkasan-divider {
                display: none;
            }

            .btn-checkout {
                height: 48px;
                padding: 0 18px;
                font-size: 14px;
                border-radius: 12px;
                box-shadow: 0 4px 14px rgba(232, 101, 10, .3);
            }

            /* ══ ITEM ROW — Shopee style ══ */
            .item-row {
                display: grid;
                grid-template-columns: 26px 1fr;
                grid-template-rows: auto auto;
                column-gap: 10px;
                row-gap: 0;
                padding: 14px 14px 14px 12px;
                position: relative;
                align-items: start;
            }

            .item-check-wrap {
                grid-column: 1;
                grid-row: 1;
                padding-top: 22px;
                align-self: start;
            }

            .item-product {
                grid-column: 2;
                grid-row: 1;
                align-items: flex-start;
            }

            .item-img {
                width: 70px;
                height: 70px;
            }

            .item-nama {
                font-size: 13px;
            }

            .item-stok {
                font-size: 11px;
            }

            .item-harga-col {
                display: none;
            }

            .item-subtotal-col:not(.mob-subtotal-inline) {
                display: none;
            }

            .qty-center {
                grid-column: 2;
                grid-row: 2;
                display: flex;
                align-items: center;
                justify-content: space-between;
                margin-top: 12px;
                padding-right: 0;
                gap: 8px;
            }

            .qty-wrap {
                height: 34px;
                border-radius: 8px;
            }

            .qty-btn {
                width: 32px;
                height: 34px;
                font-size: 18px;
            }

            .qty-num {
                width: 36px;
                font-size: 13px;
            }

            .mob-subtotal-inline {
                display: block !important;
                text-align: right;
                flex-shrink: 0;
            }

            .mob-harga-satuan {
                display: block;
                font-size: 10px;
                color: #bbb;
                margin-bottom: 2px;
                white-space: nowrap;
            }

            .mob-subtotal-inline .subtotal-label {
                font-size: 10px;
                color: #999;
                margin-bottom: 1px;
            }

            .mob-subtotal-inline .subtotal-val {
                font-size: 15px;
                font-weight: 800;
                color: var(--orange);
            }

            .item-actions {
                position: absolute;
                top: 12px;
                right: 12px;
            }

            .hapus-btn {
                width: 28px;
                height: 28px;
                font-size: 12px;
                border-radius: 7px;
            }

            .page-wrap {
                margin: 14px auto;
                padding: 0 12px;
                gap: 10px;
            }

            .select-all-bar {
                padding: 10px 14px;
                font-size: 13px;
            }

            .select-all-bar .info {
                display: none;
            }

            .kantin-header {
                padding: 10px 14px;
            }
        }

        @supports (padding-bottom: env(safe-area-inset-bottom)) {
            @media (max-width: 768px) {
                .cart-right {
                    padding-bottom: calc(16px + env(safe-area-inset-bottom));
                }

                body {
                    padding-bottom: calc(88px + env(safe-area-inset-bottom));
                }
            }
        }

        /* Search overlay */
        .search-overlay {
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, .5);
            z-index: 1200;
            display: none;
            align-items: flex-end;
            backdrop-filter: blur(4px);
        }

        .search-overlay.open {
            display: flex;
        }

        .search-sheet {
            width: 100%;
            background: #fff;
            border-radius: 20px 20px 0 0;
            padding: 20px 16px 32px;
            animation: slideUp .3s cubic-bezier(.34, 1.2, .64, 1);
        }

        @keyframes slideUp {
            from {
                transform: translateY(100%);
            }

            to {
                transform: translateY(0);
            }
        }

        .search-sheet-handle {
            width: 40px;
            height: 4px;
            background: #ddd;
            border-radius: 2px;
            margin: 0 auto 18px;
        }

        .search-sheet h3 {
            font-size: 15px;
            font-weight: 700;
            color: #333;
            margin-bottom: 14px;
        }

        .search-sheet-box {
            display: flex;
            background: #f5f5f5;
            border-radius: 50px;
            overflow: hidden;
            height: 50px;
            border: 2px solid transparent;
            transition: .25s;
        }

        .search-sheet-box:focus-within {
            border-color: #f98020;
            background: #fff;
        }

        .search-sheet-box input {
            flex: 1;
            border: none;
            outline: none;
            padding: 0 18px;
            font-size: 15px;
            background: transparent;
            font-family: inherit;
        }

        .search-sheet-box button {
            width: 52px;
            border: none;
            background: linear-gradient(145deg, #f98020, #e06010);
            color: #fff;
            cursor: pointer;
            font-size: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 0 50px 50px 0;
        }

        .search-suggestions {
            margin-top: 16px;
        }

        .search-suggestions p {
            font-size: 12px;
            color: #aaa;
            font-weight: 600;
            letter-spacing: .5px;
            text-transform: uppercase;
            margin-bottom: 10px;
        }

        .suggestion-chips {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }

        .chip {
            padding: 6px 14px;
            background: var(--orange-light);
            color: var(--orange);
            border-radius: 50px;
            font-size: 13px;
            font-weight: 600;
            border: 1px solid rgba(232, 101, 10, .2);
            cursor: pointer;
        }

        .chip:active {
            background: #ffe0c0;
        }

        .kantin-visit-btn {
            margin-left: 8px;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 4px 12px;
            background: linear-gradient(135deg, #f98020, var(--orange));
            color: #fff;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 700;
            transition: .2s;
            white-space: nowrap;
        }

        .kantin-visit-btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 10px rgba(232, 101, 10, .3);
        }
    </style>
</head>

<body>

    <!-- ══════════ TOPBAR (Desktop) ══════════ -->
    <div class="topbar">
        <div class="top-left">
            <a href="user.php">Home</a><span class="sep">|</span>
            <a href="menu.php">Menu</a><span class="sep">|</span>
            <a href="pesan.php?tab=pesanan.php">Pesanan</a>
        </div>
        <div class="top-right">
            <a href=""><i class="fa-regular fa-bell"></i> Notifikasi</a>
            <a href=""><i class="fa-regular fa-circle-question"></i> Bantuan</a>
            <div class="lang">
                <i class="fa-solid fa-globe" style="font-size:11px"></i>
                <span>Bahasa Indonesia</span>
                <i class="fa-solid fa-chevron-down"></i>
                <div class="lang-drop">
                    <a href="" class="active"><i class="fa-solid fa-check"></i> Bahasa Indonesia</a>
                    <a href="">English</a>
                </div>
            </div>
            <div class="profile">
                <?php if ($pp_user): ?>
                    <img src="../gambar/profil/<?= htmlspecialchars($pp_user) ?>"
                        alt="<?= htmlspecialchars($username_user) ?>"
                        onerror="this.src='https://ui-avatars.com/api/?name=<?= urlencode($username_user) ?>&background=e8650a&color=fff&size=32'">
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

    <!-- ══════════ NAVBAR (Desktop) ══════════ -->
    <div class="navbar orange-bg">
        <div class="logo">
            <img src="../gambar/logo.png" alt="Logo">
            <div class="logo-text">
                <span class="logo-title">E-Canteen</span>
                <span class="logo-sub">Esemkita</span>
            </div>
        </div>
        <span class="nav-divider"></span>
        <span class="nav-label">Keranjang</span>
        <div class="icon-actions">
            <div class="icon-btn">
                <button onclick="openChatPopup()" class="icon-btn notif-chat-btn"
                    style="background:none;border:none;cursor:pointer;">
                    <i class="fa-solid fa-comment-dots"></i>
                    <span class="chat-badge" id="chatBadge" style="display:none"></span>
                </button>
            </div>
        </div>
    </div>

    <!-- ══════════ MOBILE NAV ══════════ -->
    <div class="mobile-nav">
        <div class="mob-top orange-bg">
            <a href="user.php" class="mob-back-btn" aria-label="Kembali">
                <i class="fa-solid fa-arrow-left"></i>
            </a>
            <div class="mob-logo">
                <img src="../gambar/logo.png" alt="Logo">
                <div class="mob-logo-text">
                    <span class="mob-logo-title">E-Canteen</span>
                    <span class="mob-logo-sub">Esemkita</span>
                </div>
            </div>
            <div class="mob-icons">
                <div class="mob-icon-btn">
                    <button onclick="openChatPopup()" class="icon-btn notif-chat-btn"
                        style="background:none;border:none;cursor:pointer;">
                        <i class="fa-solid fa-comment-dots"></i>
                        <span class="chat-badge" id="chatBadge" style="display:none"></span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- ══════════ BOTTOM NAV (Mobile) ══════════ -->
    <div class="mob-bottom-nav">
        <div class="mob-nav-item"><i class="fa-solid fa-house"></i><span>Home</span></div>
        <div class="mob-nav-item"><i class="fa-solid fa-bowl-food"></i><span>Menu</span></div>
        <div class="mob-nav-search"
            onclick="document.getElementById('searchOverlay').classList.add('open'); setTimeout(()=>document.getElementById('sheetInput').focus(),300)">
            <div class="mob-nav-search-btn"><i class="fa-solid fa-magnifying-glass"></i></div>
            <span>Cari</span>
        </div>
        <div class="mob-nav-item" style="position:relative">
            <i class="fa-solid fa-bag-shopping"></i>
            <span>Pesanan</span>
            <?php if ($total_item > 0): ?>
                <div
                    style="position:absolute;top:4px;right:calc(50% - 18px);background:var(--orange);color:#fff;border-radius:8px;font-size:9px;font-weight:700;padding:1px 5px;min-width:16px;text-align:center">
                    <?= $total_item ?></div>
            <?php endif; ?>
        </div>
        <div class="mob-nav-item"><i class="fa-regular fa-user"></i><span>Akun</span></div>
    </div>

    <!-- ══════════ PAGE CONTENT ══════════ -->
    <div class="page-wrap">

        <?php if (empty($data_kantin)): ?>
            <div class="kosong-wrap">
                <div class="kosong">
                    <div class="kosong-icon"><i class="fa-solid fa-cart-shopping"></i></div>
                    <h3>Keranjang Kamu Masih Kosong</h3>
                    <p>Yuk, temukan makanan dan minuman favoritmu di kantin!</p>
                    <a href="menu.php"><i class="fa-solid fa-bowl-food"></i>&nbsp; Jelajahi Menu</a>
                </div>
            </div>

        <?php else: ?>

            <!-- Table Header (Desktop only) -->
            <div class="cart-table-head">
                <div></div>
                <div>Produk</div>
                <div style="text-align:center">Harga Satuan</div>
                <div style="text-align:center">Kuantitas</div>
                <div style="text-align:right">Total Harga</div>
                <div style="text-align:center">Aksi</div>
            </div>

            <!-- Select All -->
            <div class="select-all-bar">
                <input type="checkbox" id="checkAll" onchange="toggleAll(this)">
                <label for="checkAll">Pilih Semua</label>
                <span class="info"><?= count($data_kantin) ?> kantin &middot; <?= $total_item ?> item</span>
            </div>

            <!-- Per Kantin -->
            <?php foreach ($data_kantin as $nama_kantin => $menus): ?>
                <div class="kantin-card">
                    <div class="kantin-header">
                        <div class="kantin-icon"><i class="fa-solid fa-store"></i></div>
                        <span class="kantin-nama"><?= htmlspecialchars($nama_kantin) ?></span>
                        <span class="kantin-badge"><?= count($menus) ?> menu</span>
                        <a href="kategori.php?id_kantin=<?= (int) $menus[0]['id_kantin'] ?>" class="kantin-visit-btn">
                            <i class="fa-solid fa-arrow-up-right-from-square"></i> Kunjungi
                        </a>
                    </div>
                    <?php foreach ($menus as $item): ?>
                        <div class="item-row" id="row-<?= $item['id_keranjang'] ?>">

                            <!-- Checkbox -->
                            <div class="item-check-wrap">
                                <input type="checkbox" class="item-check" data-id="<?= $item['id_keranjang'] ?>"
                                    data-harga="<?= $item['harga_total'] ?>" onchange="hitungTotal()">
                            </div>

                            <!-- Product -->
                            <div class="item-product">
                                <img class="item-img" src="../penjual/uploads/<?= htmlspecialchars($item['foto']) ?>"
                                    onerror="this.src='gambar/default-food.png'"
                                    alt="<?= htmlspecialchars($item['nama_makanan']) ?>">
                                <div class="item-info">
                                    <div class="item-nama"><?= htmlspecialchars($item['nama_makanan']) ?></div>
                                    <div class="item-stok">Stok: <?= $item['stok'] ?></div>
                                    <?php if (!empty($item['nama'])): ?>
                                        <div class="cart-varian-wrap">
                                            <div class="cart-varian">
                                                <?php if (!empty($item['foto_varian'])): ?>
                                                    <img src="../penjual/uploads/<?= htmlspecialchars($item['foto_varian']) ?>"
                                                        alt="<?= htmlspecialchars($item['nama']) ?>" class="cart-varian-img"
                                                        onerror="this.style.display='none'">
                                                <?php endif; ?>
                                                <span class="cart-varian-text"><?= htmlspecialchars($item['nama']) ?></span>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Harga Satuan — desktop only kolom -->
                            <div class="item-harga-col">
                                Rp<?= number_format($item['harga_total'], 0, ',', '.') ?>
                            </div>

                            <!-- Qty + Subtotal inline -->
                            <div class="qty-center">
                                <div class="qty-wrap">
                                    <button class="qty-btn"
                                        onclick="ubahQty(<?= $item['id_keranjang'] ?>, 'kurang', <?= $item['harga_total'] ?>, <?= $item['stok'] ?>)">−</button>
                                    <input type="number" class="qty-num" id="qty-<?= $item['id_keranjang'] ?>"
                                        value="<?= $item['jumlah'] ?>" data-stok="<?= $item['stok'] ?>" inputmode="numeric">
                                    <button class="qty-btn"
                                        onclick="ubahQty(<?= $item['id_keranjang'] ?>, 'tambah', <?= $item['harga_total'] ?>, <?= $item['stok'] ?>)">+</button>
                                </div>

                                <!-- Subtotal inline mobile -->
                                <div class="item-subtotal-col mob-subtotal-inline">
                                    <span
                                        class="mob-harga-satuan">Rp<?= number_format($item['harga_total'], 0, ',', '.') ?>/pcs</span>
                                    <div class="subtotal-label">Total</div>
                                    <div class="subtotal-val" id="sub-<?= $item['id_keranjang'] ?>_mob">
                                        Rp<?= number_format($item['harga_total'] * $item['jumlah'], 0, ',', '.') ?>
                                    </div>
                                </div>
                            </div>

                            <!-- Subtotal kolom — desktop only -->
                            <div class="item-subtotal-col">
                                <div class="subtotal-label">Total</div>
                                <div class="subtotal-val" id="sub-<?= $item['id_keranjang'] ?>">
                                    Rp<?= number_format($item['harga_total'] * $item['jumlah'], 0, ',', '.') ?>
                                </div>
                            </div>

                            <!-- Hapus -->
                            <div class="item-actions">
                                <button class="hapus-btn" onclick="hapusItem(<?= $item['id_keranjang'] ?>)" title="Hapus">
                                    <i class="fa-regular fa-trash-can"></i>
                                </button>
                            </div>

                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>

            <!-- STICKY CHECKOUT BAR -->
            <div class="cart-right">
                <div class="ringkasan-card">
                    <div class="ringkasan-row">
                        <span>Total (<span id="jumlah-terpilih">0</span> item dipilih)</span>
                        <span id="total-harga-display">Rp 0</span>
                    </div>
                    <div class="ringkasan-divider"></div>
                    <button class="btn-checkout" onclick="prosesOrder()">
                        <i class="fa-solid fa-bag-shopping"></i> Pesan Sekarang (<span id="checkout-count">0</span>)
                    </button>
                </div>
            </div>

        <?php endif; ?>
    </div><!-- /page-wrap -->

    <!-- Search Overlay (Mobile) -->
    <div class="search-overlay" id="searchOverlay" onclick="if(event.target===this)this.classList.remove('open')">
        <div class="search-sheet">
            <div class="search-sheet-handle"></div>
            <h3>Cari Makanan &amp; Minuman</h3>
            <div class="search-sheet-box">
                <input id="sheetInput" type="text" placeholder="Ketik nama menu...">
                <button><i class="fa-solid fa-magnifying-glass"></i></button>
            </div>
            <div class="search-suggestions">
                <p>Populer</p>
                <div class="suggestion-chips">
                    <div class="chip">Nasi Goreng</div>
                    <div class="chip">Mie Ayam</div>
                    <div class="chip">Es Teh</div>
                    <div class="chip">Bakso</div>
                    <div class="chip">Soto Ayam</div>
                    <div class="chip">Jus Alpukat</div>
                </div>
            </div>
        </div>
    </div>
    <script>


        /* ── Chat badge notifikasi ── */
        async function loadNotifChat() {
            try {
                const res = await fetch('chat_api.php?action=jumlah_belum_baca');
                const data = await res.json();
                const badges = document.querySelectorAll('.chat-badge');
                badges.forEach(b => {
                    if (data.belum_baca > 0) {
                        b.style.display = 'flex';
                        b.textContent = data.belum_baca;
                    } else {
                        b.style.display = 'none';
                    }
                });
            } catch (e) { console.error('Notif chat error:', e); }
        }
        loadNotifChat();
        setInterval(loadNotifChat, 10000);

    </script>
    <script>
let pendingSaves = new Set();

function fmt(n) {
    return 'Rp' + n.toLocaleString('id-ID');
}

function toggleAll(master) {
    document.querySelectorAll('.item-check').forEach(cb => cb.checked = master.checked);
    hitungTotal();
}

function hitungTotal() {
    let total = 0, count = 0;
    document.querySelectorAll('.item-check:checked').forEach(cb => {
        const id    = cb.dataset.id;
        const harga = parseInt(cb.dataset.harga);
        const qty   = parseInt(document.getElementById('qty-' + id).value);
        total += harga * qty;
        count += qty;
    });
    document.getElementById('total-harga-display').textContent = fmt(total);
    document.getElementById('jumlah-terpilih').textContent     = count;
    document.getElementById('checkout-count').textContent      = count;

    const semua   = document.querySelectorAll('.item-check');
    const centang = document.querySelectorAll('.item-check:checked');
    document.getElementById('checkAll').checked = semua.length === centang.length && semua.length > 0;
}

function updateSubtotalEls(id_keranjang, harga, jumlah) {
    const desktopEl = document.getElementById('sub-' + id_keranjang);
    if (desktopEl) desktopEl.textContent = fmt(harga * jumlah);
    const mobEl = document.getElementById('sub-' + id_keranjang + '_mob');
    if (mobEl) mobEl.textContent = fmt(harga * jumlah);
}

function ubahQty(id_keranjang, action, harga, stok) {
    const qtyEl = document.getElementById('qty-' + id_keranjang);
    let qty = parseInt(qtyEl.value) || 1;

    if (action === 'tambah') {
        if (qty >= stok) {
            confirmPopup({
                judul    : 'Stok Tidak Cukup',
                ikon     : '📦',
                pesan    : 'Maksimal ' + stok + ' porsi untuk menu ini.',
                tipe     : 'warning',
                labelOK  : 'Oke',
                labelBatal: null,
            });
            return;
        }
        qty++;
    } else {
        if (qty <= 1) return;
        qty--;
    }

    qtyEl.value = qty;
    updateSubtotalEls(id_keranjang, harga, qty);
    hitungTotal();

    fetch('keranjang.php', {
        method : 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body   : 'action=' + action + '&id_keranjang=' + id_keranjang
    })
    .then(r => r.json())
    .then(data => {
        if (data.status === 'ok') {
            qtyEl.value = data.jumlah;
            updateSubtotalEls(id_keranjang, harga, data.jumlah);
            hitungTotal();
        }
    });
}

function hapusItem(id_keranjang) {
    confirmPopup({
        judul  : 'Hapus dari Keranjang',
        ikon   : '🗑️',
        pesan  : 'Hapus item ini dari keranjang?',
        tipe   : 'danger',
        labelOK: 'Ya, Hapus',
        onOK   : function () {
            const row = document.getElementById('row-' + id_keranjang);
            row.style.opacity    = '0.3';
            row.style.transition = 'opacity .3s';
            setTimeout(() => { window.location.href = 'keranjang.php?hapus=' + id_keranjang; }, 300);
        }
    });
}

function prosesOrder() {
    const tercentang = document.querySelectorAll('.item-check:checked');
    if (tercentang.length === 0) {
        confirmPopup({
            judul    : 'Belum Ada yang Dipilih',
            ikon     : '⚠️',
            pesan    : 'Pilih minimal 1 item untuk dipesan!',
            tipe     : 'info',
            labelOK  : 'Oke, Mengerti',
            labelBatal: null,
        });
        return;
    }

    let itemMelanggar = [];
    tercentang.forEach(cb => {
        const id   = cb.dataset.id;
        const row  = document.getElementById('row-' + id);
        const stok = parseInt(row.querySelector('.qty-num').dataset.stok);
        const qty  = parseInt(document.getElementById('qty-' + id).value);
        const nama = row.querySelector('.item-nama').textContent.trim();
        if (qty > stok) itemMelanggar.push(nama + ' (maks. ' + stok + ')');
    });

    if (itemMelanggar.length > 0) {
        confirmPopup({
            judul    : 'Stok Tidak Mencukupi',
            ikon     : '📦',
            pesan    : 'Item berikut melebihi stok yang tersedia:',
            poin     : itemMelanggar,
            tipe     : 'warning',
            labelOK  : 'Oke, Perbaiki',
            labelBatal: null,
        });
        return;
    }

    if (pendingSaves.size > 0) {
        confirmPopup({
            judul    : 'Menyimpan Perubahan',
            ikon     : '⏳',
            pesan    : 'Jumlah item masih disimpan, coba lagi sebentar.',
            tipe     : 'info',
            labelOK  : 'Oke',
            labelBatal: null,
        });
        return;
    }

    const ids = Array.from(tercentang).map(cb => cb.dataset.id).join(',');
    window.location.href = 'co.php?from=keranjang&ids=' + ids;
}

// ── Event listener input qty (keyboard) ──
document.querySelectorAll('.qty-num').forEach(function(el) {
    const id_keranjang = el.id.replace('qty-', '');
    const stok         = parseInt(el.dataset.stok);
    const row          = document.getElementById('row-' + id_keranjang);
    const harga        = parseInt(row.querySelector('.item-check').dataset.harga);
    let debounceTimer;

    el.addEventListener('input', function() {
    let qty = parseInt(this.value) || 1;
    if (qty < 1) qty = 1;

    if (qty > stok) {
        qty = stok;
        this.value = stok;
        updateSubtotalEls(id_keranjang, harga, qty);
        hitungTotal();

        confirmPopup({
            judul    : 'Melebihi Stok',
            ikon     : '📦',
            pesan    : 'Maksimal ' + stok + ' porsi untuk menu ini.',
            tipe     : 'warning',
            labelOK  : 'Oke',
            labelBatal: null,
        });

        // Batalkan debounce, langsung simpan nilai max
        clearTimeout(debounceTimer);
        pendingSaves.add(id_keranjang);
        fetch('keranjang.php', {
            method : 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body   : 'action=set&id_keranjang=' + id_keranjang + '&jumlah=' + qty
        })
        .then(r => r.json())
        .finally(() => { pendingSaves.delete(id_keranjang); });
        return;
    }

    updateSubtotalEls(id_keranjang, harga, qty);
    hitungTotal();

    clearTimeout(debounceTimer);
    pendingSaves.add(id_keranjang);

    debounceTimer = setTimeout(() => {
        fetch('keranjang.php', {
            method : 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body   : 'action=set&id_keranjang=' + id_keranjang + '&jumlah=' + qty
        })
        .then(r => r.json())
        .then(data => {
            if (data.status === 'ok') {
                this.value = qty;
                updateSubtotalEls(id_keranjang, harga, qty);
                hitungTotal();
            }
        })
        .finally(() => { pendingSaves.delete(id_keranjang); });
    }, 600);
});

    el.addEventListener('blur', function() {
        let qty = parseInt(this.value) || 1;
        if (qty < 1) { qty = 1; this.value = 1; }
        if (qty > stok) { qty = stok; this.value = stok; }
        updateSubtotalEls(id_keranjang, harga, qty);
        hitungTotal();
    });
});

localStorage.setItem("last_page", window.location.href);
    </script>
    <script src="../custom_confirm.js"></script>
    <?php include 'chat_popup.php'; ?>
</body>

</html>