/* ═══════════════════════════════════════════
   dasbord.js  —  E-Canteen Dashboard
   Lengkap: Rekap Panel, Stok Harian, Tabel Pembelian
═══════════════════════════════════════════ */

/* ─────────────────────────────────────────
   GLOBALS
───────────────────────────────────────── */
var rdpCategory    = 'harian';
var rekapPanelOpen = false;

/* ─────────────────────────────────────────
   HELPERS
───────────────────────────────────────── */
function rupiahFmt(num) {
  return new Intl.NumberFormat('id-ID', {
    style: 'currency', currency: 'IDR', minimumFractionDigits: 0
  }).format(num);
}

function dateDisplay(dateStr) {
  if (!dateStr) return '-';
  var d = new Date(dateStr.replace(' ', 'T'));
  return d.toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' });
}

function dateOnly(dateStr) {
  if (!dateStr) return '';
  return dateStr.substring(0, 10);
}

function getWeekNumber(dateStr) {
  var d    = new Date(dateOnly(dateStr));
  var jan4 = new Date(d.getFullYear(), 0, 4);
  var s1   = new Date(jan4);
  s1.setDate(jan4.getDate() - ((jan4.getDay() + 6) % 7));
  var week = Math.floor((d - s1) / (7 * 24 * 3600 * 1000)) + 1;
  return d.getFullYear() + '-W' + (week < 10 ? '0' : '') + week;
}

function getMonthKey(dateStr) { return dateOnly(dateStr).substring(0, 7); }

function monthLabel(key) {
  var p = key.split('-');
  var d = new Date(parseInt(p[0]), parseInt(p[1]) - 1, 1);
  return d.toLocaleDateString('id-ID', { month: 'long', year: 'numeric' });
}

function weekLabel(key) {
  var parts  = key.split('-W');
  var year   = parseInt(parts[0]);
  var week   = parseInt(parts[1]);
  var jan4   = new Date(year, 0, 4);
  var s1     = new Date(jan4);
  s1.setDate(jan4.getDate() - ((jan4.getDay() + 6) % 7));
  var monday = new Date(s1);
  monday.setDate(s1.getDate() + (week - 1) * 7);
  var sunday = new Date(monday);
  sunday.setDate(monday.getDate() + 6);
  var fmt = { day: 'numeric', month: 'short' };
  return 'Minggu ' + week + ' (' +
    monday.toLocaleDateString('id-ID', fmt) + ' \u2013 ' +
    sunday.toLocaleDateString('id-ID', fmt) + ' ' + year + ')';
}

/* ─────────────────────────────────────────
   NAVBAR SEARCH → filter tabel + buka panel rekap
───────────────────────────────────────── */
function onNavSearchInput() {
  var val = (document.getElementById('navSearchInput').value || '').trim();
  var filtered = (window.rekapData || []).filter(function(t) {
    return (t.produk  || '').toLowerCase().includes(val.toLowerCase()) ||
           (t.pembeli || '').toLowerCase().includes(val.toLowerCase()) ||
           String(t.id).includes(val);
  });
  window._renderTable(filtered, 1);

  if (val.length > 0 && !rekapPanelOpen) openRekapPanel();
}

function triggerNavSearch() {
  onNavSearchInput();
  document.getElementById('navSearchInput').blur();
}

function openRekapPanel() {
  rekapPanelOpen = true;
  document.getElementById('rekapDropdownPanel').classList.add('open');
  if (!document.getElementById('rdpDateRow').innerHTML.trim()) {
    renderRdpDateRow('harian');
  }
}

function closeRekapPanel() {
  rekapPanelOpen = false;
  document.getElementById('rekapDropdownPanel').classList.remove('open');
  document.getElementById('navSearchInput').value = '';
  document.getElementById('rdpOutput').innerHTML = '';
  document.getElementById('rdpBottomActions').style.display = 'none';
  window._renderTable(window.rekapData || [], 1);
}

/* ─────────────────────────────────────────
   PANEL REKAP – KATEGORI & FILTER TANGGAL
───────────────────────────────────────── */
function selectRdpCategory(cat, btn) {
  rdpCategory = cat;
  document.querySelectorAll('.rdp-tab').forEach(function(t) { t.classList.remove('active'); });
  btn.classList.add('active');
  renderRdpDateRow(cat);
  document.getElementById('rdpOutput').innerHTML = '';
  document.getElementById('rdpBottomActions').style.display = 'none';
}

function renderRdpDateRow(cat) {
  var row   = document.getElementById('rdpDateRow');
  var today = new Date();
  var yyyy  = today.getFullYear();
  var mm    = String(today.getMonth() + 1).padStart(2, '0');
  var dd    = String(today.getDate()).padStart(2, '0');
  var DATA  = window.rekapData || [];

  if (cat === 'harian') {
    row.innerHTML =
      '<label>Pilih Tanggal:</label>' +
      '<input type="date" id="rdpFilterHarian" value="' + yyyy + '-' + mm + '-' + dd + '" />';

  } else if (cat === 'mingguan') {
    var weeks = [], seen = {};
    DATA.forEach(function(o) {
      var wk = getWeekNumber(o.tanggal);
      if (!seen[wk]) { seen[wk] = true; weeks.push(wk); }
    });
    weeks.sort().reverse();
    row.innerHTML =
      '<label>Pilih Minggu:</label>' +
      '<select id="rdpFilterMingguan">' +
      weeks.map(function(w) { return '<option value="' + w + '">' + weekLabel(w) + '</option>'; }).join('') +
      '</select>';

  } else if (cat === 'bulanan') {
    var months = [], seenM = {};
    DATA.forEach(function(o) {
      var mk = getMonthKey(o.tanggal);
      if (!seenM[mk]) { seenM[mk] = true; months.push(mk); }
    });
    months.sort().reverse();
    row.innerHTML =
      '<label>Pilih Bulan:</label>' +
      '<select id="rdpFilterBulanan">' +
      months.map(function(m) { return '<option value="' + m + '">' + monthLabel(m) + '</option>'; }).join('') +
      '</select>';

  } else {
    row.innerHTML =
      '<span class="rdp-all-note"><i class="fa-solid fa-circle-info"></i> Menampilkan seluruh transaksi.</span>';
  }
}

/* ─────────────────────────────────────────
   GENERATE REKAP
───────────────────────────────────────── */
function generateRdpRecap() {
  var DATA     = window.rekapData || [];
  var filtered = [];
  var title    = '';

  if (rdpCategory === 'harian') {
    var tgl = document.getElementById('rdpFilterHarian').value;
    filtered = DATA.filter(function(o) { return dateOnly(o.tanggal) === tgl; });
    title = 'Rekap Harian \u2013 ' + dateDisplay(tgl);

  } else if (rdpCategory === 'mingguan') {
    var wk = document.getElementById('rdpFilterMingguan').value;
    filtered = DATA.filter(function(o) { return getWeekNumber(o.tanggal) === wk; });
    title = 'Rekap ' + weekLabel(wk);

  } else if (rdpCategory === 'bulanan') {
    var bln = document.getElementById('rdpFilterBulanan').value;
    filtered = DATA.filter(function(o) { return getMonthKey(o.tanggal) === bln; });
    title = 'Rekap Bulanan \u2013 ' + monthLabel(bln);

  } else {
    filtered = DATA.slice();
    title = 'Rekap Semua Transaksi';
  }

  renderRdpOutput(filtered, title);
}

function renderRdpOutput(filtered, title) {
  var output    = document.getElementById('rdpOutput');
  var actionsEl = document.getElementById('rdpBottomActions');

  if (!filtered.length) {
    output.innerHTML =
      '<div class="rdp-no-result">' +
        '<i class="fa-solid fa-magnifying-glass"></i> Tidak ada data untuk periode ini.' +
      '</div>';
    actionsEl.style.display = 'none';
    return;
  }

  var grandTotal = filtered.reduce(function(s, o) { return s + parseFloat(o.total || 0); }, 0);

  var grouped = {};
  filtered.forEach(function(o) {
    var key = dateOnly(o.tanggal);
    if (!grouped[key]) grouped[key] = [];
    grouped[key].push(o);
  });
  var sortedDates = Object.keys(grouped).sort();

  var html =
    '<div class="rdp-result-box">' +
      '<div class="rdp-result-title">' + title + '</div>' +
      '<div class="rdp-result-summary">' +
        '<span>' + filtered.length + ' Transaksi</span>' +
        '<strong>' + rupiahFmt(grandTotal) + '</strong>' +
      '</div>';

  sortedDates.forEach(function(date) {
    var items    = grouped[date];
    var dayTotal = items.reduce(function(s, o) { return s + parseFloat(o.total || 0); }, 0);
    html +=
      '<div class="rdp-day-group">' +
        '<div class="rdp-day-header">' +
          '<span>' + dateDisplay(date) + '</span>' +
          '<span>' + rupiahFmt(dayTotal) + '</span>' +
        '</div>';
    items.forEach(function(o) {
      var waktu = o.tanggal ? o.tanggal.substring(11, 16) : '-';
      html +=
        '<div class="rdp-row">' +
          '<span class="rdp-row-produk">' + (o.produk || '-') + '</span>' +
          '<span class="rdp-row-pembeli">' + (o.pembeli || 'Anonim') + ' &bull; ' + waktu + '</span>' +
          '<span class="rdp-row-harga">' + rupiahFmt(o.total || 0) + '</span>' +
        '</div>';
    });
    html += '</div>';
  });

  html += '</div>';
  output.innerHTML = html;
  actionsEl.style.display = 'flex';
}

/* ─────────────────────────────────────────
   PRINT REKAP
───────────────────────────────────────── */
function printRdpRecap() {
  var content = document.getElementById('rdpOutput').innerHTML;
  if (!content.trim()) { alert('Tidak ada data rekap!'); return; }
  var css =
    'body{font-family:Arial,sans-serif;padding:40px;background:#fff;color:#222;}' +
    'h1{color:#ff6b00;margin-bottom:4px;}p{color:#888;margin-bottom:20px;}' +
    '.rdp-result-title{font-size:1.15rem;font-weight:700;color:#ff6b00;margin-bottom:8px;}' +
    '.rdp-result-summary{display:flex;justify-content:space-between;background:#fff3eb;padding:10px 14px;border-radius:8px;margin-bottom:16px;font-weight:600;}' +
    '.rdp-day-group{margin-bottom:14px;}' +
    '.rdp-day-header{display:flex;justify-content:space-between;background:#ff6b00;color:#fff;padding:8px 12px;border-radius:6px;font-weight:600;margin-bottom:4px;}' +
    '.rdp-row{display:flex;justify-content:space-between;padding:6px 12px;border-bottom:1px solid #eee;font-size:.9rem;}' +
    '.rdp-row-produk{flex:2;font-weight:600;}.rdp-row-pembeli{flex:2;color:#777;}.rdp-row-harga{flex:1;text-align:right;color:#c84b11;font-weight:700;}';
  var win = window.open('', '_blank', 'width=900,height=700');
  win.document.write('<!DOCTYPE html><html lang="id"><head><meta charset="UTF-8"><title>Rekap Penjualan</title><style>' + css + '</style></head><body><h1>Rekap Penjualan E-Canteen</h1><p>Dicetak: ' + new Date().toLocaleString('id-ID') + '</p>' + content + '</body></html>');
  win.document.close();
  setTimeout(function() { win.print(); }, 500);
}

function downloadRdpPNG() {
  var el = document.getElementById('rdpOutput');
  if (!el.innerHTML.trim()) { alert('Tidak ada data rekap!'); return; }
  html2canvas(el, { backgroundColor: '#fff', scale: 2 }).then(function(canvas) {
    var link = document.createElement('a');
    link.download = 'rekap-penjualan.png';
    link.href = canvas.toDataURL();
    link.click();
  });
}

/* ─────────────────────────────────────────
   STRUK POPUP
───────────────────────────────────────── */
function showStruk(orderId) {
  var DATA  = window.rekapData || [];
  var order = DATA.find(function(o) { return o.id == orderId; });
  if (!order) return;
  var waktu = order.tanggal ? order.tanggal.substring(11, 16) : '-';
  var html =
    '<div class="struk-box">' +
      '<h2 style="text-align:center;color:#e8714a;">E-Canteen</h2><hr>' +
      '<p><strong>Order ID:</strong> #' + order.id + '</p>' +
      '<p><strong>Pembeli:</strong> '   + (order.pembeli || 'Anonim') + '</p>' +
      '<p><strong>Produk:</strong> '    + (order.produk  || '-') + '</p>' +
      '<p><strong>Jumlah:</strong> '    + (order.jumlah  || 1) + 'x</p>' +
      '<p><strong>Metode:</strong> '    + (order.metode  || '-') + '</p>' +
      '<p><strong>Tanggal:</strong> '   + dateDisplay(order.tanggal) + '</p>' +
      '<p><strong>Waktu:</strong> '     + waktu + '</p><hr>' +
      '<h3 style="text-align:right;color:#c84b11;">' + rupiahFmt(order.total || 0) + '</h3>' +
      '<p style="text-align:center;font-size:12px;color:#777;">Terima kasih telah membeli ❤️</p>' +
    '</div>';
  document.getElementById('strukPreviewArea').innerHTML = html;
  document.getElementById('strukPopup').classList.add('show');
}

function closeStrukPopup() {
  document.getElementById('strukPopup').classList.remove('show');
}

function downloadCurrentStruk() {
  var el = document.getElementById('strukPreviewArea');
  if (!el.innerHTML.trim()) { alert('Tidak ada struk.'); return; }
  html2canvas(el, { backgroundColor: '#fff', scale: 2 }).then(function(canvas) {
    var link = document.createElement('a');
    link.download = 'struk-e-canteen.png';
    link.href = canvas.toDataURL();
    link.click();
  });
}

/* ─────────────────────────────────────────
   PRINT FULL TABLE
───────────────────────────────────────── */
function printFullTable() {
  closeTableMenu();
  var DATA       = window.rekapData || [];
  var grandTotal = DATA.reduce(function(s, o) { return s + parseFloat(o.total || 0); }, 0);
  var rows = '';
  DATA.forEach(function(o, idx) {
    var waktu = o.tanggal ? o.tanggal.substring(11, 16) : '-';
    rows +=
      '<tr><td>' + (idx+1) + '</td><td>' + (o.produk || '-') + '</td><td>#' + o.id +
      '</td><td>' + dateDisplay(o.tanggal) + '</td><td>' + waktu +
      '</td><td>' + (o.pembeli || 'Anonim') + '</td><td>' + rupiahFmt(o.total || 0) + '</td></tr>';
  });
  var css =
    'body{font-family:Arial,sans-serif;padding:40px;background:#f5f5f5;color:#222;}' +
    '.container{background:#fff;padding:30px;border-radius:16px;box-shadow:0 5px 20px rgba(0,0,0,.1);}' +
    'h1{color:#ff6b00;}p{color:#666;margin-bottom:20px;}' +
    'table{width:100%;border-collapse:collapse;}thead{background:#ff6b00;color:#fff;}' +
    'th,td{padding:14px;border:1px solid #eee;text-align:left;}' +
    'tbody tr:nth-child(even){background:#fafafa;}' +
    '.total-row{background:#fff3eb;font-weight:bold;}';
  var win = window.open('', '_blank', 'width=1000,height=700');
  win.document.write(
    '<!DOCTYPE html><html lang="id"><head><meta charset="UTF-8"><title>Rekapan Pembelian</title>' +
    '<style>' + css + '</style></head><body><div class="container">' +
    '<h1>Rekapan Pembelian</h1>' +
    '<p>Dicetak: ' + new Date().toLocaleString('id-ID') + ' &bull; ' + DATA.length + ' Transaksi</p>' +
    '<table><thead><tr><th>#</th><th>Produk</th><th>Order ID</th><th>Tanggal</th><th>Waktu</th><th>Pembeli</th><th>Total</th></tr></thead>' +
    '<tbody>' + rows +
    '<tr class="total-row"><td colspan="6">TOTAL KESELURUHAN</td><td>' + rupiahFmt(grandTotal) + '</td></tr>' +
    '</tbody></table></div></body></html>'
  );
  win.document.close();
  win.focus();
  setTimeout(function() { win.print(); }, 800);
}

/* ─────────────────────────────────────────
   TABLE DROPDOWN
───────────────────────────────────────── */
function toggleTableMenu(event) {
  event.stopPropagation();
  document.getElementById('tableDropdown').classList.toggle('show');
  // tutup stok dropdown kalau buka
  var sd = document.getElementById('stokDropdown');
  if (sd) sd.classList.remove('show');
}

function closeTableMenu() {
  var el = document.getElementById('tableDropdown');
  if (el) el.classList.remove('show');
}

/* ─────────────────────────────────────────
   STOK DROPDOWN
───────────────────────────────────────── */
function toggleStokMenu(event) {
  event.stopPropagation();
  document.getElementById('stokDropdown').classList.toggle('show');
  // tutup tabel dropdown kalau buka
  var td = document.getElementById('tableDropdown');
  if (td) td.classList.remove('show');
}

function closeStokMenu() {
  var el = document.getElementById('stokDropdown');
  if (el) el.classList.remove('show');
}

/* ═══════════════════════════════════════════
   STOK HARIAN — Engine Utama
   Sumber data: window.stokTerjualData & window.stokDitambahData
═══════════════════════════════════════════ */
(function () {
  var ROWS_PER_PAGE  = 10;
  var currentPage    = 1;
  var currentData    = [];
  var activeDayRange = 14; // default 14 hari

  /* ── Merge terjual + ditambah jadi satu array flat ── */
  function buildMergedData(dayRange) {
    var terjualArr  = window.stokTerjualData   || [];
    var ditambahArr = window.stokDitambahData  || [];

    // batas tanggal
    var cutoff = new Date();
    cutoff.setDate(cutoff.getDate() - dayRange);
    var cutoffStr = cutoff.toISOString().substring(0, 10);

    // filter terjual sesuai range
    var filtered = terjualArr.filter(function(r) {
      return r.tgl >= cutoffStr;
    });

    // lookup ditambah: key = tgl + '|' + nama_menu
    var ditambahMap = {};
    ditambahArr.forEach(function(r) {
      if (r.tgl < cutoffStr) return;
      var key = r.tgl + '|' + (r.nama_menu || '');
      ditambahMap[key] = (ditambahMap[key] || 0) + (r.ditambah || 0);
    });

    // gabungkan
    var merged = filtered.map(function(r) {
      var key      = r.tgl + '|' + (r.nama_menu || '');
      var ditambah = ditambahMap[key] || 0;
      return {
        tgl       : r.tgl,
        nama_menu : r.nama_menu || '-',
        terjual   : r.terjual   || 0,
        ditambah  : ditambah,
        selisih   : ditambah - (r.terjual || 0),
        pendapatan: r.pendapatan || 0,
      };
    });

    // Juga tambahkan baris dari ditambah yang tidak ada di terjual
    var terjualKeys = {};
    filtered.forEach(function(r) {
      terjualKeys[r.tgl + '|' + (r.nama_menu || '')] = true;
    });
    ditambahArr.forEach(function(r) {
      if (r.tgl < cutoffStr) return;
      var key = r.tgl + '|' + (r.nama_menu || '');
      if (!terjualKeys[key]) {
        merged.push({
          tgl       : r.tgl,
          nama_menu : r.nama_menu || '-',
          terjual   : 0,
          ditambah  : r.ditambah || 0,
          selisih   : r.ditambah || 0,
          pendapatan: 0,
        });
      }
    });

    // sort terbaru dulu
    merged.sort(function(a, b) {
      if (b.tgl !== a.tgl) return b.tgl.localeCompare(a.tgl);
      return (b.terjual - a.terjual);
    });

    return merged;
  }

  /* ── Summary cards ── */
  function renderSummaryCards(data) {
    var wrap = document.getElementById('stokSummaryCards');
    if (!wrap) return;

    var totalTerjual    = 0;
    var totalDitambah   = 0;
    var totalPendapatan = 0;
    var defisitCount    = 0;
    data.forEach(function(r) {
      totalTerjual    += r.terjual;
      totalDitambah   += r.ditambah;
      totalPendapatan += r.pendapatan;
      if (r.selisih < 0) defisitCount++;
    });
    var totalSelisih = totalDitambah - totalTerjual;

    var svgTerjual =
      '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="#e8714a">' +
        '<path d="M17 18a2 2 0 0 1 2 2a2 2 0 0 1-2 2a2 2 0 0 1-2-2c0-1.11.89-2 2-2M1 2h3.27l.94 2H20a1 1 0 0 1 1 1c0 .17-.05.34-.12.5l-3.58 6.47c-.34.61-1 1.03-1.75 1.03H8.1l-.9 1.63l-.03.12a.25.25 0 0 0 .25.25H19v2H7a2 2 0 0 1-2-2c0-.35.09-.68.24-.96l1.36-2.45L3 4H1zm6 16a2 2 0 0 1 2 2a2 2 0 0 1-2 2a2 2 0 0 1-2-2c0-1.11.89-2 2-2m9-7l2.78-5H6.14l2.36 5z"/>' +
      '</svg>';

    var svgDitambah =
      '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="#22c55e">' +
        '<path d="M19 3H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V5a2 2 0 0 0-2-2m-7 14a1 1 0 0 1-1-1v-4H7a1 1 0 0 1 0-2h4V6a1 1 0 0 1 2 0v4h4a1 1 0 0 1 0 2h-4v4a1 1 0 0 1-1 1"/>' +
      '</svg>';

    var svgSelisih = '<i class="fa-solid fa-scale-balanced" style="color:' + (totalSelisih >= 0 ? '#22c55e' : '#ef4444') + ';font-size:1.1rem;"></i>';

    var svgPendapatan =
      '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="#f59e0b">' +
        '<path d="M11.8 10.9c-2.27-.59-3-1.2-3-2.15c0-1.09 1.01-1.85 2.7-1.85c1.78 0 2.44.85 2.5 2.1h2.21c-.07-1.72-1.12-3.3-3.21-3.81V3h-3v2.16c-1.94.42-3.5 1.68-3.5 3.61c0 2.31 1.91 3.46 4.7 4.13c2.5.6 3 1.48 3 2.41c0 .69-.49 1.79-2.7 1.79c-2.06 0-2.87-.92-2.98-2.1h-2.2c.12 2.19 1.76 3.42 3.68 3.83V21h3v-2.15c1.95-.37 3.5-1.5 3.5-3.55c0-2.84-2.43-3.81-4.7-4.4"/>' +
      '</svg>';

    wrap.innerHTML =
      '<div class="stok-summary-card">' +
        '<div class="stok-sc-icon" style="background:#fff0e8;">' + svgTerjual + '</div>' +
        '<div class="stok-sc-body">' +
          '<div class="stok-sc-label">Total Terjual</div>' +
          '<div class="stok-sc-value" style="color:#e8714a;">' + totalTerjual.toLocaleString('id-ID') + ' item</div>' +
        '</div>' +
      '</div>' +
      '<div class="stok-summary-card">' +
        '<div class="stok-sc-icon" style="background:#e8f9ee;">' + svgDitambah + '</div>' +
        '<div class="stok-sc-body">' +
          '<div class="stok-sc-label">Total Ditambah</div>' +
          '<div class="stok-sc-value" style="color:#22c55e;">+ ' + totalDitambah.toLocaleString('id-ID') + ' item</div>' +
        '</div>' +
      '</div>' +
      '<div class="stok-summary-card">' +
        '<div class="stok-sc-icon" style="background:' + (totalSelisih >= 0 ? '#e8f9ee' : '#fde8e8') + ';">' + svgSelisih + '</div>' +
        '<div class="stok-sc-body">' +
          '<div class="stok-sc-label">Selisih Bersih</div>' +
          '<div class="stok-sc-value" style="color:' + (totalSelisih >= 0 ? '#22c55e' : '#ef4444') + ';">' +
            (totalSelisih >= 0 ? '+' : '') + totalSelisih.toLocaleString('id-ID') + ' item' +
          '</div>' +
        '</div>' +
      '</div>' +
      '<div class="stok-summary-card">' +
        '<div class="stok-sc-icon" style="background:#fff9e6;">' + svgPendapatan + '</div>' +
        '<div class="stok-sc-body">' +
          '<div class="stok-sc-label">Total Pendapatan</div>' +
          '<div class="stok-sc-value" style="color:#f59e0b;">' + rupiahFmt(totalPendapatan) + '</div>' +
        '</div>' +
      '</div>';
  }

  /* ── Render Tabel ── */
  function renderStokTable(data, page) {
    currentData = data;
    currentPage = page || 1;

    var tbody    = document.getElementById('stokHarianBody');
    var pagWrap  = document.getElementById('stokPaginationWrap');
    if (!tbody) return;

    var totalPages = Math.ceil(data.length / ROWS_PER_PAGE);
    var start      = (currentPage - 1) * ROWS_PER_PAGE;
    var slice      = data.slice(start, start + ROWS_PER_PAGE);

    if (!slice.length) {
      tbody.innerHTML =
        '<tr><td colspan="7" class="stok-empty-row">' +
          '<i class="fa-solid fa-box-open" style="font-size:2rem;color:#f0c0a0;margin-bottom:8px;display:block;"></i>' +
          'Belum ada data stok untuk periode ini.' +
        '</td></tr>';
      if (pagWrap) pagWrap.innerHTML = '';
      return;
    }

    var html = '';
    slice.forEach(function(r, i) {
      var idx         = start + i + 1;
      var selClass    = r.selisih > 0 ? 'selisih-plus' : (r.selisih < 0 ? 'selisih-minus' : 'selisih-zero');
      var selPrefix   = r.selisih > 0 ? '+' : '';
      var tglDisplay  = new Date(r.tgl + 'T00:00:00').toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' });

      html +=
        '<tr class="stok-row">' +
          '<td class="stok-td-no">' + idx + '</td>' +
          '<td class="stok-td-tgl">' +
            '<span class="stok-tgl-badge">' + tglDisplay + '</span>' +
          '</td>' +
          '<td class="stok-td-menu">' +
            '<span class="stok-menu-name">' + escHtml(r.nama_menu) + '</span>' +
          '</td>' +
          '<td class="stok-td-center">' +
            '<span class="stok-qty-badge terjual">' +
              '<i class="fa-solid fa-arrow-down"></i> ' + r.terjual +
            '</span>' +
          '</td>' +
          /* ── PERUBAHAN: icon fa-plus untuk stok ditambah ── */
          '<td class="stok-td-center">' +
            (r.ditambah > 0
              ? '<span class="stok-qty-badge ditambah"><i class="fa-solid fa-plus"></i> ' + r.ditambah + '</span>'
              : '<span class="stok-qty-badge none">—</span>'
            ) +
          '</td>' +
          '<td class="stok-td-center">' +
            '<span class="stok-selisih ' + selClass + '">' + selPrefix + r.selisih + '</span>' +
          '</td>' +
          '<td class="stok-td-right">' +
            '<span class="stok-pendapatan">' + rupiahFmt(r.pendapatan) + '</span>' +
          '</td>' +
        '</tr>';
    });

    tbody.innerHTML = html;

    /* pagination */
    if (pagWrap) {
      if (totalPages <= 1) { pagWrap.innerHTML = ''; return; }
      var ph = '<button class="page-btn nav-btn" onclick="stokGoPage(' + (currentPage-1) + ')" ' + (currentPage===1?'disabled':'') + '>&lsaquo; Prev</button>';
      for (var p = 1; p <= totalPages; p++) {
        ph += '<button class="page-btn' + (p===currentPage?' active':'') + '" onclick="stokGoPage(' + p + ')">' + p + '</button>';
      }
      ph += '<button class="page-btn nav-btn" onclick="stokGoPage(' + (currentPage+1) + ')" ' + (currentPage===totalPages?'disabled':'') + '>Next &rsaquo;</button>';
      pagWrap.innerHTML = ph;
    }
  }

  /* ── Escape HTML sederhana ── */
  function escHtml(str) {
    return String(str)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  /* ── Public API ── */
  window.stokGoPage = function(p) {
    var totalPages = Math.ceil(currentData.length / ROWS_PER_PAGE);
    if (p < 1 || p > totalPages) return;
    renderStokTable(currentData, p);
    // scroll ke tabel
    var wrap = document.getElementById('stokTableWrap');
    if (wrap) wrap.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
  };

  window.filterStokHarian = function(days) {
    activeDayRange = parseInt(days) || 14;
    var data = buildMergedData(activeDayRange);
    renderSummaryCards(data);
    renderStokTable(data, 1);
  };

  /* ── Print Stok Harian ── */
  window.printStokHarian = function() {
    closeStokMenu();
    var data = buildMergedData(activeDayRange);
    if (!data.length) { alert('Tidak ada data stok untuk dicetak!'); return; }

    var grandTerjual    = 0;
    var grandDitambah   = 0;
    var grandPendapatan = 0;
    var rows = '';
    data.forEach(function(r, idx) {
      var tglDisplay = new Date(r.tgl + 'T00:00:00').toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' });
      var selColor   = r.selisih > 0 ? '#16a34a' : (r.selisih < 0 ? '#dc2626' : '#555');
      var selPrefix  = r.selisih > 0 ? '+' : '';
      rows +=
        '<tr style="background:' + (idx%2===1?'#fff8f4':'#fff') + '">' +
          '<td style="text-align:center;">'  + (idx+1)             + '</td>' +
          '<td>'                             + tglDisplay           + '</td>' +
          '<td><strong>'                     + r.nama_menu          + '</strong></td>' +
          '<td style="text-align:center;color:#e8714a;font-weight:700;">' + r.terjual   + '</td>' +
          '<td style="text-align:center;color:#22c55e;font-weight:700;">+ ' + r.ditambah  + '</td>' +
          '<td style="text-align:center;color:' + selColor + ';font-weight:700;">' + selPrefix + r.selisih + '</td>' +
          '<td style="text-align:right;font-weight:600;">' + rupiahFmt(r.pendapatan) + '</td>' +
        '</tr>';
      grandTerjual    += r.terjual;
      grandDitambah   += r.ditambah;
      grandPendapatan += r.pendapatan;
    });

    var printDate   = new Date().toLocaleString('id-ID');
    var labelRange  = activeDayRange + ' Hari Terakhir';

    var css =
      '@import url("https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap");' +
      'body{font-family:"Plus Jakarta Sans",Arial,sans-serif;padding:32px 48px;background:#fdf6f0;color:#222;margin:0;}' +
      '.print-header{background:linear-gradient(135deg,#c84b11,#e8714a);color:#fff;padding:24px 32px;border-radius:14px;margin-bottom:24px;display:flex;align-items:center;justify-content:space-between;}' +
      '.print-header h1{margin:0;font-size:1.4rem;font-weight:800;letter-spacing:.3px;}' +
      '.print-header .ph-meta{font-size:.82rem;opacity:.85;margin-top:4px;}' +
      '.print-header .ph-badge{background:rgba(255,255,255,.2);padding:8px 16px;border-radius:20px;font-size:.82rem;font-weight:700;white-space:nowrap;}' +
      '.summary-bar{display:flex;gap:12px;margin-bottom:22px;flex-wrap:wrap;}' +
      '.sum-card{flex:1;min-width:140px;background:#fff;border-radius:10px;padding:14px 16px;box-shadow:0 2px 8px rgba(232,119,34,.1);border:1.5px solid #f5dece;}' +
      '.sum-card .sc-label{font-size:.72rem;color:#888;font-weight:700;text-transform:uppercase;letter-spacing:.5px;margin-bottom:4px;}' +
      '.sum-card .sc-val{font-size:1.1rem;font-weight:800;}' +
      'table{width:100%;border-collapse:collapse;background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 2px 10px rgba(0,0,0,.07);}' +
      'thead tr{background:linear-gradient(135deg,#e8714a,#f5a87b);}' +
      'thead th{color:#fff;font-size:.8rem;font-weight:700;padding:12px 10px;text-align:left;white-space:nowrap;letter-spacing:.3px;}' +
      'tbody tr{border-bottom:1px solid #fdf0ea;}' +
      'tbody td{padding:10px 10px;font-size:.82rem;border-bottom:1px solid #f8f0ea;}' +
      '.total-row td{background:#fff3e8;font-weight:800;font-size:.88rem;border-top:2px solid #e8714a;}' +
      '.keterangan{margin-top:16px;font-size:.75rem;color:#aaa;font-style:italic;}' +
      '@media print{body{background:#fff;padding:20px;}}';

    var win = window.open('', '_blank', 'width=1050,height=750');
    win.document.write(
      '<!DOCTYPE html><html lang="id"><head><meta charset="UTF-8"><title>Rekap Stok Harian</title><style>' + css + '</style></head><body>' +
      '<div class="print-header">' +
        '<div><h1>📦 Rekap Stok Harian E-Canteen</h1><div class="ph-meta">Dicetak: ' + printDate + '</div></div>' +
        '<div class="ph-badge">📅 ' + labelRange + '</div>' +
      '</div>' +
      '<div class="summary-bar">' +
        '<div class="sum-card"><div class="sc-label">Total Terjual</div><div class="sc-val" style="color:#e8714a;">' + grandTerjual.toLocaleString('id-ID') + ' item</div></div>' +
        '<div class="sum-card"><div class="sc-label">Total Ditambah</div><div class="sc-val" style="color:#22c55e;"><span style="font-weight:900;margin-right:3px;">+</span>' + grandDitambah.toLocaleString('id-ID') + ' item</div></div>' +
        '<div class="sum-card"><div class="sc-label">Selisih Bersih</div><div class="sc-val" style="color:' + ((grandDitambah-grandTerjual)>=0?'#22c55e':'#ef4444') + ';">' + (grandDitambah-grandTerjual>=0?'+':'') + (grandDitambah-grandTerjual).toLocaleString('id-ID') + ' item</div></div>' +
        '<div class="sum-card"><div class="sc-label">Total Pendapatan</div><div class="sc-val" style="color:#f59e0b;">' + rupiahFmt(grandPendapatan) + '</div></div>' +
      '</div>' +
      '<table><thead><tr><th>#</th><th>Tanggal</th><th>Nama Menu</th><th style="text-align:center;">Terjual</th><th style="text-align:center;">Ditambah</th><th style="text-align:center;">Selisih</th><th style="text-align:right;">Pendapatan</th></tr></thead>' +
      '<tbody>' + rows +
      '<tr class="total-row">' +
        '<td colspan="3">TOTAL KESELURUHAN</td>' +
        '<td style="text-align:center;color:#e8714a;">' + grandTerjual.toLocaleString('id-ID') + '</td>' +
        '<td style="text-align:center;color:#22c55e;">+ ' + grandDitambah.toLocaleString('id-ID') + '</td>' +
        '<td style="text-align:center;color:' + ((grandDitambah-grandTerjual)>=0?'#22c55e':'#ef4444') + ';">' + (grandDitambah-grandTerjual>=0?'+':'') + (grandDitambah-grandTerjual).toLocaleString('id-ID') + '</td>' +
        '<td style="text-align:right;">' + rupiahFmt(grandPendapatan) + '</td>' +
      '</tr>' +
      '</tbody></table>' +
      '<p class="keterangan">* Selisih = Stok Ditambah - Stok Terjual. Hijau = surplus, Merah = defisit.</p>' +
      '</body></html>'
    );
    win.document.close();
    win.focus();
    setTimeout(function() { win.print(); }, 700);
  };

  /* ── Export Excel ── */
  window.exportStokExcel = function() {
    closeStokMenu();
    var overlay = document.getElementById('excelLoadingOverlay');
    if (overlay) { overlay.style.display = 'flex'; }

    var url = 'dasbord.php?action=export_stok_excel&_=' + Date.now();

    fetch(url)
      .then(function(res) {
        var ct = res.headers.get('Content-Type') || '';
        if (ct.includes('json')) {
          return res.json().then(function(d) { throw new Error(d.error || 'Export gagal'); });
        }
        return res.blob();
      })
      .then(function(blob) {
        var a   = document.createElement('a');
        a.href  = URL.createObjectURL(blob);
        a.download = 'Rekap_Stok_Harian_' + new Date().toISOString().replace(/[:.]/g, '-').substring(0,19) + '.xlsx';
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(a.href);
      })
      .catch(function(err) {
        alert('Gagal export Excel: ' + err.message);
      })
      .finally(function() {
        if (overlay) { overlay.style.display = 'none'; }
      });
  };

  /* ── Init saat DOM ready ── */
  function initStokHarian() {
    var data = buildMergedData(activeDayRange);
    renderSummaryCards(data);
    renderStokTable(data, 1);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initStokHarian);
  } else {
    initStokHarian();
  }

})(); // end IIFE stok harian

/* ═══════════════════════════════════════════
   MOBILE SIDEBAR DRAWER
═══════════════════════════════════════════ */
function toggleSidebar() {
  var sidebar = document.querySelector('.sidebar');
  var overlay = document.getElementById('sidebarOverlay');
  var btn     = document.getElementById('hamburgerBtn');
  var isOpen  = sidebar.classList.toggle('open');
  overlay.classList.toggle('show', isOpen);
  btn.innerHTML = isOpen
    ? '<i class="fa-solid fa-xmark"></i>'
    : '<i class="fa-solid fa-bars"></i>';
  btn.setAttribute('aria-expanded', isOpen);
}

function closeSidebar() {
  var sidebar = document.querySelector('.sidebar');
  var overlay = document.getElementById('sidebarOverlay');
  var btn     = document.getElementById('hamburgerBtn');
  sidebar.classList.remove('open');
  overlay.classList.remove('show');
  btn.innerHTML = '<i class="fa-solid fa-bars"></i>';
  btn.setAttribute('aria-expanded', 'false');
}

window.addEventListener('resize', function() {
  if (window.innerWidth > 768) closeSidebar();
});

/* ═══════════════════════════════════════════
   INIT
═══════════════════════════════════════════ */
document.addEventListener('DOMContentLoaded', function() {

  /* Klik di luar semua dropdown & panel rekap → tutup semuanya */
  document.addEventListener('click', function(e) {
    closeTableMenu();
    closeStokMenu();

    var panel  = document.getElementById('rekapDropdownPanel');
    var navbar = document.querySelector('.navbar');
    if (rekapPanelOpen &&
        !panel.contains(e.target) &&
        !navbar.contains(e.target)) {
      closeRekapPanel();
    }
  });

  document.getElementById('strukPopup').addEventListener('click', function(e) {
    if (e.target.id === 'strukPopup') closeStrukPopup();
  });

});