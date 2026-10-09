/**
 * =================================================================
 * pesan_js_patch.js  —  FIXED VERSION
 *
 * Perbaikan:
 * 1. Mendefinisikan fungsi bukaRating() yang sebelumnya hilang
 * 2. Mendefinisikan fungsi beliLagi() dengan redirect ke menu_klik.php
 * 3. renderCardPesanan() versi final (override versi di pesan.php)
 * 4. Polling notif badge real-time tiap 15 detik
 * 5. markAsRead() untuk card notifikasi
 * =================================================================
 */

/* ══════════════════════════════════════════
   STATE NOTIF TERPUSAT
══════════════════════════════════════════ */
let _notifUnreadCount  = (typeof PHP !== 'undefined') ? PHP.totalNotif : 0;
let _lastKnownStatuses = {};
let _notifData         = [];

/* ══════════════════════════════════════════
   BUKA RATING  ← fungsi yang sebelumnya HILANG
   Redirect ke rating.php?id_transaksi=xxx
══════════════════════════════════════════ */
function bukaRating(id_transaksi, namaProduk) {
    if (!id_transaksi) {
        toast('❌ Data transaksi tidak ditemukan');
        return;
    }
    window.location.href = 'rating.php?id_transaksi=' + id_transaksi;
}

/* ══════════════════════════════════════════
   BELI LAGI
   Redirect ke menu_klik.php?id=<id_menu>
══════════════════════════════════════════ */
function beliLagi(idMenu, namaProduk) {
    if (!idMenu) {
        toast('❌ Data menu tidak ditemukan');
        return;
    }
    window.location.href = 'klik.php?id=' + idMenu;
}

/* ══════════════════════════════════════════
   UPDATE SEMUA BADGE NOTIFIKASI
══════════════════════════════════════════ */
function updateAllNotifBadges(count) {
    _notifUnreadCount = count;

    /* Topbar pill (desktop) */
    const topbarLink = document.querySelector('.topbar .top-right a[href*="notif"]');
    if (topbarLink) {
        let pill = document.getElementById('topbarNotifPill');
        if (count > 0) {
            if (!pill) {
                pill = document.createElement('span');
                pill.className = 'notif-pill';
                pill.id = 'topbarNotifPill';
                topbarLink.appendChild(pill);
            }
            pill.textContent = count;
            pill.style.display = '';
        } else if (pill) {
            pill.style.display = 'none';
        }
    }

    /* Sidebar pill (desktop) */
    const sidebarNotifMenu = [...document.querySelectorAll('.sidebar-menu')].find(m =>
        m.querySelector('span')?.innerText.trim() === 'Notifikasi'
    );
    if (sidebarNotifMenu) {
        let pill = document.getElementById('sidebarNotifPill');
        if (count > 0) {
            if (!pill) {
                pill = document.createElement('span');
                pill.className = 'sidebar-notif-pill';
                pill.id = 'sidebarNotifPill';
                sidebarNotifMenu.appendChild(pill);
            }
            pill.textContent = count;
            pill.style.display = '';
        } else if (pill) {
            pill.style.display = 'none';
        }
    }

    /* Drawer badge (mobile hamburger) */
    const drawerNotifBtn = [...document.querySelectorAll('.drawer-item')].find(el =>
        el.querySelector('span:not(.drawer-item-icon):not(.drawer-badge)')?.innerText.trim() === 'Notifikasi'
    );
    if (drawerNotifBtn) {
        let badge = document.getElementById('drawerNotifBadge');
        if (count > 0) {
            if (!badge) {
                badge = document.createElement('span');
                badge.className = 'drawer-badge';
                badge.id = 'drawerNotifBadge';
                drawerNotifBtn.appendChild(badge);
            }
            badge.textContent = count;
            badge.style.display = '';
        } else if (badge) {
            badge.style.display = 'none';
        }
    }

    /* Mobile top-right bell */
    const mobBell = document.querySelector('.mob-icons .mob-icon-btn:nth-child(2)');
    if (mobBell) {
        let badge = document.getElementById('mobileNotifBadge');
        if (count > 0) {
            if (!badge) {
                badge = document.createElement('div');
                badge.className = 'mob-badge';
                badge.id = 'mobileNotifBadge';
                mobBell.appendChild(badge);
            }
            badge.textContent = count;
            badge.style.display = '';
        } else if (badge) {
            badge.style.display = 'none';
        }
    }

    /* Bottom nav "Pesanan" badge */
    const btnNavPesanan = document.getElementById('btnNavPesanan');
    if (btnNavPesanan) {
        let badge = document.getElementById('bottomNavNotifBadge');
        if (count > 0) {
            if (!badge) {
                badge = document.createElement('div');
                badge.className = 'mob-nav-badge';
                badge.id = 'bottomNavNotifBadge';
                btnNavPesanan.appendChild(badge);
            }
            badge.textContent = count;
            badge.style.display = '';
        } else if (badge) {
            badge.style.display = 'none';
        }
    }
}

/* ══════════════════════════════════════════
   POLL NOTIF BADGE (tiap 15 detik)
══════════════════════════════════════════ */
async function pollNotifBadge() {
    try {
        const res  = await fetch('get_notifikasi.php');
        const data = await res.json();
        if (data.status !== 'ok') return;

        const list = data.data || [];
        const unreadCount = list.filter(t => Number(t.is_read) === 0).length;

        list.forEach(t => {
            const prev = _lastKnownStatuses[t.id_transaksi];
            if (prev !== undefined && prev !== t.status) {
                const judul = t.notif_judul || 'Update Pesanan';
                if (typeof toast === 'function') toast('🔔 ' + judul);
            }
            _lastKnownStatuses[t.id_transaksi] = t.status;
        });

        updateAllNotifBadges(unreadCount);

        /* Refresh card notif jika halaman notif aktif */
        const notifList = document.getElementById('notifList');
        if (notifList && document.querySelector('.notif-title')) {
            _notifData = list;
            list.forEach(t => {
                const existing = document.getElementById('notif-card-' + t.id_transaksi);
                if (!existing) {
                    const tmp = document.createElement('div');
                    tmp.innerHTML = renderCardNotif(t);
                    notifList.prepend(tmp.firstElementChild);
                } else {
                    const isUnread = Number(t.is_read) === 0;
                    existing.classList.toggle('unread', isUnread);
                    let dot = existing.querySelector('.notif-unread-dot');
                    if (isUnread && !dot) {
                        dot = document.createElement('span');
                        dot.className = 'notif-unread-dot';
                        const iconLabel = existing.querySelector('.notif-icon-label');
                        if (iconLabel) iconLabel.appendChild(dot);
                    } else if (!isUnread && dot) {
                        dot.remove();
                    }
                }
            });
        }
    } catch (e) { /* silent fail */ }
}

/* ══════════════════════════════════════════
   MARK AS READ
══════════════════════════════════════════ */
function markAsRead(id_transaksi, cardEl) {
    if (cardEl.dataset.read === '1') return;
    cardEl.dataset.read = '1';
    fetch('notif_read.php', {
        method : 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body   : 'id_transaksi=' + id_transaksi
    })
    .then(r => r.json())
    .then(data => {
        if (data.status === 'ok') {
            cardEl.classList.remove('unread');
            const dot = cardEl.querySelector('.notif-unread-dot');
            if (dot) dot.remove();
            const newCount = typeof data.total_notif !== 'undefined'
                ? parseInt(data.total_notif)
                : Math.max(0, _notifUnreadCount - 1);
            updateAllNotifBadges(newCount);
        } else {
            cardEl.dataset.read = '';
        }
    })
    .catch(() => { cardEl.dataset.read = ''; });
}

/* ══════════════════════════════════════════
   RENDER CARD NOTIFIKASI
══════════════════════════════════════════ */
function renderCardNotif(t) {
    const [lblStatus, clsBadge, icoStatus] = labelStatus(t.status);
    const imgSrc   = fotoPenjual(t.foto);
    const isUnread = Number(t.is_read) === 0;

    let aksi = '';
    if (t.status === 'dimasak' || t.status === 'siap') {
        aksi = `<button class="btn-struk"
                    onclick="event.stopPropagation();showStrukFromNotif(${t.id_transaksi},this)">
                    <i class="fa-solid fa-download"></i> Download Struk
                </button>`;
    } else if (t.status === 'selesai') {
        aksi = `<button class="btn-struk btn-struk-show"
                    onclick="event.stopPropagation();showStrukFromNotif(${t.id_transaksi},this)">
                    <i class="fa-solid fa-receipt"></i> Lihat &amp; Tunjukkan Struk
                </button>`;
    } else if (t.status === 'lunas') {
        aksi = `<button class="btn-nilai"
                    onclick="event.stopPropagation();bukaRating(${t.id_transaksi},'${(t.nama_makanan||'').replace(/'/g,"\\'")}')">
                    <i class="fa-regular fa-star"></i> Beri Rating
                </button>`;
    }

    return `
    <div class="notif-card-db ${isUnread ? 'unread' : ''}"
         id="notif-card-${t.id_transaksi}"
         data-id="${t.id_transaksi}"
         onclick="markAsRead(${t.id_transaksi}, this)">
        <div class="notif-top">
            <div class="notif-icon-label">
                <i class="fa-solid ${icoStatus}" style="color:#e8650a"></i>
                <span class="status-badge ${clsBadge}">${lblStatus}</span>
                ${isUnread ? '<span class="notif-unread-dot"></span>' : ''}
            </div>
            <span class="notif-time">${selisihWaktu(t.tanggal)}</span>
        </div>
        ${t.notif_judul ? `<div class="notif-judul">${t.notif_judul}</div>` : ''}
        <div class="notif-body">
            <img class="notif-img" src="${imgSrc}"
                 onerror="this.src='../gambar/default-food.png'" alt="">
            <div class="notif-detail-info">
                <h4>${t.nama_makanan || '-'}</h4>
                <p>${t.nama_kantin || '-'} &nbsp;·&nbsp; ${formatRupiah(t.total)}</p>
                <p style="font-size:12px;color:#999;margin-top:2px">${t.notif_pesan || ''}</p>
                <p style="font-size:11px;color:#bbb;margin-top:4px">${formatTanggal(t.tanggal)}</p>
                ${aksi ? `<div class="notif-actions">${aksi}</div>` : ''}
            </div>
        </div>
    </div>`;
}

/* ══════════════════════════════════════════
   RENDER CARD PESANAN  ← override versi di pesan.php
   Tombol "Beri Rating" dan "Beli Lagi" sudah pakai
   bukaRating() dan beliLagi() yang terdefinisi di atas
══════════════════════════════════════════ */
function renderCardPesanan(t) {
    const [lblStatus, clsBadge, icoStatus] = labelStatus(t.status);
    const imgSrc = fotoPenjual(t.foto);
    let tombol = '';

    if (t.status === 'pending') {
        tombol = `<button class="btn-batal" onclick="batalPesanan(${t.id_transaksi})">
                    <i class="fa-solid fa-ban"></i> Batalkan
                  </button>`;

    } else if (t.status === 'dimasak') {
        tombol = `<button class="btn-struk" onclick="showStrukById(${t.id_transaksi})">
                    <i class="fa-solid fa-download"></i> Download Struk
                  </button>
                  <span class="info-tidak-batal">
                    <i class="fa-solid fa-lock"></i> Tidak dapat dibatalkan
                  </span>`;

    } else if (t.status === 'siap') {
        tombol = `<button class="btn-struk" onclick="showStrukById(${t.id_transaksi})">
                    <i class="fa-solid fa-download"></i> Download Struk
                  </button>
                  <span class="info-siap">
                    <i class="fa-solid fa-bell"></i> Segera ambil!
                  </span>`;

    } else if (t.status === 'selesai') {
        tombol = `<button class="btn-struk btn-struk-show" onclick="showStrukById(${t.id_transaksi})">
                    <i class="fa-solid fa-receipt"></i> Lihat &amp; Tunjukkan Struk
                  </button>
                  <span class="info-tunggu-lunas">
                    <i class="fa-solid fa-circle-info"></i> Tunjukkan ke penjual untuk konfirmasi
                  </span>`;

    } else if (t.status === 'lunas') {
        /* ── TOMBOL BERI RATING & BELI LAGI ── */
        const idMenu   = t.id_menu || '';
        const namaSafe = (t.nama_makanan || '').replace(/'/g, "\\'");
        // Cek apakah sudah pernah rating (disimpan di localStorage)
        const sudahRating = localStorage.getItem('rated_trx_' + t.id_transaksi) === '1';
        const ratingBtn = sudahRating
            ? `<span class="btn-disabled" style="color:#15803d;background:#f0fdf4;border:1.5px solid #86efac;">
                 <i class="fa-solid fa-star"></i> Sudah Dinilai
               </span>`
            : `<button class="btn-nilai"
                   style="min-height:44px;touch-action:manipulation;cursor:pointer;"
                   onclick="bukaRating(${t.id_transaksi},'${namaSafe}')">
                 <i class="fa-regular fa-star"></i> Beri Rating
               </button>`;
        tombol = ratingBtn +
                  `<button class="btn-ulang"
                      style="min-height:44px;touch-action:manipulation;"
                      onclick="beliLagi(${idMenu},'${namaSafe}')">
                    <i class="fa-solid fa-rotate-right"></i> Beli Lagi
                  </button>`;

    } else if (t.status === 'ditolak') {
        tombol = `<span class="btn-disabled">
                    <i class="fa-solid fa-ban"></i> Pesanan Ditolak
                  </span>`;
    }

    return `
    <div class="order-card-item" id="card-${t.id_transaksi}">
        <div class="order-card-header">
            <div class="order-card-header-left">
                <i class="fa-solid ${icoStatus}" style="color:#e8650a"></i>
                <h3>${t.nama_kantin || 'Kantin'}</h3>
                <span class="status-badge ${clsBadge}">${lblStatus}</span>
                ${t.antrian > 0
    ? `<span class="antrian-badge">
         Antrian #${t.antrian}<br>
       </span>`
    : ''}
            </div>
            <span class="order-card-date">${formatTanggal(t.tanggal)}</span>
        </div>
        <div class="order-card-body">
            <div class="order-item-row">
                <img class="order-item-img" src="${imgSrc}"
                     onerror="this.src='../gambar/default-food.png'"
                     alt="${t.nama_makanan || ''}">
                <div class="order-item-info">
                    <h4>${t.nama_makanan || '-'}</h4>
                    <p>Jumlah: ${t.jumlah}x</p>
                    ${t.metode ? `<small>Bayar via: ${t.metode}</small>` : ''}
                </div>
            </div>
            ${t.status === 'selesai' ? `
            <div class="struk-notice">
                <i class="fa-solid fa-triangle-exclamation"></i>
                Pesanan sudah siap. Download struk dan tunjukkan ke
                <strong>${t.nama_kantin}</strong> untuk konfirmasi pembayaran.
            </div>` : ''}
            ${t.status === 'lunas' ? `
            <div class="lunas-notice">
                <i class="fa-solid fa-circle-check"></i>
                Pesanan selesai &amp; lunas! Terima kasih sudah memesan.
                Jangan lupa beri rating!
            </div>` : ''}
        </div>
        <div class="order-card-footer">
            <div>
                <div class="order-total-label">Total Pesanan</div>
                <div class="order-total-val">${formatRupiah(t.total)}</div>
            </div>
            <div class="order-action-btns">${tombol}</div>
        </div>
    </div>`;
}

/* ══════════════════════════════════════════
   STRUK — dari halaman notif
══════════════════════════════════════════ */
function showStrukFromNotif(id, btn) {
    let t = (_notifData  || []).find(x => x.id_transaksi == id)
         || (_allPesanan || []).find(x => x.id_transaksi == id);

    if (!t) {
        if (btn) {
            btn.disabled = true;
            btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i>';
        }
        fetch('get_pesanan.php?filter=All')
            .then(r => r.json())
            .then(data => {
                t = (data.data || []).find(x => x.id_transaksi == id);
                if (t) {
                    showStruk({
                        no      : '#INV' + String(t.id_transaksi).padStart(5, '0'),
                        kantin  : t.nama_kantin  || '-',
                        produk  : t.nama_makanan || '-',
                        qty     : t.jumlah,
                        total   : formatRupiah(t.total),
                        status  : t.status,
                        tanggal : formatTanggal(t.tanggal)
                    });
                } else {
                    if (typeof toast === 'function') toast('Data tidak ditemukan');
                }
                if (btn) {
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fa-solid fa-receipt"></i> Lihat &amp; Tunjukkan Struk';
                }
            })
            .catch(() => {
                if (typeof toast === 'function') toast('Gagal memuat data');
                if (btn) btn.disabled = false;
            });
        return;
    }

    showStruk({
        no      : '#INV' + String(t.id_transaksi).padStart(5, '0'),
        kantin  : t.nama_kantin  || '-',
        produk  : t.nama_makanan || '-',
        qty     : t.jumlah,
        total   : formatRupiah(t.total),
        status  : t.status,
        tanggal : formatTanggal(t.tanggal)
    });
}

/* ══════════════════════════════════════════
   INISIALISASI POLLING
══════════════════════════════════════════ */
(function initNotifPolling() {
    setTimeout(async () => {
        try {
            const res  = await fetch('get_notifikasi.php');
            const data = await res.json();
            if (data.status === 'ok') {
                (data.data || []).forEach(t => {
                    _lastKnownStatuses[t.id_transaksi] = t.status;
                });
                const unread = (data.data || []).filter(t => Number(t.is_read) === 0).length;
                updateAllNotifBadges(unread);
            }
        } catch (e) {}
        setInterval(pollNotifBadge, 15000);
    }, 3000);
})();

/* ══════════════════════════════════════════
   CSS TAMBAHAN
══════════════════════════════════════════ */
(function injectNotifStyles() {
    const style = document.createElement('style');
    style.textContent = `
        .notif-unread-dot {
            width:8px; height:8px;
            background:#e8650a; border-radius:50%;
            display:inline-block; flex-shrink:0;
            margin-left:4px;
            animation:pulse-dot 1.8s infinite;
        }
        @keyframes pulse-dot {
            0%,100%{transform:scale(1);opacity:1}
            50%{transform:scale(1.4);opacity:.7}
        }
        .notif-judul {
            font-size:13px; font-weight:700;
            color:#1e293b; margin:6px 0 4px; padding-left:2px;
        }
        .badge-siap  { background:#eff6ff; color:#1d4ed8; }
        .badge-lunas { background:#f0fdf4; color:#15803d; }
        .info-tidak-batal {
            font-size:11px; color:#94a3b8;
            display:flex; align-items:center; gap:4px;
            padding:4px 10px; border-radius:50px; background:#f1f5f9;
        }
        .info-siap {
            font-size:12px; color:#1d4ed8; font-weight:700;
            display:flex; align-items:center; gap:4px;
            padding:4px 12px; border-radius:50px; background:#eff6ff;
            animation:pulse-blue 1.5s infinite;
        }
        @keyframes pulse-blue{0%,100%{opacity:1}50%{opacity:.6}}
        .info-tunggu-lunas {
            font-size:11px; color:#7c3aed;
            display:flex; align-items:center; gap:4px;
            padding:4px 10px; border-radius:50px; background:#f5f3ff;
        }
        .struk-notice {
            background:#f5f3ff; border:1px solid #e9d5ff;
            border-radius:10px; padding:10px 14px;
            font-size:12px; color:#7c3aed;
            display:flex; align-items:center; gap:8px; margin-top:10px;
        }
        .lunas-notice {
            background:#f0fdf4; border:1px solid #bbf7d0;
            border-radius:10px; padding:10px 14px;
            font-size:12px; color:#15803d;
            display:flex; align-items:center; gap:8px; margin-top:10px;
        }
        .btn-struk-show {
            background:linear-gradient(135deg,#8b5cf6,#7c3aed) !important;
        }
        /* ── Mobile touch: area klik tombol lebih besar ── */
        .btn-nilai, .btn-ulang, .btn-batal, .btn-struk {
            min-height: 44px;
            touch-action: manipulation;
            -webkit-tap-highlight-color: transparent;
            cursor: pointer;
        }
    `;
    document.head.appendChild(style);
})();