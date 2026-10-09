<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title>Chat – E-Canteen</title>
<link rel="stylesheet" href="../chat.css">
<style>
/* Inline overrides khusus popup widget */
body {
  font-family: 'Plus Jakarta Sans', 'Segoe UI', system-ui, sans-serif;
  font-size: 15px;
}
</style>
</head>
<body>

<?php
// chat_siswa.php
session_start();
if (empty($_SESSION['id_user'])) {
    header('Location: login.php');
    exit;
}
$id_user   = (int)$_SESSION['id_user'];
$nama_user = htmlspecialchars($_SESSION['nama'] ?? 'Siswa');

// Ambil daftar penjual untuk pilihan chat baru
require_once __DIR__ . '../../koneksi.php';
$penjual_list = $conn->query(
    "SELECT p.id_penjual, p.nama, k.nama_kantin
     FROM penjual p JOIN kantin k ON p.id_kantin = k.id_kantin
     WHERE p.status = 1
     ORDER BY k.nama_kantin, p.nama"
)->fetch_all(MYSQLI_ASSOC);
?>

<!-- ══ FAB BUTTON (trigger chat) ══════════════════════════ -->
<button class="chat-fab" id="chatFab" title="Buka Chat">
  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
    <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
  </svg>
  <span class="chat-fab-badge" id="fabBadge"></span>
</button>

<!-- ══ OVERLAY (mobile backdrop) ══════════════════════════ -->
<div class="chat-overlay" id="chatOverlay"></div>

<!-- ══ POPUP CHAT ═══════════════════════════════════════════ -->
<div class="chat-popup" id="chatPopup">

  <!-- Header popup -->
  <div class="chat-popup-header" id="popupHeader">
    <!-- Back button (mobile: kembali ke daftar) -->
    <button class="popup-header-back" id="btnBack" title="Kembali">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
        <polyline points="15 18 9 12 15 6"/>
      </svg>
    </button>

    <!-- Avatar + Info (ditampilkan saat room aktif) -->
    <span class="popup-header-avatar" id="headerAvatar" style="display:none">?</span>
    <div class="popup-header-info" id="headerInfo" style="display:none">
      <strong id="headerNama">–</strong>
      <small id="headerKantin">–</small>
    </div>

    <!-- Judul default (saat belum ada room aktif) -->
    <div class="popup-header-info" id="headerDefault">
      <strong style="font-size:15px">💬 Pesan</strong>
      <small>E-Canteen ESEMKITA</small>
    </div>

    <!-- Actions -->
    <div class="popup-header-actions">
      <!-- New chat -->
      <button class="popup-header-btn" id="btnNewChat" title="Chat baru">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
          <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
        </svg>
      </button>
      <!-- Expand / Shrink (desktop only) -->
      <button class="popup-header-btn btn-expand" id="btnExpand" title="Perbesar">
        <svg id="iconExpand" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
          <polyline points="15 3 21 3 21 9"/><polyline points="9 21 3 21 3 15"/>
          <line x1="21" y1="3" x2="14" y2="10"/><line x1="3" y1="21" x2="10" y2="14"/>
        </svg>
      </button>
      <!-- Close -->
      <button class="popup-header-btn" id="btnClose" title="Tutup">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
          <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
        </svg>
      </button>
    </div>
  </div><!-- /popup-header -->

  <!-- Popup Body -->
  <div class="chat-popup-body">

    <!-- ── SIDEBAR (daftar room) ── -->
    <aside class="chat-sidebar" id="chatSidebar">

      <!-- Tombol new chat (hanya sidebar expanded) -->
      <div class="sidebar-new-chat">
        <button class="btn-new-chat-full" id="btnNewChatSidebar">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="width:14px;height:14px">
            <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
          </svg>
          Chat Baru
        </button>
      </div>

      <div class="room-list" id="roomList">
        <div class="room-placeholder">Memuat percakapan…</div>
      </div>
    </aside>

    <!-- ── CHAT AREA ── -->
    <div class="chat-area" id="chatArea">

      <!-- Empty state -->
      <div class="chat-empty" id="chatEmpty">
        <div class="empty-icon">💬</div>
        <p>Pilih percakapan</p>
        <small>atau mulai chat baru dengan penjual</small>
      </div>

      <!-- Active chat -->
      <div class="chat-active hidden" id="chatActive">
        <div class="chat-body" id="chatBody">
          <div class="msg-placeholder" id="msgPlaceholder">Memuat pesan…</div>
        </div>

        <footer class="chat-footer" id="chatFooter">
          <textarea id="inputPesan" rows="1"
                    placeholder="Tulis pesan…"
                    maxlength="2000"></textarea>
          <button class="btn-kirim" id="btnKirim" title="Kirim">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
              <line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/>
            </svg>
          </button>
        </footer>
      </div>
    </div><!-- /chat-area -->

    <!-- ── NEW CHAT PANEL (penjual picker) ── -->
    <div class="new-chat-popup" id="newChatPanel">
      <div class="new-chat-popup-header">
        <button class="popup-header-btn" id="btnCloseNewChat" style="flex-shrink:0">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
            <polyline points="15 18 9 12 15 6"/>
          </svg>
        </button>
        <strong>Pilih Penjual</strong>
      </div>
      <div class="penjual-list">
        <p class="popup-label">Penjual tersedia</p>
        <?php foreach ($penjual_list as $p): ?>
        <button class="popup-item"
                data-penjual="<?= $p['id_penjual'] ?>"
                data-nama="<?= htmlspecialchars($p['nama']) ?>"
                data-kantin="<?= htmlspecialchars($p['nama_kantin']) ?>">
          <span class="av-sm"><?= mb_substr($p['nama'], 0, 1) ?></span>
          <span>
            <strong><?= htmlspecialchars($p['nama']) ?></strong>
            <small><?= htmlspecialchars($p['nama_kantin']) ?></small>
          </span>
        </button>
        <?php endforeach; ?>
      </div>
    </div>

  </div><!-- /popup-body -->
</div><!-- /chat-popup -->

<script>
const SESSION_ROLE = 'user';
const SESSION_ID   = <?= $id_user ?>;
const API          = '../chat_api.php';

let activeRoom   = null;
let lastPesanId  = 0;
let pollingTimer = null;
let isExpanded   = false;

// ── DOM refs ──────────────────────────────────────────────────
const popup        = document.getElementById('chatPopup');
const overlay      = document.getElementById('chatOverlay');
const fab          = document.getElementById('chatFab');
const fabBadge     = document.getElementById('fabBadge');
const btnClose     = document.getElementById('btnClose');
const btnExpand    = document.getElementById('btnExpand');
const iconExpand   = document.getElementById('iconExpand');
const btnBack      = document.getElementById('btnBack');
const headerAvatar = document.getElementById('headerAvatar');
const headerInfo   = document.getElementById('headerInfo');
const headerDefault= document.getElementById('headerDefault');
const headerNama   = document.getElementById('headerNama');
const headerKantin = document.getElementById('headerKantin');
const roomList     = document.getElementById('roomList');
const chatEmpty    = document.getElementById('chatEmpty');
const chatActive   = document.getElementById('chatActive');
const chatBody     = document.getElementById('chatBody');
const chatFooter   = document.getElementById('chatFooter');
const inputPesan   = document.getElementById('inputPesan');
const btnKirim     = document.getElementById('btnKirim');
const newChatPanel = document.getElementById('newChatPanel');

// ── Open / Close popup ────────────────────────────────────────
function openPopup() {
  popup.classList.add('active');
  overlay.classList.add('active');
  document.body.style.overflow = 'hidden';
  muatDaftarRoom();
}

function closePopup() {
  popup.classList.remove('active');
  overlay.classList.remove('active');
  document.body.style.overflow = '';
  stopPolling();
}

fab.addEventListener('click', openPopup);
overlay.addEventListener('click', closePopup);
btnClose.addEventListener('click', closePopup);

// ── Expand / Collapse (desktop) ───────────────────────────────
btnExpand.addEventListener('click', () => {
  isExpanded = !isExpanded;
  popup.classList.toggle('expanded', isExpanded);

  // Swap icon
  if (isExpanded) {
    iconExpand.innerHTML = `
      <polyline points="4 14 10 14 10 20"/><polyline points="20 10 14 10 14 4"/>
      <line x1="10" y1="14" x2="3" y2="21"/><line x1="21" y1="3" x2="14" y2="10"/>`;
    btnExpand.title = 'Perkecil';
  } else {
    iconExpand.innerHTML = `
      <polyline points="15 3 21 3 21 9"/><polyline points="9 21 3 21 3 15"/>
      <line x1="21" y1="3" x2="14" y2="10"/><line x1="3" y1="21" x2="10" y2="14"/>`;
    btnExpand.title = 'Perbesar';
  }
});

// ── Init ──────────────────────────────────────────────────────
muatDaftarRoom();
// Poll unread count for FAB badge even when popup closed
setInterval(pollFabBadge, 8000);
pollFabBadge();

// ── Room List ─────────────────────────────────────────────────
async function muatDaftarRoom() {
  const data = await api('daftar_room');
  if (!data.rooms) return;

  // Update FAB badge
  const totalUnread = data.rooms.reduce((s, r) => s + (parseInt(r.belum_baca) || 0), 0);
  updateFabBadge(totalUnread);

  if (data.rooms.length === 0) {
    roomList.innerHTML = '<div class="room-placeholder">Belum ada percakapan</div>';
    return;
  }

  roomList.innerHTML = data.rooms.map(r => roomItem(r)).join('');
  roomList.querySelectorAll('.room-item').forEach(el => {
    el.addEventListener('click', () => bukaRoom({
      room:   el.dataset.room,
      nama:   el.dataset.nama,
      kantin: el.dataset.kantin
    }));
  });
}

function roomItem(r) {
  const inisial = (r.nama_penjual || '?').charAt(0).toUpperCase();
  const preview = r.pesan_terakhir
    ? esc(r.pesan_terakhir).substring(0, 36) + (r.pesan_terakhir.length > 36 ? '…' : '')
    : '<em style="color:var(--text-3)">Belum ada pesan</em>';
  const waktu   = r.waktu_terakhir ? formatWaktu(r.waktu_terakhir) : '';
  const badge   = r.belum_baca > 0 ? `<span class="badge">${r.belum_baca}</span>` : '';
  const aktif   = activeRoom == r.id_room ? ' active' : '';

  return `
    <div class="room-item${aktif}"
         data-room="${r.id_room}"
         data-nama="${esc(r.nama_penjual)}"
         data-kantin="${esc(r.nama_kantin)}"
         data-penjual="${r.id_penjual}">
      <div class="room-av">${inisial}</div>
      <div class="room-info">
        <div class="room-top">
          <strong>${esc(r.nama_penjual)}</strong>
          <span class="room-waktu">${waktu}</span>
        </div>
        <div class="room-preview">${preview}${badge}</div>
      </div>
    </div>`;
}

// ── Buka Room ─────────────────────────────────────────────────
async function bukaRoom({room, nama, kantin}) {
  stopPolling();
  activeRoom  = parseInt(room);
  lastPesanId = 0;

  // Update header
  headerAvatar.textContent = (nama || '?').charAt(0).toUpperCase();
  headerNama.textContent   = nama   || '–';
  headerKantin.textContent = kantin || '–';
  headerAvatar.style.display = 'flex';
  headerInfo.style.display   = 'flex';
  headerDefault.style.display = 'none';

  // Show back button on mobile
  btnBack.classList.add('visible');

  // Switch to chat area
  popup.classList.add('room-active');
  chatEmpty.classList.add('hidden');
  chatActive.classList.remove('hidden');

  chatBody.innerHTML = '<div class="msg-placeholder">Memuat pesan…</div>';

  await muatPesan(true);
  tandaiDibaca();
  muatDaftarRoom();
  startPolling();
}

async function muatPesan(pertama = false) {
  const data = await api(`get_pesan&id_room=${activeRoom}&setelah=${lastPesanId}`);
  if (!data.pesan) return;

  const dibawah = chatBody.scrollHeight - chatBody.scrollTop - chatBody.clientHeight < 80;
  if (pertama) chatBody.innerHTML = '';

  data.pesan.forEach(p => {
    lastPesanId = Math.max(lastPesanId, parseInt(p.id_pesan));
    chatBody.insertAdjacentHTML('beforeend', gelembung(p));
  });

  if (pertama || dibawah) chatBody.scrollTop = chatBody.scrollHeight;
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

// ── Kirim Pesan ───────────────────────────────────────────────
btnKirim.addEventListener('click', kirimPesan);
inputPesan.addEventListener('keydown', e => {
  if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); kirimPesan(); }
});

async function kirimPesan() {
  const pesan = inputPesan.value.trim();
  if (!pesan || !activeRoom) return;
  inputPesan.value = '';
  inputPesan.style.height = '';
  await api('kirim_pesan', { id_room: activeRoom, pesan });
  await muatPesan();
  tandaiDibaca();
}

// ── Auto-resize textarea ──────────────────────────────────────
inputPesan.addEventListener('input', function() {
  this.style.height = '';
  this.style.height = Math.min(this.scrollHeight, 110) + 'px';
});

// ── Tandai dibaca ─────────────────────────────────────────────
function tandaiDibaca() {
  if (!activeRoom) return;
  api('tandai_dibaca', { id_room: activeRoom });
}

// ── Polling ───────────────────────────────────────────────────
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
  pollingTimer = null;
}

// ── FAB badge (unread count) ──────────────────────────────────
async function pollFabBadge() {
  const data = await api('daftar_room');
  if (!data.rooms) return;
  const total = data.rooms.reduce((s, r) => s + (parseInt(r.belum_baca) || 0), 0);
  updateFabBadge(total);
}
function updateFabBadge(count) {
  fabBadge.textContent = count > 0 ? (count > 99 ? '99+' : count) : '';
  fabBadge.classList.toggle('visible', count > 0);
}

// ── Back button (mobile) ──────────────────────────────────────
btnBack.addEventListener('click', () => {
  stopPolling();
  activeRoom = null;

  popup.classList.remove('room-active');
  chatActive.classList.add('hidden');
  chatEmpty.classList.remove('hidden');

  headerAvatar.style.display  = 'none';
  headerInfo.style.display    = 'none';
  headerDefault.style.display = 'flex';
  btnBack.classList.remove('visible');
});

// ── New Chat Panel ────────────────────────────────────────────
function openNewChat() {
  newChatPanel.classList.add('active');
}
function closeNewChat() {
  newChatPanel.classList.remove('active');
}

btnNewChat.addEventListener('click', openNewChat);
document.getElementById('btnNewChatSidebar').addEventListener('click', openNewChat);
document.getElementById('btnCloseNewChat').addEventListener('click', closeNewChat);

document.querySelectorAll('.popup-item').forEach(btn => {
  btn.addEventListener('click', async () => {
    closeNewChat();
    const data = await api('get_or_create_room', { id_penjual: btn.dataset.penjual }, 'POST');
    if (!data.id_room) return;
    await muatDaftarRoom();
    bukaRoom({
      room:   data.id_room,
      nama:   btn.dataset.nama,
      kantin: btn.dataset.kantin
    });
  });
});

// ── Keyboard avoidance (mobile) ───────────────────────────────
// Uses visualViewport API for accurate keyboard height detection
if (window.visualViewport) {
  let rafId = null;
  function onViewportResize() {
    if (rafId) cancelAnimationFrame(rafId);
    rafId = requestAnimationFrame(() => {
      // Only apply on mobile (popup is fullscreen)
      if (window.innerWidth > 768) return;

      const viewportHeight = window.visualViewport.height;
      const windowHeight   = window.innerHeight;
      const keyboardHeight = windowHeight - viewportHeight;

      // Shift the chat footer up by keyboard height
      if (keyboardHeight > 50) {
        chatFooter.style.paddingBottom = (keyboardHeight + 8) + 'px';
        // Scroll to bottom when keyboard appears
        setTimeout(() => { chatBody.scrollTop = chatBody.scrollHeight; }, 100);
      } else {
        chatFooter.style.paddingBottom = '';
      }
    });
  }
  window.visualViewport.addEventListener('resize', onViewportResize);
  window.visualViewport.addEventListener('scroll', onViewportResize);
}

// Fallback: focus/blur on textarea (older browsers)
inputPesan.addEventListener('focus', () => {
  if (window.innerWidth > 768) return;
  setTimeout(() => { chatBody.scrollTop = chatBody.scrollHeight; }, 320);
});
inputPesan.addEventListener('blur', () => {
  if (window.innerWidth > 768) return;
  chatFooter.style.paddingBottom = '';
});

// ── Util ──────────────────────────────────────────────────────
async function api(action, body = null, method = null) {
  const url = `${API}?action=${action}`;
  const isPost = body || method === 'POST';
  const opt = { method: isPost ? 'POST' : 'GET' };
  if (body) {
    const fd = new FormData();
    Object.entries(body).forEach(([k, v]) => fd.append(k, v));
    opt.body = fd;
  }
  try {
    const r = await fetch(url, opt);
    return r.json();
  } catch { return {}; }
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