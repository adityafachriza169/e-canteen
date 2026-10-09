/**
 * =================================================================
 * detail_pesanan_popup.js  — v2 (pesanan + notifikasi)
 *
 * CARA PAKAI:
 * Tambahkan di pesan.php SETELAH pesan_js_patch.js:
 *   <script src="detail_pesanan_popup.js"></script>
 *
 * FITUR:
 * - Klik card PESANAN → popup detail
 * - Klik card NOTIFIKASI → popup detail + markAsRead otomatis
 * - Detail: nama menu, kantin, qty, total, status, tanggal, metode bayar
 * - Tombol Download Struk / Lihat Struk sesuai status
 * - ESC / klik overlay → tutup
 * =================================================================
 */

/* ══════════════════════════════════════════
   INJECT MODAL HTML ke <body>
══════════════════════════════════════════ */
(function injectDetailModal() {
    if (document.getElementById('detailPesananOverlay')) return;

    const html = `
    <div class="detail-pesanan-overlay" id="detailPesananOverlay">
        <div class="detail-pesanan-box" id="detailPesananBox">
            <div class="detail-pesanan-handle"></div>

            <!-- Header -->
            <div class="detail-pesanan-header">
                <h2 id="dpTitle">Detail Pesanan</h2>
                <button class="detail-pesanan-close" id="dpClose" aria-label="Tutup">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>

            <!-- Kantin strip -->
            <div class="detail-kantin-strip">
                <div class="detail-kantin-icon">
                    <i class="fa-solid fa-store"></i>
                </div>
                <div>
                    <div class="detail-kantin-name" id="dpKantin">—</div>
                    <div class="detail-kantin-sub" id="dpNoInvoice">#INV—</div>
                </div>
            </div>

            <!-- Produk item -->
            <div class="detail-produk-item">
                <img class="detail-produk-img" id="dpFoto" src="" alt="">
                <div style="flex:1;min-width:0">
                    <div class="detail-produk-name" id="dpNamaProduk">—</div>
                    <div class="detail-produk-qty"  id="dpQty">—</div>
                </div>
                <div class="detail-produk-price" id="dpPriceItem">—</div>
            </div>

            <!-- Info rows -->
            <div class="detail-info-section">
                <div class="detail-info-row">
                    <span class="detail-info-label"><i class="fa-regular fa-calendar" style="width:14px"></i> Tanggal</span>
                    <span class="detail-info-value" id="dpTanggal">—</span>
                </div>
                <div class="detail-info-row" id="dpMetodeRow" style="display:none">
                    <span class="detail-info-label"><i class="fa-solid fa-wallet" style="width:14px"></i> Metode Bayar</span>
                    <span class="detail-info-value" id="dpMetode">—</span>
                </div>
                <div class="detail-info-row" id="dpAntrianRow" style="display:none">
                    <span class="detail-info-label"><i class="fa-solid fa-hashtag" style="width:14px"></i> No. Antrian</span>
                    <span class="detail-info-value" id="dpAntrian">—</span>
                </div>
                <div class="detail-info-row">
                    <span class="detail-info-label"><i class="fa-solid fa-circle-dot" style="width:14px"></i> Status</span>
                    <span class="detail-info-value">
                        <span class="detail-status-badge status-badge" id="dpStatusBadge">—</span>
                    </span>
                </div>
                <div class="detail-info-row" id="dpNotifPesanRow" style="display:none">
                    <span class="detail-info-label"><i class="fa-solid fa-comment" style="width:14px"></i> Info</span>
                    <span class="detail-info-value" id="dpNotifPesan" style="color:#64748b;font-weight:500">—</span>
                </div>
            </div>

            <!-- Total -->
            <div class="detail-total-row">
                <span class="detail-total-label">Total Pembayaran</span>
                <span class="detail-total-val" id="dpTotal">—</span>
            </div>

            <!-- Actions -->
            <div class="detail-action-row" id="dpActions"></div>
        </div>
    </div>`;

    document.body.insertAdjacentHTML('beforeend', html);

    document.getElementById('detailPesananOverlay').addEventListener('click', function(e) {
        if (e.target === this) closeDetailPesanan();
    });
    document.getElementById('dpClose').addEventListener('click', closeDetailPesanan);
})();

/* ══════════════════════════════════════════
   BUKA MODAL DETAIL
   Bisa dipanggil dari pesanan maupun notifikasi
══════════════════════════════════════════ */
function openDetailPesanan(t) {
    const [lblStatus, clsBadge] = labelStatus(t.status);

    /* ── Nomor invoice / antrian ── */
    let invNo;
    if (t.antrian > 0) {
        invNo = '#KANTIN' + (t.id_kantin || '?') + ' · Antrian #' + t.antrian;
    } else {
        invNo = '#INV' + String(t.id_transaksi).padStart(5, '0');
    }

    /* ── Isi field ── */
    document.getElementById('dpTitle').textContent        = 'Detail Pesanan';
    document.getElementById('dpKantin').textContent       = t.nama_kantin   || 'Kantin';
    document.getElementById('dpNoInvoice').textContent    = invNo;
    document.getElementById('dpNamaProduk').textContent   = t.nama_makanan  || '—';
    document.getElementById('dpQty').textContent          = 'Jumlah: ' + (t.jumlah || 1) + 'x';
    document.getElementById('dpTanggal').textContent      = formatTanggal(t.tanggal);
    document.getElementById('dpTotal').textContent        = formatRupiah(t.total);
    document.getElementById('dpPriceItem').textContent    = formatRupiah(t.total);

    /* Foto */
    const foto = document.getElementById('dpFoto');
    foto.src = fotoPenjual(t.foto);
    foto.onerror = () => { foto.src = '../gambar/default-food.png'; };
    foto.alt = t.nama_makanan || '';

    /* Status badge */
    const badge = document.getElementById('dpStatusBadge');
    badge.className = 'detail-status-badge status-badge ' + clsBadge;
    badge.innerHTML = `<i class="fa-solid fa-circle" style="font-size:7px"></i> ${lblStatus}`;

    /* Metode bayar */
    const metodeRow = document.getElementById('dpMetodeRow');
    if (t.metode) {
        document.getElementById('dpMetode').textContent = t.metode;
        metodeRow.style.display = '';
    } else {
        metodeRow.style.display = 'none';
    }

    /* Antrian row */
const antrianRow = document.getElementById('dpAntrianRow');
if (t.antrian > 0) {
    document.getElementById('dpAntrian').innerHTML =
        '#' + t.antrian +
        '<br><small>Kode: #KANTIN' +
        (t.id_kantin || 1) +
        'KNVV' +
        String(t.antrian).padStart(3,'0') +
        '</small>';

    antrianRow.style.display = '';
} else {
    antrianRow.style.display = 'none';
}

    /* Pesan notifikasi (kalau ada) */
    const notifRow = document.getElementById('dpNotifPesanRow');
    const notifTxt = t.notif_pesan || '';
    if (notifTxt) {
        document.getElementById('dpNotifPesan').textContent = notifTxt;
        notifRow.style.display = '';
    } else {
        notifRow.style.display = 'none';
    }

    /* ── Tombol aksi ── */
    const actions = document.getElementById('dpActions');
    actions.innerHTML = '';

    const btnTutup = `<button class="btn-detail-secondary" onclick="closeDetailPesanan()">
        <i class="fa-solid fa-xmark"></i> Tutup
    </button>`;

    if (t.status === 'pending') {
        actions.innerHTML = `
            <button class="btn-detail-secondary" onclick="closeDetailPesanan(); batalPesanan(${t.id_transaksi})">
                <i class="fa-solid fa-ban"></i> Batalkan
            </button>
            ${btnTutup}`;

    } else if (t.status === 'dimasak' || t.status === 'siap') {
        actions.innerHTML = `
            <button class="btn-detail-struk" onclick="closeDetailPesanan(); _showStrukAuto(${t.id_transaksi})">
                <i class="fa-solid fa-download"></i> Download Struk
            </button>
            ${btnTutup}`;

    } else if (t.status === 'selesai') {
        actions.innerHTML = `
            <button class="btn-detail-struk purple" onclick="closeDetailPesanan(); _showStrukAuto(${t.id_transaksi})">
                <i class="fa-solid fa-receipt"></i> Lihat &amp; Tunjukkan Struk
            </button>
            ${btnTutup}`;

    } else if (t.status === 'lunas') {
        const idMenu = t.id_menu || 0;
        actions.innerHTML = `
            <button class="btn-detail-struk" onclick="closeDetailPesanan(); _showStrukAuto(${t.id_transaksi})">
                <i class="fa-solid fa-download"></i> Download Struk
            </button>
            <button class="btn-detail-secondary" onclick="closeDetailPesanan(); beliLagi(${idMenu},'${(t.nama_makanan||'').replace(/'/g,"\\'")}')">
                <i class="fa-solid fa-rotate-right"></i> Beli Lagi
            </button>`;

    } else {
        /* ditolak / status lain */
        actions.innerHTML = btnTutup;
    }

    /* ── Tampilkan ── */
    document.body.style.overflow = 'hidden';
    document.getElementById('detailPesananOverlay').classList.add('open');
}

/* Helper: coba dari _allPesanan dulu, fallback ke _notifData */
function _showStrukAuto(id) {
    const t = (_allPesanan || []).find(x => x.id_transaksi == id)
           || (_notifData   || []).find(x => x.id_transaksi == id);
    if (t) {
        if (typeof showStrukById === 'function' && (_allPesanan || []).find(x => x.id_transaksi == id)) {
            showStrukById(id);
        } else {
            showStrukFromNotif(id, null);
        }
    } else {
        showStrukFromNotif(id, null);
    }
}

/* ══════════════════════════════════════════
   TUTUP MODAL
══════════════════════════════════════════ */
function closeDetailPesanan() {
    document.getElementById('detailPesananOverlay').classList.remove('open');
    document.body.style.overflow = '';
}

/* ESC key */
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeDetailPesanan();
});

/* ══════════════════════════════════════════
   PATCH renderCardPesanan
   Tambahkan onclick ke wrapper card
   tanpa mengganggu tombol di dalamnya
══════════════════════════════════════════ */
(function patchRenderCardPesanan() {
    const tryPatch = () => {
        if (typeof window.renderCardPesanan !== 'function') return false;
        const _orig = window.renderCardPesanan;
        window.renderCardPesanan = function(t) {
            let html = _orig(t);
            /* Tambahkan data-tid dan onclick ke .order-card-item */
            html = html.replace(
                /(<div class="order-card-item"[^>]*)(>)/,
                `$1 data-tid="${t.id_transaksi}" onclick="handleCardClick(event,${t.id_transaksi},'pesanan')"$2`
            );
            return html;
        };
        return true;
    };

    if (!tryPatch()) {
        /* Bila belum tersedia saat load, tunggu */
        document.addEventListener('DOMContentLoaded', tryPatch);
    }
})();

/* ══════════════════════════════════════════
   PATCH renderCardNotif
   Tambahkan klik detail ke .notif-card-db
   markAsRead tetap jalan seperti biasa
══════════════════════════════════════════ */
(function patchRenderCardNotif() {
    const tryPatch = () => {
        if (typeof window.renderCardNotif !== 'function') return false;
        const _orig = window.renderCardNotif;
        window.renderCardNotif = function(t) {
            let html = _orig(t);
            /* Ganti onclick lama (markAsRead) dengan handler baru yang juga buka detail */
            html = html.replace(
                /onclick="markAsRead\(([^)]+)\)"/,
                `onclick="handleCardClick(event,${t.id_transaksi},'notif')"`
            );
            return html;
        };
        return true;
    };

    if (!tryPatch()) {
        document.addEventListener('DOMContentLoaded', tryPatch);
    }
})();

/* ══════════════════════════════════════════
   handleCardClick
   Dipakai oleh card pesanan & card notif
   Buka detail, dan kalau notif juga markAsRead
══════════════════════════════════════════ */
function handleCardClick(event, id, source) {
    /* Jangan buka popup kalau klik tombol/link di dalam card */
    const skipEl = event.target.closest(
        'button, a, .btn-batal, .btn-struk, .btn-nilai, .btn-ulang, ' +
        '.btn-disabled, .info-siap, .info-tidak-batal, .info-tunggu-lunas, .notif-actions'
    );
    if (skipEl) return;

    /* Cari data transaksi */
    const t = (_allPesanan || []).find(x => x.id_transaksi == id)
           || (_notifData   || []).find(x => x.id_transaksi == id);

    if (!t) return;

    /* Kalau dari notif → markAsRead dulu (silent) */
    if (source === 'notif') {
        const cardEl = document.getElementById('notif-card-' + id);
        if (cardEl && typeof markAsRead === 'function') {
            markAsRead(id, cardEl);
        }
    }

    openDetailPesanan(t);
}

/* ══════════════════════════════════════════
   CSS INJECT (biar satu file, tidak perlu .css terpisah)
══════════════════════════════════════════ */
(function injectDetailStyles() {
    if (document.getElementById('detailPesananStyles')) return;
    const s = document.createElement('style');
    s.id = 'detailPesananStyles';
    s.textContent = `
/* Overlay */
.detail-pesanan-overlay{position:fixed;inset:0;background:rgba(0,0,0,.52);backdrop-filter:blur(6px);-webkit-backdrop-filter:blur(6px);z-index:8000;display:flex;align-items:flex-end;justify-content:center;padding:0;opacity:0;visibility:hidden;transition:opacity .28s,visibility .28s}
.detail-pesanan-overlay.open{opacity:1;visibility:visible}
/* Box */
.detail-pesanan-box{background:#fff;width:100%;max-width:540px;border-radius:24px 24px 0 0;padding:0 0 32px;max-height:92dvh;overflow-y:auto;box-shadow:0 -8px 40px rgba(0,0,0,.18);transform:translateY(30px);transition:transform .32s cubic-bezier(.34,1.2,.64,1);scrollbar-width:none}
.detail-pesanan-box::-webkit-scrollbar{display:none}
.detail-pesanan-overlay.open .detail-pesanan-box{transform:translateY(0)}
@media(min-width:600px){
  .detail-pesanan-overlay{align-items:center;padding:20px}
  .detail-pesanan-box{border-radius:20px;max-height:88dvh;transform:scale(.94) translateY(16px)}
  .detail-pesanan-overlay.open .detail-pesanan-box{transform:scale(1) translateY(0)}
}
/* Handle */
.detail-pesanan-handle{width:40px;height:4px;background:#e2e8f0;border-radius:99px;margin:14px auto 0}
/* Header */
.detail-pesanan-header{display:flex;align-items:center;gap:12px;padding:18px 20px 14px;border-bottom:1px solid #f1f5f9;position:sticky;top:0;background:#fff;z-index:2}
.detail-pesanan-header h2{font-size:16px;font-weight:800;color:#1a0a00;flex:1}
.detail-pesanan-close{width:32px;height:32px;border-radius:50%;background:#f1f5f9;display:flex;align-items:center;justify-content:center;color:#64748b;font-size:14px;cursor:pointer;flex-shrink:0;transition:background .15s;border:none}
.detail-pesanan-close:hover{background:#e2e8f0}
/* Kantin strip */
.detail-kantin-strip{display:flex;align-items:center;gap:12px;padding:16px 20px;background:#fffbf7;border-bottom:1px solid #fde8d0}
.detail-kantin-icon{width:42px;height:42px;border-radius:12px;background:linear-gradient(135deg,#e8650a,#f97316);display:flex;align-items:center;justify-content:center;color:#fff;font-size:18px;flex-shrink:0}
.detail-kantin-name{font-size:15px;font-weight:800;color:#1a0a00}
.detail-kantin-sub{font-size:12px;color:#a07050;margin-top:2px}
/* Produk */
.detail-produk-item{display:flex;align-items:center;gap:14px;padding:16px 20px;border-bottom:1px solid #f8fafc}
.detail-produk-img{width:64px;height:64px;border-radius:12px;object-fit:cover;flex-shrink:0;border:1px solid #f0e8df}
.detail-produk-name{font-size:14px;font-weight:700;color:#1a0a00;word-break:break-word}
.detail-produk-qty{font-size:13px;color:#64748b;margin-top:3px}
.detail-produk-price{font-size:14px;font-weight:800;color:#e8650a;margin-left:auto;white-space:nowrap;padding-left:10px}
/* Info rows */
.detail-info-section{padding:14px 20px 0}
.detail-info-row{display:flex;align-items:flex-start;justify-content:space-between;gap:12px;padding:10px 0;border-bottom:1px solid #f8fafc}
.detail-info-row:last-child{border-bottom:none}
.detail-info-label{font-size:13px;color:#94a3b8;flex-shrink:0;min-width:100px}
.detail-info-value{font-size:13px;font-weight:600;color:#334155;text-align:right}
/* Status badge */
.detail-status-badge{display:inline-flex;align-items:center;gap:5px;padding:3px 10px;border-radius:99px;font-size:12px;font-weight:700}
/* Total */
.detail-total-row{display:flex;align-items:center;justify-content:space-between;padding:16px 20px;background:#fffbf7;border-top:2px dashed #fde8d0;margin-top:12px}
.detail-total-label{font-size:14px;color:#7a5a40;font-weight:600}
.detail-total-val{font-size:20px;font-weight:800;color:#e8650a}
/* Actions */
.detail-action-row{display:flex;gap:10px;padding:16px 20px 0;flex-wrap:wrap}
.btn-detail-struk{flex:1;min-width:130px;padding:12px 16px;border-radius:12px;background:linear-gradient(135deg,#e8650a,#f97316);color:#fff;font-size:13px;font-weight:700;display:flex;align-items:center;justify-content:center;gap:7px;cursor:pointer;border:none;font-family:'Plus Jakarta Sans',sans-serif;transition:opacity .15s,transform .1s}
.btn-detail-struk:hover{opacity:.92}
.btn-detail-struk:active{transform:scale(.97)}
.btn-detail-struk.purple{background:linear-gradient(135deg,#8b5cf6,#7c3aed)}
.btn-detail-secondary{flex:1;min-width:100px;padding:12px 16px;border-radius:12px;background:#f8fafc;border:1.5px solid #e2e8f0;color:#475569;font-size:13px;font-weight:700;display:flex;align-items:center;justify-content:center;gap:7px;cursor:pointer;font-family:'Plus Jakarta Sans',sans-serif;transition:background .15s}
.btn-detail-secondary:hover{background:#f1f5f9}
/* Card hover */
.order-card-item{cursor:pointer;transition:transform .15s,box-shadow .15s}
.order-card-item:hover{transform:translateY(-2px);box-shadow:0 8px 28px rgba(232,101,10,.14)}
.order-card-item:active{transform:translateY(0)}
/* Notif card hover */
.notif-card-db{cursor:pointer;transition:background .15s,transform .12s}
.notif-card-db:hover{background:#fffbf7 !important;transform:translateX(2px)}
.notif-card-db:active{transform:translateX(0)}
    `;
    document.head.appendChild(s);
})();