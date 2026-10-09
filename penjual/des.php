<?php
session_start();
require_once 'koneksi.php';

// ── AMBIL ID_KANTIN DARI SESSION (bukan dari POST) ───────
if (!isset($_SESSION['username']) || (int)($_SESSION['role'] ?? 0) !== 1) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'pesan' => 'Sesi tidak valid, silakan login ulang']);
        exit;
    }
    header('Location: ../users/login.php');
    exit;
}

$_ses_username = $_SESSION['username'];
$_stmt = mysqli_prepare($db, "SELECT p.id_kantin, p.id_penjual FROM penjual p WHERE p.username = ? LIMIT 1");
mysqli_stmt_bind_param($_stmt, "s", $_ses_username);
mysqli_stmt_execute($_stmt);
$_penjual_row = mysqli_fetch_assoc(mysqli_stmt_get_result($_stmt));
mysqli_stmt_close($_stmt);

// id_kantin & id_penjual selalu dari session, TIDAK bisa dimanipulasi lewat POST
$id_kantin_session  = (int) ($_penjual_row['id_kantin']  ?? 0);
$id_penjual_session = (int) ($_penjual_row['id_penjual'] ?? 0);

if (!$id_kantin_session) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'pesan' => 'Data penjual tidak ditemukan']);
        exit;
    }
    header('Location: ../users/login.php');
    exit;
}

// ── PROSES SUBMIT FORM (via fetch, return JSON) ──────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    header('Content-Type: application/json');

    $aksi      = $_POST['aksi'] ?? 'tambah';
    $nama      = mysqli_real_escape_string($db, trim($_POST['nama_makanan'] ?? ''));
    $harga     = (int) ($_POST['harga'] ?? 0);
    $stok      = (int) ($_POST['stok']  ?? 0);
    $kategori  = mysqli_real_escape_string($db, $_POST['kategori'] ?? '');
    $deskripsi = mysqli_real_escape_string($db, trim($_POST['deskripsi'] ?? ''));
    // PERBAIKAN: id_kantin wajib dari session, bukan POST
    $id_kantin = $id_kantin_session;

    // Cek ada varian awal untuk validasi
    $ada_varian_check = !empty($_POST['varian_nama']) && count(array_filter(
        is_array($_POST['varian_nama']) ? $_POST['varian_nama'] : [$_POST['varian_nama']],
        fn($v) => trim($v) !== ''
    )) > 0;

    if (!$nama || !$harga || (!$ada_varian_check && !$stok) || !$kategori) {
        echo json_encode([
            'status' => 'error',
            'pesan'  => 'Field wajib belum lengkap',
        ]);
        exit;
    }

    // ── Upload Foto Utama ──────────────────────────────────
    $nama_foto = $_POST['foto_lama'] ?? '';

    if (!empty($_FILES['foto']['name']) && $_FILES['foto']['size'] > 0) {
        $ext_ok   = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
        $ekstensi = strtolower(pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION));

        if (!in_array($ekstensi, $ext_ok)) {
            echo json_encode(['status' => 'error', 'pesan' => 'Format foto tidak didukung']);
            exit;
        }

        if (!is_dir('uploads')) mkdir('uploads', 0755, true);

        $nama_foto = time() . '_' . bin2hex(random_bytes(4)) . '.' . $ekstensi;
        $tujuan    = 'uploads/' . $nama_foto;

        if (!move_uploaded_file($_FILES['foto']['tmp_name'], $tujuan)) {
            echo json_encode(['status' => 'error', 'pesan' => 'Gagal upload foto']);
            exit;
        }

        if ($aksi === 'edit' && !empty($_POST['foto_lama'])) {
            $lama = 'uploads/' . $_POST['foto_lama'];
            if (file_exists($lama)) unlink($lama);
        }
    }

    // ── Helper: pastikan tabel stok_log ada ───────────────
    function pastikanTabelStokLog($db) {
        mysqli_query($db, "CREATE TABLE IF NOT EXISTS `stok_log` (
            `id`         INT          NOT NULL AUTO_INCREMENT PRIMARY KEY,
            `id_menu`    INT          NOT NULL,
            `jumlah`     INT          NOT NULL,
            `tipe`       VARCHAR(20)  NOT NULL DEFAULT 'tambah',
            `keterangan` VARCHAR(255) DEFAULT NULL,
            `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX `idx_id_menu` (`id_menu`),
            INDEX `idx_created_at` (`created_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }

    // ── Helper simpan varian ──────────────────────────────
    function simpanVarian($db, $id_menu) {
        $varian_nama  = $_POST['varian_nama']  ?? [];
        $varian_harga = $_POST['varian_harga'] ?? [];
        $varian_stok  = $_POST['varian_stok']  ?? [];

        // Pastikan array
        if (!is_array($varian_nama))  $varian_nama  = [$varian_nama];
        if (!is_array($varian_harga)) $varian_harga = [$varian_harga];
        if (!is_array($varian_stok))  $varian_stok  = [$varian_stok];

        $total       = count($varian_nama);
        $total_stok  = 0;   // akumulator stok seluruh varian

        for ($idx = 0; $idx < $total; $idx++) {
            $v_nama  = mysqli_real_escape_string($db, trim($varian_nama[$idx] ?? ''));
            $v_harga = (int) ($varian_harga[$idx] ?? 0);
            $v_stok  = max(0, (int) ($varian_stok[$idx] ?? 0));

            if ($v_nama === '') continue;

            $total_stok += $v_stok;

            $v_foto = '';

            // Cek file varian
            $has_file = (
                isset($_FILES['varian_foto']['tmp_name'][$idx])
                && is_uploaded_file($_FILES['varian_foto']['tmp_name'][$idx])
                && $_FILES['varian_foto']['size'][$idx] > 0
                && $_FILES['varian_foto']['error'][$idx] === UPLOAD_ERR_OK
            );

            if ($has_file) {
                $v_ext    = strtolower(pathinfo($_FILES['varian_foto']['name'][$idx], PATHINFO_EXTENSION));
                $ext_ok_v = ['jpg', 'jpeg', 'png', 'webp', 'gif'];

                if (in_array($v_ext, $ext_ok_v)) {
                    if (!is_dir('uploads')) mkdir('uploads', 0755, true);
                    $v_foto = time() . '_v' . $idx . '_' . bin2hex(random_bytes(3)) . '.' . $v_ext;
                    move_uploaded_file($_FILES['varian_foto']['tmp_name'][$idx], 'uploads/' . $v_foto);
                }
            }

            $v_foto_esc = mysqli_real_escape_string($db, $v_foto);
            $q = mysqli_query($db,
                "INSERT INTO varian_menu (id_menu, nama, foto_varian, harga_tambah, stok)
                 VALUES ($id_menu, '$v_nama', '$v_foto_esc', $v_harga, $v_stok)"
            );

            if (!$q) {
                error_log("Varian insert error idx=$idx : " . mysqli_error($db));
            }
        }

        return $total_stok;
    }

    // ── Cek apakah ada varian dikirim ─────────────────────
    $ada_varian = !empty($_POST['varian_nama']) && count(array_filter(
        is_array($_POST['varian_nama']) ? $_POST['varian_nama'] : [$_POST['varian_nama']],
        fn($v) => trim($v) !== ''
    )) > 0;

    // ── Proses ke Database ─────────────────────────────────
    if ($aksi === 'tambah') {

        // Stok sementara 0 dulu jika ada varian — akan di-UPDATE setelah varian disimpan
        $stok_awal = $ada_varian ? 0 : $stok;

        $sql = "INSERT INTO menu_req (nama_makanan, foto, id_kantin, id_penjual, harga, stok, deskripsi, kategori, aktif, created_at)
                VALUES ('$nama', '$nama_foto', $id_kantin, $id_penjual_session, '$harga', $stok_awal, '$deskripsi', '$kategori', 1, NOW())";

        if (!mysqli_query($db, $sql)) {
            if ($nama_foto && file_exists('uploads/' . $nama_foto)) unlink('uploads/' . $nama_foto);
            echo json_encode(['status' => 'error', 'pesan' => 'Query gagal: ' . mysqli_error($db)]);
            exit;
        }

        $id_baru = mysqli_insert_id($db);

        if ($ada_varian) {
            $total_stok_varian = simpanVarian($db, $id_baru);
            // Update stok menu_req = total stok semua varian
            mysqli_query($db, "UPDATE menu_req SET stok = $total_stok_varian WHERE id = $id_baru");

            // ── Catat stok awal produk baru ke stok_log ──
            if ($total_stok_varian > 0) {
                pastikanTabelStokLog($db);
                $ket_tambah = mysqli_real_escape_string($db, "Stok awal produk baru (dengan varian): $total_stok_varian");
                mysqli_query($db, "INSERT INTO stok_log (id_menu, jumlah, tipe, keterangan, created_at)
                                   VALUES ($id_baru, $total_stok_varian, 'tambah', '$ket_tambah', NOW())");
            }
        } else {
            // ── Catat stok awal produk baru ke stok_log ──
            if ($stok_awal > 0) {
                pastikanTabelStokLog($db);
                $ket_tambah = mysqli_real_escape_string($db, "Stok awal produk baru: $stok_awal");
                mysqli_query($db, "INSERT INTO stok_log (id_menu, jumlah, tipe, keterangan, created_at)
                                   VALUES ($id_baru, $stok_awal, 'tambah', '$ket_tambah', NOW())");
            }
        }

        echo json_encode(['status' => 'sukses', 'id_menu' => $id_baru, 'id_kantin' => $id_kantin]);
        exit;

    } elseif ($aksi === 'edit') {

        $id = (int) ($_POST['id'] ?? 0);
        if (!$id) {
            echo json_encode(['status' => 'error', 'pesan' => 'ID tidak valid']);
            exit;
        }

        // ── Ambil stok LAMA sebelum diubah ────────────────
        $q_lama    = mysqli_query($db, "SELECT stok FROM menu_req WHERE id = $id LIMIT 1");
        $row_lama  = mysqli_fetch_assoc($q_lama);
        $stok_lama = (int) ($row_lama['stok'] ?? 0);

        // Hapus varian lama lalu insert ulang, dapatkan total stok baru
        mysqli_query($db, "DELETE FROM varian_menu WHERE id_menu = $id");

        if ($ada_varian) {
            $total_stok_varian = simpanVarian($db, $id);
            $stok_final = $total_stok_varian;
        } else {
            $stok_final = $stok;
        }

        $set_foto = $nama_foto ? ", foto = '$nama_foto'" : '';
        $sql = "UPDATE menu_req SET
                    nama_makanan = '$nama',
                    harga        = '$harga',
                    stok         = $stok_final,
                    kategori     = '$kategori',
                    deskripsi    = '$deskripsi',
                    id_kantin    = $id_kantin
                    $set_foto
                WHERE id = $id";

        if (!mysqli_query($db, $sql)) {
            echo json_encode(['status' => 'error', 'pesan' => 'Query gagal: ' . mysqli_error($db)]);
            exit;
        }

        // ── Catat ke stok_log jika stok NAIK ──────────────
        $selisih_stok = $stok_final - $stok_lama;
        if ($selisih_stok > 0) {
            pastikanTabelStokLog($db);
            $ket = mysqli_real_escape_string($db, "Edit produk: stok naik dari $stok_lama ke $stok_final (+$selisih_stok)");
            mysqli_query($db, "INSERT INTO stok_log (id_menu, jumlah, tipe, keterangan, created_at)
                               VALUES ($id, $selisih_stok, 'tambah', '$ket', NOW())");
        }

        echo json_encode(['status' => 'sukses', 'id_menu' => $id, 'id_kantin' => $id_kantin]);
        exit;

    } else {
        echo json_encode(['status' => 'error', 'pesan' => 'Aksi tidak dikenal']);
        exit;
    }
}

// ── TAMPIL HALAMAN ───────────────────────────────────────
$edit_mode   = false;
$menu        = null;
$varian_list = [];

if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $id = (int) $_GET['edit'];
    $q  = mysqli_query($db, "SELECT * FROM menu_req WHERE id = $id");
    if ($q && mysqli_num_rows($q) > 0) {
        $menu      = mysqli_fetch_assoc($q);
        $edit_mode = true;

        $qv = mysqli_query($db, "SELECT * FROM varian_menu WHERE id_menu = $id ORDER BY id ASC");
        while ($rv = mysqli_fetch_assoc($qv)) {
            $varian_list[] = $rv;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Detail Menu</title>
  <link rel="stylesheet" href="dess.css">
  <link rel="stylesheet" href="../custom_confirm.css">
</head>
<body>

  <!-- FORM TERSEMBUNYI -->
  <form id="menuForm" enctype="multipart/form-data" style="display:none;">
    <input type="hidden" name="aksi"         id="aksiHidden"      value="<?= $edit_mode ? 'edit' : 'tambah' ?>">
    <input type="hidden" name="id"           id="idHidden"        value="<?= $edit_mode ? $menu['id'] : '' ?>">
    <input type="hidden" name="foto_lama"    id="fotoLamaHidden"  value="<?= $edit_mode ? htmlspecialchars($menu['foto']) : '' ?>">
    <input type="hidden" name="id_kantin"    id="id_kantinHidden" value="<?= $id_kantin_session ?>">
    <input type="file"   name="foto"         id="fotoHidden"      accept="image/*">
    <!-- Container untuk menampung input file & data varian saat submit -->
    <div id="varianFormContainer"></div>
  </form>

  <!-- HEADER -->
  <header>
    <a href="produk.php" class="back">
      <svg viewBox="0 0 24 24"><polyline points="15 18 9 12 15 6"/></svg>
    </a>
    <div class="header-title">Detail Menu</div>
    <button id="editBtn">Edit</button>
  </header>

  <!-- KONTEN UTAMA -->
  <section id="mainSection">
    <div class="main-card">

      <div
        class="nama-input"
        id="namaMenu"
        contenteditable="true"
        data-placeholder="NAMA MENU"
        spellcheck="false"
      ><?= $edit_mode ? htmlspecialchars($menu['nama_makanan']) : '' ?></div>

      <div class="inner-grid">

        <!-- CARD FOTO + HARGA + STOK -->
        <div class="card-foto">
          <div class="section-title">Foto Menu</div>
          <div class="upload-area" id="uploadArea" onclick="document.getElementById('fileInput').click()">
            <div class="plus-icon" id="plusIcon"></div>
            <img class="preview-img" id="previewImg" alt="Preview">
          </div>
          <input type="file" id="fileInput" accept="image/*">

            <div class="bottom-grid">
            <div class="field-box">
              <div class="field-label">Harga</div>
              <div class="field-row">
                <span class="field-prefix">Rp</span>
                <input type="number" class="field-input" id="hargaInput" min="0" placeholder="0"
                       value="<?= $edit_mode ? (int)$menu['harga'] : '' ?>">
              </div>
            </div>
            <div class="field-box" id="stokBox">
              <div class="field-label">
                Stok
                <span id="stokAutoLabel" style="display:none;font-size:10px;color:#888;font-weight:400;margin-left:4px;">(dari varian)</span>
              </div>
              <div class="field-row">
                <input type="number" class="field-input" id="stokInput" min="0" placeholder="0"
                       value="<?= $edit_mode ? $menu['stok'] : '' ?>">
                <span class="field-suffix">Pcs</span>
              </div>
            </div>
          </div>
        </div>

        <!-- CARD KANAN -->
        <div class="card-detail">

          <div class="card-katdesc">
            <div class="katdesc-kiri">
              <div class="section-title">Kategori</div>
              <div class="select-box">
                <div class="select-selected" onclick="toggleDropdown()">
                  <div class="icon-circle">
                    <img src="" alt="" id="selectedImg" style="display:none;">
                  </div>
                  <span class="select-label" id="selectedLabel">Pilih Kategori</span>
                  <span class="arrow">&#8964;</span>
                </div>
                <div class="select-options" id="selectOptions">
                  <div class="option" onclick="selectOption(this, 'MAKANAN', 'Makanan.png')">
                    <div class="icon-circle"><img src="Makanan.png" alt="Makanan"></div>
                    <span>MAKANAN</span>
                  </div>
                  <div class="option" onclick="selectOption(this, 'MINUMAN', 'MINUMAN.png')">
                    <div class="icon-circle"><img src="MINUMAN.png" alt="Minuman"></div>
                    <span>MINUMAN</span>
                  </div>
                  <div class="option" onclick="selectOption(this, 'SNACK', 'SNACK.png')">
                    <div class="icon-circle"><img src="SNACK.png" alt="Snack"></div>
                    <span>SNACK</span>
                  </div>
                </div>
              </div>
            </div>

            <div class="katdesc-divider"></div>

            <div class="katdesc-kanan">
              <div class="section-title">Deskripsi</div>
              <textarea
                class="custom-textarea"
                id="deskripsiInput"
                placeholder="Masukkan deskripsi menu..."
              ><?= $edit_mode ? htmlspecialchars($menu['deskripsi']) : '' ?></textarea>
            </div>
          </div>

          <!-- VARIAN -->
          <div class="varian-section">
            <div class="varian-header">
              <div class="varian-title">
                Varian Menu <span id="varianCount"></span>
              </div>
              <button id="tambahVarianBtn" onclick="tambahVarian()">
                <svg viewBox="0 0 24 24">
                  <line x1="12" y1="5" x2="12" y2="19"/>
                  <line x1="5"  y1="12" x2="19" y2="12"/>
                </svg>
                Tambah Varian
              </button>
            </div>
            <div class="varian-empty" id="varianEmpty">
              Belum ada varian. Klik "Tambah Varian" untuk menambahkan.
            </div>
            <div class="varian-grid" id="varianGrid"></div>
          </div>

        </div>
      </div>
    </div>
  </section>

  <script>
    // Sinkron file foto utama
    document.getElementById('fileInput').addEventListener('change', function () {
      const dt = new DataTransfer();
      if (this.files[0]) dt.items.add(this.files[0]);
      document.getElementById('fotoHidden').files = dt.files;
    });

    <?php if ($edit_mode && $menu['kategori']): ?>
    window.addEventListener('DOMContentLoaded', function () {
      const map = { MAKANAN: 'Makanan.png', MINUMAN: 'MINUMAN.png', SNACK: 'SNACK.png' };
      const kat = '<?= htmlspecialchars($menu['kategori']) ?>';
      document.getElementById('selectedLabel').textContent = kat;
      if (map[kat]) {
        const img = document.getElementById('selectedImg');
        img.src = map[kat];
        img.style.display = 'block';
      }
      <?php if ($menu['foto']): ?>
      document.getElementById('previewImg').src = 'uploads/<?= htmlspecialchars($menu['foto']) ?>';
      document.getElementById('previewImg').style.display = 'block';
      document.getElementById('plusIcon').style.display = 'none';
      <?php endif; ?>
    });
    <?php endif; ?>

    <?php if (!empty($varian_list)): ?>
    window.addEventListener('DOMContentLoaded', function () {
      <?php foreach ($varian_list as $v): ?>
      (function () {
        varianCounter++;
        const id   = varianCounter;
        const grid = document.getElementById('varianGrid');
        const card = document.createElement('div');
        card.className = 'varian-card';
        card.id = 'varian-' + id;
        card.innerHTML = `
          <div class="varian-gambar" onclick="triggerVarianFile(${id})">
            <div class="varian-upload-placeholder" id="placeholder-${id}" style="display:<?= empty($v['foto_varian']) ? 'flex' : 'none' ?>">
              <svg viewBox="0 0 24 24">
                <rect x="3" y="3" width="18" height="18" rx="3"/>
                <circle cx="8.5" cy="8.5" r="1.5"/>
                <polyline points="21 15 16 10 5 21"/>
              </svg>
              <span>Upload Gambar</span>
            </div>
            <img class="varian-preview" id="preview-varian-${id}"
                 src="<?= !empty($v['foto_varian']) ? 'uploads/' . htmlspecialchars($v['foto_varian']) : '' ?>"
                 style="display:<?= !empty($v['foto_varian']) ? 'block' : 'none' ?>"
                 alt="Varian">
            <div class="varian-overlay">
              <button class="btn-ganti-foto" onclick="event.stopPropagation(); triggerVarianFile(${id})">
                <svg viewBox="0 0 24 24" style="width:11px;height:11px;stroke:var(--orange-deep);fill:none;stroke-width:2.5;stroke-linecap:round;">
                  <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                  <polyline points="17 8 12 3 7 8"/>
                  <line x1="12" y1="3" x2="12" y2="15"/>
                </svg>
                Ganti Foto
              </button>
            </div>
          </div>
          <input type="file" class="varian-file-input" id="file-varian-${id}" accept="image/*"
                 onchange="handleVarianFile(${id}, this)">
          <div class="varian-isi">
            <input type="text" class="varian-nama-input" id="nama-varian-${id}"
                   placeholder="Nama varian..." maxlength="40"
                   value="<?= htmlspecialchars($v['nama']) ?>">
            <div class="varian-harga-row">
              <span class="varian-prefix">Rp</span>
              <input type="number" class="varian-harga-input" id="harga-varian-${id}"
                     placeholder="0" min="0" value="<?= (int)$v['harga_tambah'] ?>">
            </div>
            <div class="varian-harga-row" style="margin-top:6px;">
              <span class="varian-prefix" style="font-size:11px;min-width:28px;">Stok</span>
              <input type="number" class="varian-harga-input" id="stok-varian-${id}"
                     placeholder="0" min="0" value="<?= (int)($v['stok'] ?? 0) ?>">
            </div>
            <button class="btn-hapus-varian" onclick="hapusVarian(${id})">
              <svg viewBox="0 0 24 24">
                <polyline points="3 6 5 6 21 6"/>
                <path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/>
                <path d="M10 11v6"/><path d="M14 11v6"/>
                <path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/>
              </svg>
              Hapus Varian
            </button>
          </div>
        `;
        grid.appendChild(card);
        updateVarianCount();
      })();
      <?php endforeach; ?>
    });
    <?php endif; ?>
  </script>

  <script>
  // Kumpulkan semua data varian (nama, harga, foto) ke dalam form tersembunyi
  // sebelum form di-submit — supaya foto varian ikut terkirim ke server
  function kumpulkanVarianKeForm() {
    const container = document.getElementById('varianFormContainer');
    container.innerHTML = ''; // reset dulu

    const grid = document.getElementById('varianGrid');
    if (!grid) return;

    const cards = grid.querySelectorAll('.varian-card');
    const dt    = new DataTransfer();

    cards.forEach(function(card, idx) {
      const cardId = card.id.replace('varian-', '');

      // Nama varian
      const namaInput = document.getElementById('nama-varian-' + cardId);
      const hidden_nama = document.createElement('input');
      hidden_nama.type  = 'hidden';
      hidden_nama.name  = 'varian_nama[]';
      hidden_nama.value = namaInput ? namaInput.value : '';
      container.appendChild(hidden_nama);

      // Harga tambah
      const hargaInput = document.getElementById('harga-varian-' + cardId);
      const hidden_harga = document.createElement('input');
      hidden_harga.type  = 'hidden';
      hidden_harga.name  = 'varian_harga[]';
      hidden_harga.value = hargaInput ? hargaInput.value : '0';
      container.appendChild(hidden_harga);

      // Stok varian
      const stokInput = document.getElementById('stok-varian-' + cardId);
      const hidden_stok = document.createElement('input');
      hidden_stok.type  = 'hidden';
      hidden_stok.name  = 'varian_stok[]';
      hidden_stok.value = stokInput ? (stokInput.value || '0') : '0';
      container.appendChild(hidden_stok);

      // File foto varian — kumpulkan ke DataTransfer dengan urutan index yang sama
      const fileInput = document.getElementById('file-varian-' + cardId);
      if (fileInput && fileInput.files && fileInput.files[0]) {
        dt.items.add(fileInput.files[0]);
      } else {
        // Tidak ada file baru — tambahkan file kosong agar index tetap sejajar
        dt.items.add(new File([''], '', { type: 'image/png' }));
      }
    });

    // Masukkan semua file varian ke satu input file[] di form
    const varianFileInput = document.createElement('input');
    varianFileInput.type     = 'file';
    varianFileInput.name     = 'varian_foto[]';
    varianFileInput.multiple = true;
    varianFileInput.style.display = 'none';
    varianFileInput.files = dt.files;
    container.appendChild(varianFileInput);
  }

  // Pastikan kumpulkanVarianKeForm() dipanggil sebelum form submit
  document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('menuForm');
    if (form) {
      form.addEventListener('submit', function () {
        kumpulkanVarianKeForm();
      });
    }
  });
  </script>

  <script src="script.js"></script>
  <script src="../custom_confirm.js"></script>
</body>
</html>