<?php
ob_start();
session_start();
include "koneksi.php";

if (!isset($_SESSION['role']) || $_SESSION['role'] != 2) {
    header("Location: login.php");
    exit;
}

$id_user = (int) $_SESSION['id_user'];

/* =========================
   AMBIL ID TRANSAKSI
========================= */
$raw_ids = $_GET['ids'] ?? '';
$id_list = array_filter(array_map('intval', explode(',', $raw_ids)));

if (empty($id_list)) {
    header("Location: pesan.php?tab=pesanan");
    exit;
}

$placeholders = implode(',', array_fill(0, count($id_list), '?'));
$types = str_repeat('i', count($id_list)) . 'i';

/* =========================
   AMBIL DATA TRANSAKSI
========================= */
$q = mysqli_prepare(
    $konek,
    "SELECT *
     FROM transaksi
     WHERE id_transaksi IN ($placeholders)
     AND id_user = ?
     ORDER BY id_kantin ASC, id_transaksi ASC"
);
$params = array_merge($id_list, [$id_user]);
mysqli_stmt_bind_param($q, $types, ...$params);
mysqli_stmt_execute($q);
$result = mysqli_stmt_get_result($q);

$transaksi = [];
while ($r = mysqli_fetch_assoc($result)) {
    $transaksi[] = $r;
}

if (empty($transaksi)) {
    header("Location: pesan.php?tab=pesanan");
    exit;
}

/* =========================
   VALIDASI METODE QRIS
========================= */
foreach ($transaksi as $t) {
    if (strtoupper($t['metode']) !== 'QRIS') {
        header("Location: pesan.php?tab=pesanan");
        exit;
    }
}

/* =========================
   GROUP PER KANTIN
========================= */
$kantin_groups = [];
foreach ($transaksi as $t) {
    $kid = (int)$t['id_kantin'];
    if (!isset($kantin_groups[$kid])) {
        $kantin_groups[$kid] = [
            'id_kantin'   => $kid,
            'nama_kantin' => $t['nama_kantin'],
            'items'       => [],
            'total'       => 0,
            'qris_path'   => '',
            // id transaksi milik kantin ini (untuk field hidden di form)
            'ids'         => [],
        ];
    }
    $kantin_groups[$kid]['items'][] = $t;
    $kantin_groups[$kid]['total']  += (float)$t['total'];
    $kantin_groups[$kid]['ids'][]   = (int)$t['id_transaksi'];
}

/* =========================
   AMBIL QRIS PER KANTIN
========================= */
foreach ($kantin_groups as $kid => &$kg) {
    $q_qris = mysqli_prepare(
        $konek,
        "SELECT pq.gambar_qris
         FROM penjual_qris pq
         JOIN penjual p ON p.id_penjual = pq.id_penjual
         WHERE p.id_kantin = ?
         AND pq.verified = 1
         ORDER BY pq.verified_at DESC
         LIMIT 1"
    );
    mysqli_stmt_bind_param($q_qris, "i", $kid);
    mysqli_stmt_execute($q_qris);
    $row_qris = mysqli_fetch_assoc(mysqli_stmt_get_result($q_qris));

    $gambar_qris = $row_qris['gambar_qris'] ?? '';
    if (!empty($gambar_qris)) {
        $try = "../admin/uploads/qris/" . $gambar_qris;
        if (file_exists($try)) {
            $kg['qris_path'] = $try;
        }
    }
}
unset($kg);

/* =========================
   GRAND TOTAL
========================= */
$grand_total = array_sum(array_column($kantin_groups, 'total'));

/* =========================
   PROSES UPLOAD BUKTI PER TOKO
   POST: upload_kantin_{id_kantin}
========================= */
$pesan_error  = [];
$pesan_sukses = [];

// Cek apakah semua toko sudah diupload (tombol "Selesai Semua")
$semua_selesai = isset($_POST['selesai_semua']);

if ($semua_selesai || !empty($_POST['upload_kantin'])) {

    $folder = "../gambar/bukti/";
    if (!is_dir($folder)) mkdir($folder, 0755, true);

    $ext_ok = ['jpg', 'jpeg', 'png', 'webp'];

    // id_kantin mana yang di-submit upload-nya sekarang
    $kantin_disubmit = isset($_POST['upload_kantin']) ? (int)$_POST['upload_kantin'] : null;

    foreach ($kantin_groups as $kid => &$kg) {

        // Kalau bukan submit untuk kantin ini, skip
        if (!$semua_selesai && $kantin_disubmit !== $kid) continue;

        $file_key = 'bukti_' . $kid;

        if (!isset($_FILES[$file_key]) || $_FILES[$file_key]['error'] != 0) {
            $pesan_error[$kid] = "Upload bukti pembayaran untuk " . htmlspecialchars($kg['nama_kantin']) . " terlebih dahulu.";
            continue;
        }

        $ext = strtolower(pathinfo($_FILES[$file_key]['name'], PATHINFO_EXTENSION));

        if (!in_array($ext, $ext_ok)) {
            $pesan_error[$kid] = "Format file harus JPG, PNG, atau WEBP.";
            continue;
        }

        if ($_FILES[$file_key]['size'] > 5 * 1024 * 1024) {
            $pesan_error[$kid] = "Ukuran maksimal 5 MB.";
            continue;
        }

        $nama_file = "bukti_" . $id_user . "_k" . $kid . "_" . time() . "_" . rand(100,999) . "." . $ext;

        if (!move_uploaded_file($_FILES[$file_key]['tmp_name'], $folder . $nama_file)) {
            $pesan_error[$kid] = "Gagal upload file, coba lagi.";
            continue;
        }

        // Update semua transaksi milik kantin ini
        foreach ($kg['ids'] as $id_trx) {
            $upd = mysqli_prepare(
                $konek,
                "UPDATE transaksi
                 SET bukti_pembayaran = ?,
                     status = 'pending',
                     is_read = 0
                 WHERE id_transaksi = ? AND id_user = ?"
            );
            mysqli_stmt_bind_param($upd, "sii", $nama_file, $id_trx, $id_user);
            mysqli_stmt_execute($upd);
        }

        $pesan_sukses[$kid] = $nama_file;
    }
    unset($kg);
}

/* =========================
   CEK STATUS UPLOAD DARI DB
   (untuk tahu toko mana yang sudah upload sebelumnya)
========================= */
// Re-fetch transaksi supaya status terbaru
$q2 = mysqli_prepare(
    $konek,
    "SELECT id_transaksi, id_kantin, bukti_pembayaran, status
     FROM transaksi
     WHERE id_transaksi IN ($placeholders) AND id_user = ?"
);
mysqli_stmt_bind_param($q2, $types, ...$params);
mysqli_stmt_execute($q2);
$res2 = mysqli_stmt_get_result($q2);

$sudah_upload = []; // id_kantin => nama_file bukti
while ($row2 = mysqli_fetch_assoc($res2)) {
    $kid2 = (int)$row2['id_kantin'];
    if (!empty($row2['bukti_pembayaran'])) {
        $sudah_upload[$kid2] = $row2['bukti_pembayaran'];
    }
}

// Jika semua kantin sudah upload → redirect
$semua_kantin_ids = array_keys($kantin_groups);
$sudah_semua = count(array_intersect($semua_kantin_ids, array_keys($sudah_upload))) === count($semua_kantin_ids);
if ($sudah_semua && empty($pesan_error)) {
    header("Location: pesan.php?tab=pesanan&baru=1");
    exit;
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

body {
    font-family: Arial, sans-serif;
    background: #f5f5f5;
    margin: 0;
    padding: 20px 16px 40px;
}

.container { max-width: 520px; margin: auto; }

.page-header { text-align: center; margin-bottom: 20px; }
.page-header h2 { margin: 0 0 4px; font-size: 22px; color: #222; }
.page-header p  { margin: 0; font-size: 13px; color: #888; }

/* Progress bar toko */
.progress-wrap {
    display: flex;
    gap: 6px;
    margin-bottom: 18px;
}
.progress-step {
    flex: 1;
    height: 6px;
    border-radius: 4px;
    background: #e0e0e0;
    transition: background .3s;
}
.progress-step.done    { background: #27ae60; }
.progress-step.current { background: #e8650a; }

/* Card */
.card {
    background: #fff;
    border-radius: 16px;
    padding: 20px;
    margin-bottom: 18px;
    box-shadow: 0 2px 12px rgba(0,0,0,.07);
    position: relative;
}

/* Badge "Sudah Upload" */
.badge-done {
    position: absolute;
    top: 16px; right: 16px;
    background: #27ae60;
    color: #fff;
    font-size: 11px;
    font-weight: 700;
    padding: 3px 10px;
    border-radius: 20px;
    display: flex;
    align-items: center;
    gap: 4px;
}

/* Toko header */
.toko-header {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 15px;
    font-weight: 700;
    color: #e8650a;
    margin-bottom: 14px;
    padding-bottom: 12px;
    border-bottom: 2px dashed #f0e0d0;
}

/* QRIS */
.qris { text-align: center; margin-bottom: 12px; }
.qris img {
    width: 220px;
    max-width: 100%;
    border-radius: 14px;
    border: 3px solid #e8650a;
}
.qris-unavail {
    padding: 24px 0;
    color: #bbb;
    font-size: 13px;
}
.qris-unavail i { font-size: 34px; display: block; margin-bottom: 8px; }

.toko-total {
    text-align: center;
    font-size: 22px;
    font-weight: 800;
    color: #e8650a;
    margin: 4px 0 14px;
}

/* Item list */
.item {
    padding: 9px 0;
    border-bottom: 1px solid #f0f0f0;
    font-size: 13px;
    color: #444;
}
.item:last-child { border-bottom: none; }
.item-name { font-weight: 700; color: #222; margin-bottom: 3px; }
.item-meta { display: flex; justify-content: space-between; }

/* Upload section */
.upload-section { margin-top: 16px; padding-top: 14px; border-top: 1px solid #f0f0f0; }
.upload-label {
    font-size: 13px;
    font-weight: 700;
    color: #444;
    margin-bottom: 10px;
    display: flex;
    align-items: center;
    gap: 6px;
}

.upload-box {
    border: 2px dashed #e8650a;
    border-radius: 12px;
    padding: 22px 16px;
    text-align: center;
    cursor: pointer;
    background: #fff7f1;
    position: relative;
    transition: background .15s;
}
.upload-box:hover  { background: #ffefe0; }
.upload-box.done   { border-color: #27ae60; background: #f0fff4; cursor: default; }
.upload-box input  {
    position: absolute; inset: 0;
    opacity: 0; width: 100%; cursor: pointer;
}
.upload-box.done input { pointer-events: none; }
.upload-box i      { font-size: 32px; color: #e8650a; }
.upload-box.done i { color: #27ae60; }
.upload-box p      { margin: 8px 0 4px; font-size: 13px; font-weight: 600; color: #555; }
.upload-box small  { color: #aaa; font-size: 11px; }

.preview-img {
    width: 100%;
    border-radius: 10px;
    margin-top: 12px;
    display: none;
}

.error-msg {
    background: #ffdddd;
    color: #a10000;
    padding: 10px 14px;
    border-radius: 10px;
    font-size: 12px;
    margin-top: 10px;
}

/* Per-toko submit button */
.btn-upload {
    width: 100%;
    margin-top: 12px;
    padding: 12px;
    border: none;
    border-radius: 10px;
    background: #e8650a;
    color: #fff;
    font-size: 14px;
    font-weight: 700;
    cursor: pointer;
    transition: background .15s, opacity .15s;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
}
.btn-upload:hover:not(:disabled) { background: #c9550a; }
.btn-upload:disabled { background: #ccc; cursor: not-allowed; opacity: .7; }
.btn-upload.done { background: #27ae60; cursor: default; }

/* Grand total */
.grand-total-card {
    background: #fff;
    border-radius: 16px;
    padding: 18px 20px;
    margin-bottom: 18px;
    box-shadow: 0 2px 12px rgba(0,0,0,.07);
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.grand-total-card .label { font-size: 15px; font-weight: 700; color: #444; }
.grand-total-card .amount { font-size: 22px; font-weight: 800; color: #e8650a; }

/* Loading overlay */
#loading {
    display: none;
    position: fixed; inset: 0;
    background: rgba(0,0,0,.52);
    z-index: 999;
    justify-content: center; align-items: center;
    flex-direction: column; gap: 12px;
    color: #fff; font-size: 16px; font-weight: 600;
}
.spinner {
    width: 42px; height: 42px;
    border: 4px solid rgba(255,255,255,.3);
    border-top-color: #fff;
    border-radius: 50%;
    animation: spin .7s linear infinite;
}
@keyframes spin { to { transform: rotate(360deg); } }
</style>
</head>
<body>

<div id="loading">
    <div class="spinner"></div>
    Mengupload...
</div>

<div class="container">

    <!-- Header -->
    <div class="page-header">
        <h2><i class="fa-solid fa-qrcode" style="color:#e8650a;"></i> Bayar QRIS</h2>
        <p>
            <?php if (count($kantin_groups) > 1): ?>
                Upload bukti pembayaran untuk setiap toko
            <?php else: ?>
                Scan QRIS &amp; upload bukti pembayaran
            <?php endif; ?>
        </p>
    </div>

    <!-- Progress steps (jika lebih dari 1 toko) -->
    <?php if (count($kantin_groups) > 1): ?>
    <div class="progress-wrap">
        <?php $step = 0; foreach ($kantin_groups as $kid => $kg): $step++;
            $isDone = isset($sudah_upload[$kid]);
            $cls = $isDone ? 'done' : ($step === 1 && !$isDone ? 'current' : '');
        ?>
        <div class="progress-step <?= $cls ?>" id="step-<?= $kid ?>"></div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- ═══ CARD PER TOKO ═══ -->
    <?php $toko_no = 0; foreach ($kantin_groups as $kid => $kg): $toko_no++;
        $isDone = isset($sudah_upload[$kid]);
    ?>

    <div class="card" id="card-<?= $kid ?>">

        <!-- Badge sudah upload -->
        <?php if ($isDone): ?>
        <div class="badge-done">
            <i class="fa-solid fa-circle-check"></i> Sudah Upload
        </div>
        <?php endif; ?>

        <!-- Nama toko -->
        <div class="toko-header">
            <i class="fa-solid fa-store"></i>
            <?= htmlspecialchars($kg['nama_kantin']) ?>
            <?php if (count($kantin_groups) > 1): ?>
            <span style="margin-left:auto;font-size:11px;color:#bbb;font-weight:500;">
                <?= $toko_no ?>/<?= count($kantin_groups) ?>
            </span>
            <?php endif; ?>
        </div>

        <!-- QRIS -->
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

        <!-- Total toko ini -->
        <div class="toko-total">
            Rp<?= number_format($kg['total'], 0, ',', '.') ?>
        </div>

        <!-- Rincian item -->
        <?php foreach ($kg['items'] as $t): ?>
        <div class="item">
            <div class="item-name"><?= htmlspecialchars($t['nama_makanan']) ?></div>
            <div class="item-meta">
                <span>× <?= (int)$t['jumlah'] ?></span>
                <span><b>Rp<?= number_format($t['total'], 0, ',', '.') ?></b></span>
            </div>
        </div>
        <?php endforeach; ?>

        <!-- Upload per toko — form sendiri -->
        <div class="upload-section">

            <div class="upload-label">
                <i class="fa-solid fa-file-image" style="color:#e8650a;"></i>
                Bukti Pembayaran
            </div>

            <?php if (isset($pesan_error[$kid])): ?>
            <div class="error-msg">
                <i class="fa-solid fa-circle-exclamation"></i>
                <?= htmlspecialchars($pesan_error[$kid]) ?>
            </div>
            <?php endif; ?>

            <form method="POST"
                  enctype="multipart/form-data"
                  id="form-<?= $kid ?>"
                  onsubmit="submitUpload(event, <?= $kid ?>)">

                <input type="hidden" name="upload_kantin" value="<?= $kid ?>">

                <?php if ($isDone): ?>
                <!-- Sudah diupload, tampilkan preview dari server jika ada -->
                <div class="upload-box done">
                    <i class="fa-solid fa-circle-check"></i>
                    <p>Bukti berhasil diupload</p>
                </div>

                <?php else: ?>
                <div class="upload-box" id="box-<?= $kid ?>">
                    <input type="file"
                           name="bukti_<?= $kid ?>"
                           id="file-<?= $kid ?>"
                           accept=".jpg,.jpeg,.png,.webp"
                           onchange="previewBukti(<?= $kid ?>, this)">
                    <i class="fa-solid fa-cloud-arrow-up" id="icon-<?= $kid ?>"></i>
                    <p id="boxtext-<?= $kid ?>">Klik untuk pilih foto bukti</p>
                    <small>JPG, PNG, WEBP — maks. 5 MB</small>
                </div>

                <img class="preview-img" id="preview-<?= $kid ?>" alt="Preview">

                <button type="submit"
                        class="btn-upload"
                        id="btn-<?= $kid ?>"
                        disabled>
                    <i class="fa-solid fa-cloud-arrow-up"></i>
                    Upload Bukti
                </button>
                <?php endif; ?>

            </form>
        </div>

    </div><!-- end card -->

    <?php endforeach; ?>

    <!-- Grand total jika >1 toko -->
    <?php if (count($kantin_groups) > 1): ?>
    <div class="grand-total-card">
        <span class="label">
            <i class="fa-solid fa-calculator" style="color:#e8650a;margin-right:6px;"></i>
            Total Semua Toko
        </span>
        <span class="amount">Rp<?= number_format($grand_total, 0, ',', '.') ?></span>
    </div>
    <?php endif; ?>

</div><!-- end .container -->

<script>
function previewBukti(kid, input) {
    if (!input.files[0]) return;
    const file = input.files[0];
    if (file.size > 5 * 1024 * 1024) {
        alert('Ukuran file maksimal 5 MB.');
        input.value = '';
        return;
    }
    const reader = new FileReader();
    reader.onload = function(e) {
        const prev = document.getElementById('preview-' + kid);
        prev.src   = e.target.result;
        prev.style.display = 'block';

        // Update tampilan upload box
        const box  = document.getElementById('box-' + kid);
        const icon = document.getElementById('icon-' + kid);
        const txt  = document.getElementById('boxtext-' + kid);
        if (box)  box.style.background  = '#fff7f1';
        if (icon) { icon.className = 'fa-solid fa-image'; icon.style.color = '#e8650a'; }
        if (txt)  txt.textContent = file.name;

        document.getElementById('btn-' + kid).disabled = false;
    };
    reader.readAsDataURL(file);
}

function submitUpload(e, kid) {
    const btn = document.getElementById('btn-' + kid);
    if (btn && btn.disabled) {
        e.preventDefault();
        alert('Pilih file bukti terlebih dahulu.');
        return;
    }
    document.getElementById('loading').style.display = 'flex';
}

// Update progress bar warna setelah page load
(function() {
    const steps = document.querySelectorAll('.progress-step');
    let foundCurrent = false;
    steps.forEach(function(s) {
        if (!s.classList.contains('done') && !foundCurrent) {
            s.classList.add('current');
            foundCurrent = true;
        }
    });
})();
</script>

</body>
</html>