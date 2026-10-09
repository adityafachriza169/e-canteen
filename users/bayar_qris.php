<?php
ob_start();
session_start();
date_default_timezone_set('Asia/Jakarta');
include "koneksi.php";

if (!isset($_SESSION['role']) || $_SESSION['role'] != 2) {
    header("Location: login.php"); exit;
}
$id_user = (int) $_SESSION['id_user'];

if (empty($_SESSION['pending_qris']['items'])) {
    header("Location: menu.php"); exit;
}

$pending     = $_SESSION['pending_qris'];
$all_items   = $pending['items'];
$grand_total = $pending['total'];

/* ─── Group per kantin ─── */
$kantin_groups = [];
foreach ($all_items as $it) {
    $kid = (int)$it['id_kantin'];
    if (!isset($kantin_groups[$kid])) {
        $kantin_groups[$kid] = [
            'id_kantin'   => $kid,
            'nama_kantin' => $it['nama_kantin'],
            'items'       => [],
            'total'       => 0,
            'qris_path'   => '',
        ];
    }
    $kantin_groups[$kid]['items'][] = $it;
    $kantin_groups[$kid]['total']  += (float)$it['harga'] * (int)$it['jumlah'];
}

/* ─── Ambil gambar QRIS per kantin ─── */
foreach ($kantin_groups as $kid => &$kg) {
    $q = mysqli_prepare($konek,
        "SELECT pq.gambar_qris FROM penjual_qris pq
         JOIN penjual p ON p.id_penjual = pq.id_penjual
         WHERE p.id_kantin = ? AND pq.verified = 1
         ORDER BY pq.verified_at DESC LIMIT 1");
    mysqli_stmt_bind_param($q, "i", $kid);
    mysqli_stmt_execute($q);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($q));
    $gambar = $row['gambar_qris'] ?? '';
    if (!empty($gambar)) {
        $try = "../admin/uploads/qris/" . $gambar;
        if (file_exists($try)) $kg['qris_path'] = $try;
    }
}
unset($kg);

/* ═══════════════════════════════════
   PROSES KONFIRMASI
═══════════════════════════════════ */
$pesan_error = [];

if (!empty($_POST['konfirm_kantin'])) {
    $kid_submit  = (int)$_POST['konfirm_kantin'];
    $sudah_bayar = !empty($_POST['sudah_bayar_' . $kid_submit]);

    if (!isset($kantin_groups[$kid_submit])) {
        $pesan_error[$kid_submit] = "Kantin tidak valid.";
    } elseif (isset($pending['done'][$kid_submit])) {
        // Sudah dikonfirmasi sebelumnya, skip
    } elseif (!$sudah_bayar) {
        $pesan_error[$kid_submit] = "Centang konfirmasi bahwa kamu sudah membayar.";
    } else {
        $items_kantin = array_values(array_filter($all_items,
            fn($it) => (int)$it['id_kantin'] === $kid_submit));

        $hasil = proses_insert_transaksi($konek, $id_user, $items_kantin, 'QRIS');

        if ($hasil === true) {
            $_SESSION['pending_qris']['done'][$kid_submit] = true;
            $pending = $_SESSION['pending_qris'];
        } else {
            $pesan_error[$kid_submit] = $hasil;
        }
    }
}

/* ─── Cek semua selesai ─── */
$done_kantins = $_SESSION['pending_qris']['done'] ?? [];
$semua_done   = count(array_diff(array_keys($kantin_groups), array_keys($done_kantins))) === 0;
if ($semua_done && !empty($done_kantins)) {
    unset($_SESSION['pending_qris']);
    header("Location: pesan.php?tab=pesanan&baru=1");
    exit;
}

/* ═══════════════════════════════════════════════════════
   FUNGSI: Insert transaksi + kurangi stok (tanpa bukti file)
═══════════════════════════════════════════════════════ */
function proses_insert_transaksi($konek, $id_user, $items, $metode) {
    $tanggal       = date('Y-m-d H:i:s');
    $antrian_cache = [];

    mysqli_begin_transaction($konek);
    try {
        foreach ($items as $item) {
            $id_menu      = (int)($item['id_menu']    ?? 0);
            $id_kantin    = (int)($item['id_kantin']  ?? 0);
            $id_varian    = (int)($item['id_varian']  ?? 0);
            $nama_kantin  = trim($item['nama_kantin']  ?? '');
            $nama_makanan = trim($item['nama_makanan'] ?? '');
            $nama_varian  = trim($item['nama_varian']  ?? '');
            $jumlah       = max(1, (int)($item['jumlah'] ?? 1));
            $harga        = (float)($item['harga']     ?? 0);
            $subtotal     = $harga * $jumlah;
            $id_keranjang = !empty($item['id_keranjang']) ? (int)$item['id_keranjang'] : null;
            $catatan      = trim($item['catatan'] ?? '');
            if (!empty($nama_varian)) {
                $catatan = "Varian: $nama_varian" . (!empty($catatan) ? " | $catatan" : '');
            }
            if (!$id_menu || !$id_kantin) continue;

            /* ── Cek stok varian ── */
            if ($id_varian > 0) {
                $q_vs = mysqli_prepare($konek,
                    "SELECT stok FROM varian_menu WHERE id=? AND id_menu=? FOR UPDATE");
                mysqli_stmt_bind_param($q_vs, "ii", $id_varian, $id_menu);
                mysqli_stmt_execute($q_vs);
                $vs = mysqli_fetch_assoc(mysqli_stmt_get_result($q_vs));
                if (!$vs) throw new Exception("Varian tidak ditemukan: $nama_varian");
                if ($vs['stok'] < $jumlah)
                    throw new Exception("Stok varian '$nama_varian' tidak cukup. Sisa: {$vs['stok']}");
            }

            /* ── Cek stok menu ── */
            $q_stok = mysqli_prepare($konek,
                "SELECT stok FROM menu_req WHERE id=? AND aktif=1 FOR UPDATE");
            mysqli_stmt_bind_param($q_stok, "i", $id_menu);
            mysqli_stmt_execute($q_stok);
            $stok_row = mysqli_fetch_assoc(mysqli_stmt_get_result($q_stok));
            if (!$stok_row) throw new Exception("Menu tidak ditemukan atau tidak aktif: $nama_makanan");
            if ($id_varian === 0 && $stok_row['stok'] < $jumlah)
                throw new Exception("Stok $nama_makanan tidak cukup. Sisa: {$stok_row['stok']}");

            /* ── Nomor antrian ── */
            if (!isset($antrian_cache[$id_kantin])) {
                $start = date('Y-m-d') . ' 00:00:00';
                $end   = date('Y-m-d') . ' 23:59:59';
                $q_ant = mysqli_prepare($konek,
                    "SELECT COALESCE(MAX(antrian),0) AS max_antrian
                     FROM transaksi
                     WHERE id_kantin=? AND tanggal BETWEEN ? AND ? AND status!='batal'");
                mysqli_stmt_bind_param($q_ant, "iss", $id_kantin, $start, $end);
                mysqli_stmt_execute($q_ant);
                $ant_row = mysqli_fetch_assoc(mysqli_stmt_get_result($q_ant));
                $antrian_cache[$id_kantin] = (int)($ant_row['max_antrian'] ?? 0);
            }
            $antrian = ++$antrian_cache[$id_kantin];

            /* ── FIX: Insert transaksi TANPA kolom bukti_pembayaran ── */
            $ins = mysqli_prepare($konek,
                "INSERT INTO transaksi
                 (id_user, tanggal, total, metode, nama_makanan,
                  nama_kantin, id_kantin, jumlah, id_menu,
                  status, antrian, catatan)
                 VALUES (?,?,?,?,?,?,?,?,?,'pending',?,?)");
            mysqli_stmt_bind_param($ins, "isdsssiiiis",
                $id_user, $tanggal, $subtotal, $metode,
                $nama_makanan, $nama_kantin, $id_kantin, $jumlah, $id_menu,
                $antrian, $catatan);
            mysqli_stmt_execute($ins);

            /* ── Kurangi stok ── */
            if ($id_varian > 0) {
                $u1 = mysqli_prepare($konek,
                    "UPDATE varian_menu SET stok=stok-? WHERE id=?");
                mysqli_stmt_bind_param($u1, "ii", $jumlah, $id_varian);
                mysqli_stmt_execute($u1);

                $u2 = mysqli_prepare($konek,
                    "UPDATE menu_req
                     SET stok=(SELECT COALESCE(SUM(stok),0) FROM varian_menu WHERE id_menu=?),
                         terjual=terjual+?
                     WHERE id=?");
                mysqli_stmt_bind_param($u2, "iii", $id_menu, $jumlah, $id_menu);
                mysqli_stmt_execute($u2);
            } else {
                $u = mysqli_prepare($konek,
                    "UPDATE menu_req SET stok=stok-?, terjual=terjual+? WHERE id=?");
                mysqli_stmt_bind_param($u, "iii", $jumlah, $jumlah, $id_menu);
                mysqli_stmt_execute($u);
            }

            /* ── Hapus dari keranjang ── */
            if ($id_keranjang) {
                $del = mysqli_prepare($konek,
                    "DELETE FROM keranjang WHERE id=? AND id_user=?");
                mysqli_stmt_bind_param($del, "ii", $id_keranjang, $id_user);
                mysqli_stmt_execute($del);
            }
        }

        mysqli_commit($konek);
        return true;

    } catch (Exception $e) {
        mysqli_rollback($konek);
        return $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Bayar QRIS</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<style>
*, *::before, *::after { box-sizing: border-box; }
body { font-family: Arial, sans-serif; background: #f5f5f5; margin: 0; padding: 20px 16px 40px; }
.container { max-width: 520px; margin: auto; }
.page-header { text-align: center; margin-bottom: 20px; }
.page-header h2 { margin: 0 0 4px; font-size: 22px; color: #222; }
.page-header p  { margin: 0; font-size: 13px; color: #888; }
.progress-wrap { display: flex; gap: 6px; margin-bottom: 18px; }
.progress-step { flex: 1; height: 6px; border-radius: 4px; background: #e0e0e0; transition: background .3s; }
.progress-step.done    { background: #27ae60; }
.progress-step.current { background: #e8650a; }
.card { background: #fff; border-radius: 16px; padding: 20px; margin-bottom: 18px; box-shadow: 0 2px 12px rgba(0,0,0,.07); position: relative; }
.badge-done { position: absolute; top: 16px; right: 16px; background: #27ae60; color: #fff; font-size: 11px; font-weight: 700; padding: 3px 10px; border-radius: 20px; display: flex; align-items: center; gap: 4px; }
.toko-header { display: flex; align-items: center; gap: 8px; font-size: 15px; font-weight: 700; color: #e8650a; margin-bottom: 14px; padding-bottom: 12px; border-bottom: 2px dashed #f0e0d0; }
.qris { text-align: center; margin-bottom: 12px; }
.qris img { width: 220px; max-width: 100%; border-radius: 14px; border: 3px solid #e8650a; }
.qris-unavail { padding: 24px 0; color: #bbb; font-size: 13px; }
.qris-unavail i { font-size: 34px; display: block; margin-bottom: 8px; }
.toko-total { text-align: center; font-size: 22px; font-weight: 800; color: #e8650a; margin: 4px 0 14px; }
.item { padding: 9px 0; border-bottom: 1px solid #f0f0f0; font-size: 13px; color: #444; }
.item:last-child { border-bottom: none; }
.item-name { font-weight: 700; color: #222; margin-bottom: 3px; }
.item-meta { display: flex; justify-content: space-between; }
.confirm-section { margin-top: 16px; padding-top: 14px; border-top: 1px solid #f0f0f0; }
.confirm-label { font-size: 13px; font-weight: 700; color: #444; margin-bottom: 10px; display: flex; align-items: center; gap: 6px; }

/* Checkbox area */
.checkbox-wrap {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    background: #fff8f0;
    border: 1.5px solid #f0c080;
    border-radius: 12px;
    padding: 14px 16px;
    cursor: pointer;
    transition: background .15s, border-color .15s;
}
.checkbox-wrap:hover { background: #ffefe0; border-color: #e8650a; }
.checkbox-wrap.checked { background: #f0fff4; border-color: #27ae60; }
.checkbox-wrap input[type=checkbox] {
    width: 20px; height: 20px;
    accent-color: #e8650a;
    flex-shrink: 0;
    margin-top: 1px;
    cursor: pointer;
}
.checkbox-wrap input[type=checkbox]:checked { accent-color: #27ae60; }
.checkbox-label {
    font-size: 13px;
    font-weight: 700;
    color: #333;
    line-height: 1.5;
    cursor: pointer;
    user-select: none;
}
.checkbox-label small {
    display: block;
    font-weight: 400;
    color: #888;
    margin-top: 2px;
    font-size: 11px;
}

/* Button konfirmasi */
.btn-konfirmasi {
    width: 100%;
    margin-top: 12px;
    padding: 12px;
    border: none;
    border-radius: 10px;
    background: #ccc;
    color: #fff;
    font-size: 14px;
    font-weight: 700;
    cursor: not-allowed;
    transition: background .15s, opacity .15s;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    opacity: .7;
}
.btn-konfirmasi:not(:disabled):hover { background: #c9550a; }
.btn-konfirmasi.done { background: #27ae60; cursor: default; opacity: 1; }

.error-msg { background: #ffdddd; color: #a10000; padding: 10px 14px; border-radius: 10px; font-size: 12px; margin-top: 10px; display: flex; align-items: center; gap: 6px; }
.grand-total-card { background: #fff; border-radius: 16px; padding: 18px 20px; margin-bottom: 18px; box-shadow: 0 2px 12px rgba(0,0,0,.07); display: flex; justify-content: space-between; align-items: center; }
.grand-total-card .label { font-size: 15px; font-weight: 700; color: #444; }
.grand-total-card .amount { font-size: 22px; font-weight: 800; color: #e8650a; }
.info-box { background: #fff8f0; border: 1.5px solid #f0c080; border-radius: 12px; padding: 12px 16px; font-size: 13px; color: #7a4800; margin-bottom: 18px; display: flex; align-items: flex-start; gap: 8px; line-height: 1.5; }
.info-box i { margin-top: 2px; color: #e8650a; flex-shrink: 0; }
#loading { display: none; position: fixed; inset: 0; background: rgba(0,0,0,.52); z-index: 999; justify-content: center; align-items: center; flex-direction: column; gap: 12px; color: #fff; font-size: 16px; font-weight: 600; }
.spinner { width: 42px; height: 42px; border: 4px solid rgba(255,255,255,.3); border-top-color: #fff; border-radius: 50%; animation: spin .7s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }
</style>
</head>
<body>

<div id="loading">
    <div class="spinner"></div>
    Memproses pesanan...
</div>

<div class="container">

    <div class="page-header">
        <h2><i class="fa-solid fa-qrcode" style="color:#e8650a;"></i> Bayar QRIS</h2>
        <p>
            <?php if (count($kantin_groups) > 1): ?>
                Konfirmasi pembayaran untuk setiap toko
            <?php else: ?>
                Scan QRIS &amp; konfirmasi pembayaran
            <?php endif; ?>
        </p>
    </div>

    <div class="info-box">
        <i class="fa-solid fa-circle-info"></i>
        <span>Pesanan <b>belum diproses</b> dan stok <b>belum berkurang</b> sampai kamu konfirmasi sudah membayar.</span>
    </div>

    <?php if (count($kantin_groups) > 1): ?>
    <div class="progress-wrap">
        <?php foreach ($kantin_groups as $kid => $kg):
            $isDone = isset($done_kantins[$kid]);
        ?>
        <div class="progress-step <?= $isDone ? 'done' : '' ?>" id="step-<?= $kid ?>"></div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php $toko_no = 0; foreach ($kantin_groups as $kid => $kg): $toko_no++;
        $isDone = isset($done_kantins[$kid]);
    ?>

    <div class="card" id="card-<?= $kid ?>">

        <?php if ($isDone): ?>
        <div class="badge-done">
            <i class="fa-solid fa-circle-check"></i> Sudah Dikonfirmasi
        </div>
        <?php endif; ?>

        <div class="toko-header">
            <i class="fa-solid fa-store"></i>
            <?= htmlspecialchars($kg['nama_kantin']) ?>
            <?php if (count($kantin_groups) > 1): ?>
            <span style="margin-left:auto;font-size:11px;color:#bbb;font-weight:500;">
                <?= $toko_no ?>/<?= count($kantin_groups) ?>
            </span>
            <?php endif; ?>
        </div>

        <div class="qris">
            <?php if (!empty($kg['qris_path'])): ?>
            <img src="<?= htmlspecialchars($kg['qris_path']) ?>"
                 alt="QRIS <?= htmlspecialchars($kg['nama_kantin']) ?>">
            <?php else: ?>
            <div class="qris-unavail">
                <i class="fa-solid fa-image-slash"></i>
                QRIS tidak tersedia
            </div>
            <?php endif; ?>
        </div>

        <div class="toko-total">
            Rp<?= number_format($kg['total'], 0, ',', '.') ?>
        </div>

        <?php foreach ($kg['items'] as $t): ?>
        <div class="item">
            <div class="item-name">
                <?= htmlspecialchars($t['nama_makanan']) ?>
                <?php if (!empty($t['nama_varian'])): ?>
                <span style="font-size:11px;color:#e8650a;font-weight:600;">
                    — <?= htmlspecialchars($t['nama_varian']) ?>
                </span>
                <?php endif; ?>
            </div>
            <div class="item-meta">
                <span>× <?= (int)$t['jumlah'] ?></span>
                <span><b>Rp<?= number_format((float)$t['harga'] * (int)$t['jumlah'], 0, ',', '.') ?></b></span>
            </div>
        </div>
        <?php endforeach; ?>

        <!-- Konfirmasi per toko -->
        <div class="confirm-section">

            <?php if (isset($pesan_error[$kid])): ?>
            <div class="error-msg">
                <i class="fa-solid fa-circle-exclamation"></i>
                <?= htmlspecialchars($pesan_error[$kid]) ?>
            </div>
            <?php endif; ?>

            <form method="POST" id="form-<?= $kid ?>"
                  onsubmit="submitKonfirmasi(event, <?= $kid ?>)">

                <input type="hidden" name="konfirm_kantin" value="<?= $kid ?>">

                <?php if ($isDone): ?>
                <div class="checkbox-wrap checked" style="cursor:default;">
                    <input type="checkbox" checked disabled style="accent-color:#27ae60;">
                    <div class="checkbox-label">
                        Pembayaran sudah dikonfirmasi
                        <small>Pesanan sedang diproses oleh kantin</small>
                    </div>
                </div>
                <button type="button" class="btn-konfirmasi done" disabled>
                    <i class="fa-solid fa-circle-check"></i>
                    Pesanan Dibuat!
                </button>

                <?php else: ?>
                <label class="checkbox-wrap" id="wrap-<?= $kid ?>" for="cbk-<?= $kid ?>">
                    <input type="checkbox"
                           name="sudah_bayar_<?= $kid ?>"
                           id="cbk-<?= $kid ?>"
                           onchange="toggleCheck(<?= $kid ?>, this.checked)">
                    <div class="checkbox-label">
                        Saya sudah melakukan pembayaran QRIS
                        <small>Pastikan transfer sudah berhasil sebelum konfirmasi</small>
                    </div>
                </label>

                <button type="submit"
                        class="btn-konfirmasi"
                        id="btn-<?= $kid ?>"
                        disabled>
                    <i class="fa-solid fa-check"></i>
                    Konfirmasi Sudah Bayar
                </button>
                <?php endif; ?>

            </form>
        </div>

    </div>

    <?php endforeach; ?>

    <?php if (count($kantin_groups) > 1): ?>
    <div class="grand-total-card">
        <span class="label">
            <i class="fa-solid fa-calculator" style="color:#e8650a;margin-right:6px;"></i>
            Total Semua Toko
        </span>
        <span class="amount">Rp<?= number_format($grand_total, 0, ',', '.') ?></span>
    </div>
    <?php endif; ?>

</div>

<script>
function toggleCheck(kid, checked) {
    const wrap = document.getElementById('wrap-' + kid);
    const btn  = document.getElementById('btn-' + kid);
    wrap.classList.toggle('checked', checked);
    btn.disabled = !checked;
    if (checked) {
        btn.style.background = '#e8650a';
        btn.style.cursor = 'pointer';
        btn.style.opacity = '1';
    } else {
        btn.style.background = '#ccc';
        btn.style.cursor = 'not-allowed';
        btn.style.opacity = '.7';
    }
}

function submitKonfirmasi(e, kid) {
    const cbk = document.getElementById('cbk-' + kid);
    if (!cbk || !cbk.checked) {
        e.preventDefault();
        alert('Centang konfirmasi bahwa kamu sudah membayar.');
        return;
    }
    document.getElementById('loading').style.display = 'flex';
}

(function() {
    let foundCurrent = false;
    document.querySelectorAll('.progress-step').forEach(function(s) {
        if (!s.classList.contains('done') && !foundCurrent) {
            s.classList.add('current');
            foundCurrent = true;
        }
    });
})();
</script>

</body>
</html>