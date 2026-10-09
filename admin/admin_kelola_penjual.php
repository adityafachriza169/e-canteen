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

/* ========================= AJAX: CEK PASSWORD REKAP ========================= */
if (isset($_POST['cek_password_rekap'])) {
    header('Content-Type: application/json');
    $id_penjual    = intval($_POST['id_penjual'] ?? 0);
    $password_raw  = $_POST['password_input'] ?? '';
    $password_md5  = md5($password_raw);

    if ($id_penjual <= 0 || $password_raw === '') {
        echo json_encode(['ok' => false, 'msg' => 'Data tidak lengkap']);
        exit;
    }

    $stmt = mysqli_prepare($db, "SELECT id_penjual FROM penjual WHERE id_penjual = ? AND password = ?");
    mysqli_stmt_bind_param($stmt, "is", $id_penjual, $password_md5);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_store_result($stmt);
    $found = mysqli_stmt_num_rows($stmt) > 0;
    mysqli_stmt_close($stmt);

    echo json_encode(['ok' => $found, 'msg' => $found ? 'OK' : 'Password salah. Coba lagi.']);
    exit;
}

$pesan_sukses = "";
$pesan_error  = "";

/* ========================= HAPUS QRIS ========================= */
if (isset($_GET['hapus_qris'])) {
    $id_qris = intval($_GET['hapus_qris']);
    $res_qris = mysqli_query($db, "SELECT gambar_qris FROM penjual_qris WHERE id_qris = '$id_qris'");
    if ($row_qris = mysqli_fetch_assoc($res_qris)) {
        $file_qris = $row_qris['gambar_qris'];
        if (!empty($file_qris) && file_exists("uploads/qris/" . $file_qris)) {
            unlink("uploads/qris/" . $file_qris);
        }
    }
    mysqli_query($db, "DELETE FROM penjual_qris WHERE id_qris = '$id_qris'");
    header("Location: admin_kelola_penjual.php?sukses=5");
    exit;
}

/* ========================= APPROVE QRIS ========================= */
if (isset($_GET['approve_qris'])) {
    $id_qris = intval($_GET['approve_qris']);
    mysqli_query($db, "UPDATE penjual_qris SET verified = 1, verified_at = NOW() WHERE id_qris = '$id_qris'");
    header("Location: admin_kelola_penjual.php?sukses=3");
    exit;
}

/* ========================= REJECT QRIS ========================= */
if (isset($_GET['reject_qris'])) {
    $id_qris = intval($_GET['reject_qris']);
    $res_qris = mysqli_query($db, "SELECT gambar_qris FROM penjual_qris WHERE id_qris = '$id_qris'");
    if ($row_qris = mysqli_fetch_assoc($res_qris)) {
        $file_qris = $row_qris['gambar_qris'];
        if (!empty($file_qris) && file_exists("uploads/qris/" . $file_qris)) {
            unlink("uploads/qris/" . $file_qris);
        }
    }
    mysqli_query($db, "DELETE FROM penjual_qris WHERE id_qris = '$id_qris'");
    header("Location: admin_kelola_penjual.php?sukses=4");
    exit;
}

/* ========================= PULIHKAN MENU (RESTORE) ========================= */
if (isset($_GET['pulihkan_menu'])) {
    $id_menu = intval($_GET['pulihkan_menu']);
    mysqli_query($db, "UPDATE menu_req SET deleted_at = NULL, aktif = 0 WHERE id = '$id_menu'");
    header("Location: admin_kelola_penjual.php?sukses=11");
    exit;
}

/* ========================= HAPUS MENU PERMANEN (HARD DELETE) ========================= */
if (isset($_GET['hapus_menu_permanen'])) {
    $id_menu = intval($_GET['hapus_menu_permanen']);
    $cek_menu = mysqli_query($db, "SELECT id FROM menu_req WHERE id = '$id_menu' AND deleted_at IS NOT NULL");
    if (mysqli_num_rows($cek_menu) > 0) {
        mysqli_query($db, "UPDATE transaksi SET id_menu = NULL WHERE id_menu = '$id_menu'");
        mysqli_query($db, "DELETE FROM menu_req WHERE id = '$id_menu'");
    }
    header("Location: admin_kelola_penjual.php?sukses=12");
    exit;
}

/* ========================= TAMBAH PENJUAL ========================= */
if (isset($_POST['simpan'])) {
    $nama      = mysqli_real_escape_string($db, $_POST['nama']);
    $username  = mysqli_real_escape_string($db, $_POST['username']);
    $email     = mysqli_real_escape_string($db, $_POST['email']);
    $no_tlp    = mysqli_real_escape_string($db, $_POST['no_tlp'] ?? '');
    $passsword = $_POST['password'];
    $id_kantin = intval($_POST['canteen']);

    if (!preg_match('/@gmail\.com$/i', $email)) {
        $pesan_error = "❌ Email harus menggunakan @gmail.com";
    }

    if ($pesan_error == "") {
        if (!preg_match('/^(08|628)[0-9]{7,11}$/', $no_tlp)) {
            $pesan_error = "❌ No. telepon tidak valid. Gunakan format 08xxx atau 628xxx (hanya angka)";
        }
    }

    if ($pesan_error == "") {
        $cekUsername = mysqli_query($db, "SELECT id_penjual FROM penjual WHERE username='$username' AND deleted_at IS NULL");
        if (mysqli_num_rows($cekUsername) > 0) {
            $pesan_error = "❌ Username <strong>$username</strong> sudah digunakan oleh akun lain, silakan pilih username yang berbeda";
        }
    }

    if ($pesan_error == "") {
        $cekAktif = mysqli_query($db, "SELECT id_kantin FROM penjual WHERE id_kantin='$id_kantin' AND status != 0 AND deleted_at IS NULL");
        if (mysqli_num_rows($cekAktif) > 0) {
            $pesan_error = "❌ Kantin $id_kantin sudah terdaftar oleh penjual lain yang masih aktif";
        }
    }

    $password    = md5($passsword);
    $profil      = "";
    $gambar_qris = "";

    if ($pesan_error == "") {
        if (isset($_FILES['profil']) && $_FILES['profil']['error'] == 0) {
            $folder = "uploads/";
            if (!is_dir($folder)) mkdir($folder, 0777, true);
            $namaFile = $_FILES['profil']['name'];
            $tmpFile  = $_FILES['profil']['tmp_name'];
            $ext      = strtolower(pathinfo($namaFile, PATHINFO_EXTENSION));
            $allowed  = ['jpg', 'jpeg', 'png', 'webp'];
            if (in_array($ext, $allowed)) {
                $namaBaru = time() . "_" . rand(1000, 9999) . "." . $ext;
                if (move_uploaded_file($tmpFile, $folder . $namaBaru)) {
                    $profil = $namaBaru;
                } else {
                    $pesan_error = "❌ Gagal upload foto profil";
                }
            } else {
                $pesan_error = "❌ Format foto profil tidak didukung";
            }
        }
    }

    if ($pesan_error == "") {
        if (!isset($_FILES['gambar_qris']) || $_FILES['gambar_qris']['error'] != 0) {
            $pesan_error = "❌ Gambar QRIS wajib diupload";
        } else {
            $folder_qris = "uploads/qris/";
            if (!is_dir($folder_qris)) mkdir($folder_qris, 0777, true);
            $namaQris = $_FILES['gambar_qris']['name'];
            $tmpQris  = $_FILES['gambar_qris']['tmp_name'];
            $extQ     = strtolower(pathinfo($namaQris, PATHINFO_EXTENSION));
            $allowedQ = ['jpg', 'jpeg', 'png', 'webp'];
            if (in_array($extQ, $allowedQ)) {
                $namaBaruQ = "qris_" . time() . "_" . rand(1000, 9999) . "." . $extQ;
                if (!move_uploaded_file($tmpQris, $folder_qris . $namaBaruQ)) {
                    $pesan_error = "❌ Gagal upload gambar QRIS";
                } else {
                    $gambar_qris = $namaBaruQ;
                }
            } else {
                $pesan_error = "❌ Format gambar QRIS tidak didukung (jpg/png/webp)";
            }
        }
    }

    if ($pesan_error == "") {
        $sql = "INSERT INTO penjual (id_kantin, nama, email, username, password, profil, status, no_tlp)
                VALUES ('$id_kantin','$nama','$email','$username','$password','$profil', 1, '$no_tlp')";
        if (mysqli_query($db, $sql)) {
            $id_penjual_baru = mysqli_insert_id($db);
            $sqlQ = "INSERT INTO penjual_qris (id_penjual, gambar_qris, verified)
                     VALUES ('$id_penjual_baru', '$gambar_qris', 0)";
            mysqli_query($db, $sqlQ);
            header("Location: admin_kelola_penjual.php?sukses=1");
            exit;
        } else {
            $pesan_error = "❌ Registrasi gagal: " . mysqli_error($db);
        }
    }
}

/* ========================= EDIT PENJUAL AKTIF ========================= */
if (isset($_POST['edit'])) {
    $id    = intval($_POST['edit_id']);
    $nama  = mysqli_real_escape_string($db, $_POST['edit_nama']);
    $email = mysqli_real_escape_string($db, $_POST['edit_email']);
    if (!preg_match('/@gmail\.com$/i', $email)) {
        $pesan_error = "❌ Email harus menggunakan @gmail.com";
    } else {
        mysqli_query($db, "UPDATE penjual SET nama='$nama', email='$email' WHERE id_penjual='$id'");
        header("Location: admin_kelola_penjual.php?sukses=2");
        exit;
    }
}

/* ========================= EDIT PENJUAL NONAKTIF ========================= */
if (isset($_POST['edit_nonaktif'])) {
    $id_penjual = intval($_POST['enon_id']);
    $nama       = mysqli_real_escape_string($db, $_POST['enon_nama']);
    $email      = mysqli_real_escape_string($db, $_POST['enon_email']);
    $new_kantin = intval($_POST['enon_kantin']);

    if (!preg_match('/@gmail\.com$/i', $email)) {
        $pesan_error = "❌ Email harus menggunakan @gmail.com";
    } else {
        if ($new_kantin > 0) {
            mysqli_query($db, "UPDATE penjual SET nama='$nama', email='$email',
                               nonaktif_dari_kantin='$new_kantin'
                               WHERE id_penjual='$id_penjual' AND status=0");
        } else {
            mysqli_query($db, "UPDATE penjual SET nama='$nama', email='$email'
                               WHERE id_penjual='$id_penjual' AND status=0");
        }
        header("Location: admin_kelola_penjual.php?sukses=9");
        exit;
    }
}

/* ========================= NONAKTIFKAN ========================= */
if (isset($_GET['nonaktifkan'])) {
    $id_penjual = intval($_GET['nonaktifkan']);
    $res_pj = mysqli_query($db, "SELECT id_kantin FROM penjual WHERE id_penjual='$id_penjual' AND status != 0 AND deleted_at IS NULL");
    if ($row_pj = mysqli_fetch_assoc($res_pj)) {
        mysqli_query($db, "UPDATE menu_req SET aktif=0 WHERE id_penjual='$id_penjual' AND deleted_at IS NULL");
        mysqli_query($db, "UPDATE penjual
                           SET status=0,
                               nonaktif_dari_kantin = id_kantin,
                               id_kantin = NULL
                           WHERE id_penjual='$id_penjual'");
        header("Location: admin_kelola_penjual.php?sukses=6");
        exit;
    } else {
        header("Location: admin_kelola_penjual.php");
        exit;
    }
}

/* ========================= AKTIFKAN KEMBALI ========================= */
if (isset($_GET['aktifkan'])) {
    $id_penjual = intval($_GET['aktifkan']);
    $res_pj = mysqli_query($db, "SELECT nonaktif_dari_kantin FROM penjual WHERE id_penjual='$id_penjual' AND status=0 AND deleted_at IS NULL");
    if ($row_pj = mysqli_fetch_assoc($res_pj)) {
        $kantin_asal = intval($row_pj['nonaktif_dari_kantin']);
        if ($kantin_asal > 0) {
            $cekOkupasi = mysqli_query($db, "SELECT id_penjual FROM penjual
                                             WHERE id_kantin='$kantin_asal' AND status != 0 AND deleted_at IS NULL");
            if (mysqli_num_rows($cekOkupasi) > 0) {
                header("Location: admin_kelola_penjual.php?error_aktifkan=$kantin_asal");
                exit;
            }
            mysqli_query($db, "UPDATE penjual
                               SET status=1,
                                   id_kantin = nonaktif_dari_kantin,
                                   nonaktif_dari_kantin = NULL
                               WHERE id_penjual='$id_penjual'");
            mysqli_query($db, "UPDATE menu_req SET aktif=1 WHERE id_penjual='$id_penjual' AND deleted_at IS NULL");
            header("Location: admin_kelola_penjual.php?sukses=7");
            exit;
        } else {
            header("Location: admin_kelola_penjual.php?error_nokantin=$id_penjual");
            exit;
        }
    } else {
        header("Location: admin_kelola_penjual.php");
        exit;
    }
}

/* ========================= HAPUS PERMANEN PENJUAL (SOFT DELETE) ========================= */
if (isset($_GET['hapus_permanen'])) {
    $id_penjual = intval($_GET['hapus_permanen']);
    $cek = mysqli_query($db, "SELECT id_penjual FROM penjual WHERE id_penjual='$id_penjual' AND deleted_at IS NULL");
    if (mysqli_num_rows($cek) > 0) {
        mysqli_query($db, "UPDATE penjual
                           SET deleted_at = NOW(),
                               status = 0,
                               id_kantin = NULL
                           WHERE id_penjual = '$id_penjual'");
        mysqli_query($db, "UPDATE menu_req SET aktif = 0, deleted_at = NOW() WHERE id_penjual = '$id_penjual' AND deleted_at IS NULL");
    }
    header("Location: admin_kelola_penjual.php?sukses=8");
    exit;
}

/* ========================= PESAN SUKSES / ERROR ========================= */
if (isset($_GET['sukses'])) {
    $map = [
        1  => "✅ Akun penjual berhasil ditambahkan",
        2  => "✅ Data penjual berhasil diperbarui",
        3  => "✅ QRIS berhasil diverifikasi",
        4  => "✅ QRIS berhasil ditolak dan dihapus",
        5  => "✅ QRIS berhasil dihapus",
        6  => "✅ Akun penjual berhasil dinonaktifkan (menu penjual ini ikut dinonaktifkan)",
        7  => "✅ Akun penjual berhasil diaktifkan kembali beserta menunya",
        8  => "✅ Akun penjual berhasil dihapus (data rekap tetap tersimpan di database)",
        9  => "✅ Data penjual nonaktif berhasil diperbarui",
        11 => "✅ Menu berhasil dipulihkan dari daftar terhapus",
        12 => "✅ Menu dihapus permanen dari database",
    ];
    $pesan_sukses = $map[$_GET['sukses']] ?? "";
}
if (isset($_GET['error_aktifkan'])) {
    $k = intval($_GET['error_aktifkan']);
    $pesan_error = "❌ Tidak bisa diaktifkan: Kantin $k masih ditempati oleh penjual aktif lain. Nonaktifkan penjual kantin $k terlebih dahulu.";
}
if (isset($_GET['error_nokantin'])) {
    $pesan_error = "❌ Tidak bisa diaktifkan: Data kantin asal tidak ditemukan. Gunakan tombol Edit untuk mengatur kantin terlebih dahulu.";
}

/* ========================= AMBIL DATA PENJUAL ========================= */
$data_aktif    = mysqli_query($db, "SELECT * FROM penjual WHERE status != 0 AND deleted_at IS NULL ORDER BY id_kantin ASC");
$data_nonaktif = mysqli_query($db, "SELECT * FROM penjual WHERE status = 0 AND deleted_at IS NULL ORDER BY nonaktif_dari_kantin ASC");

$kantinTerpakai = [];
$resKantin = mysqli_query($db, "SELECT id_kantin FROM penjual WHERE status != 0 AND deleted_at IS NULL AND id_kantin IS NOT NULL");
while ($rk = mysqli_fetch_assoc($resKantin)) {
    $kantinTerpakai[] = intval($rk['id_kantin']);
}

/* Semua QRIS pending */
$q_pending = mysqli_query($db, "
    SELECT pq.*, p.nama AS nama_penjual, p.id_kantin,
           COALESCE(p.id_kantin, p.nonaktif_dari_kantin) AS kantin_tampil
    FROM penjual_qris pq
    JOIN penjual p ON p.id_penjual = pq.id_penjual
    WHERE pq.verified = 0
      AND p.deleted_at IS NULL
    ORDER BY pq.created_at ASC
");
$pending_count = mysqli_num_rows($q_pending);

/* Semua QRIS per kantin (aktif) */
$q_all_qris = mysqli_query($db, "
    SELECT pq.*, p.nama AS nama_penjual, p.id_kantin,
           COALESCE(p.id_kantin, p.nonaktif_dari_kantin) AS kantin_tampil
    FROM penjual_qris pq
    JOIN penjual p ON p.id_penjual = pq.id_penjual
    WHERE p.status != 0
      AND p.deleted_at IS NULL
    ORDER BY p.id_kantin ASC, pq.created_at DESC
");
$qris_per_kantin = [];
while ($r = mysqli_fetch_assoc($q_all_qris)) {
    $qris_per_kantin[$r['id_kantin']][] = $r;
}

/* =====================================================================
   REKAPAN PER id_penjual
   ===================================================================== */
$rekap_per_penjual = [];
$q_rekap = mysqli_query($db, "
    SELECT
        m.id_penjual,
        t.id_kantin,
        t.nama_kantin,
        COUNT(*) AS total_transaksi,
        SUM(CASE WHEN t.status = 'lunas' THEN t.total ELSE 0 END) AS total_pendapatan,
        SUM(CASE WHEN t.status = 'lunas' THEN t.jumlah ELSE 0 END) AS total_item_terjual,
        SUM(CASE WHEN t.status = 'batal'   THEN 1 ELSE 0 END) AS total_batal,
        SUM(CASE WHEN t.status = 'ditolak' THEN 1 ELSE 0 END) AS total_ditolak,
        SUM(CASE WHEN t.status = 'lunas'   THEN 1 ELSE 0 END) AS total_lunas,
        SUM(CASE WHEN t.metode = 'CASH' AND t.status = 'lunas' THEN t.total ELSE 0 END) AS pendapatan_cash,
        SUM(CASE WHEN t.metode = 'QRIS' AND t.status = 'lunas' THEN t.total ELSE 0 END) AS pendapatan_qris,
        MAX(CASE WHEN t.status = 'lunas' THEN t.total ELSE 0 END) AS transaksi_tertinggi,
        MIN(t.tanggal) AS transaksi_pertama,
        MAX(t.tanggal) AS transaksi_terakhir
    FROM transaksi t
    JOIN menu_req m ON m.id = t.id_menu
    GROUP BY m.id_penjual, t.id_kantin, t.nama_kantin
    ORDER BY m.id_penjual ASC
");
while ($r = mysqli_fetch_assoc($q_rekap)) {
    $rekap_per_penjual[$r['id_penjual']] = $r;
}

/* Menu terlaris per penjual */
$terlaris_per_penjual = [];
$q_terlaris = mysqli_query($db, "
    SELECT m.id_penjual, t.nama_makanan,
           SUM(t.jumlah) AS qty_terjual, SUM(t.total) AS total_omset
    FROM transaksi t
    JOIN menu_req m ON m.id = t.id_menu
    WHERE t.status = 'lunas'
    GROUP BY m.id_penjual, t.id_menu, t.nama_makanan
    ORDER BY m.id_penjual ASC, qty_terjual DESC
");
while ($r = mysqli_fetch_assoc($q_terlaris)) {
    $idp = $r['id_penjual'];
    if (!isset($terlaris_per_penjual[$idp])) $terlaris_per_penjual[$idp] = [];
    $terlaris_per_penjual[$idp][] = $r;
}

/* Pendapatan harian 7 hari terakhir per penjual */
$harian_per_penjual = [];
$q_harian = mysqli_query($db, "
    SELECT m.id_penjual, DATE(t.tanggal) AS tgl,
           SUM(CASE WHEN t.status = 'lunas' THEN t.total ELSE 0 END) AS pendapatan_harian,
           COUNT(CASE WHEN t.status = 'lunas' THEN 1 END) AS trx_harian
    FROM transaksi t
    JOIN menu_req m ON m.id = t.id_menu
    WHERE t.tanggal >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    GROUP BY m.id_penjual, DATE(t.tanggal)
    ORDER BY m.id_penjual ASC, tgl ASC
");
while ($r = mysqli_fetch_assoc($q_harian)) {
    $harian_per_penjual[$r['id_penjual']][] = $r;
}

/* 5 transaksi terbaru per penjual */
$trx_terbaru_per_penjual = [];
$q_trx_terbaru = mysqli_query($db, "
    SELECT t.*, m.id_penjual, u.nama AS nama_pembeli, u.kelas AS kelas_pembeli
    FROM transaksi t
    JOIN menu_req m ON m.id = t.id_menu
    LEFT JOIN users u ON u.id_user = t.id_user
    ORDER BY m.id_penjual ASC, t.tanggal DESC
");
while ($r = mysqli_fetch_assoc($q_trx_terbaru)) {
    $idp = $r['id_penjual'];
    if (!isset($trx_terbaru_per_penjual[$idp])) $trx_terbaru_per_penjual[$idp] = [];
    if (count($trx_terbaru_per_penjual[$idp]) < 5) $trx_terbaru_per_penjual[$idp][] = $r;
}

/* ========================= REKAP PER MENU ========================= */
$rekap_menu_per_penjual = [];
$q_rekap_menu = mysqli_query($db, "
    SELECT
        m.id AS id_menu,
        m.id_penjual,
        m.nama_makanan,
        m.kategori,
        m.harga AS harga_menu,
        m.foto,
        m.aktif,
        m.deskripsi,
        m.stok,
        m.waktu_proses,
        m.terjual AS terjual_counter,
        m.deleted_at AS menu_deleted_at,
        COUNT(t.id_transaksi) AS total_transaksi,
        SUM(CASE WHEN t.status = 'lunas' THEN t.jumlah ELSE 0 END) AS total_terjual,
        SUM(CASE WHEN t.status = 'lunas' THEN t.total ELSE 0 END) AS total_omset,
        SUM(CASE WHEN t.status = 'batal' THEN 1 ELSE 0 END) AS total_batal,
        SUM(CASE WHEN t.status = 'ditolak' THEN 1 ELSE 0 END) AS total_ditolak,
        SUM(CASE WHEN t.metode = 'CASH' AND t.status = 'lunas' THEN t.total ELSE 0 END) AS omset_cash,
        SUM(CASE WHEN t.metode = 'QRIS' AND t.status = 'lunas' THEN t.total ELSE 0 END) AS omset_qris,
        MAX(CASE WHEN t.status = 'lunas' THEN t.total ELSE 0 END) AS transaksi_terbesar,
        MIN(CASE WHEN t.status = 'lunas' THEN t.tanggal END) AS pertama_laku,
        MAX(CASE WHEN t.status = 'lunas' THEN t.tanggal END) AS terakhir_laku,
        AVG(CASE WHEN t.status = 'lunas' THEN t.total END) AS rata_rata_omset,
        (SELECT AVG(ul.bintang) FROM ulasan ul WHERE ul.id_menu = m.id) AS rata_bintang,
        (SELECT COUNT(*) FROM ulasan ul WHERE ul.id_menu = m.id) AS jumlah_ulasan
    FROM menu_req m
    LEFT JOIN transaksi t ON t.id_menu = m.id
    GROUP BY m.id, m.id_penjual, m.nama_makanan, m.kategori, m.harga, m.foto, m.aktif, m.terjual, m.deleted_at, m.deskripsi, m.stok, m.waktu_proses
    ORDER BY m.id_penjual ASC, total_omset DESC
");
while ($r = mysqli_fetch_assoc($q_rekap_menu)) {
    $idp = $r['id_penjual'];
    if (!isset($rekap_menu_per_penjual[$idp])) $rekap_menu_per_penjual[$idp] = [];
    $rekap_menu_per_penjual[$idp][] = $r;
}

/* ========================= ULASAN PER MENU ========================= */
$ulasan_per_menu = [];
$q_ulasan = mysqli_query($db, "
    SELECT ul.*, u.nama AS nama_user, u.kelas AS kelas_user
    FROM ulasan ul
    LEFT JOIN users u ON u.id_user = ul.id_user
    ORDER BY ul.id_menu ASC, ul.created_at DESC
");
while ($r = mysqli_fetch_assoc($q_ulasan)) {
    $ulasan_per_menu[$r['id_menu']][] = $r;
}

/* ========================= 10 TRANSAKSI TERBARU PER MENU ========================= */
$trx_per_menu = [];
$q_trx_menu = mysqli_query($db, "
    SELECT t.*, u.nama AS nama_pembeli, u.kelas AS kelas_pembeli
    FROM transaksi t
    LEFT JOIN users u ON u.id_user = t.id_user
    WHERE t.id_menu IS NOT NULL
    ORDER BY t.id_menu ASC, t.tanggal DESC
");
while ($r = mysqli_fetch_assoc($q_trx_menu)) {
    $idm = $r['id_menu'];
    if (!isset($trx_per_menu[$idm])) $trx_per_menu[$idm] = [];
    if (count($trx_per_menu[$idm]) < 10) $trx_per_menu[$idm][] = $r;
}

/* ========================= DAFTAR VARIAN PER MENU ========================= */
$varian_per_menu = [];
$q_varian = mysqli_query($db, "
    SELECT id, id_menu, nama, foto_varian, harga_tambah, stok
    FROM varian_menu
    ORDER BY id_menu ASC, id ASC
");
while ($r = mysqli_fetch_assoc($q_varian)) {
    $idm = $r['id_menu'];
    if (!isset($varian_per_menu[$idm])) $varian_per_menu[$idm] = [];
    $varian_per_menu[$idm][] = $r;
}

/* ========================= REKAP PENJUALAN PER VARIAN ========================= */
$rekap_varian_per_menu = [];
$q_rekap_varian = mysqli_query($db, "
    SELECT t.id_menu, t.catatan, t.status, t.jumlah, t.total, t.metode
    FROM transaksi t
    WHERE t.id_menu IS NOT NULL
      AND t.catatan IS NOT NULL
      AND t.catatan LIKE 'Varian:%'
    ORDER BY t.id_menu ASC
");
while ($r = mysqli_fetch_assoc($q_rekap_varian)) {
    $idm = $r['id_menu'];
    $catatan = $r['catatan'];
    $varian_raw = '';
    if (preg_match('/^Varian:\s*(.+?)(?:\s*\|.*)?$/i', $catatan, $matches)) {
        $varian_raw = trim($matches[1]);
    }
    if ($varian_raw === '') continue;
    if (!isset($rekap_varian_per_menu[$idm])) $rekap_varian_per_menu[$idm] = [];
    if (!isset($rekap_varian_per_menu[$idm][$varian_raw])) {
        $rekap_varian_per_menu[$idm][$varian_raw] = [
            'nama_varian'   => $varian_raw,
            'total_order'   => 0,
            'total_terjual' => 0,
            'total_omset'   => 0,
            'total_lunas'   => 0,
            'total_batal'   => 0,
            'total_ditolak' => 0,
            'omset_cash'    => 0,
            'omset_qris'    => 0,
        ];
    }
    $v = &$rekap_varian_per_menu[$idm][$varian_raw];
    $v['total_order']++;
    if ($r['status'] === 'lunas') {
        $v['total_terjual'] += intval($r['jumlah']);
        $v['total_omset']   += floatval($r['total']);
        $v['total_lunas']++;
        if ($r['metode'] === 'CASH') $v['omset_cash'] += floatval($r['total']);
        if ($r['metode'] === 'QRIS') $v['omset_qris'] += floatval($r['total']);
    } elseif ($r['status'] === 'batal') {
        $v['total_batal']++;
    } elseif ($r['status'] === 'ditolak') {
        $v['total_ditolak']++;
    }
    unset($v);
}
foreach ($rekap_varian_per_menu as $idm => $varianMap) {
    $arr = array_values($varianMap);
    usort($arr, function($a, $b) { return $b['total_omset'] <=> $a['total_omset']; });
    $rekap_varian_per_menu[$idm] = $arr;
}

/* Daftar semua penjual untuk panel rekap */
$semua_penjual_list = [];
$q_semua_pj = mysqli_query($db, "
    SELECT id_penjual,
           id_kantin,
           nonaktif_dari_kantin,
           COALESCE(id_kantin, nonaktif_dari_kantin) AS kantin_ref,
           nama, username, status, deleted_at
    FROM penjual
    ORDER BY kantin_ref ASC
");
while ($r = mysqli_fetch_assoc($q_semua_pj)) {
    $semua_penjual_list[] = $r;
}

/* Daftar penjual AKTIF untuk tab rekap semua */
$kantin_aktif_list = [];
$data_pj_rekap_tmp = mysqli_query($db, "SELECT id_penjual, id_kantin, nama FROM penjual WHERE status != 0 AND deleted_at IS NULL ORDER BY id_kantin ASC");
while ($r = mysqli_fetch_assoc($data_pj_rekap_tmp)) {
    $kantin_aktif_list[] = $r;
}

/* =====================================================================
   RENDER PANEL REKAP
   ===================================================================== */
function renderRekapPanel(
    $id_penjual, $nm_penjual, $is_nonaktif, $is_deleted,
    $rekap_per_penjual, $terlaris_per_penjual, $harian_per_penjual,
    $trx_terbaru_per_penjual, $rekap_menu_per_penjual, $ulasan_per_menu,
    $trx_per_menu, $varian_per_menu, $rekap_varian_per_menu, $uid_prefix = ''
) {
    $uid = $uid_prefix . 'p' . $id_penjual;
    $rekap    = $rekap_per_penjual[$id_penjual] ?? null;
    $terlaris = $terlaris_per_penjual[$id_penjual] ?? [];
    $harian   = $harian_per_penjual[$id_penjual] ?? [];
    $trx_baru = $trx_terbaru_per_penjual[$id_penjual] ?? [];
    $menu_list = $rekap_menu_per_penjual[$id_penjual] ?? [];

    $total_pdp   = $rekap ? floatval($rekap['total_pendapatan'])    : 0;
    $total_trx   = $rekap ? intval($rekap['total_transaksi'])        : 0;
    $total_lunas = $rekap ? intval($rekap['total_lunas'])            : 0;
    $total_batal = $rekap ? intval($rekap['total_batal'])            : 0;
    $total_tolak = $rekap ? intval($rekap['total_ditolak'])          : 0;
    $total_item  = $rekap ? intval($rekap['total_item_terjual'])     : 0;
    $pdp_cash    = $rekap ? floatval($rekap['pendapatan_cash'])      : 0;
    $pdp_qris    = $rekap ? floatval($rekap['pendapatan_qris'])      : 0;
    $trx_maks    = $rekap ? floatval($rekap['transaksi_tertinggi'])  : 0;
    $tgl_pertama = $rekap ? $rekap['transaksi_pertama']              : null;
    $tgl_trakhir = $rekap ? $rekap['transaksi_terakhir']             : null;
    $nama_kantin_trx = $rekap ? $rekap['nama_kantin']               : "—";
    $id_kantin_trx   = $rekap ? intval($rekap['id_kantin'])          : 0;

    $total_metode = $pdp_cash + $pdp_qris;
    $pct_cash = $total_metode > 0 ? round(($pdp_cash / $total_metode) * 100) : 0;
    $pct_qris = $total_metode > 0 ? round(($pdp_qris / $total_metode) * 100) : 0;

    $menu_data_json = [];
    foreach ($menu_list as $mn) {
        $id_m = intval($mn['id_menu']);
        $menu_data_json[$id_m] = [
            'id_menu'         => $id_m,
            'nama_makanan'    => $mn['nama_makanan'],
            'kategori'        => $mn['kategori'],
            'harga_menu'      => intval($mn['harga_menu']),
            'foto'            => $mn['foto'] ?? '',
            'deskripsi'       => $mn['deskripsi'] ?? '',
            'stok'            => intval($mn['stok']),
            'waktu_proses'    => $mn['waktu_proses'] ?? '',
            'aktif'           => intval($mn['aktif']),
            'menu_deleted_at' => $mn['menu_deleted_at'] ?? null,
            'total_transaksi' => intval($mn['total_transaksi']),
            'total_terjual'   => intval($mn['total_terjual']),
            'total_omset'     => floatval($mn['total_omset']),
            'total_batal'     => intval($mn['total_batal']),
            'total_ditolak'   => intval($mn['total_ditolak']),
            'omset_cash'      => floatval($mn['omset_cash']),
            'omset_qris'      => floatval($mn['omset_qris']),
            'transaksi_terbesar' => floatval($mn['transaksi_terbesar']),
            'pertama_laku'    => $mn['pertama_laku'] ?? null,
            'terakhir_laku'   => $mn['terakhir_laku'] ?? null,
            'rata_rata_omset' => floatval($mn['rata_rata_omset']),
            'rata_bintang'    => $mn['rata_bintang'] ? floatval($mn['rata_bintang']) : null,
            'jumlah_ulasan'   => intval($mn['jumlah_ulasan']),
            'ulasan'          => $ulasan_per_menu[$id_m] ?? [],
            'transaksi'       => $trx_per_menu[$id_m] ?? [],
            'varian_list'     => $varian_per_menu[$id_m] ?? [],
            'rekap_varian'    => $rekap_varian_per_menu[$id_m] ?? [],
        ];
    }

    ob_start();
    if (!$rekap && empty($menu_list)): ?>
      <div class="rekap-empty-kantin">
        <div class="rek-ico">🛒</div>
        <p style="font-size:15px;font-weight:700;color:var(--text);margin-bottom:6px;">Belum ada transaksi</p>
        <p><?= htmlspecialchars($nm_penjual) ?> belum memiliki data transaksi maupun menu.</p>
      </div>
    <?php else: ?>

    <div style="background:var(--primary-soft);border:1px solid rgba(255,106,31,0.2);border-radius:12px;padding:12px 16px;margin-bottom:16px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px;">
      <div>
        <div style="font-size:15px;font-weight:800;color:var(--primary);">
          🏪 <?= $rekap ? htmlspecialchars($nama_kantin_trx) : htmlspecialchars($nm_penjual) ?>
          <?php if ($rekap && $id_kantin_trx): ?>
            <span style="font-size:12px;font-weight:500;color:var(--text-sub);">(Kantin <?= $id_kantin_trx ?>)</span>
          <?php endif; ?>
        </div>
        <div style="font-size:12.5px;color:var(--text-sub);margin-top:4px;">
          Penjual: <strong style="color:var(--text);"><?= htmlspecialchars($nm_penjual) ?></strong>
          <?php if ($is_deleted): ?>
            <span style="background:#6b728020;color:#6b7280;padding:2px 8px;border-radius:20px;font-size:11px;font-weight:700;margin-left:6px;">🗑 Akun Dihapus</span>
          <?php elseif ($is_nonaktif): ?>
            <span style="background:var(--red-soft);color:var(--red);padding:2px 8px;border-radius:20px;font-size:11px;font-weight:700;margin-left:6px;">Nonaktif</span>
          <?php endif; ?>
          <span style="background:var(--blue-soft);color:var(--blue);padding:2px 8px;border-radius:20px;font-size:11px;font-weight:700;margin-left:4px;">ID #<?= $id_penjual ?></span>
        </div>
      </div>
      <?php if ($tgl_pertama): ?>
      <div style="font-size:12px;color:var(--text-sub);text-align:right;">
        <div>Transaksi pertama: <strong style="color:var(--text);"><?= date('d M Y', strtotime($tgl_pertama)) ?></strong></div>
        <div>Terakhir: <strong style="color:var(--text);"><?= date('d M Y H:i', strtotime($tgl_trakhir)) ?></strong></div>
      </div>
      <?php endif; ?>
    </div>

    <div class="rekap-subtab-wrap" data-uid="<?= $uid ?>">
      <button class="rekap-subtab-btn active" onclick="switchSubTabByUid(this, 'ringkasan')">📊 Ringkasan</button>
      <button class="rekap-subtab-btn" onclick="switchSubTabByUid(this, 'permenu')">🍽 Per Menu</button>
      <button class="rekap-subtab-btn" onclick="switchSubTabByUid(this, 'transaksi')">🕒 Transaksi Terbaru</button>
    </div>

    <!-- RINGKASAN -->
    <div class="rekap-subpanel active" data-uid="<?= $uid ?>" data-tab="ringkasan">
    <?php if ($rekap): ?>
    <div class="rekap-stats-grid">
      <div class="rekap-stat-card"><div class="rekap-stat-icon">💰</div><div class="rekap-stat-val green">Rp <?= number_format($total_pdp, 0, ',', '.') ?></div><div class="rekap-stat-label">Total Pendapatan</div></div>
      <div class="rekap-stat-card"><div class="rekap-stat-icon">🧾</div><div class="rekap-stat-val blue"><?= number_format($total_lunas) ?></div><div class="rekap-stat-label">Transaksi Lunas</div></div>
      <div class="rekap-stat-card"><div class="rekap-stat-icon">📦</div><div class="rekap-stat-val orange"><?= number_format($total_item) ?></div><div class="rekap-stat-label">Item Terjual</div></div>
      <div class="rekap-stat-card"><div class="rekap-stat-icon">🏆</div><div class="rekap-stat-val purple">Rp <?= number_format($trx_maks, 0, ',', '.') ?></div><div class="rekap-stat-label">Transaksi Terbesar</div></div>
    </div>
    <div class="rekap-two-col">
      <div>
        <div class="rekap-section-title">📋 Ringkasan Status Transaksi</div>
        <div class="rekap-info-row"><span class="rekap-info-key">📊 Total Semua</span><span class="rekap-info-val"><?= number_format($total_trx) ?></span></div>
        <div class="rekap-info-row"><span class="rekap-info-key">✅ Lunas</span><span class="rekap-info-val" style="color:var(--green);"><?= number_format($total_lunas) ?></span></div>
        <div class="rekap-info-row"><span class="rekap-info-key">❌ Batal</span><span class="rekap-info-val" style="color:var(--red);"><?= number_format($total_batal) ?></span></div>
        <div class="rekap-info-row"><span class="rekap-info-key">🚫 Ditolak</span><span class="rekap-info-val" style="color:#ef4444;"><?= number_format($total_tolak) ?></span></div>
        <div class="rekap-info-row"><span class="rekap-info-key">📈 Success Rate</span><span class="rekap-info-val" style="color:var(--green);"><?= $total_trx > 0 ? round(($total_lunas/$total_trx)*100,1) : 0 ?>%</span></div>
      </div>
      <div>
        <div class="rekap-section-title">💳 Metode Pembayaran</div>
        <div class="metode-bar-wrap">
          <div class="metode-bar-label"><span>💵 CASH</span><span style="font-weight:700;">Rp <?= number_format($pdp_cash,0,',','.') ?> (<?= $pct_cash ?>%)</span></div>
          <div class="metode-bar-track"><div class="metode-bar-fill cash" style="width:<?= $pct_cash ?>%;"></div></div>
        </div>
        <div class="metode-bar-wrap">
          <div class="metode-bar-label"><span>📲 QRIS</span><span style="font-weight:700;">Rp <?= number_format($pdp_qris,0,',','.') ?> (<?= $pct_qris ?>%)</span></div>
          <div class="metode-bar-track"><div class="metode-bar-fill qris" style="width:<?= $pct_qris ?>%;"></div></div>
        </div>
        <div style="margin-top:12px;" class="rekap-info-row">
          <span class="rekap-info-key">💰 Total Seluruh Metode</span>
          <span class="rekap-info-val">Rp <?= number_format($total_metode,0,',','.') ?></span>
        </div>
        <?php if (!empty($harian)): ?>
        <div style="margin-top:14px;">
          <div class="rekap-section-title">📅 Pendapatan 7 Hari Terakhir</div>
          <?php foreach ($harian as $h): ?>
          <div class="rekap-info-row">
            <span class="rekap-info-key"><?= date('d M', strtotime($h['tgl'])) ?> <span style="font-size:11px;">(<?= $h['trx_harian'] ?> trx)</span></span>
            <span class="rekap-info-val" style="color:var(--green);">Rp <?= number_format($h['pendapatan_harian'],0,',','.') ?></span>
          </div>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      </div>
    </div>
    <?php if (!empty($terlaris)): ?>
    <div style="margin-bottom:16px;">
      <div class="rekap-section-title">🌟 Menu Terlaris</div>
      <?php $rank = 0; foreach ($terlaris as $menu): $rank++;
        $rank_class = $rank == 1 ? 'rank-1' : ($rank == 2 ? 'rank-2' : ($rank == 3 ? 'rank-3' : 'rank-other')); ?>
      <div class="terlaris-item">
        <span class="terlaris-rank <?= $rank_class ?>"><?= $rank ?></span>
        <span class="terlaris-name"><?= htmlspecialchars($menu['nama_makanan']) ?></span>
        <div class="terlaris-meta">
          <div class="terlaris-qty">🛒 <?= number_format($menu['qty_terjual']) ?> terjual</div>
          <div class="terlaris-omset">Omset: Rp <?= number_format($menu['total_omset'],0,',','.') ?></div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
    <?php else: ?>
    <div class="rekap-empty-kantin"><div class="rek-ico">📊</div><p>Belum ada data transaksi untuk penjual ini.</p></div>
    <?php endif; ?>
    </div>

    <!-- PER MENU -->
    <div class="rekap-subpanel" data-uid="<?= $uid ?>" data-tab="permenu">
    <?php if (!empty($menu_list)): ?>
      <div class="permenu-toolbar" data-uid="<?= $uid ?>">
        <div class="permenu-search-wrap">
          <span class="permenu-search-icon">🔍</span>
          <input type="text" class="permenu-search-input" placeholder="Cari nama menu..." data-uid="<?= $uid ?>" oninput="filterMenuTable(this)">
          <button class="permenu-clear-btn" onclick="clearMenuSearch(this)" title="Hapus pencarian">✕</button>
        </div>
        <div class="permenu-filter-wrap">
          <span style="font-size:12.5px;color:var(--text-sub);font-weight:600;white-space:nowrap;">Filter:</span>
          <button class="permenu-filter-btn active" data-filter="SEMUA" data-uid="<?= $uid ?>" onclick="filterMenuKategori(this)">🍱 Semua</button>
          <button class="permenu-filter-btn" data-filter="MAKANAN" data-uid="<?= $uid ?>" onclick="filterMenuKategori(this)">🍽 Makanan</button>
          <button class="permenu-filter-btn" data-filter="MINUMAN" data-uid="<?= $uid ?>" onclick="filterMenuKategori(this)">🥤 Minuman</button>
          <button class="permenu-filter-btn" data-filter="SNACK" data-uid="<?= $uid ?>" onclick="filterMenuKategori(this)">🍿 Snack</button>
        </div>
        <div class="permenu-count-info" data-uid="<?= $uid ?>">
          <span class="permenu-count-num" data-uid="<?= $uid ?>"><?= count($menu_list) ?></span>
          <span style="color:var(--text-sub);font-size:12px;"> menu ditampilkan</span>
        </div>
      </div>
      <div style="font-size:13px;color:var(--text-sub);margin-bottom:14px;padding:10px 14px;background:var(--blue-soft);border-radius:10px;border:1px solid rgba(59,130,246,0.2);">
        ℹ️ Rekap mencakup <strong style="color:var(--blue);">semua menu</strong> —
        termasuk <span style="background:var(--yellow-soft);color:var(--yellow);padding:1px 7px;border-radius:20px;font-size:11px;font-weight:700;">⏸ Nonaktif</span> dan
        <span style="background:var(--red-soft);color:var(--red);padding:1px 7px;border-radius:20px;font-size:11px;font-weight:700;">🗑 Dihapus</span>.
        <strong style="color:var(--blue);">Klik baris menu</strong> untuk melihat detail lengkap.
      </div>
      <div style="overflow-x:auto;">
        <table class="rekap-trx-table rekap-menu-table" data-uid="<?= $uid ?>">
          <thead>
            <tr>
              <th>Menu</th><th>Kategori</th><th>Harga</th><th>Trx</th>
              <th style="text-align:center;">Terjual</th><th style="text-align:right;">Omset</th>
              <th style="text-align:center;">⭐ Rating</th><th style="text-align:center;">Batal/Tolak</th>
              <th style="text-align:right;">CASH</th><th style="text-align:right;">QRIS</th>
              <th>Terakhir Laku</th>
            </tr>
          </thead>
          <tbody>
            <?php
            $grand_total_omset   = 0;
            $grand_total_terjual = 0;
            foreach ($menu_list as $mn):
                $grand_total_omset   += floatval($mn['total_omset']);
                $grand_total_terjual += intval($mn['total_terjual']);
                $is_del = !empty($mn['menu_deleted_at']);
                $is_nonaktif_menu = (!$is_del && !$mn['aktif']);
                $row_style = $is_del ? 'opacity:.6;background:rgba(239,68,68,0.04);' : ($is_nonaktif_menu ? 'opacity:.75;background:rgba(245,158,11,0.04);' : '');
                $omset   = floatval($mn['total_omset']);
                $terjual = intval($mn['total_terjual']);
                $rating  = $mn['rata_bintang'] ? round($mn['rata_bintang'],1) : null;
                $ulasan_ct = intval($mn['jumlah_ulasan']);
                $id_menu_enc = intval($mn['id_menu']);
                $kat = strtoupper($mn['kategori']);
                $punya_varian = !empty($varian_per_menu[$id_menu_enc]) || !empty($rekap_varian_per_menu[$id_menu_enc]);
            ?>
            <tr style="<?= $row_style ?>cursor:pointer;" class="menu-row-clickable"
                data-kategori="<?= htmlspecialchars($kat) ?>"
                data-nama="<?= htmlspecialchars(strtolower($mn['nama_makanan'])) ?>"
                data-uid="<?= $uid ?>"
                onclick="bukaDetailMenu(<?= $id_menu_enc ?>, <?= htmlspecialchars(json_encode($menu_data_json[$id_menu_enc] ?? []), ENT_QUOTES) ?>)">
              <td>
                <div style="font-weight:700;color:var(--text);font-size:13px;"><?= htmlspecialchars($mn['nama_makanan']) ?></div>
                <?php if ($is_del): ?>
                  <span style="font-size:10.5px;background:var(--red-soft);color:var(--red);padding:1px 7px;border-radius:20px;font-weight:700;">🗑 Dihapus</span>
                  <span style="font-size:10px;color:var(--text-sub);margin-left:4px;"><?= date('d/m/y', strtotime($mn['menu_deleted_at'])) ?></span>
                <?php elseif ($is_nonaktif_menu): ?>
                  <span style="font-size:10.5px;background:var(--yellow-soft);color:var(--yellow);padding:1px 7px;border-radius:20px;font-weight:700;">⏸ Nonaktif</span>
                <?php else: ?>
                  <span style="font-size:10.5px;background:var(--green-soft);color:var(--green);padding:1px 7px;border-radius:20px;font-weight:700;">🟢 Aktif</span>
                <?php endif; ?>
                <?php if ($punya_varian): ?>
                  <span style="font-size:10px;background:rgba(139,92,246,0.12);color:#7c3aed;padding:1px 7px;border-radius:20px;font-weight:700;margin-left:3px;">🎨 Ada Varian</span>
                <?php endif; ?>
              </td>
              <td>
                <?php
                $kat_map = ['MAKANAN'=>['bg'=>'rgba(249,115,22,0.12)','cl'=>'#ea580c','ic'=>'🍽'],
                            'MINUMAN'=>['bg'=>'rgba(59,130,246,0.12)','cl'=>'#2563eb','ic'=>'🥤'],
                            'SNACK'  =>['bg'=>'rgba(168,85,247,0.12)','cl'=>'#7c3aed','ic'=>'🍿']];
                $k = $kat_map[$kat] ?? ['bg'=>'var(--card2)','cl'=>'var(--text-sub)','ic'=>'📦'];
                ?>
                <span style="background:<?= $k['bg'] ?>;color:<?= $k['cl'] ?>;padding:2px 9px;border-radius:20px;font-size:11.5px;font-weight:700;"><?= $k['ic'] ?> <?= htmlspecialchars($mn['kategori']) ?></span>
              </td>
              <td style="font-weight:700;white-space:nowrap;">Rp <?= number_format(intval($mn['harga_menu']),0,',','.') ?></td>
              <td style="text-align:center;"><span style="font-size:11.5px;color:var(--text-sub);"><?= $mn['total_transaksi'] > 0 ? $mn['total_transaksi'] : '—' ?></span></td>
              <td style="text-align:center;font-weight:700;color:var(--primary);"><?= number_format($terjual) ?></td>
              <td style="text-align:right;font-weight:800;color:var(--green);white-space:nowrap;"><?= $omset > 0 ? 'Rp ' . number_format($omset,0,',','.') : '<span style="color:var(--text-sub);">—</span>' ?></td>
              <td style="text-align:center;">
                <?php if ($rating): ?>
                  <span style="color:#f59e0b;font-weight:700;"><?= $rating ?>⭐</span>
                  <div style="font-size:10.5px;color:var(--text-sub);">(<?= $ulasan_ct ?>)</div>
                <?php else: ?><span style="color:var(--text-sub);font-size:12px;">—</span><?php endif; ?>
              </td>
              <td style="text-align:center;">
                <?php $bt = intval($mn['total_batal']); $tl = intval($mn['total_ditolak']); ?>
                <?php if ($bt > 0 || $tl > 0): ?>
                  <span style="color:var(--red);font-size:12px;"><?= $bt ?>/<?= $tl ?></span>
                <?php else: ?><span style="color:var(--green);font-size:12px;">—</span><?php endif; ?>
              </td>
              <td style="text-align:right;white-space:nowrap;color:var(--green);"><?= floatval($mn['omset_cash']) > 0 ? 'Rp '.number_format(floatval($mn['omset_cash']),0,',','.') : '<span style="color:var(--text-sub);">—</span>' ?></td>
              <td style="text-align:right;white-space:nowrap;color:var(--blue);"><?= floatval($mn['omset_qris']) > 0 ? 'Rp '.number_format(floatval($mn['omset_qris']),0,',','.') : '<span style="color:var(--text-sub);">—</span>' ?></td>
              <td style="white-space:nowrap;font-size:12px;color:var(--text-sub);"><?= $mn['terakhir_laku'] ? date('d/m/y H:i', strtotime($mn['terakhir_laku'])) : '—' ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
          <tfoot>
            <tr style="background:var(--primary-soft);font-weight:800;" class="tfoot-total" data-uid="<?= $uid ?>">
              <td colspan="4" style="color:var(--primary);">📊 TOTAL</td>
              <td style="text-align:center;color:var(--primary);" class="tfoot-terjual" data-uid="<?= $uid ?>"><?= number_format($grand_total_terjual) ?> item</td>
              <td style="text-align:right;color:var(--green);" class="tfoot-omset" data-uid="<?= $uid ?>">Rp <?= number_format($grand_total_omset,0,',','.') ?></td>
              <td colspan="5"></td>
            </tr>
            <tr class="no-result-row" data-uid="<?= $uid ?>" style="display:none;">
              <td colspan="11" style="text-align:center;padding:24px;color:var(--text-sub);">
                <div style="font-size:24px;margin-bottom:6px;">🔍</div>
                <div style="font-size:13px;font-weight:600;">Tidak ada menu yang cocok</div>
              </td>
            </tr>
          </tfoot>
        </table>
      </div>
    <?php else: ?>
      <div class="rekap-empty-kantin"><div class="rek-ico">🍽</div><p>Belum ada menu terdaftar untuk penjual ini.</p></div>
    <?php endif; ?>
    </div>

    <!-- TRANSAKSI TERBARU -->
    <div class="rekap-subpanel" data-uid="<?= $uid ?>" data-tab="transaksi">
    <?php if (!empty($trx_baru)): ?>
      <div style="overflow-x:auto;">
        <table class="rekap-trx-table">
          <thead><tr><th>#</th><th>Tanggal</th><th>Menu</th><th>Pembeli</th><th>Qty</th><th>Total</th><th>Metode</th><th>Status</th></tr></thead>
          <tbody>
            <?php foreach ($trx_baru as $trx):
              $st = $trx['status'];
              $label_map = ['lunas'=>'✅ Lunas','batal'=>'❌ Batal','ditolak'=>'🚫 Ditolak','pending'=>'⏳ Pending','siap'=>'🍽 Siap','dimasak'=>'🔥 Dimasak'];
              $label_st = $label_map[$st] ?? ucfirst($st);
            ?>
            <tr>
              <td style="color:var(--text-sub);"><?= $trx['antrian'] ?></td>
              <td style="white-space:nowrap;"><?= date('d/m/y H:i', strtotime($trx['tanggal'])) ?></td>
              <td><?= htmlspecialchars($trx['nama_makanan']) ?></td>
              <td><?= htmlspecialchars($trx['nama_pembeli'] ?? '—') ?><br><span style="font-size:11px;color:var(--text-sub);"><?= htmlspecialchars($trx['kelas_pembeli'] ?? '') ?></span></td>
              <td style="text-align:center;"><?= $trx['jumlah'] ?></td>
              <td style="white-space:nowrap;font-weight:700;">Rp <?= number_format($trx['total'],0,',','.') ?></td>
              <td><?= htmlspecialchars($trx['metode']) ?></td>
              <td><span class="status-badge status-<?= strtolower(str_replace(' ','-',$st)) ?>"><?= $label_st ?></span></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php else: ?>
      <div class="rekap-empty-kantin"><div class="rek-ico">🕒</div><p>Belum ada transaksi untuk penjual ini.</p></div>
    <?php endif; ?>
    </div>

    <?php endif;
    return ob_get_clean();
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Kelola Akun Penjual</title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../custom_confirm.css">
<style>
  body.dark{--bg:#0e0e16;--bg2:#13131f;--card:#1a1a2b;--card2:#1f1f33;--header:rgba(14,14,22,0.92);--text:#f0f0f8;--text-sub:#8888aa;--border:rgba(255,255,255,0.07);--primary:#ff7a3d;--primary-soft:rgba(255,122,61,0.15);--shadow:rgba(0,0,0,0.5);--nav-hover:rgba(255,255,255,0.06);--glass:rgba(255,255,255,0.04);--tr-hover:#1e2a45;--input:#1d2235;--sub:#8888aa;--modal-bg:rgba(0,0,0,0.7);--green:#10b981;--green-soft:rgba(16,185,129,0.15);--blue:#3b82f6;--blue-soft:rgba(59,130,246,0.15);--purple:#8b5cf6;--purple-soft:rgba(139,92,246,0.15);--red:#ef4444;--red-soft:rgba(239,68,68,0.12);--yellow:#f59e0b;--yellow-soft:rgba(245,158,11,0.15);}
  body.light{--bg:#f5f5fa;--bg2:#ededf5;--card:#ffffff;--card2:#fafafa;--header:rgba(255,255,255,0.95);--text:#111122;--text-sub:#666680;--border:rgba(0,0,0,0.07);--primary:#ff6a1f;--primary-soft:rgba(255,106,31,0.1);--shadow:rgba(0,0,0,0.07);--nav-hover:rgba(0,0,0,0.05);--glass:rgba(255,255,255,0.7);--tr-hover:#fafafa;--input:#ffffff;--sub:#666680;--modal-bg:rgba(0,0,0,0.45);--green:#059669;--green-soft:rgba(5,150,105,0.1);--blue:#2563eb;--blue-soft:rgba(37,99,235,0.1);--purple:#7c3aed;--purple-soft:rgba(124,58,237,0.1);--red:#dc2626;--red-soft:rgba(220,38,38,0.08);--yellow:#d97706;--yellow-soft:rgba(217,119,6,0.1);}

  *{box-sizing:border-box;margin:0;padding:0;font-family:'Plus Jakarta Sans',sans-serif;}
  body{background:var(--bg);min-height:100vh;color:var(--text);transition:background .3s,color .3s;}

  @keyframes fadeUp{from{opacity:0;transform:translateY(16px);}to{opacity:1;transform:translateY(0);}}
  @keyframes fadeDown{from{opacity:0;transform:translateY(-8px);}to{opacity:1;transform:translateY(0);}}
  @keyframes modalIn{from{opacity:0;transform:scale(.95) translateY(12px);}to{opacity:1;transform:scale(1) translateY(0);}}
  @keyframes shake{0%,100%{transform:translateX(0);}20%,60%{transform:translateX(-6px);}40%,80%{transform:translateX(6px);}}

  header{background:var(--header);padding:14px 20px;display:flex;justify-content:space-between;align-items:center;border-bottom:1px solid var(--border);backdrop-filter:blur(20px);-webkit-backdrop-filter:blur(20px);position:sticky;top:0;z-index:50;}
  .header-left,.header-right{display:flex;align-items:center;gap:10px;}
  .hamburger{display:none;flex-direction:column;gap:5px;cursor:pointer;padding:7px;border-radius:10px;background:none;border:none;margin-top:0;width:auto;}
  .hamburger span{display:block;width:20px;height:2px;background:var(--primary);border-radius:2px;transition:.3s;}
  .logo{display:flex;align-items:center;gap:9px;font-weight:800;font-size:18px;color:var(--text);letter-spacing:-0.3px;}
  .logo-box{width:48px;height:48px;border-radius:9px;display:flex;align-items:center;justify-content:center;}
  .logo-box img{width:48px;height:48px;object-fit:contain;border-radius:4px;}
  .nav-menu{display:flex;gap:4px;}
  .nav-menu a{text-decoration:none;color:var(--text-sub);font-size:13.5px;font-weight:500;padding:7px 13px;border-radius:8px;transition:.2s;}
  .nav-menu a:hover{background:var(--nav-hover);color:var(--text);}
  .nav-menu a.active{background:var(--primary-soft);color:var(--primary);}
  .dark-toggle{background:none;border:1.5px solid var(--border);border-radius:20px;padding:6px 14px;cursor:pointer;font-size:12.5px;font-weight:600;color:var(--text-sub);transition:.2s;width:auto;height:auto;position:static;}
  .dark-toggle:hover{border-color:var(--primary);color:var(--primary);}
  .profile-btn{width:34px;height:34px;background:var(--card2);border-radius:9px;display:flex;align-items:center;justify-content:center;font-size:16px;cursor:pointer;border:1px solid var(--border);transition:.2s;position:relative;}
  .profile-btn:hover{border-color:var(--primary);}
  .profile-dropdown{position:absolute;top:46px;right:0;background:var(--card);width:190px;border-radius:14px;box-shadow:0 12px 36px var(--shadow);padding:14px;display:none;border:1px solid var(--border);z-index:60;animation:fadeDown .25s ease;}
  .profile-name{font-size:13px;font-weight:600;color:var(--text);margin-bottom:3px;}
  .profile-email{font-size:12px;color:var(--text-sub);margin-bottom:12px;word-break:break-all;}
  .logout-btn{display:block;text-decoration:none;background:linear-gradient(135deg,#ff4d4d,#ff6a6a);color:white;padding:8px 12px;border-radius:9px;font-size:13px;font-weight:600;text-align:center;transition:.2s;}
  .logout-btn:hover{opacity:.85;}

  .nav-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:98;backdrop-filter:blur(3px);}
  .nav-overlay.open{display:block;}
  .mobile-nav{position:fixed;top:0;left:-290px;width:270px;height:100%;background:var(--card);z-index:99;padding:0;transition:left .3s cubic-bezier(.4,0,.2,1);box-shadow:8px 0 40px rgba(0,0,0,0.25);border-right:1px solid var(--border);overflow-y:auto;display:flex;flex-direction:column;}
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
  .mn-theme{width:100%;padding:11px;border:1.5px solid var(--border);border-radius:11px;background:none;color:var(--text-sub);font-weight:600;font-size:13.5px;cursor:pointer;transition:.2s;font-family:inherit;margin-top:0;}
  .mn-theme:hover{border-color:var(--primary);color:var(--primary);}
  .mn-logout{display:flex;align-items:center;justify-content:center;gap:8px;background:linear-gradient(135deg,#ff4d4d,#ff6a6a);color:white !important;padding:11px;border-radius:11px;font-size:13.5px;font-weight:600;text-decoration:none;transition:.2s;}
  .mn-logout:hover{opacity:.85;}

  .container{max-width:1200px;margin:auto;padding:30px;animation:fadeUp .6s ease;}
  .title{font-size:24px;font-weight:800;margin-bottom:8px;color:var(--text);letter-spacing:-0.5px;}
  .subtitle{font-size:13.5px;color:var(--text-sub);margin-bottom:22px;}
  .card{background:var(--card);border:1px solid var(--border);border-radius:18px;padding:24px;margin-bottom:22px;box-shadow:0 8px 20px var(--shadow);}

  .btn-tambah{display:inline-flex;align-items:center;gap:8px;background:var(--primary);color:white;border:none;padding:11px 20px;border-radius:12px;font-size:14px;font-weight:700;cursor:pointer;transition:.2s;box-shadow:0 4px 14px rgba(255,106,31,0.3);font-family:inherit;}
  .btn-tambah:hover{opacity:.88;transform:translateY(-1px);}
  .btn-verif-qris{display:inline-flex;align-items:center;gap:8px;background:linear-gradient(135deg,#10b981,#059669);color:white;border:none;padding:11px 20px;border-radius:12px;font-size:14px;font-weight:700;cursor:pointer;transition:.2s;box-shadow:0 4px 14px rgba(16,185,129,0.3);font-family:inherit;position:relative;}
  .btn-verif-qris:hover{opacity:.88;transform:translateY(-1px);}
  .notif-dot{position:absolute;top:-6px;right:-6px;background:#ef4444;color:white;font-size:11px;font-weight:700;width:20px;height:20px;border-radius:50%;display:flex;align-items:center;justify-content:center;border:2px solid var(--bg);}
  .btn-kelola-qris{display:inline-flex;align-items:center;gap:8px;background:linear-gradient(135deg,#6366f1,#8b5cf6);color:white;border:none;padding:11px 20px;border-radius:12px;font-size:14px;font-weight:700;cursor:pointer;transition:.2s;box-shadow:0 4px 14px rgba(99,102,241,0.3);font-family:inherit;}
  .btn-kelola-qris:hover{opacity:.88;transform:translateY(-1px);}

  /* ===================== MODAL PASSWORD GATE ===================== */
  .pw-gate-box{max-width:460px;}
  .pw-gate-header-band{background:linear-gradient(135deg,var(--primary),#ff9a5c);border-radius:14px;padding:20px 22px;margin-bottom:22px;display:flex;align-items:center;gap:14px;}
  .pw-gate-icon{font-size:36px;flex-shrink:0;}
  .pw-gate-title-wrap .pw-gate-title{font-size:17px;font-weight:800;color:white;line-height:1.3;}
  .pw-gate-title-wrap .pw-gate-sub{font-size:12.5px;color:rgba(255,255,255,0.8);margin-top:3px;}
  .pw-gate-info-row{display:flex;align-items:center;gap:10px;background:var(--bg2);border:1px solid var(--border);border-radius:12px;padding:12px 14px;margin-bottom:16px;}
  .pw-gate-info-ico{font-size:22px;flex-shrink:0;}
  .pw-gate-info-label{font-size:12px;color:var(--text-sub);font-weight:500;}
  .pw-gate-info-val{font-size:14px;font-weight:800;color:var(--text);}
  .pw-gate-note{background:var(--yellow-soft);border:1px solid rgba(245,158,11,0.3);border-radius:10px;padding:10px 14px;margin-bottom:18px;font-size:12.5px;color:var(--text-sub);line-height:1.6;}
  .pw-gate-note strong{color:var(--yellow);}
  .pw-field-wrap{position:relative;margin-bottom:6px;}
  .pw-field-wrap input{width:100%;padding:13px 46px 13px 16px;border-radius:12px;border:1.5px solid var(--border);background:var(--input);color:var(--text);font-size:15px;font-family:inherit;transition:border-color .2s,box-shadow .2s;letter-spacing:1px;}
  .pw-field-wrap input:focus{outline:none;border-color:var(--primary);box-shadow:0 0 0 3px rgba(255,106,31,0.12);}
  .pw-field-wrap input.pw-error{border-color:var(--red);animation:shake .4s ease;}
  .pw-toggle-vis{position:absolute;right:14px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;font-size:17px;color:var(--text-sub);padding:4px;transition:color .2s;}
  .pw-toggle-vis:hover{color:var(--primary);}
  .pw-error-msg{font-size:12.5px;color:var(--red);font-weight:600;margin-bottom:12px;min-height:18px;display:flex;align-items:center;gap:5px;}
  .pw-submit-btn{width:100%;padding:13px;border:none;border-radius:12px;background:linear-gradient(135deg,var(--primary),#ff9a5c);color:white;font-size:14px;font-weight:700;cursor:pointer;font-family:inherit;transition:.2s;display:flex;align-items:center;justify-content:center;gap:8px;margin-top:4px;}
  .pw-submit-btn:hover:not(:disabled){opacity:.9;transform:translateY(-1px);}
  .pw-submit-btn:disabled{opacity:.6;cursor:not-allowed;transform:none;}
  .pw-spinner{width:16px;height:16px;border:2.5px solid rgba(255,255,255,0.4);border-top-color:white;border-radius:50%;animation:spin .7s linear infinite;display:none;}
  @keyframes spin{to{transform:rotate(360deg);}}
  /* ============================================================== */

  .modal-overlay{display:none;position:fixed;inset:0;background:var(--modal-bg);z-index:200;align-items:center;justify-content:center;backdrop-filter:blur(4px);padding:16px;}
  .modal-overlay.open{display:flex;}
  .modal-box{background:var(--card);border-radius:20px;padding:28px;width:100%;max-width:640px;max-height:90vh;overflow-y:auto;border:1px solid var(--border);box-shadow:0 24px 60px var(--shadow);animation:modalIn .3s ease;}
  .modal-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:22px;}
  .modal-title{font-size:18px;font-weight:800;color:var(--text);}
  .modal-close{width:32px;height:32px;border-radius:9px;background:var(--card2);border:1px solid var(--border);color:var(--text-sub);font-size:17px;cursor:pointer;display:flex;align-items:center;justify-content:center;transition:.2s;}
  .modal-close:hover{background:var(--primary-soft);color:var(--primary);}

  .modal-box-qris{max-width:700px;}
  .qris-verif-empty{text-align:center;padding:40px 20px;color:var(--text-sub);}
  .qris-verif-empty .evi{font-size:42px;margin-bottom:12px;}
  .qris-verif-item{display:flex;justify-content:space-between;align-items:center;padding:16px;border:1px solid var(--border);border-radius:14px;margin-bottom:12px;background:var(--card2);gap:12px;flex-wrap:wrap;}
  .qvi-left{display:flex;align-items:center;gap:14px;flex:1;min-width:0;}
  .qvi-thumb{width:64px;height:64px;border-radius:12px;border:2px solid var(--border);object-fit:cover;flex-shrink:0;background:var(--card);cursor:pointer;}
  .qvi-thumb-placeholder{width:64px;height:64px;border-radius:12px;background:linear-gradient(135deg,#6366f1,#8b5cf6);display:flex;align-items:center;justify-content:center;font-size:26px;flex-shrink:0;}
  .qvi-info{min-width:0;}
  .qvi-kantin{font-size:14px;font-weight:700;color:var(--text);}
  .qvi-penjual{font-size:11.5px;font-weight:600;color:var(--primary);background:var(--primary-soft);padding:3px 9px;border-radius:20px;margin-top:5px;display:inline-block;}
  .qvi-date{font-size:12px;color:var(--text-sub);margin-top:4px;}
  .btn-lihat-qris{display:inline-flex;align-items:center;gap:4px;background:rgba(99,102,241,0.12);color:#6366f1;padding:4px 10px;border-radius:8px;font-size:12px;font-weight:600;text-decoration:none;transition:.2s;border:none;cursor:pointer;font-family:inherit;margin-top:6px;}
  .btn-lihat-qris:hover{background:rgba(99,102,241,0.2);}
  .qvi-actions{display:flex;gap:8px;flex-shrink:0;}
  .btn-approve{background:linear-gradient(135deg,#10b981,#059669);color:white;padding:8px 16px;border-radius:9px;text-decoration:none;font-size:13px;font-weight:600;display:inline-flex;align-items:center;gap:5px;transition:.2s;border:none;cursor:pointer;font-family:inherit;}
  .btn-approve:hover{opacity:.85;transform:translateY(-1px);}
  .btn-reject{background:linear-gradient(135deg,#ef4444,#dc2626);color:white;padding:8px 16px;border-radius:9px;text-decoration:none;font-size:13px;font-weight:600;display:inline-flex;align-items:center;gap:5px;transition:.2s;border:none;cursor:pointer;font-family:inherit;}
  .btn-reject:hover{opacity:.85;transform:translateY(-1px);}
  .modal-qris-note{background:var(--primary-soft);border:1px solid rgba(255,106,31,0.2);border-radius:12px;padding:12px 16px;margin-bottom:18px;font-size:13px;color:var(--text-sub);line-height:1.6;}
  .modal-qris-note strong{color:var(--primary);}

  .modal-box-kel-qris{max-width:780px;}
  .kantin-group{margin-bottom:14px;border:1px solid var(--border);border-radius:14px;overflow:hidden;}
  .kantin-header{display:flex;justify-content:space-between;align-items:center;padding:14px 18px;background:var(--card2);cursor:pointer;transition:background .2s;user-select:none;}
  .kantin-header:hover{background:var(--primary-soft);}
  .kantin-header-left{display:flex;align-items:center;gap:10px;}
  .kantin-icon{font-size:20px;}
  .kantin-nama{font-size:14px;font-weight:700;color:var(--text);}
  .kantin-meta{font-size:12px;color:var(--text-sub);margin-top:2px;}
  .kantin-badge-wrap{display:flex;align-items:center;gap:8px;}
  .badge-jml{background:var(--primary-soft);color:var(--primary);padding:3px 10px;border-radius:20px;font-size:12px;font-weight:700;}
  .badge-jml-0{background:rgba(0,0,0,0.05);color:var(--text-sub);}
  .kantin-chevron{font-size:13px;color:var(--text-sub);transition:transform .3s;}
  .kantin-chevron.open{transform:rotate(180deg);}
  .kantin-body{display:none;padding:14px 18px;border-top:1px solid var(--border);}
  .kantin-body.open{display:block;}
  .qris-item{display:flex;justify-content:space-between;align-items:center;padding:13px;border:1px solid var(--border);border-radius:12px;margin-bottom:10px;background:var(--card);gap:10px;flex-wrap:wrap;}
  .qris-item:last-child{margin-bottom:0;}
  .qris-item-left{display:flex;align-items:center;gap:12px;flex:1;min-width:0;}
  .qris-thumb{width:54px;height:54px;border-radius:10px;border:2px solid var(--border);object-fit:cover;flex-shrink:0;background:var(--card2);cursor:pointer;}
  .qris-thumb-ph{width:54px;height:54px;border-radius:10px;background:linear-gradient(135deg,#6366f1,#8b5cf6);display:flex;align-items:center;justify-content:center;font-size:22px;flex-shrink:0;}
  .qris-item-info{min-width:0;}
  .qris-item-kantin{font-size:13.5px;font-weight:700;color:var(--text);}
  .qris-item-date{font-size:12px;color:var(--text-sub);margin-top:2px;}
  .qris-item-badges{display:flex;gap:6px;flex-wrap:wrap;margin-top:5px;}
  .badge-verified-qris{background:linear-gradient(135deg,#10b981,#059669);color:white;padding:2px 9px;border-radius:20px;font-size:11px;font-weight:700;}
  .badge-unverif-qris{background:linear-gradient(135deg,#f59e0b,#d97706);color:white;padding:2px 9px;border-radius:20px;font-size:11px;font-weight:700;}
  .qris-item-empty{text-align:center;padding:22px;color:var(--text-sub);font-size:13px;border:1px dashed var(--border);border-radius:12px;}
  .btn-hapus-qris-admin{background:linear-gradient(135deg,#ef4444,#dc2626);color:white;padding:7px 14px;border-radius:9px;text-decoration:none;font-size:12.5px;font-weight:600;display:inline-flex;align-items:center;gap:5px;transition:.2s;flex-shrink:0;white-space:nowrap;}
  .btn-hapus-qris-admin:hover{opacity:.85;transform:translateY(-1px);}

  .modal-box-preview{max-width:420px;}
  .qris-preview-img{width:100%;border-radius:14px;object-fit:contain;border:2px solid var(--border);display:block;}
  .qris-preview-info{margin-top:14px;font-size:13.5px;color:var(--text-sub);text-align:center;line-height:1.7;}

  .modal-box-rekap{max-width:960px;}
  .rekap-kantin-tabs{display:flex;gap:6px;flex-wrap:wrap;margin-bottom:20px;padding-bottom:16px;border-bottom:1px solid var(--border);}
  .rekap-tab-btn{padding:7px 14px;border-radius:10px;border:1.5px solid var(--border);background:var(--card2);color:var(--text-sub);font-size:13px;font-weight:600;cursor:pointer;transition:.2s;font-family:inherit;}
  .rekap-tab-btn:hover{border-color:var(--primary);color:var(--primary);}
  .rekap-tab-btn.active{background:var(--primary);color:white;border-color:var(--primary);}
  .rekap-panel{display:none;}
  .rekap-panel.active{display:block;}

  .rekap-subtab-wrap{display:flex;gap:6px;margin-bottom:16px;flex-wrap:wrap;}
  .rekap-subtab-btn{padding:7px 15px;border-radius:9px;border:1.5px solid var(--border);background:var(--card2);color:var(--text-sub);font-size:12.5px;font-weight:600;cursor:pointer;transition:.2s;font-family:inherit;}
  .rekap-subtab-btn:hover{border-color:var(--primary);color:var(--primary);}
  .rekap-subtab-btn.active{background:linear-gradient(135deg,var(--primary),#ff9a5c);color:white;border-color:var(--primary);}
  .rekap-subpanel{display:none;}
  .rekap-subpanel.active{display:block;}

  .permenu-toolbar{display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:14px;padding:12px 14px;background:var(--card2);border:1px solid var(--border);border-radius:14px;}
  .permenu-search-wrap{position:relative;flex:1;min-width:180px;}
  .permenu-search-icon{position:absolute;left:10px;top:50%;transform:translateY(-50%);font-size:14px;pointer-events:none;}
  .permenu-search-input{width:100%;padding:8px 32px 8px 32px;border-radius:9px;border:1.5px solid var(--border);background:var(--input);color:var(--text);font-size:13px;font-family:inherit;transition:border-color .2s;}
  .permenu-search-input:focus{outline:none;border-color:var(--primary);}
  .permenu-clear-btn{position:absolute;right:8px;top:50%;transform:translateY(-50%);background:none;border:none;color:var(--text-sub);cursor:pointer;font-size:13px;padding:2px 4px;border-radius:4px;transition:.2s;display:none;}
  .permenu-clear-btn:hover{color:var(--red);}
  .permenu-filter-wrap{display:flex;align-items:center;gap:6px;flex-wrap:wrap;}
  .permenu-filter-btn{padding:6px 12px;border-radius:20px;border:1.5px solid var(--border);background:var(--card);color:var(--text-sub);font-size:12px;font-weight:600;cursor:pointer;transition:.2s;font-family:inherit;white-space:nowrap;}
  .permenu-filter-btn:hover{border-color:var(--primary);color:var(--primary);}
  .permenu-filter-btn.active{background:var(--primary);color:white;border-color:var(--primary);}
  .permenu-count-info{font-size:12.5px;color:var(--text-sub);white-space:nowrap;margin-left:auto;}
  .permenu-count-num{font-weight:800;color:var(--primary);}
  .menu-row-clickable:hover td{background:var(--primary-soft) !important;}

  .modal-box-detail-menu{max-width:820px;}
  .detail-menu-hero{display:flex;gap:18px;align-items:flex-start;margin-bottom:20px;padding-bottom:18px;border-bottom:1px solid var(--border);flex-wrap:wrap;}
  .detail-menu-foto{width:110px;height:110px;border-radius:16px;object-fit:cover;border:2px solid var(--border);flex-shrink:0;background:var(--card2);}
  .detail-menu-foto-ph{width:110px;height:110px;border-radius:16px;background:linear-gradient(135deg,var(--primary-soft),rgba(255,106,31,0.05));display:flex;align-items:center;justify-content:center;font-size:44px;flex-shrink:0;border:2px solid var(--border);}
  .detail-menu-info{flex:1;min-width:0;}
  .detail-menu-nama{font-size:20px;font-weight:800;color:var(--text);margin-bottom:6px;line-height:1.3;}
  .detail-menu-badges{display:flex;gap:6px;flex-wrap:wrap;margin-bottom:8px;}
  .detail-menu-harga{font-size:18px;font-weight:800;color:var(--primary);margin-bottom:6px;}
  .detail-menu-desc{font-size:13px;color:var(--text-sub);line-height:1.7;}
  .detail-menu-meta-row{display:flex;gap:16px;flex-wrap:wrap;margin-top:8px;}
  .detail-menu-meta-item{font-size:12px;color:var(--text-sub);display:flex;align-items:center;gap:5px;}
  .detail-menu-meta-item strong{color:var(--text);}

  .detail-stats-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:10px;margin-bottom:18px;}
  .detail-stat-card{background:var(--card2);border:1px solid var(--border);border-radius:12px;padding:12px;text-align:center;}
  .detail-stat-icon{font-size:20px;margin-bottom:4px;}
  .detail-stat-val{font-size:16px;font-weight:800;color:var(--text);}
  .detail-stat-val.green{color:var(--green);}
  .detail-stat-val.blue{color:var(--blue);}
  .detail-stat-val.orange{color:var(--primary);}
  .detail-stat-val.purple{color:var(--purple);}
  .detail-stat-label{font-size:11px;color:var(--text-sub);margin-top:2px;font-weight:500;}
  .detail-section-title{font-size:13px;font-weight:700;color:var(--text);margin-bottom:10px;margin-top:16px;display:flex;align-items:center;gap:6px;padding-bottom:6px;border-bottom:1px solid var(--border);}
  .detail-two-col{display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:14px;}
  .detail-info-row{display:flex;justify-content:space-between;align-items:center;padding:7px 10px;border-radius:8px;background:var(--bg2);border:1px solid var(--border);margin-bottom:6px;font-size:12.5px;}
  .detail-info-key{color:var(--text-sub);font-weight:500;}
  .detail-info-val{color:var(--text);font-weight:700;}

  .varian-section{margin-top:4px;margin-bottom:4px;}
  .varian-list-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:10px;margin-bottom:10px;}
  .varian-card{background:var(--bg2);border:1px solid var(--border);border-radius:12px;padding:10px 12px;display:flex;align-items:center;gap:10px;}
  .varian-card-foto{width:44px;height:44px;border-radius:8px;object-fit:cover;border:1.5px solid var(--border);flex-shrink:0;background:var(--card);}
  .varian-card-foto-ph{width:44px;height:44px;border-radius:8px;background:linear-gradient(135deg,rgba(139,92,246,0.15),rgba(139,92,246,0.05));display:flex;align-items:center;justify-content:center;font-size:20px;flex-shrink:0;border:1.5px solid var(--border);}
  .varian-card-info{min-width:0;flex:1;}
  .varian-card-nama{font-size:12.5px;font-weight:700;color:var(--text);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
  .varian-card-harga{font-size:11px;color:var(--text-sub);margin-top:2px;}
  .varian-card-stok{font-size:11px;margin-top:2px;}
  .varian-rekap-table{width:100%;border-collapse:collapse;font-size:12.5px;}
  .varian-rekap-table th{background:linear-gradient(135deg,rgba(139,92,246,0.15),rgba(139,92,246,0.08));color:var(--purple);padding:8px 10px;font-weight:700;text-align:left;border-bottom:2px solid rgba(139,92,246,0.2);}
  .varian-rekap-table td{padding:9px 10px;color:var(--text);border-bottom:1px solid var(--border);}
  .varian-rekap-table tr:last-child td{border-bottom:none;}
  .varian-rekap-table tr:hover td{background:rgba(139,92,246,0.04);}
  .varian-rank-badge{display:inline-flex;align-items:center;justify-content:center;width:22px;height:22px;border-radius:50%;font-size:11px;font-weight:800;color:white;}
  .varian-rank-1{background:linear-gradient(135deg,#f59e0b,#fbbf24);}
  .varian-rank-2{background:linear-gradient(135deg,#9ca3af,#d1d5db);}
  .varian-rank-3{background:linear-gradient(135deg,#cd7f32,#d97706);}
  .varian-rank-other{background:rgba(139,92,246,0.3);color:var(--purple);}
  .varian-omset-bar-wrap{margin-top:2px;}
  .varian-omset-bar{height:5px;border-radius:20px;background:linear-gradient(90deg,#8b5cf6,#a78bfa);min-width:4px;transition:width .5s ease;}
  .varian-info-note{background:rgba(139,92,246,0.08);border:1px solid rgba(139,92,246,0.2);border-radius:10px;padding:10px 14px;font-size:12px;color:var(--text-sub);margin-bottom:12px;line-height:1.6;}
  .varian-info-note strong{color:var(--purple);}

  .ulasan-item{padding:12px;border:1px solid var(--border);border-radius:12px;margin-bottom:10px;background:var(--bg2);}
  .ulasan-item:last-child{margin-bottom:0;}
  .ulasan-header{display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:6px;}
  .ulasan-user{font-size:13px;font-weight:700;color:var(--text);}
  .ulasan-kelas{font-size:11px;color:var(--text-sub);margin-top:2px;}
  .ulasan-bintang{color:#f59e0b;font-size:14px;font-weight:700;}
  .ulasan-tanggal{font-size:11px;color:var(--text-sub);}
  .ulasan-komentar{font-size:13px;color:var(--text-sub);line-height:1.6;margin-top:4px;}
  .ulasan-foto{margin-top:8px;}
  .ulasan-foto img{width:64px;height:64px;border-radius:8px;object-fit:cover;border:1px solid var(--border);cursor:pointer;}

  .detail-trx-table{width:100%;border-collapse:collapse;font-size:12px;}
  .detail-trx-table th{background:var(--bg2);color:var(--text-sub);padding:7px 9px;font-weight:700;text-align:left;border-bottom:1px solid var(--border);}
  .detail-trx-table td{padding:8px 9px;color:var(--text);border-bottom:1px solid var(--border);}
  .detail-trx-table tr:last-child td{border-bottom:none;}
  .detail-trx-table tr:hover td{background:var(--tr-hover);}

  .rekap-stats-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin-bottom:20px;}
  .rekap-stat-card{background:var(--card2);border:1px solid var(--border);border-radius:14px;padding:16px;text-align:center;}
  .rekap-stat-icon{font-size:24px;margin-bottom:6px;}
  .rekap-stat-val{font-size:18px;font-weight:800;color:var(--text);line-height:1.2;}
  .rekap-stat-val.green{color:var(--green);}
  .rekap-stat-val.blue{color:var(--blue);}
  .rekap-stat-val.purple{color:var(--purple);}
  .rekap-stat-val.orange{color:var(--primary);}
  .rekap-stat-label{font-size:11.5px;color:var(--text-sub);margin-top:4px;font-weight:500;}
  .rekap-two-col{display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:16px;}
  .rekap-section-title{font-size:13px;font-weight:700;color:var(--text);margin-bottom:10px;display:flex;align-items:center;gap:6px;}
  .metode-bar-wrap{margin-bottom:14px;}
  .metode-bar-label{display:flex;justify-content:space-between;font-size:12px;color:var(--text-sub);margin-bottom:5px;}
  .metode-bar-track{height:8px;border-radius:20px;background:var(--bg2);overflow:hidden;}
  .metode-bar-fill{height:100%;border-radius:20px;transition:width .5s ease;}
  .metode-bar-fill.cash{background:linear-gradient(90deg,#10b981,#34d399);}
  .metode-bar-fill.qris{background:linear-gradient(90deg,#6366f1,#a78bfa);}
  .terlaris-item{display:flex;justify-content:space-between;align-items:center;padding:10px 12px;border-radius:10px;margin-bottom:8px;background:var(--bg2);border:1px solid var(--border);}
  .terlaris-item:last-child{margin-bottom:0;}
  .terlaris-rank{width:24px;height:24px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:800;color:white;flex-shrink:0;margin-right:10px;}
  .rank-1{background:linear-gradient(135deg,#f59e0b,#fbbf24);}
  .rank-2{background:linear-gradient(135deg,#9ca3af,#d1d5db);}
  .rank-3{background:linear-gradient(135deg,#cd7f32,#d97706);}
  .rank-other{background:var(--border);color:var(--text-sub);}
  .terlaris-name{font-size:13px;font-weight:600;color:var(--text);flex:1;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
  .terlaris-meta{text-align:right;flex-shrink:0;}
  .terlaris-qty{font-size:13px;font-weight:700;color:var(--primary);}
  .terlaris-omset{font-size:11px;color:var(--text-sub);}
  .rekap-trx-table{width:100%;border-collapse:collapse;}
  .rekap-trx-table th{background:var(--bg2);color:var(--text-sub);padding:8px 10px;font-size:11.5px;font-weight:700;text-align:left;border-bottom:1px solid var(--border);}
  .rekap-trx-table td{padding:9px 10px;font-size:12.5px;color:var(--text);border-bottom:1px solid var(--border);}
  .rekap-trx-table tr:last-child td{border-bottom:none;}
  .rekap-trx-table tr:hover td{background:var(--tr-hover);}
  .rekap-menu-table tfoot td{padding:10px;font-size:13px;border-top:2px solid var(--border);}
  .status-badge{display:inline-block;padding:2px 9px;border-radius:20px;font-size:11px;font-weight:700;white-space:nowrap;}
  .status-lunas{background:var(--green-soft);color:var(--green);}
  .status-batal{background:var(--red-soft);color:var(--red);}
  .status-ditolak{background:rgba(239,68,68,0.08);color:#ef4444;}
  .status-pending{background:var(--yellow-soft);color:var(--yellow);}
  .status-siap{background:var(--blue-soft);color:var(--blue);}
  .status-dimasak{background:var(--purple-soft);color:var(--purple);}
  .rekap-info-row{display:flex;justify-content:space-between;align-items:center;padding:8px 12px;border-radius:9px;background:var(--bg2);border:1px solid var(--border);margin-bottom:8px;font-size:13px;}
  .rekap-info-row:last-child{margin-bottom:0;}
  .rekap-info-key{color:var(--text-sub);font-weight:500;}
  .rekap-info-val{color:var(--text);font-weight:700;}
  .rekap-empty-kantin{text-align:center;padding:40px 20px;color:var(--text-sub);}
  .rekap-empty-kantin .rek-ico{font-size:36px;margin-bottom:10px;}

  /* ==================== PHONE INPUT ==================== */
  .phone-input-wrap{position:relative;}
  .phone-prefix{position:absolute;left:14px;top:50%;transform:translateY(-50%);font-size:14px;font-weight:700;color:var(--primary);pointer-events:none;user-select:none;}
  .phone-input-wrap input{padding-left:42px;}
  .phone-hint{font-size:11.5px;margin-top:5px;display:flex;align-items:center;gap:4px;min-height:18px;font-weight:600;transition:color .2s;}
  /* ===================================================== */

  /* ==================== EMAIL FEEDBACK ==================== */
  .email-feedback{font-size:11.5px;margin-top:5px;min-height:18px;font-weight:600;transition:color .2s;}
  /* ======================================================= */

  .form-section-label{grid-column:1/-1;font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.8px;color:var(--text-sub);padding:10px 0 4px;border-top:1px solid var(--border);margin-top:6px;}
  .form-section-label:first-child{border-top:none;margin-top:0;padding-top:0;}
  .form-grid{display:grid;grid-template-columns:1fr 1fr;gap:18px;}
  .form-full{grid-column:1/-1;}
  label{display:block;margin-bottom:7px;font-size:13px;font-weight:700;color:var(--sub);}
  input,select{width:100%;padding:12px 14px;border-radius:12px;border:1.5px solid var(--border);background:var(--input);color:var(--text);font-size:14px;font-family:inherit;transition:border-color .2s;}
  input:focus,select:focus{outline:none;border-color:var(--primary);}
  select option:disabled{color:#aaa;}
  .form-submit-btn{margin-top:20px;width:100%;border:none;background:var(--primary);color:white;padding:13px;border-radius:13px;font-size:14px;font-weight:700;cursor:pointer;font-family:inherit;transition:.2s;}
  .form-submit-btn:hover{opacity:.9;}
  .qris-upload-preview{margin-top:10px;display:none;border:2px dashed var(--border);border-radius:12px;padding:10px;text-align:center;}
  .qris-upload-preview img{max-height:160px;border-radius:8px;object-fit:contain;}
  .qris-upload-preview.show{display:block;}
  .qris-wajib-note{margin-top:8px;padding:8px 12px;background:rgba(239,68,68,0.08);border:1px solid rgba(239,68,68,0.2);border-radius:9px;font-size:12px;color:#ef4444;font-weight:600;}

  .table-top{display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;flex-wrap:wrap;gap:10px;}
  .table-title{font-size:15px;font-weight:700;color:var(--text);}
  .count-badge{background:var(--primary-soft);color:var(--primary);padding:4px 12px;border-radius:20px;font-size:12.5px;font-weight:700;}
  .table-wrapper{width:100%;overflow-x:auto;-webkit-overflow-scrolling:touch;}
  table{width:100%;border-collapse:collapse;min-width:800px;}
  th{background:var(--primary);color:white;padding:12px;font-size:13.5px;white-space:nowrap;font-weight:600;}
  th:first-child{border-radius:10px 0 0 0;}
  th:last-child{border-radius:0 10px 0 0;}
  td{padding:12px;border-bottom:1px solid var(--border);font-size:13.5px;color:var(--text);}
  tr:hover td{background:var(--tr-hover);}
  tr:last-child td{border-bottom:none;}
  .profil-img{width:48px;height:48px;border-radius:50%;object-fit:cover;border:2px solid var(--border);}
  .badge-kantin{display:inline-flex;align-items:center;gap:5px;background:var(--primary-soft);color:var(--primary);padding:4px 10px;border-radius:20px;font-size:12px;font-weight:700;}
  .badge-status-aktif{display:inline-flex;align-items:center;gap:5px;background:var(--green-soft);color:var(--green);padding:4px 11px;border-radius:20px;font-size:12px;font-weight:700;}
  .badge-status-nonaktif{display:inline-flex;align-items:center;gap:5px;background:var(--red-soft);color:var(--red);padding:4px 11px;border-radius:20px;font-size:12px;font-weight:700;}
  .action-wrap{display:flex;gap:6px;flex-wrap:wrap;}
  .edit-btn{background:linear-gradient(135deg,#3b82f6,#60a5fa);color:white;padding:6px 12px;border-radius:8px;text-decoration:none;font-size:12.5px;font-weight:600;white-space:nowrap;transition:.2s;display:inline-flex;align-items:center;gap:4px;border:none;cursor:pointer;font-family:inherit;}
  .edit-btn:hover{opacity:.85;transform:translateY(-1px);}
  .delete-btn{background:linear-gradient(135deg,#f59e0b,#d97706);color:white;padding:6px 12px;border-radius:8px;text-decoration:none;font-size:12.5px;font-weight:600;white-space:nowrap;transition:.2s;display:inline-flex;align-items:center;gap:4px;border:none;cursor:pointer;font-family:inherit;}
  .delete-btn:hover{opacity:.85;transform:translateY(-1px);}
  .hapus-permanen-btn{background:linear-gradient(135deg,#ff4d4d,#ff6a6a);color:white;padding:6px 12px;border-radius:8px;text-decoration:none;font-size:12.5px;font-weight:600;white-space:nowrap;transition:.2s;display:inline-flex;align-items:center;gap:4px;}
  .hapus-permanen-btn:hover{opacity:.85;transform:translateY(-1px);}
  .aktifkan-btn{background:linear-gradient(135deg,#10b981,#059669);color:white;padding:6px 12px;border-radius:8px;text-decoration:none;font-size:12.5px;font-weight:600;white-space:nowrap;transition:.2s;display:inline-flex;align-items:center;gap:4px;}
  .aktifkan-btn:hover{opacity:.85;transform:translateY(-1px);}
  .rekap-btn{background:linear-gradient(135deg,#f59e0b,#d97706);color:white;padding:6px 12px;border-radius:8px;text-decoration:none;font-size:12.5px;font-weight:600;white-space:nowrap;transition:.2s;display:inline-flex;align-items:center;gap:4px;border:none;cursor:pointer;font-family:inherit;}
  .rekap-btn:hover{opacity:.85;transform:translateY(-1px);}
  .nonaktif-table th{background:linear-gradient(135deg,#6b7280,#9ca3af);}
  .info-kantin-lama{display:inline-flex;align-items:center;gap:5px;background:rgba(245,158,11,0.12);color:var(--yellow);border:1px solid rgba(245,158,11,0.3);padding:3px 10px;border-radius:20px;font-size:11.5px;font-weight:700;}
  .warn-nokantin{background:rgba(239,68,68,0.08);color:var(--red);border:1px dashed var(--red);padding:4px 10px;border-radius:8px;font-size:11.5px;font-weight:600;display:inline-block;}
  .pesan-sukses{background:#d1fae5;color:#065f46;padding:12px 15px;border-radius:12px;margin-bottom:18px;font-size:13px;font-weight:600;}
  .pesan-error{background:#fee2e2;color:#991b1b;padding:12px 15px;border-radius:12px;margin-bottom:18px;font-size:13px;font-weight:600;}
  .empty-state{text-align:center;padding:40px 20px;color:var(--text-sub);}
  .empty-state .es-icon{font-size:42px;margin-bottom:12px;}
  .empty-state p{font-size:14px;}
  .btn-kembali{display:inline-block;margin-top:10px;text-decoration:none;color:var(--text-sub);font-size:13.5px;transition:.2s;}
  .btn-kembali:hover{color:var(--primary);}
  .row-nonaktif td{opacity:.75;}
  .softdelete-info{background:rgba(107,114,128,0.08);border:1px solid rgba(107,114,128,0.2);border-radius:12px;padding:12px 16px;margin-bottom:18px;font-size:13px;color:var(--text-sub);display:flex;align-items:flex-start;gap:10px;}
  .softdelete-info .sd-icon{font-size:18px;flex-shrink:0;margin-top:1px;}

  @media(max-width:860px){.rekap-stats-grid{grid-template-columns:repeat(2,1fr);}.rekap-two-col{grid-template-columns:1fr;}.detail-stats-grid{grid-template-columns:repeat(2,1fr);}.detail-two-col{grid-template-columns:1fr;}.varian-list-grid{grid-template-columns:repeat(2,1fr);}}
  @media(max-width:768px){header{padding:12px 16px;}.hamburger{display:flex;}.nav-menu,.dark-toggle,.profile-btn{display:none !important;}.container{padding:16px;}.title{font-size:20px;}.card{padding:16px;}.form-grid{grid-template-columns:1fr;}th,td{padding:10px 8px;font-size:13px;}.modal-box{padding:20px;}.qris-item{flex-direction:column;align-items:flex-start;}.btn-hapus-qris-admin{width:100%;justify-content:center;}.qvi-actions{width:100%;}.btn-approve,.btn-reject{flex:1;justify-content:center;}.rekap-stats-grid{grid-template-columns:repeat(2,1fr);}.action-wrap{flex-wrap:wrap;}.permenu-toolbar{flex-direction:column;align-items:stretch;}.permenu-count-info{margin-left:0;}.varian-list-grid{grid-template-columns:1fr 1fr;}.pw-gate-header-band{flex-direction:column;text-align:center;}}
  @media(max-width:400px){.logo span{display:none;}.rekap-stats-grid{grid-template-columns:1fr 1fr;}.detail-stats-grid{grid-template-columns:1fr 1fr;}.varian-list-grid{grid-template-columns:1fr;}}
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
    <a href="admin_beranda.php"><span class="mn-ico">🏠</span> Dashboard</a>
    <a href="admin_kelola_penjual.php" class="active"><span class="mn-ico">🛒</span> Penjual</a>
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
      <a href="admin_beranda.php">Dashboard</a>
      <a href="admin_kelola_penjual.php" class="active">Kelola Penjual</a>
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

<div class="container">

  <div class="title">Kelola Akun Penjual</div>
  <div class="subtitle">Manajemen akun penjual, kantin, dan menu yang terdaftar</div>

  <?php if ($pesan_sukses): ?>
    <div class="pesan-sukses"><?= $pesan_sukses ?></div>
  <?php endif; ?>
  <?php if ($pesan_error): ?>
    <div class="pesan-error"><?= $pesan_error ?></div>
  <?php endif; ?>

  <div style="display:flex;flex-wrap:wrap;gap:10px;margin-bottom:22px;">
    <?php
$count = mysqli_query($db,"
    SELECT COUNT(*) AS total
    FROM penjual
    WHERE status != 0
      AND deleted_at IS NULL
");
$totalPenjualAktif = mysqli_fetch_assoc($count)['total'];
?>

<?php if ($totalPenjualAktif >= 10): ?>
    <button class="btn-tambah"
            disabled
            style="opacity:.5;cursor:not-allowed;"
            title="Semua kantin sudah terisi">
        🚫 Maksimal 10 Penjual Aktif
    </button>
<?php else: ?>
    <button class="btn-tambah"
            onclick="bukaModalTambah()">
        ➕ Tambahkan Penjual Baru
    </button>
<?php endif; ?>
    <button class="btn-verif-qris" onclick="bukaModal('modalVerifQris')">
      📲 Verifikasi QRIS
      <?php if ($pending_count > 0): ?>
        <span class="notif-dot"><?= $pending_count ?></span>
      <?php endif; ?>
    </button>
    <button class="btn-kelola-qris" onclick="bukaModal('modalKelolaQris')">🗂 Kelola QRIS</button>
  </div>

  <div class="softdelete-info">
    <span class="sd-icon">🗄️</span>
    <span>
      Saat akun penjual <strong style="color:var(--text);">"dihapus"</strong>, data penjual dan <strong style="color:var(--text);">semua menunya tidak benar-benar dihapus dari database</strong> — hanya ditandai sebagai terhapus.
      Data tetap tersimpan untuk keperluan rekap historis dan audit transaksi.
      Gunakan tombol <strong style="color:var(--text);">📊 Rekap</strong> di kolom aksi untuk melihat rekapan penjualan (memerlukan verifikasi password akun penjual).
    </span>
  </div>

  <!-- TABEL PENJUAL AKTIF -->
  <div class="card">
    <div class="table-top">
      <div class="table-title">🟢 Daftar Akun Penjual Aktif</div>
      <span class="count-badge">
        <?php
        $count = mysqli_query($db, "SELECT COUNT(*) as total FROM penjual WHERE status != 0 AND deleted_at IS NULL");
        $crow  = mysqli_fetch_assoc($count);
        echo $crow['total'];
        ?> Penjual
      </span>
    </div>
    <div class="table-wrapper">
      <table>
        <tr>
          <th>No</th><th>Profil</th><th>Nama</th><th>Username</th><th>Email</th>
          <th>Kantin</th><th>Jml Menu Aktif</th><th>Status</th><th>Aksi</th>
        </tr>
        <?php
        $no = 1;
        mysqli_data_seek($data_aktif, 0);
        $jumlah = mysqli_num_rows($data_aktif);
        if ($jumlah == 0): ?>
        <tr>
          <td colspan="9">
            <div class="empty-state">
              <div class="es-icon">🏪</div>
              <p>Belum ada penjual yang terdaftar.<br>Klik <strong>"Tambahkan Penjual Baru"</strong> untuk menambahkan.</p>
            </div>
          </td>
        </tr>
        <?php else: while ($row = mysqli_fetch_assoc($data_aktif)):
          $jml_menu = mysqli_fetch_assoc(mysqli_query($db, "SELECT COUNT(*) AS c FROM menu_req WHERE id_penjual='{$row['id_penjual']}' AND aktif=1 AND deleted_at IS NULL"))['c'];
        ?>
        <tr>
          <td><?= $no++ ?></td>
          <td>
            <?php if (!empty($row['profil'])): ?>
              <img src="uploads/<?= htmlspecialchars($row['profil']) ?>" class="profil-img">
            <?php else: ?>
              <div style="width:48px;height:48px;border-radius:50%;background:var(--primary-soft);display:flex;align-items:center;justify-content:center;font-size:20px;">👤</div>
            <?php endif; ?>
          </td>
          <td><?= htmlspecialchars($row['nama']) ?></td>
          <td><?= htmlspecialchars($row['username']) ?></td>
          <td><?= htmlspecialchars($row['email']) ?></td>
          <td><span class="badge-kantin">🏪 Kantin <?= $row['id_kantin'] ?></span></td>
          <td>
            <span style="font-weight:700;color:var(--primary);"><?= $jml_menu ?></span>
            <span style="font-size:12px;color:var(--text-sub);"> menu</span>
          </td>
          <td><span class="badge-status-aktif">🟢 Aktif</span></td>
          <td>
            <div class="action-wrap">
              <button class="edit-btn"
                 onclick="bukaModalEdit(<?= $row['id_penjual'] ?>, '<?= addslashes(htmlspecialchars($row['nama'])) ?>', '<?= addslashes(htmlspecialchars($row['email'])) ?>')">
                ✏️ Edit
              </button>
              <button class="rekap-btn"
                 onclick="bukaPasswordGate(
                   <?= $row['id_penjual'] ?>,
                   '<?= addslashes(htmlspecialchars($row['nama'])) ?>',
                   <?= intval($row['id_kantin']) ?>,
                   '<?= addslashes(htmlspecialchars($row['username'])) ?>',
                   false
                 )">
                📊 Rekap
              </button>
              <a class="delete-btn" href="?nonaktifkan=<?= $row['id_penjual'] ?>"
                 onclick="confirmPopup({
                   judul  : 'Nonaktifkan Akun Penjual',
                   ikon   : '🚫',
                   pesan  : 'Yakin ingin menonaktifkan akun penjual ini?',
                   poin   : ['Semua menu akan dinonaktifkan'],
                   tipe   : 'warning',
                   labelOK: 'Ya, Nonaktifkan',
                   url    : this.href
                 }); return false;">
                Nonaktifkan
              </a>
            </div>
          </td>
        </tr>
        <?php endwhile; endif; ?>
      </table>
    </div>
  </div>

  <!-- TABEL PENJUAL NONAKTIF -->
  <?php
  $jumlah_nonaktif = mysqli_num_rows($data_nonaktif);
  if ($jumlah_nonaktif > 0):
  ?>
  <div class="card" style="border-color:rgba(239,68,68,0.2);">
    <div class="table-top">
      <div class="table-title">🔴 Daftar Akun Penjual Nonaktif</div>
      <span class="count-badge" style="background:var(--red-soft);color:var(--red);">
        <?= $jumlah_nonaktif ?> Nonaktif
      </span>
    </div>
    <div class="table-wrapper">
      <table class="nonaktif-table">
        <tr>
          <th>No</th><th>Profil</th><th>Nama</th><th>Username</th><th>Email</th>
          <th>Kantin Asal</th><th>Menu Tersimpan</th><th>Status</th><th>Aksi</th>
        </tr>
        <?php
        $no2 = 1;
        mysqli_data_seek($data_nonaktif, 0);
        while ($row = mysqli_fetch_assoc($data_nonaktif)):
          $kantin_asal = intval($row['nonaktif_dari_kantin']);
          $kantinDitempati = false;
          if ($kantin_asal > 0) {
              $cekOku = mysqli_query($db, "SELECT id_penjual FROM penjual WHERE id_kantin='$kantin_asal' AND status != 0 AND deleted_at IS NULL");
              $kantinDitempati = (mysqli_num_rows($cekOku) > 0);
          }
          $jml_menu_non = mysqli_fetch_assoc(mysqli_query($db, "SELECT COUNT(*) AS c FROM menu_req WHERE id_penjual='{$row['id_penjual']}' AND deleted_at IS NULL"))['c'];
        ?>
        <tr class="row-nonaktif">
          <td><?= $no2++ ?></td>
          <td>
            <?php if (!empty($row['profil'])): ?>
              <img src="uploads/<?= htmlspecialchars($row['profil']) ?>" class="profil-img" style="opacity:.6;filter:grayscale(60%);">
            <?php else: ?>
              <div style="width:48px;height:48px;border-radius:50%;background:var(--red-soft);display:flex;align-items:center;justify-content:center;font-size:20px;opacity:.6;">👤</div>
            <?php endif; ?>
          </td>
          <td><?= htmlspecialchars($row['nama']) ?></td>
          <td><?= htmlspecialchars($row['username']) ?></td>
          <td><?= htmlspecialchars($row['email']) ?></td>
          <td>
            <?php if ($kantin_asal > 0): ?>
              <span class="info-kantin-lama">🏪 Kantin <?= $kantin_asal ?></span>
              <?php if ($kantinDitempati): ?>
                <br><span class="warn-nokantin" style="margin-top:4px;">⚠️ Sedang ditempati</span>
              <?php endif; ?>
            <?php else: ?>
              <span style="color:var(--text-sub);font-size:12px;">— belum diset</span>
            <?php endif; ?>
          </td>
          <td>
            <span style="font-weight:700;color:var(--yellow);"><?= $jml_menu_non ?></span>
            <span style="font-size:12px;color:var(--text-sub);"> menu tersimpan</span>
          </td>
          <td><span class="badge-status-nonaktif">🔴 Nonaktif</span></td>
          <td>
            <div class="action-wrap">
              <button class="edit-btn"
                 onclick="bukaModalEditNonaktif(
                   <?= $row['id_penjual'] ?>,
                   '<?= addslashes(htmlspecialchars($row['nama'])) ?>',
                   '<?= addslashes(htmlspecialchars($row['email'])) ?>',
                   <?= $kantin_asal ?>
                 )">
                ✏️ Edit
              </button>
              <?php if (!$kantinDitempati && $kantin_asal > 0): ?>
                <a class="aktifkan-btn"
                   href="?aktifkan=<?= $row['id_penjual'] ?>"
                 onclick="confirmPopup({
                   judul  : 'Aktifkan kembali akun ini?\n\nKantin  <?= $kantin_asal ?>',
                   icon   : '✅',
                   pesan  : ' akan dikembalikan dan menu-menu milik penjual ini akan diaktifkan kembali.',
                   poin   : ['Semua menu akan dinonaktifkan'],
                   tipe   : 'warning',
                   labelOK: 'Ya, Aktifkan',
                   url    : this.href
                 }); return false;">
                ✅ Aktifkan
              </a>
              <?php elseif ($kantinDitempati): ?>
                <span class="warn-nokantin" title="Kantin masih ditempati penjual aktif lain">🚫 Tidak bisa aktif</span>
              <?php else: ?>
                <span class="warn-nokantin" title="Set kantin terlebih dahulu via tombol Edit">⚠️ Set kantin dulu</span>
              <?php endif; ?>
              <button class="rekap-btn"
                 onclick="bukaPasswordGate(
                   <?= $row['id_penjual'] ?>,
                   '<?= addslashes(htmlspecialchars($row['nama'])) ?>',
                   <?= $kantin_asal ?>,
                   '<?= addslashes(htmlspecialchars($row['username'])) ?>',
                   true
                 )">
                📊 Rekap
              </button>
              <a class="hapus-permanen-btn"
                 href="?hapus_permanen=<?= $row['id_penjual'] ?>"
                 onclick="confirmPopup({
                   judul  : 'HAPUS AKUN PENJUAL',
                   icon   : '🗑',
                   pesan  : ' Kantin  <?= $kantin_asal ?> akan dihapus permanent.',
                   poin   : ['Kantin bisa digunakan penjual baru'],
                   tipe   : 'warning',
                   labelOK: 'Ya, 🗑 Hapus',
                   url    : this.href
                 }); return false;">
                🗑 Hapus
              </a>
            </div>
          </td>
        </tr>
        <?php endwhile; ?>
      </table>
    </div>
  </div>
  <?php endif; ?>

  <a href="admin_beranda.php" class="btn-kembali">⬅ Kembali ke Dashboard</a>

</div>

<!-- ═══════════════════════════════════════════════════════════════
     MODAL PASSWORD GATE
════════════════════════════════════════════════════════════════ -->
<div class="modal-overlay" id="modalPasswordGate" style="z-index:250;">
  <div class="modal-box pw-gate-box">
    <div class="pw-gate-header-band">
      <div class="pw-gate-icon">🔐</div>
      <div class="pw-gate-title-wrap">
        <div class="pw-gate-title">Verifikasi Akses Rekapan</div>
        <div class="pw-gate-sub">Masukkan password akun penjual untuk melanjutkan</div>
      </div>
    </div>
    <div class="pw-gate-info-row">
      <span class="pw-gate-info-ico">🏪</span>
      <div>
        <div class="pw-gate-info-label">Kantin / Penjual</div>
        <div class="pw-gate-info-val" id="pwGateKantinLabel">—</div>
      </div>
    </div>
    <div class="pw-gate-info-row" style="margin-top:-8px;">
      <span class="pw-gate-info-ico">👤</span>
      <div>
        <div class="pw-gate-info-label">Username akun</div>
        <div class="pw-gate-info-val" id="pwGateUsernameLabel">—</div>
      </div>
    </div>
    <div class="pw-gate-note">
      🔒 Demi keamanan data, rekapan penjualan hanya bisa dibuka dengan
      <strong>password akun penjual yang bersangkutan</strong>.
      Password ini adalah password yang digunakan penjual untuk login ke sistem.
    </div>
    <label style="font-size:13px;font-weight:700;color:var(--sub);margin-bottom:8px;display:block;">Password Akun Penjual</label>
    <div class="pw-field-wrap">
      <input type="password" id="pwGateInput" placeholder="Masukkan password akun penjual..."
             onkeydown="if(event.key==='Enter') verifyPasswordGate()"
             oninput="clearPwError()">
      <button class="pw-toggle-vis" type="button" onclick="togglePwVisibility()" title="Tampilkan/sembunyikan password">👁</button>
    </div>
    <div class="pw-error-msg" id="pwGateError"></div>
    <button class="pw-submit-btn" id="pwGateSubmitBtn" onclick="verifyPasswordGate()">
      <span class="pw-spinner" id="pwGateSpinner"></span>
      <span id="pwGateBtnText">🔓 Verifikasi &amp; Buka Rekapan</span>
    </button>
    <div style="text-align:center;margin-top:14px;">
      <button onclick="tutupModal('modalPasswordGate')"
              style="background:none;border:none;cursor:pointer;color:var(--text-sub);font-size:13px;font-family:inherit;padding:6px 12px;border-radius:8px;transition:.2s;"
              onmouseover="this.style.color='var(--primary)'" onmouseout="this.style.color='var(--text-sub)'">
        Batal
      </button>
    </div>
    <input type="hidden" id="pwGatePenjualId" value="">
    <input type="hidden" id="pwGateNamaPenjual" value="">
    <input type="hidden" id="pwGateIdKantin" value="">
    <input type="hidden" id="pwGateIsNonaktif" value="">
  </div>
</div>

<!-- ═══ MODAL REKAP SINGLE ═══ -->
<div class="modal-overlay" id="modalRekapSingle" style="z-index:200;">
  <div class="modal-box modal-box-rekap">
    <div class="modal-header">
      <div class="modal-title" id="modalRekapSingleTitle">📊 Rekapan Pembelian</div>
      <div style="display:flex;align-items:center;gap:8px;">
        <a id="btnCetakRekapExcel"
           href="#"
           target="_blank"
           style="display:inline-flex;align-items:center;gap:6px;
                  background:linear-gradient(135deg,#059669,#10b981);
                  color:white;padding:7px 14px;border-radius:10px;
                  font-size:13px;font-weight:700;text-decoration:none;
                  transition:.2s;white-space:nowrap;border:none;cursor:pointer;"
           onmouseover="this.style.opacity='.85'"
           onmouseout="this.style.opacity='1'">
          📥 Unduh Excel
        </a>
        <button class="modal-close" onclick="tutupModal('modalRekapSingle')">✕</button>
      </div>
    </div>
    <div id="modalRekapSingleContent"></div>
  </div>
</div>

<div style="display:none;" id="rekapSinglePanels">
<?php foreach ($semua_penjual_list as $pj_s):
  $is_deleted  = !empty($pj_s['deleted_at']);
  $is_nonaktif = (!$is_deleted && $pj_s['status'] == 0);
  $idp = intval($pj_s['id_penjual']);
  echo '<div id="rekapSingle-' . $idp . '">';
  echo renderRekapPanel(
      $idp, $pj_s['nama'], $is_nonaktif, $is_deleted,
      $rekap_per_penjual, $terlaris_per_penjual, $harian_per_penjual, $trx_terbaru_per_penjual,
      $rekap_menu_per_penjual, $ulasan_per_menu, $trx_per_menu,
      $varian_per_menu, $rekap_varian_per_menu, 'src-'
  );
  echo '</div>';
endforeach; ?>
</div>

<!-- ═══ MODAL DETAIL MENU ═══ -->
<div class="modal-overlay" id="modalDetailMenu" style="z-index:300;">
  <div class="modal-box modal-box-detail-menu">
    <div class="modal-header">
      <div class="modal-title" id="detailMenuTitle">🍽 Detail Menu</div>
      <button class="modal-close" onclick="tutupModal('modalDetailMenu')">✕</button>
    </div>
    <div id="detailMenuContent"></div>
  </div>
</div>

<!-- ═══ MODAL VERIFIKASI QRIS ═══ -->
<div class="modal-overlay" id="modalVerifQris">
  <div class="modal-box modal-box-qris">
    <div class="modal-header">
      <div class="modal-title">📲 Verifikasi QRIS Penjual</div>
      <button class="modal-close" onclick="tutupModal('modalVerifQris')">✕</button>
    </div>
    <?php if ($pending_count > 0): ?>
    <div class="modal-qris-note">
      <strong><?= $pending_count ?> QRIS</strong> menunggu verifikasi. Periksa gambar QRIS sebelum menyetujui.
    </div>
    <?php mysqli_data_seek($q_pending, 0); while ($qris = mysqli_fetch_assoc($q_pending)):
      $k_tampil = $qris['kantin_tampil'] ?? '?'; ?>
    <div class="qris-verif-item">
      <div class="qvi-left">
        <?php if (!empty($qris['gambar_qris'])): ?>
          <img src="uploads/qris/<?= htmlspecialchars($qris['gambar_qris']) ?>" class="qvi-thumb"
               onclick="previewQrisImg('uploads/qris/<?= htmlspecialchars($qris['gambar_qris']) ?>','Kantin <?= $k_tampil ?> — <?= htmlspecialchars(addslashes($qris['nama_penjual'])) ?>')"
               title="Klik untuk memperbesar">
        <?php else: ?>
          <div class="qvi-thumb-placeholder">📲</div>
        <?php endif; ?>
        <div class="qvi-info">
          <div class="qvi-kantin">🏪 Kantin <?= $k_tampil ?></div>
          <span class="qvi-penjual"><?= htmlspecialchars($qris['nama_penjual']) ?></span>
          <div class="qvi-date">📅 <?= date('d M Y H:i', strtotime($qris['created_at'])) ?></div>
          <?php if (!empty($qris['gambar_qris'])): ?>
            <br><button class="btn-lihat-qris"
              onclick="previewQrisImg('uploads/qris/<?= htmlspecialchars($qris['gambar_qris']) ?>','Kantin <?= $k_tampil ?> — <?= htmlspecialchars(addslashes($qris['nama_penjual'])) ?>')">
              🔍 Lihat QRIS
            </button>
          <?php endif; ?>
        </div>
      </div>
      <div class="qvi-actions">
        <a href="?approve_qris=<?= (int)$qris['id_qris'] ?>" class="btn-approve"
           onclick="return confirm('Setujui QRIS Kantin <?= $k_tampil ?> milik <?= htmlspecialchars(addslashes($qris['nama_penjual'])) ?>?')">
          ✅ Setujui
        </a>
        <a href="?reject_qris=<?= (int)$qris['id_qris'] ?>" class="btn-reject"
           onclick="return confirm('Tolak dan hapus QRIS ini?')">
          ❌ Tolak
        </a>
      </div>
    </div>
    <?php endwhile; ?>
    <?php else: ?>
    <div class="qris-verif-empty">
      <div class="evi">✅</div>
      <p style="font-size:15px;font-weight:700;color:var(--text);margin-bottom:6px;">Semua QRIS sudah terverifikasi</p>
      <p>Tidak ada QRIS yang menunggu persetujuan.</p>
    </div>
    <?php endif; ?>
  </div>
</div>

<!-- ═══ MODAL KELOLA QRIS PER KANTIN ═══ -->
<div class="modal-overlay" id="modalKelolaQris">
  <div class="modal-box modal-box-kel-qris">
    <div class="modal-header">
      <div class="modal-title">🗂 Kelola QRIS Per Kantin</div>
      <button class="modal-close" onclick="tutupModal('modalKelolaQris')">✕</button>
    </div>
    <?php
    $data_penjual_list = mysqli_query($db, "SELECT id_penjual, id_kantin, nama FROM penjual WHERE status != 0 AND deleted_at IS NULL ORDER BY id_kantin ASC");
    while ($pj = mysqli_fetch_assoc($data_penjual_list)):
      $idk = $pj['id_kantin'];
      $list = $qris_per_kantin[$idk] ?? [];
      $jumlah_qris = count($list);
    ?>
    <div class="kantin-group">
      <div class="kantin-header" onclick="toggleKantin('kantin<?= $idk ?>')">
        <div class="kantin-header-left">
          <span class="kantin-icon">🏪</span>
          <div>
            <div class="kantin-nama">Kantin <?= $idk ?></div>
            <div class="kantin-meta"><?= htmlspecialchars($pj['nama']) ?></div>
          </div>
        </div>
        <div class="kantin-badge-wrap">
          <span class="badge-jml <?= $jumlah_qris == 0 ? 'badge-jml-0' : '' ?>"><?= $jumlah_qris ?> QRIS</span>
          <span class="kantin-chevron" id="chv<?= $idk ?>">▼</span>
        </div>
      </div>
      <div class="kantin-body" id="kantin<?= $idk ?>">
        <?php if ($jumlah_qris == 0): ?>
          <div class="qris-item-empty">Belum ada QRIS terdaftar untuk kantin ini.</div>
        <?php else: foreach ($list as $qris): ?>
          <div class="qris-item">
            <div class="qris-item-left">
              <?php if (!empty($qris['gambar_qris'])): ?>
                <img src="uploads/qris/<?= htmlspecialchars($qris['gambar_qris']) ?>" class="qris-thumb"
                     onclick="previewQrisImg('uploads/qris/<?= htmlspecialchars($qris['gambar_qris']) ?>','Kantin <?= $idk ?> — <?= htmlspecialchars(addslashes($pj['nama'])) ?>')"
                     title="Klik untuk memperbesar">
              <?php else: ?>
                <div class="qris-thumb-ph">📲</div>
              <?php endif; ?>
              <div class="qris-item-info">
                <div class="qris-item-kantin">🏪 Kantin <?= $idk ?></div>
                <div class="qris-item-date">📅 <?= date('d M Y H:i', strtotime($qris['created_at'])) ?></div>
                <div class="qris-item-badges">
                  <?php if ($qris['verified']): ?>
                    <span class="badge-verified-qris">✅ Terverifikasi</span>
                    <?php if (!empty($qris['verified_at'])): ?>
                      <span style="font-size:11px;color:var(--text-sub);"><?= date('d M Y', strtotime($qris['verified_at'])) ?></span>
                    <?php endif; ?>
                  <?php else: ?>
                    <span class="badge-unverif-qris">⏳ Menunggu Verifikasi</span>
                  <?php endif; ?>
                </div>
              </div>
            </div>
            <a href="?hapus_qris=<?= (int)$qris['id_qris'] ?>" class="btn-hapus-qris-admin"
               onclick="return confirm('Yakin hapus QRIS Kantin <?= $idk ?>?')">🗑 Hapus</a>
          </div>
        <?php endforeach; endif; ?>
      </div>
    </div>
    <?php endwhile; ?>
  </div>
</div>

<!-- ═══ MODAL PREVIEW GAMBAR QRIS ═══ -->
<div class="modal-overlay" id="modalPreviewQris" style="z-index:400;">
  <div class="modal-box modal-box-preview">
    <div class="modal-header">
      <div class="modal-title">🔍 Pratinjau QRIS</div>
      <button class="modal-close" onclick="tutupModal('modalPreviewQris')">✕</button>
    </div>
    <img src="" id="qrisPreviewImgEl" class="qris-preview-img" alt="QRIS">
    <div class="qris-preview-info" id="qrisPreviewInfoEl"></div>
  </div>
</div>

<!-- ═══════════════════════════════════════════════════════════════
     MODAL TAMBAH PENJUAL
════════════════════════════════════════════════════════════════ -->
<div class="modal-overlay" id="modalTambah">
  <div class="modal-box">
    <div class="modal-header">
      <div class="modal-title">➕ Tambah Penjual Baru</div>
      <button class="modal-close" onclick="tutupModalTambah()">✕</button>
    </div>
    <form method="post" enctype="multipart/form-data" onsubmit="return validasiFormTambah(event)">
      <div class="form-grid">
        <div class="form-section-label form-full">👤 Data Akun Penjual</div>
        <div>
          <label>Nama Lengkap</label>
          <input type="text" name="nama" placeholder="Nama lengkap penjual" required>
        </div>
        <div>
          <label>Username</label>
          <input type="text" name="username" placeholder="Username login" required>
        </div>
        <div>
          <label>Password</label>
          <input type="password" name="password" placeholder="Password" required>
        </div>

        <!-- ===== EMAIL dengan validasi realtime ===== -->
        <div>
          <label>Email <small style="color:var(--text-sub);font-weight:400;">(harus @gmail.com)</small></label>
          <input type="email" name="email" id="emailTambah"
                 placeholder="contoh@gmail.com"
                 oninput="validateGmail(this)"
                 onblur="validateGmail(this)"
                 autocomplete="off"
                 required>
          <div class="email-feedback" id="emailTambahFeedback" style="color:var(--text-sub);">Harus menggunakan @gmail.com</div>
        </div>

        <!-- ===== NO. TELEPON — hanya angka ===== -->
        <div class="form-full">
          <label>No. Telepon <small style="color:var(--text-sub);font-weight:400;">(format: 08xxx atau 628xxx, hanya angka)</small></label>
          <div class="phone-input-wrap">
            <span class="phone-prefix">📱</span>
            <input type="tel" name="no_tlp" id="inputNoTlp"
                   placeholder="Contoh: 081234567890"
                   inputmode="numeric"
                   autocomplete="off"
                   onkeydown="blockNonDigit(event)"
                   onpaste="handlePhonePaste(event)"
                   oninput="validatePhone(this)"
                   required>
          </div>
          <div class="phone-hint" id="phoneFeedback" style="color:var(--text-sub);">Gunakan format 08xxx atau 628xxx (hanya angka)</div>
        </div>

        <div>
          <label>Pilih Kantin</label>
          <select name="canteen" required>
            <option value="" disabled selected>-- Pilih Kantin --</option>
            <?php
            for ($k = 1; $k <= 10; $k++) {
                $disabled = in_array($k, $kantinTerpakai) ? 'disabled' : '';
                $label    = in_array($k, $kantinTerpakai) ? " (sudah aktif)" : "";
                echo "<option value=\"$k\" $disabled>Kantin $k$label</option>";
            }
            ?>
          </select>
        </div>
        <div>
          <label>Foto Profil</label>
          <input type="file" name="profil" accept="image/*" required>
        </div>
        <div class="form-section-label form-full">📲 Upload Foto QRIS <span style="color:#ef4444;font-weight:700;">*Wajib</span></div>
        <div class="form-full">
          <label>Gambar QRIS <span style="color:#ef4444;">*</span></label>
          <input type="file" name="gambar_qris" accept="image/*"
                 id="inputGambarQris" onchange="previewQrisUpload(this)" required>
          <div class="qris-wajib-note">⚠️ Gambar QRIS wajib diupload.</div>
          <div class="qris-upload-preview" id="qrisUploadPreview">
            <img id="qrisUploadPreviewImg" src="" alt="Preview QRIS">
            <div style="margin-top:6px;font-size:12px;color:var(--text-sub);">Pratinjau gambar QRIS</div>
          </div>
        </div>
      </div>
      <button type="submit" name="simpan" class="form-submit-btn">✅ Simpan Akun Penjual</button>
    </form>
  </div>
</div>

<!-- ═══ MODAL EDIT PENJUAL AKTIF ═══ -->
<div class="modal-overlay" id="modalEdit">
  <div class="modal-box">
    <div class="modal-header">
      <div class="modal-title">✏️ Edit Data Penjual</div>
      <button class="modal-close" onclick="tutupModalEdit()">✕</button>
    </div>
    <form method="post">
      <input type="hidden" name="edit_id" id="edit_id">
      <div class="form-grid">
        <div class="form-full">
          <label>Nama Lengkap</label>
          <input type="text" name="edit_nama" id="edit_nama" placeholder="Nama lengkap" required>
        </div>
        <div class="form-full">
          <label>Email <small style="color:var(--text-sub);font-weight:400;">(harus @gmail.com)</small></label>
          <input type="email" name="edit_email" id="edit_email"
                 placeholder="contoh@gmail.com"
                 oninput="validateGmail(this)"
                 onblur="validateGmail(this)"
                 autocomplete="off"
                 required>
          <div class="email-feedback" id="edit_emailFeedback" style="color:var(--text-sub);">Harus menggunakan @gmail.com</div>
        </div>
      </div>
      <button type="submit" name="edit" class="form-submit-btn" style="background:linear-gradient(135deg,#3b82f6,#2563eb);">💾 Simpan Perubahan</button>
    </form>
  </div>
</div>

<!-- ═══ MODAL EDIT PENJUAL NONAKTIF ═══ -->
<div class="modal-overlay" id="modalEditNonaktif">
  <div class="modal-box">
    <div class="modal-header">
      <div class="modal-title">✏️ Edit Data Penjual Nonaktif</div>
      <button class="modal-close" onclick="tutupModal('modalEditNonaktif')">✕</button>
    </div>
    <div style="background:var(--yellow-soft);border:1px solid rgba(245,158,11,0.3);border-radius:12px;padding:12px 16px;margin-bottom:18px;font-size:13px;color:var(--text-sub);">
      ⚠️ Anda dapat mengubah nama, email, dan <strong style="color:var(--yellow);">kantin asal</strong> penjual nonaktif ini.
    </div>
    <form method="post">
      <input type="hidden" name="enon_id" id="enon_id">
      <div class="form-grid">
        <div class="form-full">
          <label>Nama Lengkap</label>
          <input type="text" name="enon_nama" id="enon_nama" placeholder="Nama lengkap" required>
        </div>
        <div class="form-full">
          <label>Email <small style="color:var(--text-sub);font-weight:400;">(harus @gmail.com)</small></label>
          <input type="email" name="enon_email" id="enon_email"
                 placeholder="contoh@gmail.com"
                 oninput="validateGmail(this)"
                 onblur="validateGmail(this)"
                 autocomplete="off"
                 required>
          <div class="email-feedback" id="enon_emailFeedback" style="color:var(--text-sub);">Harus menggunakan @gmail.com</div>
        </div>
        <div class="form-full">
          <label>Kantin Asal <small style="color:var(--text-sub);font-weight:400;">(digunakan saat diaktifkan)</small></label>
          <select name="enon_kantin" id="enon_kantin" required>
            <option value="">-- Pilih Kantin Asal --</option>
            <?php for ($k = 1; $k <= 10; $k++): ?>
              <option value="<?= $k ?>">Kantin <?= $k ?></option>
            <?php endfor; ?>
          </select>
        </div>
      </div>
      <button type="submit" name="edit_nonaktif" class="form-submit-btn" style="background:linear-gradient(135deg,#f59e0b,#d97706);">💾 Simpan Perubahan</button>
    </form>
  </div>
</div>

<script>
/* ===== ACCORDION QRIS ===== */
function toggleKantin(id) {
  var body = document.getElementById(id);
  var idk  = id.replace('kantin','');
  var chv  = document.getElementById('chv'+idk);
  var isOpen = body.classList.contains('open');
  body.classList.toggle('open',!isOpen);
  chv.classList.toggle('open',!isOpen);
}

/* ===== SUB-TAB ===== */
function switchSubTabByUid(btn, tabName) {
  var wrap = btn.closest('.rekap-subtab-wrap');
  if (!wrap) return;
  var uid = wrap.getAttribute('data-uid');
  var scope = wrap.parentElement;
  wrap.querySelectorAll('.rekap-subtab-btn').forEach(function(b){ b.classList.remove('active'); });
  btn.classList.add('active');
  scope.querySelectorAll('.rekap-subpanel[data-uid="' + uid + '"]').forEach(function(p){
    p.classList.remove('active');
    if (p.getAttribute('data-tab') === tabName) p.classList.add('active');
  });
}

/* =====================================================
   NO. TELEPON — HANYA ANGKA, BLOK HURUF SECARA HARD
   ===================================================== */

/**
 * Blok semua key non-digit sebelum masuk ke input.
 * Tetap izinkan: angka 0-9, Backspace, Delete, Tab,
 * Arrow keys, Home, End, dan Ctrl/Cmd + A/C/V/X.
 */
function blockNonDigit(e) {
  var allowed = [
    'Backspace','Delete','Tab','Enter','Escape',
    'ArrowLeft','ArrowRight','ArrowUp','ArrowDown',
    'Home','End'
  ];
  if (allowed.indexOf(e.key) !== -1) return;
  // Izinkan Ctrl/Cmd + A, C, V, X
  if ((e.ctrlKey || e.metaKey) && ['a','c','v','x'].indexOf(e.key.toLowerCase()) !== -1) return;
  // Blok semua selain digit 0-9
  if (!/^[0-9]$/.test(e.key)) {
    e.preventDefault();
  }
}

/**
 * Saat paste: ambil hanya angka dari clipboard,
 * sisipkan ke posisi kursor, lalu trigger validasi.
 */
function handlePhonePaste(e) {
  e.preventDefault();
  var pasted = (e.clipboardData || window.clipboardData).getData('text');
  var digitsOnly = pasted.replace(/[^0-9]/g, '');
  var input = e.target;
  var start = input.selectionStart;
  var end   = input.selectionEnd;
  input.value = input.value.slice(0, start) + digitsOnly + input.value.slice(end);
  // Pindahkan kursor ke akhir hasil paste
  var newPos = start + digitsOnly.length;
  input.setSelectionRange(newPos, newPos);
  validatePhone(input);
}

/**
 * Validasi nomor telepon dengan feedback realtime.
 * Juga membersihkan paksa karakter non-digit
 * (antisipasi browser autofill / autocomplete).
 */
function validatePhone(input) {
  // Paksa hapus semua non-digit (antisipasi autofill browser)
  var cleaned = input.value.replace(/[^0-9]/g, '');
  if (input.value !== cleaned) input.value = cleaned;

  var val      = cleaned;
  var feedback = document.getElementById('phoneFeedback');
  if (!feedback) return;

  if (val === '') {
    feedback.style.color = 'var(--text-sub)';
    feedback.textContent = 'Gunakan format 08xxx atau 628xxx (hanya angka)';
    input.style.borderColor = '';
    input.setCustomValidity('Nomor telepon wajib diisi');
    return;
  }

  var start08  = val.startsWith('08');
  var start628 = val.startsWith('628');

  if (!start08 && !start628) {
    // Cek apakah sedang mengetik awalan yang belum lengkap
    if ('08'.startsWith(val) || '628'.startsWith(val)) {
      feedback.style.color    = 'var(--yellow)';
      feedback.textContent    = '⌛ Lanjutkan mengetik awalan nomor...';
      input.style.borderColor = 'var(--yellow)';
      input.setCustomValidity('Format nomor belum lengkap');
    } else {
      feedback.style.color    = 'var(--red)';
      feedback.textContent    = '❌ Harus diawali 08 atau 628';
      input.style.borderColor = 'var(--red)';
      input.setCustomValidity('Nomor harus diawali 08 atau 628');
    }
    return;
  }

  // Panjang minimal: 08 + 7 digit = 9 char | 628 + 7 digit = 10 char
  var minLen = start628 ? 10 : 9;
  var maxLen = start628 ? 13 : 12;

  if (val.length < minLen) {
    feedback.style.color    = 'var(--yellow)';
    feedback.textContent    = '⌛ Nomor terlalu pendek (min ' + minLen + ' digit)';
    input.style.borderColor = 'var(--yellow)';
    input.setCustomValidity('Nomor terlalu pendek');
    return;
  }

  if (val.length > maxLen) {
    feedback.style.color    = 'var(--red)';
    feedback.textContent    = '❌ Nomor terlalu panjang (maks ' + maxLen + ' digit)';
    input.style.borderColor = 'var(--red)';
    input.setCustomValidity('Nomor terlalu panjang');
    return;
  }

  // Semua OK
  feedback.style.color    = 'var(--green)';
  feedback.textContent    = '✅ Format nomor telepon valid';
  input.style.borderColor = 'var(--green)';
  input.setCustomValidity('');
}

/* =====================================================
   EMAIL — VALIDASI REALTIME, HARUS @gmail.com
   ===================================================== */

/**
 * Validasi email realtime dengan feedback progresif.
 * Feedback ditampilkan di elemen dengan id = input.id + 'Feedback'
 */
function validateGmail(input) {
  var val      = input.value;
  var feedbackId = input.id + 'Feedback';
  var feedback = document.getElementById(feedbackId);
  if (!feedback) return;

  // Kosong
  if (val === '') {
    feedback.style.color    = 'var(--text-sub)';
    feedback.textContent    = 'Harus menggunakan @gmail.com';
    input.style.borderColor = '';
    input.setCustomValidity('Email harus menggunakan @gmail.com');
    return;
  }

  var atIdx = val.indexOf('@');

  // Belum ada @
  if (atIdx === -1) {
    feedback.style.color    = 'var(--yellow)';
    feedback.textContent    = '⌛ Ketik email, lalu tambahkan @gmail.com';
    input.style.borderColor = 'var(--yellow)';
    input.setCustomValidity('Email harus menggunakan @gmail.com');
    return;
  }

  var local  = val.slice(0, atIdx);
  var domain = val.slice(atIdx + 1).toLowerCase();

  // Setelah @ tapi domain kosong
  if (domain === '') {
    feedback.style.color    = 'var(--yellow)';
    feedback.textContent    = '⌛ Ketik gmail.com setelah @';
    input.style.borderColor = 'var(--yellow)';
    input.setCustomValidity('Email harus menggunakan @gmail.com');
    return;
  }

  // Domain sudah diketik tapi bukan awalan gmail.com
  // Contoh: @yahoo, @hotmail → tolak langsung
  if (domain.length > 0 && !'gmail.com'.startsWith(domain)) {
    feedback.style.color    = 'var(--red)';
    feedback.textContent    = '❌ Hanya @gmail.com yang diizinkan';
    input.style.borderColor = 'var(--red)';
    input.setCustomValidity('Hanya email @gmail.com yang diizinkan');
    return;
  }

  // Domain sedang diketik tapi belum selesai (misal: @gma, @gmail)
  if (domain !== 'gmail.com') {
    feedback.style.color    = 'var(--yellow)';
    feedback.textContent    = '⌛ Lanjutkan: @gmail.com';
    input.style.borderColor = 'var(--yellow)';
    input.setCustomValidity('Email harus menggunakan @gmail.com');
    return;
  }

  // Domain sudah @gmail.com, cek local part
  if (!local || local.length < 2) {
    feedback.style.color    = 'var(--red)';
    feedback.textContent    = '❌ Nama akun email terlalu pendek';
    input.style.borderColor = 'var(--red)';
    input.setCustomValidity('Nama akun email tidak valid');
    return;
  }

  // Cek karakter tidak valid di local part (basic check)
  if (!/^[a-zA-Z0-9._%+\-]+$/.test(local)) {
    feedback.style.color    = 'var(--red)';
    feedback.textContent    = '❌ Karakter tidak valid di nama email';
    input.style.borderColor = 'var(--red)';
    input.setCustomValidity('Karakter email tidak valid');
    return;
  }

  // ✅ Valid
  feedback.style.color    = 'var(--green)';
  feedback.textContent    = '✅ Email valid';
  input.style.borderColor = 'var(--green)';
  input.setCustomValidity('');
}

/**
 * Validasi form tambah sebelum submit —
 * pastikan email dan telepon benar-benar valid.
 */
function validasiFormTambah(e) {
  var emailEl = document.getElementById('emailTambah');
  var phoneEl = document.getElementById('inputNoTlp');
  var ok = true;

  validateGmail(emailEl);
  validatePhone(phoneEl);

  if (emailEl.validationMessage !== '') {
    emailEl.focus();
    ok = false;
  } else if (phoneEl.validationMessage !== '') {
    phoneEl.focus();
    ok = false;
  }

  return ok;
}

/* =====================================================
   PASSWORD GATE
   ===================================================== */
var _pgPenjualId   = null;
var _pgNamaPenjual = '';
var _pgIdKantin    = 0;
var _pgIsNonaktif  = false;
var _pgUsername    = '';

function bukaPasswordGate(idPenjual, namaPenjual, idKantin, username, isNonaktif) {
  _pgPenjualId   = idPenjual;
  _pgNamaPenjual = namaPenjual;
  _pgIdKantin    = idKantin;
  _pgIsNonaktif  = isNonaktif;
  _pgUsername    = username;

  var kantinLabel = idKantin ? 'Kantin ' + idKantin + ' — ' + namaPenjual : namaPenjual;
  document.getElementById('pwGateKantinLabel').textContent  = kantinLabel;
  document.getElementById('pwGateUsernameLabel').textContent = username;
  document.getElementById('pwGateInput').value = '';
  document.getElementById('pwGateInput').type  = 'password';
  document.getElementById('pwGateError').textContent = '';
  setPwBtnLoading(false);

  bukaModal('modalPasswordGate');
  setTimeout(function(){ document.getElementById('pwGateInput').focus(); }, 300);
}

function clearPwError() {
  var errEl = document.getElementById('pwGateError');
  errEl.textContent = '';
  document.getElementById('pwGateInput').classList.remove('pw-error');
}

function togglePwVisibility() {
  var inp = document.getElementById('pwGateInput');
  inp.type = inp.type === 'password' ? 'text' : 'password';
}

function setPwBtnLoading(loading) {
  var btn     = document.getElementById('pwGateSubmitBtn');
  var spinner = document.getElementById('pwGateSpinner');
  var txt     = document.getElementById('pwGateBtnText');
  btn.disabled = loading;
  spinner.style.display = loading ? 'block' : 'none';
  txt.textContent = loading ? 'Memverifikasi...' : '🔓 Verifikasi & Buka Rekapan';
}

function showPwError(msg) {
  var errEl = document.getElementById('pwGateError');
  var inp   = document.getElementById('pwGateInput');
  errEl.innerHTML = '❌ ' + msg;
  inp.classList.add('pw-error');
  setTimeout(function(){ inp.classList.remove('pw-error'); }, 500);
  inp.focus();
  inp.select();
}

function verifyPasswordGate() {
  var password = document.getElementById('pwGateInput').value.trim();
  if (!password) { showPwError('Password tidak boleh kosong.'); return; }

  setPwBtnLoading(true);
  var formData = new FormData();
  formData.append('cek_password_rekap', '1');
  formData.append('id_penjual', _pgPenjualId);
  formData.append('password_input', password);

  fetch(window.location.href, { method: 'POST', body: formData })
  .then(function(r){ return r.json(); })
  .then(function(data) {
    setPwBtnLoading(false);
    if (data.ok) {
      tutupModal('modalPasswordGate');
      setTimeout(function(){
        bukaRekapAfterVerify(_pgPenjualId, _pgNamaPenjual, _pgIdKantin, _pgIsNonaktif);
      }, 200);
    } else {
      showPwError(data.msg || 'Password salah. Coba lagi.');
    }
  })
  .catch(function() {
    setPwBtnLoading(false);
    showPwError('Terjadi kesalahan jaringan. Coba lagi.');
  });
}

function bukaRekapAfterVerify(idPenjual, namaPenjual, idKantin, isNonaktif) {
  var srcPanel = document.getElementById('rekapSingle-' + idPenjual);
  var content  = document.getElementById('modalRekapSingleContent');
  var title    = document.getElementById('modalRekapSingleTitle');

  if (srcPanel && content) {
    var html = srcPanel.innerHTML;
    var newPrefix = 'modal' + idPenjual + '-';
    html = html.replace(/data-uid="src-/g, 'data-uid="' + newPrefix);
    content.innerHTML = html;
  }

  var kantinLabel = idKantin ? ' — Kantin ' + idKantin : '';
  var statusLabel = isNonaktif ? ' (Nonaktif)' : '';
  if (title) title.textContent = '📊 Rekap ' + namaPenjual + kantinLabel + statusLabel;

  // Update href tombol unduh Excel
  var btnExcel = document.getElementById('btnCetakRekapExcel');
  if (btnExcel) {
    btnExcel.href = 'admin_cetak_rekap_excel.php?id_penjual=' + idPenjual;
  }

  bukaModal('modalRekapSingle');
}

/* =====================================================
   SEARCH & FILTER — Per Menu
   ===================================================== */
function applyMenuFilter(uid) {
  var table = document.querySelector('table.rekap-menu-table[data-uid="' + uid + '"]');
  if (!table) return;
  var rows   = Array.from(table.querySelectorAll('tbody tr.menu-row-clickable'));
  var filter = (function(){ var b = document.querySelector('.permenu-filter-btn.active[data-uid="' + uid + '"]'); return b ? b.getAttribute('data-filter') : 'SEMUA'; })();
  var query  = (function(){ var i = document.querySelector('.permenu-search-input[data-uid="' + uid + '"]'); return i ? i.value.trim().toLowerCase() : ''; })();

  var visCount = 0, totalOmset = 0, totalTerjual = 0;
  rows.forEach(function(row) {
    var kat  = row.getAttribute('data-kategori') || '';
    var nama = row.getAttribute('data-nama') || '';
    var show = (filter === 'SEMUA' || kat === filter) && (query === '' || nama.indexOf(query) !== -1);
    row.style.display = show ? '' : 'none';
    if (show) {
      visCount++;
      var cells = row.querySelectorAll('td');
      totalOmset   += parseInt((cells[5] ? cells[5].textContent.replace(/[^0-9]/g,'') : '0')) || 0;
      totalTerjual += parseInt((cells[4] ? cells[4].textContent.replace(/[^0-9]/g,'') : '0')) || 0;
    }
  });

  var cNum = document.querySelector('.permenu-count-num[data-uid="' + uid + '"]');
  if (cNum) cNum.textContent = visCount;
  var ftj = document.querySelector('.tfoot-terjual[data-uid="' + uid + '"]');
  var fom = document.querySelector('.tfoot-omset[data-uid="' + uid + '"]');
  if (ftj) ftj.textContent = totalTerjual.toLocaleString('id-ID') + ' item';
  if (fom) fom.textContent = 'Rp ' + totalOmset.toLocaleString('id-ID');
  var noRes = document.querySelector('.no-result-row[data-uid="' + uid + '"]');
  var tfoot = document.querySelector('.tfoot-total[data-uid="' + uid + '"]');
  if (noRes) noRes.style.display = visCount === 0 ? '' : 'none';
  if (tfoot) tfoot.style.display = visCount === 0 ? 'none' : '';

  var toolbar = document.querySelector('.permenu-toolbar[data-uid="' + uid + '"]');
  if (toolbar) { var cb = toolbar.querySelector('.permenu-clear-btn'); if (cb) cb.style.display = query ? 'block' : 'none'; }
}

function filterMenuTable(inputEl)  { applyMenuFilter(inputEl.getAttribute('data-uid')); }
function filterMenuKategori(btn) {
  var uid = btn.getAttribute('data-uid');
  var toolbar = btn.closest('.permenu-toolbar');
  if (toolbar) toolbar.querySelectorAll('.permenu-filter-btn').forEach(function(b){ b.classList.remove('active'); });
  btn.classList.add('active');
  applyMenuFilter(uid);
}
function clearMenuSearch(clearBtn) {
  var toolbar = clearBtn.closest('.permenu-toolbar');
  if (!toolbar) return;
  var uid = toolbar.getAttribute('data-uid');
  var inp = toolbar.querySelector('.permenu-search-input');
  if (inp) inp.value = '';
  clearBtn.style.display = 'none';
  applyMenuFilter(uid);
}

/* =====================================================
   MODAL DETAIL MENU
   ===================================================== */
function bukaDetailMenu(idMenu, data) {
  if (!data || !data.nama_makanan) return;
  var title   = document.getElementById('detailMenuTitle');
  var content = document.getElementById('detailMenuContent');
  if (!content) return;

  var kat = data.kategori || '';
  var katMap = {
    'MAKANAN': { bg:'rgba(249,115,22,0.12)', cl:'#ea580c', ic:'🍽' },
    'MINUMAN': { bg:'rgba(59,130,246,0.12)',  cl:'#2563eb', ic:'🥤' },
    'SNACK':   { bg:'rgba(168,85,247,0.12)',  cl:'#7c3aed', ic:'🍿' }
  };
  var k = katMap[kat] || { bg:'var(--card2)', cl:'var(--text-sub)', ic:'📦' };

  var statusHtml = data.menu_deleted_at
    ? '<span style="font-size:11px;background:var(--red-soft);color:var(--red);padding:2px 9px;border-radius:20px;font-weight:700;">🗑 Dihapus</span>'
    : (!data.aktif
        ? '<span style="font-size:11px;background:var(--yellow-soft);color:var(--yellow);padding:2px 9px;border-radius:20px;font-weight:700;">⏸ Nonaktif</span>'
        : '<span style="font-size:11px;background:var(--green-soft);color:var(--green);padding:2px 9px;border-radius:20px;font-weight:700;">🟢 Aktif</span>');

  var varianList  = data.varian_list  || [];
  var rekapVarian = data.rekap_varian || [];
  var varianBadge = (varianList.length > 0 || rekapVarian.length > 0)
    ? '<span style="font-size:11px;background:rgba(139,92,246,0.12);color:#7c3aed;padding:2px 9px;border-radius:20px;font-weight:700;">🎨 ' + varianList.length + ' Varian</span>'
    : '';

  var ratingHtml = data.rata_bintang
    ? '<span style="color:#f59e0b;font-weight:700;">' + data.rata_bintang.toFixed(1) + '</span> ⭐ <span style="color:var(--text-sub);font-size:12px;">(' + data.jumlah_ulasan + ' ulasan)</span>'
    : '<span style="color:var(--text-sub);">Belum ada ulasan</span>';

  var fmtRp  = function(n){ return 'Rp ' + parseInt(n||0).toLocaleString('id-ID'); };
  var fmtNum = function(n){ return parseInt(n||0).toLocaleString('id-ID'); };
  var totalMetode = (data.omset_cash||0) + (data.omset_qris||0);
  var pctCash = totalMetode > 0 ? Math.round((data.omset_cash/totalMetode)*100) : 0;
  var pctQris = totalMetode > 0 ? Math.round((data.omset_qris/totalMetode)*100) : 0;

  var fotoHtml = data.foto
    ? '<img src="uploads/' + escHtml(data.foto) + '" class="detail-menu-foto" onerror="this.parentElement.innerHTML=\'<div class=detail-menu-foto-ph>' + k.ic + '</div>\'">'
    : '<div class="detail-menu-foto-ph">' + k.ic + '</div>';

  var descHtml = data.deskripsi
    ? '<div class="detail-menu-desc">' + escHtml(data.deskripsi) + '</div>'
    : '<div class="detail-menu-desc" style="font-style:italic;">Tidak ada deskripsi.</div>';

  var varianSectionHtml = '';
  if (varianList.length > 0 || rekapVarian.length > 0) {
    varianSectionHtml += '<div class="varian-section">';
    if (varianList.length > 0) {
      varianSectionHtml += '<div class="detail-section-title">🎨 Daftar Varian Menu (' + varianList.length + ' varian)</div><div class="varian-list-grid">';
      varianList.forEach(function(v) {
        var fotoV = v.foto_varian
          ? '<img src="uploads/' + escHtml(v.foto_varian) + '" class="varian-card-foto"><div class="varian-card-foto-ph" style="display:none;">🎨</div>'
          : '<div class="varian-card-foto-ph">🎨</div>';
        var hargaTxt = v.harga_tambah > 0
          ? '<span style="color:var(--primary);font-weight:600;">+' + fmtRp(v.harga_tambah) + '</span>'
          : '<span style="color:var(--text-sub);">Harga sama</span>';
        var stokColor = v.stok <= 5 ? 'color:var(--red);' : (v.stok <= 20 ? 'color:var(--yellow);' : 'color:var(--green);');
        varianSectionHtml += '<div class="varian-card">' + fotoV + '<div class="varian-card-info"><div class="varian-card-nama">' + escHtml(v.nama) + '</div><div class="varian-card-harga">' + hargaTxt + '</div><div class="varian-card-stok" style="' + stokColor + ' font-size:11px;font-weight:600;">📦 Stok: ' + fmtNum(v.stok) + '</div></div></div>';
      });
      varianSectionHtml += '</div>';
    }
    if (rekapVarian.length > 0) {
      var maxOmset = 0;
      rekapVarian.forEach(function(rv){ if (rv.total_omset > maxOmset) maxOmset = rv.total_omset; });
      varianSectionHtml += '<div class="detail-section-title">📊 Rekap Penjualan Per Varian</div>';
      varianSectionHtml += '<div class="varian-info-note">💡 Rekap dari <strong>catatan pesanan</strong> yang mencantumkan nama varian.</div>';
      varianSectionHtml += '<div style="overflow-x:auto;"><table class="varian-rekap-table"><thead><tr><th style="width:32px;">#</th><th>Nama Varian</th><th style="text-align:center;">Pesanan</th><th style="text-align:center;">Terjual</th><th style="text-align:right;">Omset</th><th style="text-align:right;">CASH</th><th style="text-align:right;">QRIS</th><th style="text-align:center;">Batal/Tolak</th><th style="text-align:center;">Success</th></tr></thead><tbody>';
      rekapVarian.forEach(function(rv, idx) {
        var rn = idx+1;
        var rc = rn===1?'varian-rank-1':rn===2?'varian-rank-2':rn===3?'varian-rank-3':'varian-rank-other';
        var sr = rv.total_order > 0 ? Math.round((rv.total_lunas/rv.total_order)*100) : 0;
        var sc = sr>=80?'var(--green)':sr>=50?'var(--yellow)':'var(--red)';
        var bw = maxOmset>0 ? Math.max(Math.round((rv.total_omset/maxOmset)*100), rv.total_omset>0?4:0) : 0;
        var btTl = (rv.total_batal||0)+(rv.total_ditolak||0);
        varianSectionHtml += '<tr><td><span class="varian-rank-badge ' + rc + '">' + rn + '</span></td>'
          + '<td style="font-weight:700;">' + escHtml(rv.nama_varian) + (rv.total_omset>0?'<div class="varian-omset-bar-wrap"><div class="varian-omset-bar" style="width:'+bw+'%;"></div></div>':'') + '</td>'
          + '<td style="text-align:center;">' + fmtNum(rv.total_order) + '</td>'
          + '<td style="text-align:center;font-weight:700;color:var(--primary);">' + fmtNum(rv.total_terjual) + '</td>'
          + '<td style="text-align:right;font-weight:800;color:var(--green);white-space:nowrap;">' + (rv.total_omset>0?fmtRp(rv.total_omset):'<span style="color:var(--text-sub);">—</span>') + '</td>'
          + '<td style="text-align:right;white-space:nowrap;color:var(--green);">' + (rv.omset_cash>0?fmtRp(rv.omset_cash):'<span style="color:var(--text-sub);">—</span>') + '</td>'
          + '<td style="text-align:right;white-space:nowrap;color:var(--blue);">' + (rv.omset_qris>0?fmtRp(rv.omset_qris):'<span style="color:var(--text-sub);">—</span>') + '</td>'
          + '<td style="text-align:center;">' + (btTl>0?'<span style="color:var(--red);font-weight:600;">'+(rv.total_batal||0)+'/'+(rv.total_ditolak||0)+'</span>':'<span style="color:var(--green);">—</span>') + '</td>'
          + '<td style="text-align:center;font-weight:700;font-size:12px;color:' + sc + ';">' + sr + '%</td></tr>';
      });
      varianSectionHtml += '</tbody></table></div>';
    } else if (varianList.length > 0) {
      varianSectionHtml += '<div class="varian-info-note" style="margin-top:8px;">📋 Menu ini memiliki ' + varianList.length + ' varian terdaftar, namun belum ada data penjualan per varian yang tercatat.</div>';
    }
    varianSectionHtml += '</div>';
  }

  var ulasanHtml = '';
  if (data.ulasan && data.ulasan.length > 0) {
    data.ulasan.forEach(function(ul) {
      var stars = ''; for (var i=0;i<5;i++) stars += i < parseInt(ul.bintang) ? '⭐' : '☆';
      var fotoUl = ul.foto ? '<div class="ulasan-foto"><img src="uploads/' + escHtml(ul.foto) + '" onclick="previewQrisImg(\'uploads/' + escHtml(ul.foto) + '\',\'Foto ulasan\')" title="Klik untuk memperbesar"></div>' : '';
      ulasanHtml += '<div class="ulasan-item"><div class="ulasan-header"><div><div class="ulasan-user">' + escHtml(ul.nama_user||'Anonim') + '</div>' + (ul.kelas_user?'<div class="ulasan-kelas">'+escHtml(ul.kelas_user)+'</div>':'') + '</div><div style="text-align:right;"><div class="ulasan-bintang">' + stars + '</div><div class="ulasan-tanggal">' + formatTgl(ul.created_at) + '</div></div></div>' + (ul.komentar?'<div class="ulasan-komentar">"'+escHtml(ul.komentar)+'"</div>':'') + fotoUl + '</div>';
    });
  } else {
    ulasanHtml = '<div style="text-align:center;padding:20px;color:var(--text-sub);font-size:13px;">Belum ada ulasan untuk menu ini.</div>';
  }

  var trxHtml = '';
  if (data.transaksi && data.transaksi.length > 0) {
    var stMap = {'lunas':{cls:'status-lunas',lb:'✅ Lunas'},'batal':{cls:'status-batal',lb:'❌ Batal'},'ditolak':{cls:'status-ditolak',lb:'🚫 Ditolak'},'pending':{cls:'status-pending',lb:'⏳ Pending'},'siap':{cls:'status-siap',lb:'🍽 Siap'},'dimasak':{cls:'status-dimasak',lb:'🔥 Dimasak'}};
    trxHtml = '<div style="overflow-x:auto;"><table class="detail-trx-table"><thead><tr><th>Tgl</th><th>Pembeli</th><th>Qty</th><th>Total</th><th>Metode</th><th>Catatan</th><th>Status</th></tr></thead><tbody>';
    data.transaksi.forEach(function(t) {
      var st = stMap[t.status] || {cls:'',lb:t.status};
      var catHtml = t.catatan ? (t.catatan.match(/^Varian:/i) ? '<span style="color:var(--purple);font-weight:600;">'+escHtml(t.catatan)+'</span>' : escHtml(t.catatan)) : '—';
      trxHtml += '<tr><td style="white-space:nowrap;">' + formatTgl(t.tanggal) + '</td><td>' + escHtml(t.nama_pembeli||'—') + (t.kelas_pembeli?'<br><span style="font-size:10px;color:var(--text-sub);">'+escHtml(t.kelas_pembeli)+'</span>':'') + '</td><td style="text-align:center;">' + t.jumlah + '</td><td style="white-space:nowrap;font-weight:700;">' + fmtRp(t.total) + '</td><td>' + escHtml(t.metode) + '</td><td style="font-size:11px;max-width:120px;">' + catHtml + '</td><td><span class="status-badge ' + st.cls + '">' + st.lb + '</span></td></tr>';
    });
    trxHtml += '</tbody></table></div>';
    if (data.transaksi.length >= 10) trxHtml += '<div style="text-align:center;padding:8px;font-size:12px;color:var(--text-sub);">Menampilkan 10 transaksi terbaru</div>';
  } else {
    trxHtml = '<div style="text-align:center;padding:20px;color:var(--text-sub);font-size:13px;">Belum ada transaksi untuk menu ini.</div>';
  }

  var tglInfo = '';
  if (data.pertama_laku) {
    tglInfo = '<div class="detail-info-row"><span class="detail-info-key">📅 Pertama Laku</span><span class="detail-info-val">' + formatTgl(data.pertama_laku) + '</span></div>'
            + '<div class="detail-info-row"><span class="detail-info-key">🕒 Terakhir Laku</span><span class="detail-info-val">' + formatTgl(data.terakhir_laku) + '</span></div>';
  }

  content.innerHTML = ''
    + '<div class="detail-menu-hero">' + fotoHtml + '<div class="detail-menu-info"><div class="detail-menu-nama">' + escHtml(data.nama_makanan) + '</div><div class="detail-menu-badges"><span style="background:' + k.bg + ';color:' + k.cl + ';padding:3px 10px;border-radius:20px;font-size:12px;font-weight:700;">' + k.ic + ' ' + escHtml(kat) + '</span>' + statusHtml + varianBadge + '</div><div class="detail-menu-harga">' + fmtRp(data.harga_menu) + '</div>' + descHtml + '<div class="detail-menu-meta-row"><div class="detail-menu-meta-item">📦 Stok: <strong>' + fmtNum(data.stok) + '</strong></div><div class="detail-menu-meta-item">⏱ Proses: <strong>' + escHtml(data.waktu_proses||'—') + '</strong></div><div class="detail-menu-meta-item">⭐ ' + ratingHtml + '</div></div></div></div>'
    + '<div class="detail-stats-grid"><div class="detail-stat-card"><div class="detail-stat-icon">💰</div><div class="detail-stat-val green">' + fmtRp(data.total_omset) + '</div><div class="detail-stat-label">Total Omset</div></div><div class="detail-stat-card"><div class="detail-stat-icon">🛒</div><div class="detail-stat-val orange">' + fmtNum(data.total_terjual) + '</div><div class="detail-stat-label">Item Terjual</div></div><div class="detail-stat-card"><div class="detail-stat-icon">🧾</div><div class="detail-stat-val blue">' + fmtNum(data.total_transaksi) + '</div><div class="detail-stat-label">Total Transaksi</div></div><div class="detail-stat-card"><div class="detail-stat-icon">🏆</div><div class="detail-stat-val purple">' + fmtRp(data.transaksi_terbesar) + '</div><div class="detail-stat-label">Transaksi Terbesar</div></div></div>'
    + '<div class="detail-two-col"><div><div class="detail-section-title">📋 Status Transaksi</div><div class="detail-info-row"><span class="detail-info-key">✅ Lunas</span><span class="detail-info-val" style="color:var(--green);">' + fmtNum(data.total_terjual) + ' item</span></div><div class="detail-info-row"><span class="detail-info-key">❌ Batal</span><span class="detail-info-val" style="color:var(--red);">' + fmtNum(data.total_batal) + '</span></div><div class="detail-info-row"><span class="detail-info-key">🚫 Ditolak</span><span class="detail-info-val" style="color:#ef4444;">' + fmtNum(data.total_ditolak) + '</span></div><div class="detail-info-row"><span class="detail-info-key">💵 Rata-rata/trx</span><span class="detail-info-val">' + fmtRp(data.rata_rata_omset||0) + '</span></div>' + tglInfo + '</div><div><div class="detail-section-title">💳 Metode Pembayaran</div><div class="metode-bar-wrap"><div class="metode-bar-label"><span>💵 CASH</span><span style="font-weight:700;">' + fmtRp(data.omset_cash) + ' (' + pctCash + '%)</span></div><div class="metode-bar-track"><div class="metode-bar-fill cash" style="width:' + pctCash + '%;"></div></div></div><div class="metode-bar-wrap"><div class="metode-bar-label"><span>📲 QRIS</span><span style="font-weight:700;">' + fmtRp(data.omset_qris) + ' (' + pctQris + '%)</span></div><div class="metode-bar-track"><div class="metode-bar-fill qris" style="width:' + pctQris + '%;"></div></div></div></div></div>'
    + varianSectionHtml
    + '<div class="detail-section-title">🕒 Riwayat Transaksi (10 Terbaru)</div>' + trxHtml
    + '<div class="detail-section-title">⭐ Ulasan Pembeli</div>' + ulasanHtml;

  if (title) title.textContent = '🍽 Detail: ' + data.nama_makanan;
  bukaModal('modalDetailMenu');
}

/* ===== HELPER ===== */
function escHtml(str) {
  if (!str) return '';
  return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
function formatTgl(str) {
  if (!str) return '—';
  var d = new Date(str.replace(' ','T'));
  if (isNaN(d)) return str;
  return ('0'+d.getDate()).slice(-2)+'/'+('0'+(d.getMonth()+1)).slice(-2)+'/'+String(d.getFullYear()).slice(-2)+' '+('0'+d.getHours()).slice(-2)+':'+('0'+d.getMinutes()).slice(-2);
}

/* ===== THEME ===== */
function toggleDark() {
  var body = document.body;
  var isDark = body.classList.contains('light');
  body.classList.toggle('light', !isDark);
  body.classList.toggle('dark', isDark);
  var lbl = isDark ? '☀️ Light' : '🌙 Dark';
  var lblM = isDark ? '☀️ Light Mode' : '🌙 Dark Mode';
  var btn = document.getElementById('toggleBtn');
  var btnM = document.getElementById('toggleBtnMobile');
  if (btn) btn.textContent = lbl;
  if (btnM) btnM.textContent = lblM;
  localStorage.setItem('theme', isDark ? 'dark' : 'light');
}

window.onload = function () {
  if (localStorage.getItem('theme') === 'dark') {
    document.body.classList.replace('light','dark');
    var btn = document.getElementById('toggleBtn');
    var btnM = document.getElementById('toggleBtnMobile');
    if (btn) btn.textContent = '☀️ Light';
    if (btnM) btnM.textContent = '☀️ Light Mode';
  }
  <?php if ($pesan_error && isset($_POST['simpan'])): ?>
  bukaModalTambah();
  <?php endif; ?>

  // Trigger validasi email di modal edit jika sudah terisi (saat re-open)
  document.querySelectorAll('input[type="email"]').forEach(function(el) {
    el.addEventListener('input',  function(){ validateGmail(this); });
    el.addEventListener('blur',   function(){ validateGmail(this); });
    el.addEventListener('change', function(){ validateGmail(this); });
  });
};

/* ===== PROFILE ===== */
function toggleProfile() {
  var box = document.getElementById('profileBox');
  box.style.display = box.style.display === 'block' ? 'none' : 'block';
}
window.addEventListener('click', function(e) {
  if (!e.target.closest('.profile-btn')) {
    var box = document.getElementById('profileBox');
    if (box && box.style.display === 'block') box.style.display = 'none';
  }
});

/* ===== MOBILE NAV ===== */
function openNav()  { document.getElementById('mobileNav').classList.add('open');    document.getElementById('navOverlay').classList.add('open');    document.body.style.overflow='hidden'; }
function closeNav() { document.getElementById('mobileNav').classList.remove('open'); document.getElementById('navOverlay').classList.remove('open'); document.body.style.overflow=''; }
document.getElementById('navOverlay').addEventListener('click', closeNav);

/* ===== MODAL HELPERS ===== */
function bukaModal(id)  { document.getElementById(id).classList.add('open');    document.body.style.overflow='hidden'; }
function tutupModal(id) { document.getElementById(id).classList.remove('open'); document.body.style.overflow=''; }

function bukaModalTambah()  { bukaModal('modalTambah'); }
function tutupModalTambah() { tutupModal('modalTambah'); }

function bukaModalEdit(id, nama, email) {
  document.getElementById('edit_id').value    = id;
  document.getElementById('edit_nama').value  = nama;
  var emailEl = document.getElementById('edit_email');
  emailEl.value = email;
  // Trigger validasi realtime untuk nilai yang sudah ada
  validateGmail(emailEl);
  bukaModal('modalEdit');
}
function tutupModalEdit() { tutupModal('modalEdit'); }

function bukaModalEditNonaktif(id, nama, email, kantinAsal) {
  document.getElementById('enon_id').value    = id;
  document.getElementById('enon_nama').value  = nama;
  var emailEl = document.getElementById('enon_email');
  emailEl.value = email;
  validateGmail(emailEl);
  document.getElementById('enon_kantin').value = kantinAsal > 0 ? kantinAsal : '';
  bukaModal('modalEditNonaktif');
}

/* ===== PREVIEW QRIS ===== */
function previewQrisImg(src, info) {
  document.getElementById('qrisPreviewImgEl').src = src;
  document.getElementById('qrisPreviewInfoEl').innerHTML = '<strong>' + info + '</strong>';
  bukaModal('modalPreviewQris');
}
function previewQrisUpload(input) {
  var preview = document.getElementById('qrisUploadPreview');
  var img     = document.getElementById('qrisUploadPreviewImg');
  if (input.files && input.files[0]) {
    var reader = new FileReader();
    reader.onload = function(e){ img.src = e.target.result; preview.classList.add('show'); };
    reader.readAsDataURL(input.files[0]);
  } else { preview.classList.remove('show'); img.src = ''; }
}

/* ===== TUTUP MODAL SAAT KLIK LUAR ===== */
['modalTambah','modalEdit','modalEditNonaktif','modalVerifQris','modalKelolaQris',
 'modalPreviewQris','modalRekapSingle','modalDetailMenu','modalPasswordGate'].forEach(function(id) {
  var el = document.getElementById(id);
  if (el) el.addEventListener('click', function(e){ if (e.target === this) tutupModal(id); });
});
</script>
<script src="../custom_confirm.js"></script>
</body>
</html>