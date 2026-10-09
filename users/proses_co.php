<?php
session_start();
date_default_timezone_set('Asia/Jakarta');
header('Content-Type: application/json');
include "koneksi.php";

// ── Auth ──
if (!isset($_SESSION['id_user']) || (int)($_SESSION['role'] ?? -1) !== 2) {
    echo json_encode(['status' => 'error', 'msg' => 'Unauthorized']);
    exit;
}

$id_user = (int) $_SESSION['id_user'];

// ── Baca body JSON ──
$raw  = file_get_contents('php://input');
$body = json_decode($raw, true);

if (!$body || empty($body['items'])) {
    echo json_encode(['status' => 'error', 'msg' => 'Data tidak valid']);
    exit;
}

$items    = $body['items'];
$metode   = in_array($body['metode'] ?? '', ['CASH','QRIS']) ? $body['metode'] : 'CASH';
$catatan  = trim($body['catatan'] ?? '');
$tanggal  = date('Y-m-d H:i:s');

// ── Mulai transaksi DB ──
mysqli_begin_transaction($konek);

try {
    $inserted_ids = [];
    // Cache antrian per kantin agar tidak double-hit dalam loop
    $antrian_cache = [];

    foreach ($items as $item) {
        $id_menu      = (int)($item['id_menu']    ?? 0);
        $id_kantin    = (int)($item['id_kantin']  ?? 0);
        $id_varian    = (int)($item['id_varian']  ?? 0);
        $nama_kantin  = trim($item['nama_kantin']  ?? '');
        $nama_makanan = trim($item['nama_makanan'] ?? '');
        $nama_varian  = trim($item['nama_varian']  ?? '');
        $jumlah       = max(1, (int)($item['jumlah'] ?? 1));
        $harga        = (float)($item['harga']    ?? 0);
        $subtotal     = $harga * $jumlah;
        $id_keranjang = !empty($item['id_keranjang']) ? (int)$item['id_keranjang'] : null;

        if (!$id_menu || !$id_kantin) continue;

        // Cek & kurangi stok varian jika ada id_varian
        if ($id_varian > 0) {
            $q_vstok = mysqli_prepare($konek, "SELECT stok FROM varian_menu WHERE id=? AND id_menu=? FOR UPDATE");
            mysqli_stmt_bind_param($q_vstok, "ii", $id_varian, $id_menu);
            mysqli_stmt_execute($q_vstok);
            $vstok_row = mysqli_fetch_assoc(mysqli_stmt_get_result($q_vstok));
            if (!$vstok_row) throw new Exception("Varian tidak ditemukan: $nama_varian");
            if ($vstok_row['stok'] < $jumlah) throw new Exception("Stok varian '$nama_varian' tidak cukup. Sisa: {$vstok_row['stok']}");
        }

        // Cek stok menu_req — pakai FOR UPDATE supaya tidak race condition
        $q_stok = mysqli_prepare($konek, "SELECT stok FROM menu_req WHERE id=? AND aktif=1 FOR UPDATE");
        mysqli_stmt_bind_param($q_stok, "i", $id_menu);
        mysqli_stmt_execute($q_stok);
        $stok_row = mysqli_fetch_assoc(mysqli_stmt_get_result($q_stok));
        if (!$stok_row) throw new Exception("Menu tidak ditemukan atau tidak aktif: $nama_makanan");
        // Jika tidak ada varian, cek stok menu_req langsung
        if ($id_varian === 0 && $stok_row['stok'] < $jumlah) {
            throw new Exception("Stok $nama_makanan tidak cukup. Sisa: {$stok_row['stok']}");
        }

        // Ambil nomor antrian per kantin (increment dari cache, cuma query sekali per kantin)
        if (!isset($antrian_cache[$id_kantin])) {
            $start = date('Y-m-d') . ' 00:00:00';
            $end   = date('Y-m-d') . ' 23:59:59';
            $q_ant = mysqli_prepare($konek,
                "SELECT COALESCE(MAX(antrian), 0) AS max_antrian
                 FROM transaksi
                 WHERE id_kantin = ?
                   AND tanggal BETWEEN ? AND ?
                   AND status != 'batal'");
            mysqli_stmt_bind_param($q_ant, "iss", $id_kantin, $start, $end);
            mysqli_stmt_execute($q_ant);
            $ant_row = mysqli_fetch_assoc(mysqli_stmt_get_result($q_ant));
            $antrian_cache[$id_kantin] = (int)($ant_row['max_antrian'] ?? 0);
        }
        $antrian = ++$antrian_cache[$id_kantin];

        // Insert transaksi — sertakan nama_varian di kolom catatan atau kolom tersendiri
        // Gabungkan nama_varian ke catatan jika ada, atau simpan di field catatan
        // SESUDAH (fix):
$catatan_item = trim($item['catatan'] ?? $catatan); // ← prioritas per-item, fallback ke global
if (!empty($nama_varian)) {
    $catatan_item = "Varian: $nama_varian" . (!empty($catatan_item) ? " | $catatan_item" : '');
}

        $ins = mysqli_prepare($konek,
            "INSERT INTO transaksi
            (id_user, tanggal, total, metode, nama_makanan,
             nama_kantin, id_kantin, jumlah, id_menu,
             status, antrian, catatan)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?, ?)"
        );

        mysqli_stmt_bind_param($ins, "isdsssiiiis",
            $id_user,
            $tanggal,
            $subtotal,
            $metode,
            $nama_makanan,
            $nama_kantin,
            $id_kantin,
            $jumlah,
            $id_menu,
            $antrian,
            $catatan_item
        );
        mysqli_stmt_execute($ins);
        $id_transaksi = mysqli_insert_id($konek);
        $inserted_ids[] = $id_transaksi;

        // Kurangi stok
        if ($id_varian > 0) {
            // Kurangi stok di varian_menu
            $upd_vstok = mysqli_prepare($konek, "UPDATE varian_menu SET stok=stok-? WHERE id=?");
            mysqli_stmt_bind_param($upd_vstok, "ii", $jumlah, $id_varian);
            mysqli_stmt_execute($upd_vstok);

            // Sinkron stok menu_req = total stok semua varian aktif
            $upd_stok = mysqli_prepare($konek,
                "UPDATE menu_req SET
                    stok    = (SELECT COALESCE(SUM(stok),0) FROM varian_menu WHERE id_menu = ?),
                    terjual = terjual + ?
                 WHERE id = ?"
            );
            mysqli_stmt_bind_param($upd_stok, "iii", $id_menu, $jumlah, $id_menu);
            mysqli_stmt_execute($upd_stok);
        } else {
            // Tidak ada varian — kurangi stok menu_req langsung
            $upd_stok = mysqli_prepare($konek, "UPDATE menu_req SET stok=stok-?, terjual=terjual+? WHERE id=?");
            mysqli_stmt_bind_param($upd_stok, "iii", $jumlah, $jumlah, $id_menu);
            mysqli_stmt_execute($upd_stok);
        }

        // Hapus dari keranjang jika ada
        if ($id_keranjang) {
            $del = mysqli_prepare($konek, "DELETE FROM keranjang WHERE id=? AND id_user=?");
            mysqli_stmt_bind_param($del, "ii", $id_keranjang, $id_user);
            mysqli_stmt_execute($del);
        }
    }

    if (empty($inserted_ids)) {
        throw new Exception("Tidak ada item valid yang dapat diproses.");
    }

    mysqli_commit($konek);

    echo json_encode([
        'status'         => 'ok',
        'msg'            => 'Pesanan berhasil dibuat',
        'id_transaksi'   => $inserted_ids,
        'jumlah_pesanan' => count($inserted_ids),
    ]);

} catch (Exception $e) {
    mysqli_rollback($konek);
    echo json_encode(['status' => 'error', 'msg' => $e->getMessage()]);
}