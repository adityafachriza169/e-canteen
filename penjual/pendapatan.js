/* =========================================
   E-CANTEEN — pendapatan.js
   FIX: Saldo hanya dari QRIS. Cash tetap muncul di rekap/tabel
        tapi tidak dihitung sebagai saldo/pendapatan yang bisa ditarik.
   ========================================= */

/* ── STORAGE KEYS ── */
const STORAGE_KEY = "ecanteen_rekening_v2";
const SALDO_KEY   = "ecanteen_saldo_qris";   // key baru khusus QRIS agar tidak tabrakan
const TARIK_KEY   = "ecanteen_total_tarik";

/* ── STATE ── */
let currentTab          = "Minggu";
let chartFilterMode     = "semua";   // "semua" | "qris" | "cash"  ← FIX: tambah filter chart
let selectedRekeningIdx = null;
let tarikAmount         = 0;
let trxPage             = 1;
const TRX_PER_PAGE      = 5;
let filteredTrx         = [];

// Tanggal hari ini dari PHP (WIB)
const _todayParts = (window.todayWIB || new Date().toISOString().substring(0,10)).split('-');
let currentRefDate = new Date(+_todayParts[0], +_todayParts[1]-1, +_todayParts[2]);

/* ── Helper ── */
function dateOnly(str) {
  return str ? str.substring(0, 10) : '';
}
function toLocalDateStr(d) {
  return `${d.getFullYear()}-${String(d.getMonth()+1).padStart(2,"0")}-${String(d.getDate()).padStart(2,"0")}`;
}

/* ──────────────────────────────────────────
   PENTING: is_qris = 1 → masuk saldo
            is_qris = 0 → hanya rekap, tidak masuk saldo
   ────────────────────────────────────────── */
const TRX_DATA_PHP = (window.transaksiData || []).map(t => ({
  id:        "TRX-" + String(t.id).padStart(4,"0"),
  tgl:       t.tanggal_fmt || t.tanggal,
  orderId:   "ORD-" + String(t.id).padStart(4,"0"),
  pelanggan: t.pembeli  || "Anonim",
  produk:    t.produk   || "-",
  jumlah:    t.jumlah   || 1,
  metode:    t.metode   || "-",
  total:     parseFloat(t.total) || 0,
  status: "selesai",
  tanggalStr: dateOnly(t.tanggal),
  dateObj:    (() => { const s = dateOnly(t.tanggal); const [y,m,d] = s.split('-'); return new Date(+y, +m-1, +d); })(),
  // nominal untuk chart & saldo: hanya QRIS yang dihitung
  isQris:    (t.is_qris === 1 || t.is_qris === true),
  nominal:   (t.is_qris === 1 || t.is_qris === true) ? (parseFloat(t.total) || 0) : 0,
}));

/* =========================================
   SIDEBAR / HAMBURGER
   ========================================= */
const sidebar        = document.getElementById("sidebar");
const hamburgerBtn   = document.getElementById("hamburgerBtn");
const sidebarOverlay = document.getElementById("sidebarOverlay");

function toggleSidebar() {
  sidebar.classList.contains("open") ? closeSidebar() : openSidebar();
}
function openSidebar() {
  sidebar.classList.add("open");
  hamburgerBtn.classList.add("open");
  sidebarOverlay.classList.add("show");
  document.body.style.overflow = "hidden";
}
function closeSidebar() {
  sidebar.classList.remove("open");
  hamburgerBtn.classList.remove("open");
  sidebarOverlay.classList.remove("show");
  document.body.style.overflow = "";
}
window.addEventListener("resize", () => {
  if (window.innerWidth > 768) closeSidebar();
});

/* =========================================
   SEARCH OVERLAY
   ========================================= */
function openSearch() {
  document.getElementById("searchOverlay").classList.add("open");
  setTimeout(() => document.getElementById("sheetInput").focus(), 150);
}
function closeSearch() {
  document.getElementById("searchOverlay").classList.remove("open");
}
function doSearch() {
  const q = document.getElementById("sheetInput").value.trim();
  if (q) { showToast(`Mencari "${q}"...`); setTimeout(closeSearch, 800); }
}
function searchChip(el) {
  document.getElementById("sheetInput").value = el.textContent;
  showToast(`Mencari "${el.textContent}"...`);
  setTimeout(closeSearch, 800);
}

/* =========================================
   STORAGE HELPERS
   ========================================= */
const DEFAULT_REKENING = [
  { bank: "BCA", namaBank: "Bank Central Asia",     nama: "Alma Liza", nomor: "1234567890", nomorSamar: "1234 **** **** 7890", verified: true,  utama: true  },
  { bank: "BRI", namaBank: "Bank Rakyat Indonesia", nama: "Alma Liza", nomor: "0987654321", nomorSamar: "0987 **** **** 4321", verified: true,  utama: false },
];

function loadRekening() {
  try { const r = localStorage.getItem(STORAGE_KEY); if (r) return JSON.parse(r); } catch (e) {}
  localStorage.setItem(STORAGE_KEY, JSON.stringify(DEFAULT_REKENING));
  return DEFAULT_REKENING;
}

/**
 * Saldo = total QRIS dari DB (window.saldoAwal) dikurangi tarik.
 * Cash TIDAK pernah masuk ke saldo.
 */
function loadSaldo() {
  const base      = (typeof window.saldoAwal !== "undefined") ? window.saldoAwal : 0;
  const tarikSaved = loadTotalTarik();
  const saldoSaved = localStorage.getItem(SALDO_KEY);

  if (saldoSaved !== null) {
    const s = parseInt(saldoSaved, 10);
    // Sanity-check: saldo + tarik harus ≈ base QRIS
    if (Math.abs(s + tarikSaved - base) > 1000) {
      saveSaldo(base); saveTotalTarik(0); return base;
    }
    return s;
  }
  saveSaldo(base);
  return base;
}

function saveSaldo(v)     { localStorage.setItem(SALDO_KEY, v); }
function loadTotalTarik() { const s = localStorage.getItem(TARIK_KEY); return s ? parseInt(s, 10) : 0; }
function saveTotalTarik(v){ localStorage.setItem(TARIK_KEY, v); }

function fmt(n) { return "Rp " + n.toLocaleString("id-ID"); }
function fmtShort(n) {
  if (n >= 1000000) return (n / 1000000).toFixed(1).replace(".0","") + "jt";
  if (n >= 1000)    return Math.round(n / 1000) + "rb";
  return n.toString();
}

/* =========================================
   SALDO UI
   ========================================= */
function updateSaldoUI() {
  const saldo = loadSaldo();
  const tarik = loadTotalTarik();

  const dispEl = document.getElementById("saldoDisplay");
  if (dispEl) dispEl.textContent = fmt(saldo);

  const saatIniEl = document.getElementById("saldoSaatIni");
  if (saatIniEl && typeof window.saldoAwal !== "undefined") {
    saatIniEl.textContent = fmt(window.saldoAwal);
  }

  const tarikEl = document.getElementById("saldoTarik");
  if (tarikEl) tarikEl.textContent = fmt(tarik);
}

/* =========================================
   DATE / LOCALE CONSTANTS
   ========================================= */
const HARI_ID       = ["Minggu","Senin","Selasa","Rabu","Kamis","Jum'at","Sabtu"];
const BULAN_ID      = ["Jan","Feb","Mar","Apr","Mei","Jun","Jul","Agu","Sep","Okt","Nov","Des"];
const BULAN_PANJANG = ["Januari","Februari","Maret","April","Mei","Juni","Juli","Agustus","September","Oktober","November","Desember"];

/* =========================================
   CHART DATA GENERATORS
   — Hanya menghitung nominal QRIS (nominal field sudah 0 untuk cash)
   ========================================= */
function seedRandom(seed) { let x = Math.sin(seed * 9301 + 49297) * 233280; return x - Math.floor(x); }
function nominalDari(seed, min, range) { return Math.round((seedRandom(seed) * range + min) / 1000) * 1000; }
function buatSeed(y, m, d) { return y * 100000 + m * 1000 + d * 7; }

/* ── FIX: helper hitung nominal berdasarkan filter mode ── */
function getNominalByMode(t) {
  if (chartFilterMode === "qris")  return t.isQris ? parseFloat(t.total) || 0 : 0;
  if (chartFilterMode === "cash")  return !t.isQris ? parseFloat(t.total) || 0 : 0;
  return parseFloat(t.total) || 0;  // "semua"
}

function buatDataMinggu(refDate) {
  const r = [];
  for (let i = 6; i >= 0; i--) {
    const d = new Date(refDate); d.setDate(d.getDate() - i);
    const dayStr = toLocalDateStr(d);
    let nominal = 0;
    if (TRX_DATA_PHP.length > 0) {
      TRX_DATA_PHP.forEach(t => {
        if (toLocalDateStr(t.dateObj) === dayStr) nominal += getNominalByMode(t);
      });
    } else {
      const seed = buatSeed(d.getFullYear(), d.getMonth() + 1, d.getDate());
      nominal = nominalDari(seed, 300000, 1500000);
    }
    r.push({ label: HARI_ID[d.getDay()], sub: `${d.getDate()} ${BULAN_ID[d.getMonth()]} ${d.getFullYear()}`, nominal, dateObj: new Date(d) });
  }
  return r;
}
function buatDataBulan(refDate) {
  const y = refDate.getFullYear(), m = refDate.getMonth();
  const daysInMonth = new Date(y, m + 1, 0).getDate();
  const r = [];
  for (let w = 0; w < 4; w++) {
    const startDay = w * 7 + 1, endDay = Math.min(startDay + 6, daysInMonth);
    let nominal = 0;
    if (TRX_DATA_PHP.length > 0) {
      TRX_DATA_PHP.forEach(t => {
        const td = t.dateObj;
        if (td.getFullYear() === y && td.getMonth() === m) {
          const dom = td.getDate();
          if (dom >= startDay && dom <= endDay) nominal += getNominalByMode(t);
        }
      });
    } else {
      const seed = buatSeed(y, m + 1, w + 1);
      nominal = nominalDari(seed, 2000000, 6000000);
    }
    r.push({ label: `Minggu ${w + 1}`, sub: `${startDay}–${endDay} ${BULAN_PANJANG[m]} ${y}`, nominal });
  }
  return r;
}
function buatDataTahun(refDate) {
  const y = refDate.getFullYear();
  return BULAN_PANJANG.map((nama, i) => {
    let nominal = 0;
    if (TRX_DATA_PHP.length > 0) {
      TRX_DATA_PHP.forEach(t => {
        if (t.dateObj.getFullYear() === y && t.dateObj.getMonth() === i) nominal += getNominalByMode(t);
      });
    } else {
      const seed = buatSeed(y, i + 1, 15);
      nominal = nominalDari(seed, 8000000, 18000000);
    }
    return { label: nama, sub: String(y), nominal };
  });
}
function ambilData(tab, refDate) {
  if (tab === "Minggu") return buatDataMinggu(refDate);
  if (tab === "Bulan")  return buatDataBulan(refDate);
  return buatDataTahun(refDate);
}

/* =========================================
   CHART RENDER
   ========================================= */
function updateJudul(tab, refDate) {
  const el = document.getElementById("judulPendapatan");
  const pi = document.getElementById("periodInfoText");
  if (!el || !pi) return;
  // FIX: judul sesuai filter mode
  const modeLabel = chartFilterMode === "qris" ? "QRIS" : chartFilterMode === "cash" ? "Cash" : "Semua Metode";
  const prefix = `Pendapatan ${modeLabel} `;
  if (tab === "Minggu") {
    const start = new Date(refDate); start.setDate(start.getDate() - 6);
    el.textContent = prefix + "Mingguan";
    pi.textContent = `${start.getDate()} ${BULAN_ID[start.getMonth()]} – ${refDate.getDate()} ${BULAN_ID[refDate.getMonth()]} ${refDate.getFullYear()}`;
  } else if (tab === "Bulan") {
    el.textContent = prefix + "Bulanan";
    pi.textContent = `${BULAN_PANJANG[refDate.getMonth()]} ${refDate.getFullYear()}`;
  } else {
    el.textContent = prefix + "Tahunan";
    pi.textContent = `Tahun ${refDate.getFullYear()}`;
  }
}

function renderBarChart(data) {
  const container = document.getElementById("barChart");
  if (!container) return;
  const CHART_H = 110;
  const BAR_MAX = 72;
  const maxN = Math.max(...data.map(d => d.nominal));
  container.innerHTML = "";
  data.forEach(item => {
    const barPx = maxN > 0 ? Math.max((item.nominal / maxN) * BAR_MAX, 4) : 4;
    const isTop = maxN > 0 && item.nominal === maxN;
    const col   = document.createElement("div");
    col.style.cssText = "flex:1;display:flex;flex-direction:column;align-items:center;justify-content:flex-end;gap:3px;cursor:default;height:" + CHART_H + "px;";
    col.title = `${item.label}: ${fmt(item.nominal)}`;
    col.innerHTML = `
      <div style="font-size:8.5px;font-weight:700;color:${isTop ? "#E8640A" : "#aaa"};text-align:center;line-height:1.2">${fmtShort(item.nominal)}</div>
      <div style="width:85%;border-radius:4px 4px 0 0;background:${isTop ? "linear-gradient(180deg,#f98020,#E8640A)" : "#f5c9b3"};height:${barPx}px;min-height:4px;transition:height .4s ease;${isTop ? "box-shadow:0 2px 8px rgba(232,100,10,.3)" : ""}"></div>
      <div style="font-size:8px;color:#bbb;text-align:center;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:100%;padding:0 1px">${item.label.length > 4 ? item.label.substring(0,3) + "…" : item.label}</div>`;
    container.appendChild(col);
  });
}

function renderPendapatan(tab, refDate) {
  const container = document.getElementById("dayRows");
  if (!container) return;
  const data      = ambilData(tab, refDate);
  const tertinggi = Math.max(...data.map(d => d.nominal));
  updateJudul(tab, refDate);
  renderBarChart(data);
  container.innerHTML = "";
  data.forEach((item, idx) => {
    const isTop = tertinggi > 0 && item.nominal === tertinggi;
    const row   = document.createElement("div");
    row.className = `day-row${isTop ? " highlight" : ""}`;
    row.style.animationDelay = `${idx * 40}ms`;
    row.innerHTML = `
      <div class="day-num">${idx + 1}</div>
      <div class="day-info"><div class="day-name">${item.label}</div><div class="day-date">${item.sub}</div></div>
      <div class="day-right">
        <div class="day-amount">${fmt(item.nominal)}</div>
        ${isTop ? `<span class="tertinggi-badge"><i class="fa-solid fa-arrow-trend-up" style="font-size:8px"></i> Tertinggi</span>` : ""}
      </div>`;
    container.appendChild(row);
  });
}

/* =========================================
   DATE PICKER
   ========================================= */
function renderPicker(tab) {
  const container = document.getElementById("pickerContainer");
  const now       = currentRefDate;
  if (tab === "Minggu") {
    const todayStr = toLocalDateStr(now);
    container.innerHTML = `<i class="fa-regular fa-calendar"></i><input type="date" id="datePicker" value="${todayStr}" max="${todayStr}" onchange="onPickerChange()" title="Pilih tanggal akhir">`;
  } else if (tab === "Bulan") {
    const monthStr = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, "0")}`;
    container.innerHTML = `<i class="fa-regular fa-calendar"></i><input type="month" id="datePicker" value="${monthStr}" onchange="onPickerChange()" title="Pilih bulan">`;
  } else {
    const y = now.getFullYear(); let opts = "";
    for (let yr = y - 5; yr <= y + 1; yr++) { opts += `<option value="${yr}"${yr === y ? " selected" : ""}>${yr}</option>`; }
    container.innerHTML = `<i class="fa-regular fa-calendar"></i><select class="year-select" id="datePicker" onchange="onPickerChange()" title="Pilih tahun">${opts}</select>`;
  }
}

function onPickerChange() {
  const picker = document.getElementById("datePicker");
  if (!picker) return;
  const val = picker.value;
  if (currentTab === "Minggu" && val) {
    const p = val.split("-"); currentRefDate = new Date(parseInt(p[0]), parseInt(p[1])-1, parseInt(p[2]));
  } else if (currentTab === "Bulan" && val) {
    const p = val.split("-"); currentRefDate = new Date(parseInt(p[0]), parseInt(p[1])-1, 15);
  } else if (val) {
    currentRefDate = new Date(parseInt(val), currentRefDate.getMonth(), 15);
  }
  renderPendapatan(currentTab, currentRefDate);
}

function setTab(btn, tabName) {
  document.querySelectorAll(".tab-btn").forEach(b => b.classList.remove("active"));
  btn.classList.add("active");
  currentTab = tabName;
  renderPicker(tabName);
  renderPendapatan(currentTab, currentRefDate);
}

/* ── FIX: Filter chart Semua / QRIS / Cash ── */
function setChartFilter(btn, mode) {
  document.querySelectorAll(".chart-filter-btn").forEach(b => b.classList.remove("active"));
  btn.classList.add("active");
  chartFilterMode = mode;
  renderPendapatan(currentTab, currentRefDate);
}

/* =========================================
   TRANSAKSI DATA
   — TRX_DATA berisi SEMUA transaksi (QRIS & Cash)
   — Kolom "Saldo" di tabel hanya diisi untuk QRIS
   ========================================= */
const TRX_DATA = TRX_DATA_PHP.length > 0 ? TRX_DATA_PHP : [];
filteredTrx = [...TRX_DATA];

/* =========================================
   TRANSAKSI FILTER & RENDER
   ========================================= */
function filterTrx() {
  const q          = document.getElementById("trxSearch").value.toLowerCase().trim();
  const status     = document.getElementById("trxFilter").value;
  const metode     = document.getElementById("trxMetodeFilter") ? document.getElementById("trxMetodeFilter").value : "semua";
  const tglDari    = document.getElementById("trxTglDari")    ? document.getElementById("trxTglDari").value    : "";
  const tglSampai  = document.getElementById("trxTglSampai") ? document.getElementById("trxTglSampai").value : "";

  filteredTrx = TRX_DATA.filter(t => {
    const matchQ = !q || t.id.toLowerCase().includes(q) || t.pelanggan.toLowerCase().includes(q) || t.orderId.toLowerCase().includes(q) || t.tgl.toLowerCase().includes(q) || t.metode.toLowerCase().includes(q);
    const matchS = status === "semua" || t.status === status;
    // Filter metode: qris / cash / semua
    const matchM = metode === "semua"
        || (metode === "qris"  &&  t.isQris)
        || (metode === "cash"  && !t.isQris);

    let matchTgl = true;
    if (tglDari || tglSampai) {
      const tDate = new Date(t.dateObj); tDate.setHours(0,0,0,0);
      if (tglDari)   { const dari   = new Date(tglDari);   dari.setHours(0,0,0,0);       if (tDate < dari)   matchTgl = false; }
      if (tglSampai && matchTgl) { const sampai = new Date(tglSampai); sampai.setHours(23,59,59,999); if (tDate > sampai) matchTgl = false; }
    }
    return matchQ && matchS && matchM && matchTgl;
  });
  trxPage = 1;
  renderTrx();
  updateFilterBadge();
}

function updateFilterBadge() {
  const tglDari   = document.getElementById("trxTglDari")    ? document.getElementById("trxTglDari").value    : "";
  const tglSampai = document.getElementById("trxTglSampai") ? document.getElementById("trxTglSampai").value : "";
  const metode    = document.getElementById("trxMetodeFilter") ? document.getElementById("trxMetodeFilter").value : "semua";
  const badge     = document.getElementById("filterActiveBadge");
  if (!badge) return;
  badge.style.display = (tglDari || tglSampai || metode !== "semua") ? "inline-flex" : "none";
}

function resetFilterTanggal() {
  const dari   = document.getElementById("trxTglDari");
  const sampai = document.getElementById("trxTglSampai");
  if (dari)   dari.value   = "";
  if (sampai) sampai.value = "";
  const metodeEl = document.getElementById("trxMetodeFilter");
  if (metodeEl) metodeEl.value = "semua";
  filterTrx();
}

/* ── Render tabel & mobile cards ── */
function renderTrx() {
  const body       = document.getElementById("trxBody");
  const mobileList = document.getElementById("trxMobileList");
  const paginWrap  = document.getElementById("paginationWrap");
  const start      = (trxPage - 1) * TRX_PER_PAGE;
  const slice      = filteredTrx.slice(start, start + TRX_PER_PAGE);

  const emptyHTML   = `<tr><td colspan="8" style="text-align:center;padding:28px;color:#bbb;font-size:13px"><i class="fa-solid fa-inbox" style="font-size:26px;display:block;margin-bottom:8px;opacity:.4"></i>Tidak ada transaksi</td></tr>`;
  const emptyMobile = `<div style="text-align:center;padding:28px 0;color:#bbb;font-size:13px"><i class="fa-solid fa-inbox" style="font-size:26px;display:block;margin-bottom:8px;opacity:.4"></i>Tidak ada transaksi</div>`;

  /* ── Desktop table ── */
  if (body) {
    body.innerHTML = "";
    if (slice.length === 0) { body.innerHTML = emptyHTML; }
    else {
      slice.forEach(t => {
        // Kolom Saldo: hijau jika QRIS, abu-abu dengan label "Cash" jika bukan QRIS
        const saldoCell = t.isQris
          ? `<span style="color:#15803d;font-weight:700">${fmt(t.total)}</span>`
          : `<span style="color:#94a3b8;font-size:11px;font-weight:600;display:inline-flex;align-items:center;gap:4px;">
               <i class="fa-solid fa-money-bill-wave"></i> Cash (tidak masuk saldo)
             </span>`;

        // Badge metode
        const metodeBadge = t.isQris
          ? `<span style="background:#eff6ff;color:#1d4ed8;font-size:11px;font-weight:700;padding:3px 8px;border-radius:50px;display:inline-flex;align-items:center;gap:4px;"><i class="fa-solid fa-qrcode"></i> QRIS</span>`
          : `<span style="background:#f0fdf4;color:#15803d;font-size:11px;font-weight:700;padding:3px 8px;border-radius:50px;display:inline-flex;align-items:center;gap:4px;"><i class="fa-solid fa-money-bill-wave"></i> Cash</span>`;

        const tr = document.createElement("tr");
        tr.innerHTML = `
          <td><span class="trx-id">${t.id}</span></td>
          <td><span class="trx-date">${t.tgl}</span></td>
          <td><a href="#" class="trx-order" onclick="return false">#${t.orderId}</a></td>
          <td class="trx-pelanggan">${t.pelanggan}</td>
          <td>${metodeBadge}</td>
          <td class="trx-bersih">${fmt(t.total)}</td>
          <td>${saldoCell}</td>
          <td><span class="badge selesai"><i class="fa-solid fa-circle-check"></i> Selesai</span></td>`;
        body.appendChild(tr);
      });
    }
  }

  /* ── Mobile cards ── */
  if (mobileList) {
    mobileList.innerHTML = "";
    if (slice.length === 0) { mobileList.innerHTML = emptyMobile; }
    else {
      slice.forEach(t => {
        const saldoInfo = t.isQris
          ? `<span style="color:#15803d;font-weight:700;font-size:12px">+${fmt(t.total)}</span>`
          : `<span style="color:#94a3b8;font-size:11px">Cash (tidak masuk saldo)</span>`;

        const el = document.createElement("div");
        el.className = "trx-mobile-item";
        el.innerHTML = `
          <div class="trx-mob-top">
            <span class="trx-mob-id">${t.id}</span>
            <span class="trx-mob-date">${t.tgl}</span>
          </div>
          <div class="trx-mob-mid">
            <span class="trx-mob-customer">${t.pelanggan}</span>
            <span class="trx-mob-amount">${fmt(t.total)}</span>
          </div>
          <div class="trx-mob-bottom">
            <span class="trx-mob-order">${t.isQris ? "🔵 QRIS" : "💵 Cash"}</span>
            <span class="badge selesai"><i class="fa-solid fa-circle-check"></i> Selesai</span>
          </div>
          <div style="margin-top:4px;padding-top:4px;border-top:1px dashed #f0e8e0;font-size:11px">
            Saldo: ${saldoInfo}
          </div>`;
        mobileList.appendChild(el);
      });
    }
  }

  /* ── Pagination ── */
  const totalPage = Math.ceil(filteredTrx.length / TRX_PER_PAGE);
  paginWrap.innerHTML = "";
  if (totalPage <= 1) { paginWrap.style.display = "none"; return; }
  paginWrap.style.display = "flex";
  if (trxPage > 1) {
    const prev = document.createElement("button"); prev.className = "page-btn"; prev.textContent = "‹";
    prev.onclick = () => { trxPage--; renderTrx(); }; paginWrap.appendChild(prev);
  }
  for (let p = 1; p <= totalPage; p++) {
    const btn = document.createElement("button"); btn.className = "page-btn" + (p === trxPage ? " active" : ""); btn.textContent = p;
    btn.onclick = () => { trxPage = p; renderTrx(); }; paginWrap.appendChild(btn);
  }
  if (trxPage < totalPage) {
    const nx = document.createElement("button"); nx.className = "page-btn"; nx.textContent = "›";
    nx.onclick = () => { trxPage++; renderTrx(); }; paginWrap.appendChild(nx);
  }
}

/* =========================================
   THREE-DOT MENU
   ========================================= */
function toggleDotMenu() {
  document.getElementById("dotMenu").classList.toggle("open");
}
function closeDotMenu() {
  const menu = document.getElementById("dotMenu");
  if (menu) menu.classList.remove("open");
}
document.addEventListener("click", function(e) {
  const btn  = document.getElementById("dotMenuBtn");
  const menu = document.getElementById("dotMenu");
  if (menu && btn && !menu.contains(e.target) && !btn.contains(e.target)) menu.classList.remove("open");
});

/* =========================================
   CETAK / PRINT
   ========================================= */
function buildPrintHTML(judul, data, periode) {
  const namaKantin   = window.namaKantin || "E-Canteen";
  const totalNominal = data.reduce((s, t) => s + t.total, 0);           // semua metode
  const totalQris    = data.filter(t => t.isQris).reduce((s,t) => s + t.total, 0); // saldo masuk
  const totalCash    = data.filter(t => !t.isQris).reduce((s,t) => s + t.total, 0);
const selesai = data.length;

  const rows = data.map((t, i) => `
    <tr>
      <td>${i + 1}</td>
      <td>${t.id}</td>
      <td>${t.tgl}</td>
      <td>${t.orderId}</td>
      <td>${t.pelanggan}</td>
      <td style="text-align:center">${t.isQris ? "QRIS" : "Cash"}</td>
      <td style="text-align:right">${fmt(t.total)}</td>
      <td style="text-align:right">${t.isQris ? fmt(t.total) : "—"}</td>
      <td style="text-align:center">
        <span class="status-badge selesai">Selesai</span>
      </td>
    </tr>`).join("");

  return `<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>${judul} — ${namaKantin}</title>
<style>
  @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap');
  * { margin:0; padding:0; box-sizing:border-box; }
  body { font-family:'Plus Jakarta Sans',sans-serif; font-size:12px; color:#1a1a1a; background:#fff; padding:28px 36px; }
  .print-header { display:flex; align-items:flex-start; justify-content:space-between; margin-bottom:24px; padding-bottom:16px; border-bottom:2px solid #E8640A; }
  .print-title h1 { font-size:18px; font-weight:800; color:#E8640A; }
  .print-title h2 { font-size:13px; font-weight:700; color:#555; margin-top:2px; }
  .print-title .periode { font-size:11px; color:#999; margin-top:4px; }
  .print-meta { text-align:right; font-size:11px; color:#888; line-height:1.8; }
  .print-meta strong { color:#1a1a1a; }
  .summary-row { display:flex; gap:14px; margin-bottom:20px; flex-wrap:wrap; }
  .sum-card { flex:1; min-width:130px; background:#fdf6f0; border:1.5px solid rgba(232,100,10,.2); border-radius:10px; padding:12px 14px; }
  .sum-label { font-size:10px; font-weight:600; color:#aaa; text-transform:uppercase; letter-spacing:.5px; margin-bottom:4px; }
  .sum-val { font-size:15px; font-weight:800; color:#E8640A; }
  .sum-val.green { color:#16a34a; }
  .sum-val.gray  { color:#64748b; }
  .sum-val.blue  { color:#2563eb; }
  .info-note { background:#eff6ff; border:1px solid #bfdbfe; border-radius:8px; padding:8px 12px; font-size:11px; color:#1e40af; margin-bottom:16px; }
  table { width:100%; border-collapse:collapse; font-size:11px; }
  thead th { background:#E8640A; color:#fff; padding:9px 10px; text-align:left; font-weight:700; font-size:10.5px; }
  thead th:first-child { border-radius:8px 0 0 0; }
  thead th:last-child  { border-radius:0 8px 0 0; }
  tbody tr:nth-child(even) { background:#fff9f5; }
  tbody td { padding:8px 10px; border-bottom:1px solid #f0e8e0; vertical-align:middle; }
  .status-badge { display:inline-block; padding:2px 9px; border-radius:20px; font-size:10px; font-weight:700; }
  .status-badge.selesai { background:#dcfce7; color:#16a34a; }
  .status-badge.pending  { background:#fef9c3; color:#ca8a04; }
  .print-footer { margin-top:24px; padding-top:12px; border-top:1px solid #eee; display:flex; justify-content:space-between; align-items:flex-end; font-size:10.5px; color:#aaa; }
  .sign-area { text-align:right; }
  .sign-area p { margin-bottom:48px; }
  .sign-line { border-top:1px solid #999; width:180px; margin-left:auto; padding-top:4px; color:#555; font-weight:600; }
  @media print { body { padding:16px 20px; } }
</style>
</head>
<body>
<div class="print-header">
  <div class="print-title">
    <h1>📊 ${judul}</h1>
    <h2>${namaKantin}</h2>
    <div class="periode">Periode: ${periode}</div>
  </div>
  <div class="print-meta">
    <div>Dicetak: <strong>${new Date().toLocaleDateString("id-ID",{weekday:"long",year:"numeric",month:"long",day:"numeric"})}</strong></div>
    <div>Pukul: <strong>${new Date().toLocaleTimeString("id-ID")}</strong></div>
    <div>Total Data: <strong>${data.length} transaksi</strong></div>
  </div>
</div>

<div class="info-note">
  ℹ️ <strong>Catatan:</strong> Kolom "Masuk Saldo" hanya mencatat pembayaran <strong>QRIS</strong>.
  Pembayaran <strong>Cash</strong> tidak masuk saldo namun tetap tercatat di rekap ini.
</div>

<div class="summary-row">
  <div class="sum-card">
    <div class="sum-label">Total Semua Transaksi</div>
    <div class="sum-val">${fmt(totalNominal)}</div>
  </div>
  <div class="sum-card">
    <div class="sum-label">Masuk Saldo (QRIS)</div>
    <div class="sum-val green">${fmt(totalQris)}</div>
  </div>
  <div class="sum-card">
    <div class="sum-label">Cash (tidak ke saldo)</div>
    <div class="sum-val gray">${fmt(totalCash)}</div>
  </div>
  <div class="sum-card">
    <div class="sum-label">Selesai / Pending</div>
    <div class="sum-val blue">${selesai} transaksi</div>
  </div>
</div>

<table>
  <thead>
    <tr>
      <th>No</th>
      <th>ID Transaksi</th>
      <th>Tanggal</th>
      <th>Order ID</th>
      <th>Pelanggan</th>
      <th style="text-align:center">Metode</th>
      <th style="text-align:right">Total</th>
      <th style="text-align:right">Masuk Saldo</th>
      <th style="text-align:center">Status</th>
    </tr>
  </thead>
  <tbody>
    ${rows || '<tr><td colspan="9" style="text-align:center;padding:20px;color:#bbb">Tidak ada data</td></tr>'}
  </tbody>
</table>

<div class="print-footer">
  <div>E-Canteen Dashboard • Laporan otomatis digenerate oleh sistem</div>
  <div class="sign-area">
    <p>Mengetahui,</p>
    <div class="sign-line">Penjual</div>
  </div>
</div>
<script>window.onload = function(){ window.print(); }<\/script>
</body>
</html>`;
}

function cetakHariIni() {
  closeDotMenu();
  const todayStr = window.todayWIB || toLocalDateStr(new Date());
  const data = TRX_DATA.filter(t => t.tanggalStr === todayStr);
  const [y,m,d] = todayStr.split('-');
  const hariObj = new Date(+y, +m-1, +d);
  const tgl = hariObj.toLocaleDateString("id-ID",{weekday:"long",day:"numeric",month:"long",year:"numeric"});
  if (data.length === 0) { showToast("Tidak ada transaksi hari ini"); return; }
  const win = window.open("","_blank");
  win.document.write(buildPrintHTML("Laporan Transaksi Hari Ini", data, tgl));
  win.document.close();
}
function cetakSemuanya() {
  closeDotMenu();
  const data = [...TRX_DATA];
  if (data.length === 0) { showToast("Tidak ada data transaksi"); return; }
  const win = window.open("","_blank");
  win.document.write(buildPrintHTML("Laporan Semua Transaksi", data, "Seluruh Periode"));
  win.document.close();
}
function cetakFiltered() {
  closeDotMenu();
  const data = [...filteredTrx];
  if (data.length === 0) { showToast("Tidak ada transaksi yang sesuai filter"); return; }
  const tglDari   = document.getElementById("trxTglDari") ? document.getElementById("trxTglDari").value : "";
  const tglSampai = document.getElementById("trxTglSampai") ? document.getElementById("trxTglSampai").value : "";
  let periode = "Sesuai Filter";
  if (tglDari && tglSampai) periode = `${new Date(tglDari).toLocaleDateString("id-ID")} – ${new Date(tglSampai).toLocaleDateString("id-ID")}`;
  else if (tglDari) periode = `Mulai ${new Date(tglDari).toLocaleDateString("id-ID")}`;
  else if (tglSampai) periode = `Sampai ${new Date(tglSampai).toLocaleDateString("id-ID")}`;
  const win = window.open("","_blank");
  win.document.write(buildPrintHTML("Laporan Transaksi (Filter Aktif)", data, periode));
  win.document.close();
}

/* =========================================
   DOWNLOAD REKAP — Digenerate di server (rekap.php)
   ========================================= */
function downloadRekap(mode) {
  closeDotMenu();

  const tglDari   = document.getElementById("trxTglDari")   ?.value || "";
  const tglSampai = document.getElementById("trxTglSampai") ?.value || "";

  // Validasi filter: jika mode=filter harus ada tanggal
  if (mode === "filter" && (!tglDari || !tglSampai)) {
    showToast("⚠️ Pilih rentang tanggal terlebih dahulu");
    return;
  }

  // Bangun URL ke rekap.php dengan parameter
  const params = new URLSearchParams({ mode });
  if (mode === "filter") {
    params.append("dari",    tglDari);
    params.append("sampai",  tglSampai);
  }

  showToast("⏳ Menyiapkan file Excel...");

  // Buka rekap.php — browser otomatis download file .xlsx
  window.location.href = "rekap.php?" + params.toString();
}

/* =========================================
   MODAL TARIK DANA (Saldo QRIS)
   ========================================= */
function bukaTarik() {
  selectedRekeningIdx = null; tarikAmount = 0;
  renderModalStep1();
  document.getElementById("modalTarik").classList.add("open");
}

function renderModalStep1() {
  const semua    = loadRekening();
  const verified = semua.filter(r => r.verified);
  const body     = document.getElementById("modalBody");
  const footer   = document.getElementById("modalFooter");
  const saldo    = loadSaldo();
  document.getElementById("modalSubtitle").textContent = "Saldo yang bisa ditarik adalah pembayaran QRIS";

  if (verified.length === 0) {
    body.innerHTML = `<div class="no-rekening-warning"><i class="fa-solid fa-triangle-exclamation"></i><p>Belum ada rekening terverifikasi</p><span>Tambahkan rekening bank terlebih dahulu.</span></div>`;
    footer.innerHTML = ""; return;
  }

  const utamaIdx = semua.findIndex(r => r.utama && r.verified);
  selectedRekeningIdx = utamaIdx !== -1 ? utamaIdx : semua.indexOf(verified[0]);

  body.innerHTML = `
    <div class="saldo-tersedia">
      <div class="saldo-label">Saldo QRIS Tersedia</div>
      <div class="saldo-nominal">${fmt(saldo)}</div>
      <div style="font-size:11px;color:#94a3b8;margin-top:4px;display:flex;align-items:center;gap:4px;">
        <i class="fa-solid fa-qrcode"></i> Hanya pembayaran QRIS yang masuk saldo ini
      </div>
    </div>
    <div class="amount-section">
      <div class="amount-label">Nominal Penarikan</div>
      <div class="amount-input-wrap"><span class="amount-prefix">Rp</span><input class="amount-input" type="text" id="amountInput" placeholder="Masukkan nominal..." oninput="formatAmountInput(this)" inputmode="numeric"></div>
      <div class="amount-hints" id="amountHints">
        ${[250000,500000,1000000,2000000].filter(v=>v<=saldo).map(v=>`<button class="amount-hint-btn" onclick="setAmount(${v})">${fmt(v)}</button>`).join("")}
        <button class="amount-hint-btn" onclick="setAmount(${saldo})">Semua</button>
      </div>
    </div>
    <div class="rekening-label">Pilih Rekening Tujuan</div>
    <div class="rekening-pilihan-list" id="rekeningPilihanList"></div>`;

  const list = document.getElementById("rekeningPilihanList");
  verified.forEach(rek => {
    const idxAsli    = semua.indexOf(rek);
    const isSelected = idxAsli === selectedRekeningIdx;
    const item       = document.createElement("div");
    item.className   = `rekening-pilihan-item${isSelected ? " selected" : ""}`;
    item.dataset.idx = idxAsli;
    item.innerHTML   = `
      <div class="rekening-pilihan-logo rlogo-${rek.bank.toLowerCase()}">${rek.bank}</div>
      <div class="rekening-pilihan-info">
        <div class="rekening-pilihan-nama">${rek.nama}</div>
        <div class="rekening-pilihan-nomor">${rek.nomorSamar}</div>
        <div class="rekening-pilihan-bank">${rek.namaBank}</div>
        ${rek.utama ? '<span class="rekening-pilihan-utama">Utama</span>' : ""}
      </div>
      <div class="rekening-pilihan-check">${isSelected ? '<i class="fa-solid fa-check"></i>' : ""}</div>`;
    item.addEventListener("click", () => pilihRekening(item, idxAsli));
    list.appendChild(item);
  });

  footer.innerHTML = `<button class="btn-tarik-modal" onclick="lanjutKonfirmasi()"><i class="fa-solid fa-arrow-right"></i> Lanjutkan</button>`;
}

function formatAmountInput(input) {
  let raw = input.value.replace(/\D/g,"");
  if (raw.length > 12) raw = raw.substring(0,12);
  input.value = raw ? parseInt(raw,10).toLocaleString("id-ID") : "";
  input.dataset.raw = raw;
}
function setAmount(v) {
  const inp = document.getElementById("amountInput");
  inp.value = v.toLocaleString("id-ID"); inp.dataset.raw = v;
}
function getRawAmount() {
  const inp = document.getElementById("amountInput");
  if (!inp) return 0;
  const raw = inp.dataset.raw || inp.value.replace(/\D/g,"");
  return parseInt(raw,10) || 0;
}
function pilihRekening(el, idx) {
  selectedRekeningIdx = idx;
  document.querySelectorAll(".rekening-pilihan-item").forEach(i => {
    i.classList.remove("selected");
    const c = i.querySelector(".rekening-pilihan-check"); if (c) c.innerHTML = "";
  });
  el.classList.add("selected");
  const c = el.querySelector(".rekening-pilihan-check"); if (c) c.innerHTML = '<i class="fa-solid fa-check"></i>';
}
function lanjutKonfirmasi() {
  const saldo  = loadSaldo();
  const amount = getRawAmount();
  if (!amount || amount < 10000)   { showToast("Minimal penarikan Rp 10.000"); return; }
  if (amount > saldo)              { showToast("Nominal melebihi saldo QRIS tersedia"); return; }
  if (selectedRekeningIdx === null){ showToast("Pilih rekening tujuan terlebih dahulu"); return; }
  tarikAmount = amount;
  const semua = loadRekening(); const rek = semua[selectedRekeningIdx];
  const body = document.getElementById("modalBody"); const footer = document.getElementById("modalFooter");
  document.getElementById("modalSubtitle").textContent = "Konfirmasi penarikan saldo QRIS";
  body.innerHTML = `
    <div class="konfirm-info">
      <div class="konfirm-row"><span class="konfirm-key">Nominal</span><span class="konfirm-val" style="color:#22c55e;font-size:15px">${fmt(amount)}</span></div>
      <div class="konfirm-row"><span class="konfirm-key">Sumber Dana</span><span class="konfirm-val" style="color:#1d4ed8"><i class="fa-solid fa-qrcode"></i> Saldo QRIS</span></div>
      <div class="konfirm-row"><span class="konfirm-key">Bank Tujuan</span><span class="konfirm-val">${rek.namaBank}</span></div>
      <div class="konfirm-row"><span class="konfirm-key">Nama Rekening</span><span class="konfirm-val">${rek.nama}</span></div>
      <div class="konfirm-row"><span class="konfirm-key">No. Rekening</span><span class="konfirm-val">${rek.nomorSamar}</span></div>
      <div class="konfirm-row"><span class="konfirm-key">Saldo Setelah</span><span class="konfirm-val">${fmt(saldo - amount)}</span></div>
    </div>
    <div class="konfirm-warning">Pastikan data di atas sudah benar. Penarikan yang telah diproses tidak dapat dibatalkan.</div>`;
  footer.innerHTML = `
    <button class="btn-back" onclick="renderModalStep1()"><i class="fa-solid fa-arrow-left"></i> Kembali</button>
    <button class="btn-tarik-modal" onclick="konfirmasiTarik()"><i class="fa-solid fa-circle-down"></i> Konfirmasi Tarik Dana</button>`;
}
function konfirmasiTarik() {
  const saldo    = loadSaldo();
  const newSaldo = saldo - tarikAmount;
  const newTarik = loadTotalTarik() + tarikAmount;
  saveSaldo(newSaldo); saveTotalTarik(newTarik);
  tutupModal();
  document.getElementById("suksesMsg").innerHTML = `Dana sebesar <strong>${fmt(tarikAmount)}</strong> dari saldo QRIS sedang diproses ke rekening Anda.`;
  document.getElementById("suksesOverlay").classList.add("open");
  updateSaldoUI();
}
function tutupModal()  { document.getElementById("modalTarik").classList.remove("open"); }
function tutupSukses() { document.getElementById("suksesOverlay").classList.remove("open"); }

/* =========================================
   TOAST
   ========================================= */
let toastTimer = null;
function showToast(msg, dur = 2400) {
  const t = document.getElementById("toast");
  t.textContent = msg; t.classList.add("show");
  clearTimeout(toastTimer);
  toastTimer = setTimeout(() => t.classList.remove("show"), dur);
}

/* =========================================
   KEYBOARD SHORTCUTS
   ========================================= */
document.addEventListener("keydown", e => {
  if (e.key === "Escape") { tutupModal(); tutupSukses(); closeSearch(); closeSidebar(); closeDotMenu(); }
});

/* =========================================
   SEARCH INPUT LISTENERS
   ========================================= */
document.getElementById("navSearchInput").addEventListener("keydown", e => {
  if (e.key === "Enter") {
    openSearch();
    setTimeout(() => { document.getElementById("sheetInput").value = e.target.value; }, 50);
  }
});
document.getElementById("sheetInput").addEventListener("keydown", e => {
  if (e.key === "Enter") doSearch();
});

/* =========================================
   INIT
   ========================================= */
window.addEventListener("DOMContentLoaded", () => {
  currentRefDate = new Date();
  renderPicker("Minggu");
  renderPendapatan("Minggu", currentRefDate);
  filteredTrx = [...TRX_DATA];
  filterTrx();
  updateSaldoUI();
});