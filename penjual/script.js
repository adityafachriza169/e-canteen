/* =========================
   STATE
========================= */
let fotoFile = null;
let varianFiles = {};   // { cardId: File }
let varianCounter = 0;
let mode = 'view';
const PLACEHOLDER = 'NAMA MENU';

/* =========================
   STOK OTOMATIS DARI VARIAN
========================= */
function hitungTotalStokVarian() {
  let total = 0;
  document.querySelectorAll('.varian-card').forEach(function (card) {
    const vid = card.id.replace('varian-', '');
    const stokEl = document.getElementById('stok-varian-' + vid);
    if (stokEl) total += Math.max(0, parseInt(stokEl.value) || 0);
  });
  return total;
}

function updateStokOtomatis() {
  const cards = document.querySelectorAll('.varian-card');
  const stokInput = document.getElementById('stokInput');
  const stokAutoLabel = document.getElementById('stokAutoLabel');

  if (cards.length > 0) {
    const total = hitungTotalStokVarian();
    stokInput.value = total;
    stokInput.readOnly = true;
    stokInput.style.background = '#f5f5f5';
    stokInput.style.cursor = 'not-allowed';
    if (stokAutoLabel) stokAutoLabel.style.display = 'inline';
  } else {
    stokInput.readOnly = false;
    stokInput.style.background = '';
    stokInput.style.cursor = '';
    if (stokAutoLabel) stokAutoLabel.style.display = 'none';
  }
}

/* =========================
   FOTO UTAMA
========================= */
document.getElementById('fileInput').addEventListener('change', function (e) {
  const file = e.target.files[0];
  if (!file) return;
  fotoFile = file;
  const reader = new FileReader();
  reader.onload = function (ev) {
    const img = document.getElementById('previewImg');
    img.src = ev.target.result;
    img.style.display = 'block';
    document.getElementById('plusIcon').style.display = 'none';
  };
  reader.readAsDataURL(file);
});

/* =========================
   NAMA MENU
========================= */
const namaMenu = document.getElementById('namaMenu');

namaMenu.addEventListener('keydown', function (e) {
  if (e.key === 'Enter') {
    e.preventDefault();
    namaMenu.blur();
  }
});

/* =========================
   KATEGORI DROPDOWN
========================= */
function toggleDropdown() {
  const section = document.getElementById('mainSection');
  if (section.classList.contains('locked')) return;
  document.getElementById('selectOptions').classList.toggle('open');
}

function selectOption(el, label, imgSrc) {
  document.getElementById('selectedLabel').textContent = label;
  const img = document.getElementById('selectedImg');
  img.src = imgSrc;
  img.style.display = 'block';
  document.getElementById('selectOptions').classList.remove('open');
}

document.addEventListener('click', function (e) {
  const box = document.querySelector('.select-box');
  if (box && !box.contains(e.target)) {
    document.getElementById('selectOptions').classList.remove('open');
  }
});

/* =========================
   VARIAN — HELPERS
========================= */
function updateVarianCount() {
  const cards = document.querySelectorAll('.varian-card');
  const count = cards.length;
  document.getElementById('varianCount').textContent = count > 0 ? '(' + count + ' varian)' : '';
  document.getElementById('varianEmpty').style.display = count === 0 ? 'block' : 'none';
}

function triggerVarianFile(id) {
  const section = document.getElementById('mainSection');
  if (section.classList.contains('locked')) return;
  document.getElementById('file-varian-' + id).click();
}

function handleVarianFile(id, input) {
  const file = input.files[0];
  if (!file) return;
  varianFiles[id] = file;
  const reader = new FileReader();
  reader.onload = function (ev) {
    const preview = document.getElementById('preview-varian-' + id);
    const placeholder = document.getElementById('placeholder-' + id);
    preview.src = ev.target.result;
    preview.style.display = 'block';
    if (placeholder) placeholder.style.display = 'none';
  };
  reader.readAsDataURL(file);
}

function hapusVarian(id) {
  const section = document.getElementById('mainSection');
  if (section.classList.contains('locked')) return;
  const card = document.getElementById('varian-' + id);
  if (!card) return;
  card.style.transition = 'opacity .2s, transform .2s';
  card.style.opacity = '0';
  card.style.transform = 'scale(.95)';
  setTimeout(function () {
    card.remove();
    delete varianFiles[id];
    updateVarianCount();
    updateStokOtomatis();
  }, 200);
}

/* =========================
   VARIAN — TAMBAH KARTU
========================= */
function tambahVarian() {
  const section = document.getElementById('mainSection');
  if (section.classList.contains('locked')) return;

  varianCounter++;
  const id = varianCounter;
  const grid = document.getElementById('varianGrid');

  const card = document.createElement('div');
  card.className = 'varian-card';
  card.id = 'varian-' + id;

  card.innerHTML =
    '<div class="varian-gambar" onclick="triggerVarianFile(' + id + ')">' +
    '<div class="varian-upload-placeholder" id="placeholder-' + id + '">' +
    '<svg viewBox="0 0 24 24">' +
    '<rect x="3" y="3" width="18" height="18" rx="3"/>' +
    '<circle cx="8.5" cy="8.5" r="1.5"/>' +
    '<polyline points="21 15 16 10 5 21"/>' +
    '</svg>' +
    '<span>Upload Gambar</span>' +
    '</div>' +
    '<img class="varian-preview" id="preview-varian-' + id + '" alt="Varian" style="display:none">' +
    '<div class="varian-overlay">' +
    '<button class="btn-ganti-foto" onclick="event.stopPropagation(); triggerVarianFile(' + id + ')">' +
    '<svg viewBox="0 0 24 24" style="width:11px;height:11px;stroke:var(--orange-deep);fill:none;stroke-width:2.5;stroke-linecap:round;">' +
    '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>' +
    '<polyline points="17 8 12 3 7 8"/>' +
    '<line x1="12" y1="3" x2="12" y2="15"/>' +
    '</svg>' +
    'Ganti Foto' +
    '</button>' +
    '</div>' +
    '</div>' +
    '<input type="file" class="varian-file-input" id="file-varian-' + id + '" accept="image/*" onchange="handleVarianFile(' + id + ', this)">' +
    '<div class="varian-isi">' +
    '<input type="text" class="varian-nama-input" id="nama-varian-' + id + '" placeholder="Nama varian..." maxlength="40">' +
    '<div class="varian-harga-row">' +
    '<span class="varian-prefix">Rp</span>' +
    '<input type="number" class="varian-harga-input" id="harga-varian-' + id + '" placeholder="Harga tambah..." min="0">' +
    '</div>' +
    '<div class="varian-harga-row" style="margin-top:6px;">' +
    '<span class="varian-prefix" style="font-size:11px;min-width:28px;">Stok</span>' +
    '<input type="number" class="varian-harga-input" id="stok-varian-' + id + '" placeholder="0" min="0" oninput="updateStokOtomatis()">' +
    '</div>' +
    '<button class="btn-hapus-varian" onclick="hapusVarian(' + id + ')">' +
    '<svg viewBox="0 0 24 24">' +
    '<polyline points="3 6 5 6 21 6"/>' +
    '<path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/>' +
    '<path d="M10 11v6"/><path d="M14 11v6"/>' +
    '<path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/>' +
    '</svg>' +
    'Hapus Varian' +
    '</button>' +
    '</div>';

  grid.appendChild(card);
  card.style.animation = 'fadeIn .3s ease';
  updateVarianCount();
  updateStokOtomatis();
}

/* =========================
   MODE EDIT / VIEW
========================= */
const btn = document.getElementById('editBtn');
const section = document.getElementById('mainSection');

function setLocked(locked) {
  document.querySelectorAll('input:not([type=file]):not([type=hidden]), textarea').forEach(function (el) {
    el.disabled = locked;
  });

  namaMenu.contentEditable = locked ? 'false' : 'true';

  const uploadArea = document.getElementById('uploadArea');
  uploadArea.style.pointerEvents = locked ? 'none' : 'auto';
  uploadArea.style.opacity = locked ? '.75' : '1';

  if (locked) {
    section.classList.add('locked');
  } else {
    section.classList.remove('locked');
  }

  document.querySelectorAll('.varian-gambar').forEach(function (g) {
    g.style.pointerEvents = locked ? 'none' : 'auto';
  });

  document.querySelectorAll('.btn-hapus-varian, .btn-ganti-foto').forEach(function (b) {
    b.disabled = locked;
  });

  // Tombol Tambah Varian ikut dikunci
  const tambahVarianBtn = document.getElementById('tambahVarianBtn');
  if (tambahVarianBtn) {
    tambahVarianBtn.disabled = locked;
    tambahVarianBtn.style.opacity = locked ? '.5' : '1';
    tambahVarianBtn.style.cursor = locked ? 'not-allowed' : '';
  }

  // Dropdown kategori visual
  const selectSelected = document.querySelector('.select-selected');
  if (selectSelected) {
    selectSelected.style.pointerEvents = locked ? 'none' : 'auto';
    selectSelected.style.opacity = locked ? '.75' : '1';
  }
}

/* Mulai dalam mode terkunci */
setLocked(true);

/* =========================
   PROSES KIRIM DATA KE SERVER
   (dipanggil setelah konfirmasi)
========================= */
function prosesKirimData() {
  const nama      = namaMenu.textContent.trim();
  const harga     = document.getElementById('hargaInput').value.trim();
  const stok      = document.getElementById('stokInput').value.trim();
  const kategori  = document.getElementById('selectedLabel').textContent.trim();
  const deskripsi = document.getElementById('deskripsiInput').value.trim();

  const aksi = document.getElementById('aksiHidden') ? document.getElementById('aksiHidden').value : 'tambah';
  const idEl = document.getElementById('idHidden');

  const varianCards = document.querySelectorAll('.varian-card');

  const formData = new FormData();
  formData.append('aksi', aksi);
  if (idEl && idEl.value) formData.append('id', idEl.value);
  formData.append('nama_makanan', nama);
  formData.append('harga', harga);
  formData.append('stok', stok);
  formData.append('kategori', kategori);
  formData.append('deskripsi', deskripsi);
  formData.append('id_kantin', document.getElementById('id_kantinHidden') ? document.getElementById('id_kantinHidden').value : '1');

  const fotoLama = document.getElementById('fotoLamaHidden');
  if (fotoLama && fotoLama.value) formData.append('foto_lama', fotoLama.value);
  if (fotoFile) formData.append('foto', fotoFile);

  varianCards.forEach(function (card) {
    const vid   = card.id.replace('varian-', '');
    const namaV = document.getElementById('nama-varian-' + vid).value.trim();
    const hgV   = document.getElementById('harga-varian-' + vid).value || '0';
    const stokV = document.getElementById('stok-varian-' + vid)
      ? (document.getElementById('stok-varian-' + vid).value || '0') : '0';
    const file  = varianFiles[parseInt(vid)] || varianFiles[vid] || null;

    formData.append('varian_nama[]',  namaV);
    formData.append('varian_harga[]', hgV);
    formData.append('varian_stok[]',  stokV);

    if (file) {
      formData.append('varian_foto[]', file, file.name);
    } else {
      formData.append('varian_foto[]', new Blob([], { type: 'application/octet-stream' }), 'no-file');
    }
  });

  /* Nonaktifkan tombol selama request */
  btn.textContent = 'Menyimpan...';
  btn.disabled    = true;

  fetch('des.php', { method: 'POST', body: formData })
    .then(function (res) { return res.json(); })
    .then(function (data) {
      if (data.status === 'sukses') {
        confirmPopup({
          judul   : 'Berhasil',
          pesan   : 'Menu berhasil disimpan!',
          tipe    : 'success'
        });
        window.location.href = 'produk.php?sukses=' + aksi;
      } else {
        confirmPopup({
          judul   : 'Gagal',
          pesan   : data.pesan || JSON.stringify(data),
          tipe    : 'danger',
          labelOK : 'OK'
        });
        btn.textContent      = 'Simpan';
        btn.style.background = '#27ae60';
        btn.disabled         = false;
      }
    })
    .catch(function (err) {
      console.error(err);
      confirmPopup({
        judul   : 'Error',
        pesan   : 'Error koneksi server. Cek console untuk detail.',
        tipe    : 'danger',
        labelOK : 'OK'
      });
      btn.textContent      = 'Simpan';
      btn.style.background = '#27ae60';
      btn.disabled         = false;
    });
}

/* =========================
   KONFIRMASI SIMPAN
   (tampil ringkasan data sebelum kirim)
========================= */
function tampilKonfirmasiSimpan() {
  const nama      = namaMenu.textContent.trim();
  const harga     = document.getElementById('hargaInput').value.trim();
  const stok      = document.getElementById('stokInput').value.trim();
  const kategori  = document.getElementById('selectedLabel').textContent.trim();
  const varianCards = document.querySelectorAll('.varian-card');
  const jumlahVarian = varianCards.length;

  /* Buat ringkasan singkat yang ditampilkan di dialog */
  const ringkasan =
    '<b>' + nama + '</b><br>' +
    'Harga: <b>Rp ' + Number(harga).toLocaleString('id-ID') + '</b> &nbsp;|&nbsp; ' +
    'Kategori: <b>' + kategori + '</b><br>' +
    'Stok: <b>' + stok + ' pcs</b>' +
    (jumlahVarian > 0 ? ' &nbsp;|&nbsp; Varian: <b>' + jumlahVarian + ' varian</b>' : '');

  /* ── Overlay konfirmasi kustom ─────────────────────── */
  // Hapus overlay lama jika ada
  const lama = document.getElementById('konfirmasiSimpanOverlay');
  if (lama) lama.remove();

  const overlay = document.createElement('div');
  overlay.id = 'konfirmasiSimpanOverlay';
  overlay.style.cssText = [
    'position:fixed', 'inset:0', 'z-index:9999',
    'display:flex', 'align-items:center', 'justify-content:center',
    'background:rgba(0,0,0,.45)',
    'backdrop-filter:blur(3px)',
    'animation:ksOverlayIn .2s ease'
  ].join(';');

  overlay.innerHTML =
    '<style>' +
    '@keyframes ksOverlayIn{from{opacity:0}to{opacity:1}}' +
    '@keyframes ksCardIn{from{opacity:0;transform:translateY(18px) scale(.97)}to{opacity:1;transform:none}}' +
    '#konfirmasiSimpanOverlay .ks-card{' +
      'background:#fff;border-radius:18px;padding:28px 28px 22px;' +
      'width:min(92vw,380px);box-shadow:0 20px 60px rgba(0,0,0,.22);' +
      'animation:ksCardIn .25s ease;font-family:inherit;' +
    '}' +
    '#konfirmasiSimpanOverlay .ks-icon{' +
      'width:52px;height:52px;border-radius:50%;margin:0 auto 14px;' +
      'background:linear-gradient(135deg,#fff7ed,#ffedd5);' +
      'display:flex;align-items:center;justify-content:center;' +
      'box-shadow:0 4px 14px rgba(234,88,12,.18);' +
    '}' +
    '#konfirmasiSimpanOverlay .ks-icon svg{width:26px;height:26px;stroke:#ea580c;fill:none;stroke-width:2;stroke-linecap:round;}' +
    '#konfirmasiSimpanOverlay .ks-judul{' +
      'text-align:center;font-size:17px;font-weight:700;' +
      'color:#1a1a1a;margin-bottom:8px;' +
    '}' +
    '#konfirmasiSimpanOverlay .ks-sub{' +
      'text-align:center;font-size:12px;color:#888;margin-bottom:16px;' +
    '}' +
    '#konfirmasiSimpanOverlay .ks-ringkasan{' +
      'background:#fafafa;border:1.5px solid #f0f0f0;border-radius:12px;' +
      'padding:13px 16px;font-size:13px;color:#444;line-height:1.7;' +
      'margin-bottom:20px;text-align:center;' +
    '}' +
    '#konfirmasiSimpanOverlay .ks-baris-btn{' +
      'display:flex;gap:10px;' +
    '}' +
    '#konfirmasiSimpanOverlay .ks-btn{' +
      'flex:1;padding:12px 0;border:none;border-radius:12px;' +
      'font-size:14px;font-weight:600;cursor:pointer;' +
      'transition:transform .12s,box-shadow .12s,filter .12s;' +
    '}' +
    '#konfirmasiSimpanOverlay .ks-btn:active{transform:scale(.96);}' +
    '#konfirmasiSimpanOverlay .ks-btn-batal{' +
      'background:#f3f4f6;color:#555;' +
    '}' +
    '#konfirmasiSimpanOverlay .ks-btn-batal:hover{background:#e5e7eb;}' +
    '#konfirmasiSimpanOverlay .ks-btn-lanjut{' +
      'background:linear-gradient(135deg,#f97316,#ea580c);color:#fff;' +
      'box-shadow:0 4px 14px rgba(234,88,12,.35);' +
    '}' +
    '#konfirmasiSimpanOverlay .ks-btn-lanjut:hover{filter:brightness(1.07);}' +
    '</style>' +

    '<div class="ks-card">' +
      '<div class="ks-icon">' +
        '<svg viewBox="0 0 24 24"><path d="M9 12l2 2 4-4"/><rect x="3" y="3" width="18" height="18" rx="4"/></svg>' +
      '</div>' +
      '<div class="ks-judul">Konfirmasi Simpan Menu</div>' +
      '<div class="ks-sub">Pastikan data di bawah sudah benar sebelum disimpan.</div>' +
      '<div class="ks-ringkasan">' + ringkasan + '</div>' +
      '<div class="ks-baris-btn">' +
        '<button class="ks-btn ks-btn-batal" id="ksBtnBatal">✏️ Edit Lagi</button>' +
        '<button class="ks-btn ks-btn-lanjut" id="ksBtnLanjut">Simpan ✓</button>' +
      '</div>' +
    '</div>';

  document.body.appendChild(overlay);

  /* Tutup saat klik di luar card */
  overlay.addEventListener('click', function (e) {
    if (e.target === overlay) tutupKonfirmasi();
  });

  document.getElementById('ksBtnBatal').addEventListener('click', tutupKonfirmasi);
  document.getElementById('ksBtnLanjut').addEventListener('click', function () {
    tutupKonfirmasi();
    prosesKirimData();
  });
}

function tutupKonfirmasi() {
  const overlay = document.getElementById('konfirmasiSimpanOverlay');
  if (!overlay) return;
  overlay.style.transition = 'opacity .15s';
  overlay.style.opacity    = '0';
  setTimeout(function () { overlay.remove(); }, 160);
}

/* =========================
   TOMBOL EDIT / SIMPAN
========================= */
btn.addEventListener('click', function (e) {
  e.preventDefault();

  /* ── MASUK MODE EDIT ── */
  if (mode === 'view') {
    btn.textContent      = 'Simpan';
    btn.style.background = '#27ae60';
    setLocked(false);
    updateStokOtomatis();
    mode = 'edit';
    return;
  }

  /* ── SIMPAN: validasi dulu ── */
  if (mode === 'edit') {
    const nama      = namaMenu.textContent.trim();
    const harga     = document.getElementById('hargaInput').value.trim();
    const stok      = document.getElementById('stokInput').value.trim();
    const kategori  = document.getElementById('selectedLabel').textContent.trim();
    const deskripsi = document.getElementById('deskripsiInput').value.trim();
    const fotoTerisi = document.getElementById('previewImg').style.display === 'block';

    const varianCards = document.querySelectorAll('.varian-card');
    const adaVarian   = varianCards.length > 0;

    /* ── Validasi field utama ── */
    if (!nama || nama === PLACEHOLDER) {
      confirmPopup({ judul: 'Validasi', pesan: 'Nama menu belum diisi!', tipe: 'warning' });
      return;
    }
    if (!harga || Number(harga) <= 0) {
      confirmPopup({ judul: 'Validasi', pesan: 'Harga menu belum diisi!', tipe: 'warning' });
      return;
    }
    if (!adaVarian && (!stok || Number(stok) <= 0)) {
      confirmPopup({ judul: 'Validasi', pesan: 'Stok menu belum diisi!', tipe: 'warning' });
      return;
    }
    if (kategori === 'Pilih Kategori' || !kategori) {
      confirmPopup({ judul: 'Validasi', pesan: 'Kategori menu belum dipilih!', tipe: 'warning' });
      return;
    }
    if (!deskripsi) {
      confirmPopup({ judul: 'Validasi', pesan: 'Deskripsi menu belum diisi!', tipe: 'warning' });
      return;
    }
    if (!fotoTerisi) {
      confirmPopup({ judul: 'Validasi', pesan: 'Foto menu belum diupload!', tipe: 'warning' });
      return;
    }

    /* ── Validasi varian ── */
    let varianValid = true;
    varianCards.forEach(function (card) {
      if (!varianValid) return;
      const vid   = card.id.replace('varian-', '');
      const namaV = document.getElementById('nama-varian-' + vid).value.trim();
      if (!namaV) {
        confirmPopup({ judul: 'Validasi', pesan: 'Nama varian belum diisi!', tipe: 'warning' });
        varianValid = false;
      }
    });
    if (!varianValid) return;

    /* ── Semua validasi lolos → tampil dialog konfirmasi ── */
    tampilKonfirmasiSimpan();
  }
});

/* =========================
   INIT
========================= */
updateVarianCount();
updateStokOtomatis();