// =============================================
// HAMBURGER & SIDEBAR
// =============================================
const hamburgerBtn   = document.getElementById('hamburgerBtn');
const sidebar        = document.getElementById('sidebar');
const sidebarOverlay = document.getElementById('sidebarOverlay');

function openSidebar() {
    sidebar.classList.add('open');
    sidebarOverlay.classList.add('show');
    hamburgerBtn.classList.add('open');
    document.body.style.overflow = 'hidden';
}

function closeSidebar() {
    sidebar.classList.remove('open');
    sidebarOverlay.classList.remove('show');
    hamburgerBtn.classList.remove('open');
    document.body.style.overflow = '';
}

hamburgerBtn.addEventListener('click', function () {
    sidebar.classList.contains('open') ? closeSidebar() : openSidebar();
});

sidebarOverlay.addEventListener('click', closeSidebar);

// Tutup sidebar saat link diklik (mobile)
sidebar.querySelectorAll('a').forEach(function (link) {
    link.addEventListener('click', function () {
        if (window.innerWidth <= 768) closeSidebar();
    });
});

// =============================================
// SEARCH OVERLAY (icon kaca pembesar mobile)
// =============================================
const searchTrigger  = document.getElementById('searchTrigger');
const searchOverlay  = document.getElementById('searchOverlay');
const sheetInput     = document.getElementById('sheetInput');
const sheetSearchBtn = document.getElementById('sheetSearchBtn');

if (searchTrigger) {
    searchTrigger.addEventListener('click', function () {
        searchOverlay.classList.add('open');
        setTimeout(function () { if (sheetInput) sheetInput.focus(); }, 300);
    });
}

function closeSearch() {
    searchOverlay.classList.remove('open');
}

if (sheetSearchBtn) {
    sheetSearchBtn.addEventListener('click', function () {
        const q = sheetInput.value.trim();
        if (q) window.location.href = 'produk.php?search=' + encodeURIComponent(q);
    });
}

if (sheetInput) {
    sheetInput.addEventListener('keydown', function (e) {
        if (e.key === 'Enter') {
            const q = sheetInput.value.trim();
            if (q) window.location.href = 'produk.php?search=' + encodeURIComponent(q);
        }
    });
}

function applyChip(val) {
    window.location.href = 'produk.php?search=' + encodeURIComponent(val);
}

// =============================================
// VARIAN MODAL
// =============================================
function mvFotoError(wrapId, img) {
    img.style.display = 'none';
    var wrap = document.getElementById(wrapId);
    if (wrap) {
        var placeholder = wrap.querySelector('.mv-item-foto-placeholder');
        if (placeholder) placeholder.style.display = 'flex';
    }
}

function formatRupiah(angka) {
    return 'Rp' + Number(angka).toLocaleString('id-ID');
}

function openVarianModal(nama, harga, hargaRaw, kategori, varians) {
    document.getElementById('mvNama').textContent     = nama;
    document.getElementById('mvHarga').textContent    = 'Harga dasar: ' + harga;
    document.getElementById('mvKategori').textContent = kategori;

    const jumlah = varians.length;
    document.getElementById('mvJmlVarian').textContent = jumlah;

    if (jumlah > 0) {
        const hargaTotals = varians.map(function (v) { return v.harga_total; });
        document.getElementById('mvHargaMin').textContent = formatRupiah(Math.min.apply(null, hargaTotals));
        document.getElementById('mvHargaMax').textContent = formatRupiah(Math.max.apply(null, hargaTotals));
    } else {
        document.getElementById('mvHargaMin').textContent = '-';
        document.getElementById('mvHargaMax').textContent = '-';
    }

    const list = document.getElementById('mvList');
    list.innerHTML = '';

    if (jumlah === 0) {
        list.innerHTML = `
            <div class="mv-empty">
                <i class="fa-solid fa-layer-group"></i>
                <p>Belum ada varian untuk menu ini.</p>
            </div>`;
    } else {
        varians.forEach(function (v, idx) {
            const item = document.createElement('div');
            item.className = 'mv-item';

            const fotoWrapId = 'mv-foto-wrap-' + idx;
            let fotoHTML = '';
            if (v.foto) {
                fotoHTML = `
                    <div class="mv-item-foto-wrap" id="${fotoWrapId}">
                        <span class="mv-item-num">${idx + 1}</span>
                        <img class="mv-item-foto"
                             src="uploads/${v.foto}"
                             alt="${v.nama}"
                             onerror="mvFotoError('${fotoWrapId}', this)">
                        <div class="mv-item-foto-placeholder" style="display:none">
                            <i class="fa-solid fa-image"></i>
                        </div>
                    </div>`;
            } else {
                fotoHTML = `
                    <div class="mv-item-foto-wrap">
                        <span class="mv-item-num">${idx + 1}</span>
                        <div class="mv-item-foto-placeholder">
                            <i class="fa-solid fa-image"></i>
                        </div>
                    </div>`;
            }

            let hargaHTML = '';
            if (v.harga_tambah > 0) {
                hargaHTML = `
                    <div class="mv-item-price-row">
                        <span class="mv-item-tambah">+${formatRupiah(v.harga_tambah)}</span>
                        <span class="mv-item-total">= ${formatRupiah(v.harga_total)}</span>
                    </div>`;
            } else {
                hargaHTML = `
                    <div class="mv-item-price-row">
                        <span class="mv-item-harga-dasar">Harga dasar (${harga})</span>
                    </div>`;
            }

            item.innerHTML = fotoHTML + `
                <div class="mv-item-info">
                    <div class="mv-item-nama">${v.nama}</div>
                    ${hargaHTML}
                </div>`;

            list.appendChild(item);
        });
    }

    document.getElementById('modalVarianBg').classList.add('open');
    document.body.style.overflow = 'hidden';
}

function closeVarianModal() {
    document.getElementById('modalVarianBg').classList.remove('open');
    document.body.style.overflow = '';
}

// Tutup modal dengan ESC
document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') {
        closeVarianModal();
        closeSearch();
    }
});

// Swipe down untuk tutup varian modal (mobile)
(function () {
    const modal = document.getElementById('modalVarian');
    let startY = 0;
    let isDragging = false;

    modal.addEventListener('touchstart', function (e) {
        startY = e.touches[0].clientY;
        isDragging = true;
    }, { passive: true });

    modal.addEventListener('touchmove', function (e) {
        if (!isDragging) return;
        const diff = e.touches[0].clientY - startY;
        if (diff > 0) {
            modal.style.transform = 'translateY(' + diff + 'px)';
            modal.style.transition = 'none';
        }
    }, { passive: true });

    modal.addEventListener('touchend', function (e) {
        isDragging = false;
        const diff = e.changedTouches[0].clientY - startY;
        modal.style.transition = '';
        modal.style.transform = '';
        if (diff > 100) closeVarianModal();
    }, { passive: true });
})();