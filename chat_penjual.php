<?php
session_start();
if (empty($_SESSION["id_penjual"])) {
    header("Location: login.php");
    exit;
}
$id_penjual = (int)$_SESSION["id_penjual"];
?>
<!DOCTYPE html>
<html lang="id" data-theme="light">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Chat – E-Canteen (Penjual)</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="../chat.css">
<style>
  /* ── Icon helpers (inline, no dep) ─────────────────────────── */
  .ic { font-style: normal; line-height: 1; }
  .ic-chat::before    { content: '💬'; }
  .ic-back::before    { content: '←'; font-style: normal; }
  .ic-send::before    { content: '➤'; font-style: normal; }

  /* Sun icon */
  .icon-sun { display:none; }
  .icon-moon { display:block; }
  [data-theme="dark"] .icon-sun  { display:block; }
  [data-theme="dark"] .icon-moon { display:none; }
</style>
</head>
<body>

<div class="chat-wrapper">

  <!-- ══ SIDE NAV (opsional, jika dipakai) ═══════════════════ -->
  <!-- Uncomment jika proyek memakai sidenav multi-halaman
  <nav class="sidenav">
    <div class="sidenav-logo">EC</div>
    <a href="dashboard.php" class="sidenav-item" data-tip="Dashboard">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
    </a>
    <a href="chat.php" class="sidenav-item active" data-tip="Pesan">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
    </a>
    <div class="sidenav-spacer"></div>
    <div class="sidenav-avatar"><?= strtoupper(substr($_SESSION['nama_penjual'] ?? 'P', 0, 1)) ?></div>
  </nav>
  -->

  <!-- ══ SIDEBAR — daftar room ════════════════════════════════ -->
  <aside class="sidebar" id="sidebar">
    <div class="sidebar-header">
      <div class="sidebar-title">
        <!-- Tombol kembali ke Dashboard -->
        <a href="dasbord.php" class="btn-back-dashboard" title="Kembali ke Dasbor">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
            <polyline points="15 18 9 12 15 6"/>
          </svg>
          <span>Dasbor</span>
        </a>

        <!-- Judul -->
        <span style="flex:1; text-align:center; font-size:15px; font-weight:800; color:var(--text);">Pesan Masuk</span>

        <!-- Tombol dark/light mode -->
        <button class="btn-theme" id="btnTheme" title="Ganti tema" style="
          width:34px; height:34px; border-radius:50%;
          border: 1.5px solid var(--border);
          background: var(--surface-2);
          color: var(--text-2); cursor:pointer;
          display:flex; align-items:center; justify-content:center;
          flex-shrink:0; transition: background .15s;
        ">
          <!-- Moon icon (tampil di light mode) -->
          <svg class="icon-moon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/>
          </svg>
          <!-- Sun icon (tampil di dark mode) -->
          <svg class="icon-sun" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="12" cy="12" r="5"/>
            <line x1="12" y1="1" x2="12" y2="3"/>
            <line x1="12" y1="21" x2="12" y2="23"/>
            <line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/>
            <line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/>
            <line x1="1" y1="12" x2="3" y2="12"/>
            <line x1="21" y1="12" x2="23" y2="12"/>
            <line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/>
            <line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/>
          </svg>
        </button>
      </div>
    </div>

    <div class="room-list" id="roomList">
      <div class="room-placeholder">Memuat percakapan…</div>
    </div>
  </aside>

  <!-- ══ AREA CHAT ═════════════════════════════════════════════ -->
  <main class="chat-main" id="chatMain">

    <div class="chat-empty" id="chatEmpty">
      <div class="empty-icon">💬</div>
      <p>Pilih percakapan dari daftar</p>
      <small>Klik salah satu pesan di sebelah kiri untuk mulai membalas</small>
    </div>

    <div class="chat-active hidden" id="chatActive">

      <header class="chat-header" id="chatHeader">
        <!-- Tombol kembali ke daftar room (mobile) -->
        <button class="btn-back" id="btnBack" title="Kembali">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
            <polyline points="15 18 9 12 15 6"/>
          </svg>
        </button>

        <span class="avatar" id="headerAvatar">?</span>

        <div class="header-info">
          <strong id="headerNama">–</strong>
          <small id="headerKantin">–</small>
        </div>

        <!-- Aksi di header chat -->
        <div class="header-actions">
          <!-- Tombol kembali ke Dashboard (dari panel chat) -->
          <a href="dashboard.php" class="btn-back-dashboard" title="Kembali ke Dasbor">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" width="14" height="14">
              <polyline points="15 18 9 12 15 6"/>
            </svg>
            <span>Dasbor</span>
          </a>

          <!-- Tombol dark mode di header -->
          <button class="btn-theme" id="btnThemeChat" title="Ganti tema">
            <svg class="icon-moon" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/>
            </svg>
            <svg class="icon-sun" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <circle cx="12" cy="12" r="5"/>
              <line x1="12" y1="1" x2="12" y2="3"/>
              <line x1="12" y1="21" x2="12" y2="23"/>
              <line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/>
              <line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/>
              <line x1="1" y1="12" x2="3" y2="12"/>
              <line x1="21" y1="12" x2="23" y2="12"/>
              <line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/>
              <line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/>
            </svg>
          </button>
        </div>
      </header>

      <div class="chat-body" id="chatBody"></div>

      <footer class="chat-footer">
        <textarea id="inputPesan" rows="1"
                  placeholder="Tulis balasan…"
                  maxlength="2000"></textarea>
        <button class="btn-kirim" id="btnKirim" title="Kirim">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor">
            <path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z"/>
          </svg>
        </button>
      </footer>
    </div>

  </main>
</div>

<script>
/* ──────────────────────────────────────────────────────────────
   DARK / LIGHT MODE
────────────────────────────────────────────────────────────── */
(function () {
  const root    = document.documentElement;
  const stored  = localStorage.getItem('ecanteen-theme');
  const sysDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
  const initial = stored || (sysDark ? 'dark' : 'light');
  root.setAttribute('data-theme', initial);

  function toggleTheme() {
    const cur  = root.getAttribute('data-theme');
    const next = cur === 'dark' ? 'light' : 'dark';
    root.setAttribute('data-theme', next);
    localStorage.setItem('ecanteen-theme', next);
  }

  // Daftarkan semua tombol theme (sidebar + header)
  document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('#btnTheme, #btnThemeChat').forEach(btn => {
      btn.addEventListener('click', toggleTheme);
    });
  });
})();

/* ──────────────────────────────────────────────────────────────
   LOGIKA CHAT (tidak diubah)
────────────────────────────────────────────────────────────── */
const SESSION_ROLE = 'penjual';
const SESSION_ID   = <?= $id_penjual ?>;
const API = '../chat_api.php';

let activeRoom   = null;
let lastPesanId  = 0;
let pollingTimer = null;

muatDaftarRoom();

async function muatDaftarRoom() {
  const data = await api('daftar_room');
  const list = document.getElementById('roomList');

  // Filter: hanya tampilkan room yang sudah ada pesannya
  const rooms = (data.rooms || []).filter(r => r.pesan_terakhir && r.pesan_terakhir.trim() !== '');

  if (rooms.length === 0) {
    list.innerHTML = '<div class="room-placeholder">Belum ada pesan masuk</div>';
    return;
  }

  list.innerHTML = rooms.map(r => roomItem(r)).join('');
  list.querySelectorAll('.room-item').forEach(el => {
    el.addEventListener('click', () => bukaRoom(el.dataset));
  });
}

function roomItem(r) {
  const inisial = (r.nama_user || '?').charAt(0).toUpperCase();
  const preview = r.pesan_terakhir
    ? esc(r.pesan_terakhir).substring(0, 40) + (r.pesan_terakhir.length > 40 ? '…' : '')
    : '<em>Belum ada pesan</em>';
  const waktu   = r.waktu_terakhir ? formatWaktu(r.waktu_terakhir) : '';
  const badge   = r.belum_baca > 0 ? `<span class="badge">${r.belum_baca}</span>` : '';
  const aktif   = activeRoom == r.id_room ? ' aktif' : '';

  return `
    <div class="room-item${aktif}" data-room="${r.id_room}"
         data-nama="${esc(r.nama_user)}" data-kantin="${esc(r.nama_kantin)}">
      <span class="avatar">${inisial}</span>
      <div class="room-info">
        <div class="room-top">
          <strong>${esc(r.nama_user)}</strong>
          <span class="room-waktu">${waktu}</span>
        </div>
        <div class="room-preview">${preview} ${badge}</div>
      </div>
    </div>`;
}

async function bukaRoom({room, nama, kantin}) {
  stopPolling();
  activeRoom  = parseInt(room);
  lastPesanId = 0;

  document.getElementById('headerAvatar').textContent = (nama || '?').charAt(0).toUpperCase();
  document.getElementById('headerNama').textContent   = nama  || '–';
  document.getElementById('headerKantin').textContent = kantin || '–';

  document.getElementById('chatEmpty').classList.add('hidden');
  document.getElementById('chatActive').classList.remove('hidden');

  // Mobile: geser ke panel chat
  const sidebar  = document.getElementById('sidebar');
  const chatMain = document.getElementById('chatMain');
  if (window.innerWidth <= 768) {
    sidebar.style.display  = 'none';
    chatMain.style.display = 'flex';
    chatMain.style.flexDirection = 'column';
  }

  const body = document.getElementById('chatBody');
  body.innerHTML = '<div class="msg-placeholder">Memuat pesan…</div>';

  await muatPesan(true);
  tandaiDibaca();
  muatDaftarRoom();
  startPolling();
}

async function muatPesan(pertama = false) {
  const data = await api(`get_pesan&id_room=${activeRoom}&setelah=${lastPesanId}`);
  if (!data.pesan) return;

  const body    = document.getElementById('chatBody');
  const dibawah = body.scrollHeight - body.scrollTop - body.clientHeight < 80;

  if (pertama) body.innerHTML = '';

  data.pesan.forEach(p => {
    lastPesanId = Math.max(lastPesanId, parseInt(p.id_pesan));
    body.insertAdjacentHTML('beforeend', gelembung(p));
  });

  if (pertama || dibawah) body.scrollTop = body.scrollHeight;
}

function gelembung(p) {
  const milik = (p.pengirim_role === SESSION_ROLE && parseInt(p.pengirim_id) === SESSION_ID);
  const sisi  = milik ? 'kanan' : 'kiri';
  const nama  = milik ? '' : `<span class="msg-nama">${esc(p.nama_pengirim)}</span>`;
  const baca  = milik ? (p.is_read == 1 ? '✓✓' : '✓') : '';

  return `
    <div class="bubble ${sisi}">
      ${nama}
      <div class="bubble-isi">${esc(p.pesan).replace(/\n/g, '<br>')}</div>
      <div class="bubble-meta">
        <span class="bubble-waktu">${formatWaktu(p.created_at, true)}</span>
        ${baca ? `<span class="bubble-baca">${baca}</span>` : ''}
      </div>
    </div>`;
}

document.getElementById('btnKirim').addEventListener('click', kirimPesan);
document.getElementById('inputPesan').addEventListener('keydown', e => {
  if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); kirimPesan(); }
});

async function kirimPesan() {
  const inp   = document.getElementById('inputPesan');
  const pesan = inp.value.trim();
  if (!pesan || !activeRoom) return;
  inp.value = '';
  inp.style.height = '';
  await api('kirim_pesan', { id_room: activeRoom, pesan });
  await muatPesan();
  tandaiDibaca();
}

function tandaiDibaca() {
  if (!activeRoom) return;
  api('tandai_dibaca', { id_room: activeRoom });
}

function startPolling() {
  pollingTimer = setInterval(async () => {
    if (!activeRoom) return;
    await muatPesan();
    tandaiDibaca();
    muatDaftarRoom();
  }, 3000);
}
function stopPolling() {
  clearInterval(pollingTimer);
}

// Tombol kembali ke daftar room (mobile)
document.getElementById('btnBack').addEventListener('click', () => {
  stopPolling();
  activeRoom = null;
  document.getElementById('chatActive').classList.add('hidden');
  document.getElementById('chatEmpty').classList.remove('hidden');

  // Mobile: tampilkan kembali sidebar
  if (window.innerWidth <= 768) {
    document.getElementById('sidebar').style.display  = 'flex';
    document.getElementById('chatMain').style.display = 'none';
  }
});

document.getElementById('inputPesan').addEventListener('input', function() {
  this.style.height = '';
  this.style.height = Math.min(this.scrollHeight, 120) + 'px';
});

// Responsive: atur display saat pertama load
function initLayout() {
  const sidebar  = document.getElementById('sidebar');
  const chatMain = document.getElementById('chatMain');
  if (window.innerWidth <= 768) {
    sidebar.style.display  = 'flex';
    chatMain.style.display = 'none';
  } else {
    sidebar.style.display  = '';
    chatMain.style.display = '';
  }
}
initLayout();
window.addEventListener('resize', initLayout);

async function api(action, body = null) {
  const url = `${API}?action=${action}`;
  if (!body) {
    const r = await fetch(url);
    return r.json().catch(() => ({}));
  }
  const fd = new FormData();
  Object.entries(body).forEach(([k, v]) => fd.append(k, v));
  const r = await fetch(url, { method: 'POST', body: fd });
  return r.json().catch(() => ({}));
}

function esc(str) {
  return String(str ?? '')
    .replace(/&/g,'&amp;').replace(/</g,'&lt;')
    .replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

function formatWaktu(dt, panjang = false) {
  const d = new Date(dt);
  if (isNaN(d)) return '';
  const now  = new Date();
  const sama = d.toDateString() === now.toDateString();
  if (!panjang && !sama) {
    return d.toLocaleDateString('id-ID', { day:'2-digit', month:'short' });
  }
  return d.toLocaleTimeString('id-ID', { hour:'2-digit', minute:'2-digit' });
}
</script>
</body>
</html>