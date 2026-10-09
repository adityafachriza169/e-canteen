/* ================================================================
   CUSTOM CONFIRM POPUP — custom_confirm.js
   Include di semua halaman PHP (sebelum </body>):
   <script src="custom_confirm.js"></script>
   ================================================================

   CARA PAKAI — ganti confirm() lama dengan confirmPopup():

   SEBELUM (browser default):
     onclick="return confirm('Yakin hapus ini?')"

   SESUDAH (custom popup):
     onclick="confirmPopup({ pesan: 'Yakin hapus ini?', url: this.href }); return false;"
     atau untuk tombol link:
     <a href="..." onclick="confirmPopup({pesan:'...', url:this.href}); return false;">

   ── OPSI LENGKAP ──────────────────────────────────────────────
   confirmPopup({
     judul   : "Nonaktifkan Penjual",         // judul header (opsional)
     ikon    : "⚠️",                           // emoji ikon (opsional)
     pesan   : "Keterangan aksi ini...",       // teks utama
     poin    : ["Poin 1", "Poin 2"],           // bullet list (opsional)
     tipe    : "warning",                      // warning | danger | success | info
     labelOK : "Ya, Lanjutkan",               // label tombol konfirmasi (opsional)
     labelBatal: "Batal",                     // label tombol batal (opsional)
     url     : "admin.php?hapus=1",           // URL tujuan saat OK (opsional)
     onOK    : function() { ... },            // callback saat OK (opsional, alternatif url)
   });
   ================================================================ */

(function () {

  /* ── Inject HTML popup ke body ── */
  function injectHTML() {
    if (document.getElementById('customConfirmOverlay')) return;
    var html = ''
      + '<div id="customConfirmOverlay">'
      +   '<div id="customConfirmBox">'
      +     '<div id="customConfirmHeader">'
      +       '<span id="customConfirmIcon">⚠️</span>'
      +       '<p id="customConfirmTitle">Konfirmasi</p>'
      +     '</div>'
      +     '<div id="customConfirmBody">'
      +       '<p id="customConfirmMessage"></p>'
      +       '<ul class="confirm-bullet-list" id="customConfirmBullets"></ul>'
      +     '</div>'
      +     '<div id="customConfirmFooter">'
      +       '<button class="confirm-btn" id="customConfirmCancel">Batal</button>'
      +       '<button class="confirm-btn" id="customConfirmOK">Ya, Lanjutkan</button>'
      +     '</div>'
      +   '</div>'
      + '</div>';
    document.body.insertAdjacentHTML('beforeend', html);

    /* Tutup saat klik overlay (di luar box) */
    document.getElementById('customConfirmOverlay').addEventListener('click', function (e) {
      if (e.target === this) tutupConfirm();
    });

    /* Tombol Batal */
    document.getElementById('customConfirmCancel').addEventListener('click', tutupConfirm);

    /* ESC untuk tutup */
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') tutupConfirm();
    });
  }

  /* ── Tutup popup ── */
  function tutupConfirm() {
    var overlay = document.getElementById('customConfirmOverlay');
    if (overlay) overlay.classList.remove('show');
    document.body.style.overflow = '';
  }

  /* ── Fungsi utama ── */
  window.confirmPopup = function (opts) {
    injectHTML();

    opts = opts || {};

    var overlay  = document.getElementById('customConfirmOverlay');
    var icon     = document.getElementById('customConfirmIcon');
    var title    = document.getElementById('customConfirmTitle');
    var message  = document.getElementById('customConfirmMessage');
    var bullets  = document.getElementById('customConfirmBullets');
    var btnOK    = document.getElementById('customConfirmOK');
    var btnBatal = document.getElementById('customConfirmCancel');

    /* Tipe & ikon default per tipe */
    var tipe = opts.tipe || 'warning';
    var ikonDefault = { warning: '⚠️', danger: '🗑️', success: '✅', info: 'ℹ️' };

    /* Set atribut tipe untuk CSS */
    overlay.setAttribute('data-type', tipe);

    /* Isi konten */
    icon.textContent    = opts.ikon    || ikonDefault[tipe] || '⚠️';
    title.textContent   = opts.judul   || 'Konfirmasi';
    message.textContent = opts.pesan   || '';
    btnOK.textContent   = opts.labelOK    || 'Ya, Lanjutkan';
    btnBatal.textContent = opts.labelBatal || 'Batal';

    /* Bullet list */
    bullets.innerHTML = '';
    if (opts.poin && opts.poin.length > 0) {
      opts.poin.forEach(function (p) {
        var li = document.createElement('li');
        li.textContent = p;
        bullets.appendChild(li);
      });
      bullets.style.display = '';
    } else {
      bullets.style.display = 'none';
    }

    /* Aksi tombol OK */
    var newBtn = btnOK.cloneNode(true); /* clone untuk hapus event lama */
    newBtn.textContent = opts.labelOK || 'Ya, Lanjutkan';
    btnOK.parentNode.replaceChild(newBtn, btnOK);

    newBtn.addEventListener('click', function () {
      tutupConfirm();
      if (typeof opts.onOK === 'function') {
        opts.onOK();
      } else if (opts.url) {
        window.location.href = opts.url;
      }
    });

    /* Tampilkan */
    overlay.classList.add('show');
    document.body.style.overflow = 'hidden';
  };

})();


/* ================================================================
   CONTOH PENGGUNAAN SIAP PAKAI — salin ke halaman PHP Anda
   ================================================================

   ── 1. NONAKTIFKAN PENJUAL ──
   <a href="admin_kelola_penjual.php?nonaktifkan=<?= $p['id_penjual'] ?>"
      onclick="confirmPopup({
        judul  : 'Nonaktifkan Akun Penjual',
        ikon   : '🚫',
        pesan  : 'Yakin ingin menonaktifkan akun penjual ini?',
        poin   : [
          'id_kantin akan di-set NULL',
          'Semua menu milik penjual ini akan dinonaktifkan',
          'Data akun dan rekap transaksi tetap tersimpan'
        ],
        tipe   : 'warning',
        labelOK: 'Ya, Nonaktifkan',
        url    : this.href
      }); return false;">
     🚫 Nonaktifkan
   </a>

   ── 2. AKTIFKAN KEMBALI ──
   <a href="admin_kelola_penjual.php?aktifkan=<?= $p['id_penjual'] ?>"
      onclick="confirmPopup({
        judul  : 'Aktifkan Kembali Penjual',
        ikon   : '✅',
        pesan  : 'Aktifkan kembali akun ini?',
        poin   : [
          'Kantin asal akan dikembalikan',
          'Menu-menu milik penjual ini akan diaktifkan kembali'
        ],
        tipe   : 'success',
        labelOK: 'Ya, Aktifkan',
        url    : this.href
      }); return false;">
     ✅ Aktifkan
   </a>

   ── 3. HAPUS PERMANEN PENJUAL ──
   <a href="admin_kelola_penjual.php?hapus_permanen=<?= $p['id_penjual'] ?>"
      onclick="confirmPopup({
        judul  : 'Hapus Akun Penjual',
        ikon   : '🗑️',
        pesan  : 'Akun penjual ini akan disembunyikan dari daftar.',
        poin   : [
          'Data akun tetap tersimpan di database',
          'Semua menu ikut disembunyikan (soft delete)',
          'Rekap transaksi historis tetap bisa diakses',
          'Kantin bisa digunakan penjual baru'
        ],
        tipe   : 'danger',
        labelOK: 'Ya, Hapus',
        url    : this.href
      }); return false;">
     🗑 Hapus
   </a>

   ── 4. APPROVE QRIS ──
   <a href="admin_kelola_penjual.php?approve_qris=<?= $qris['id_qris'] ?>"
      onclick="confirmPopup({
        judul  : 'Setujui QRIS',
        ikon   : '✅',
        pesan  : 'Setujui QRIS ini?',
        tipe   : 'success',
        labelOK: 'Ya, Setujui',
        url    : this.href
      }); return false;">
     ✅ Approve
   </a>

   ── 5. TOLAK QRIS ──
   <a href="admin_kelola_penjual.php?reject_qris=<?= $qris['id_qris'] ?>"
      onclick="confirmPopup({
        judul  : 'Tolak & Hapus QRIS',
        ikon   : '❌',
        pesan  : 'Tolak dan hapus QRIS ini?',
        tipe   : 'danger',
        labelOK: 'Ya, Tolak',
        url    : this.href
      }); return false;">
     ❌ Tolak
   </a>

   ── 6. HAPUS QRIS MANUAL ──
   <a href="admin_kelola_penjual.php?hapus_qris=<?= $qris['id_qris'] ?>"
      onclick="confirmPopup({
        judul  : 'Hapus QRIS',
        ikon   : '🗑️',
        pesan  : 'Yakin hapus QRIS ini?',
        tipe   : 'danger',
        labelOK: 'Ya, Hapus',
        url    : this.href
      }); return false;">
     🗑 Hapus
   </a>

   ================================================================ */
