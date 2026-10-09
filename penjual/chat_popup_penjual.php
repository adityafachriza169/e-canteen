<?php
/**
 * chat_popup_penjual.php — Chat Popup untuk PENJUAL
 * Letakkan di folder: penjual/
 */

if (empty($_SESSION['id_penjual'])) return;

$_cp_pid = (int)$_SESSION['id_penjual'];

// Ambil foto profil penjual yang login (untuk logo default di header)
$_cp_penjual_foto = '';
$_cp_db = $konek ?? $conn ?? null;
if ($_cp_db) {
    $r_me = mysqli_query($_cp_db,
        "SELECT profil FROM penjual WHERE id_penjual = $_cp_pid LIMIT 1"
    );
    if ($r_me) {
        $me = mysqli_fetch_assoc($r_me);
        $_cp_penjual_foto = $me['profil'] ?? '';
    }
}
?>

<div id="cpOverlay"></div>

<style>
@import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap');

/* ─── OVERLAY ─── */
#cpOverlay {
  display:none; position:fixed; inset:0;
  background:rgba(0,0,0,.48); z-index:9991;
  backdrop-filter:blur(3px); -webkit-backdrop-filter:blur(3px);
  cursor:pointer;
}

/* ─── WIDGET ─── */
#chatPopupWidget {
  --cp:#f97316; --cp-d:#c2410c; --cp-m:#fb923c;
  --cp-l:#fff3e8; --cp-xl:#fff7ed;
  --cbg:#faf6f2; --cs:#ffffff;
  --cb:#f0d9c5; --cbl:#faeadb;
  --ct:#1c1410; --ct2:#8a7060; --ct3:#b8a898;
  --tr:.22s cubic-bezier(.4,0,.2,1);

  position:fixed; right:22px; bottom:22px;
  width:372px; height:564px; border-radius:20px;
  background:var(--cs); overflow:hidden;
  box-shadow:0 20px 60px rgba(0,0,0,.16),0 8px 24px rgba(249,115,22,.13);
  display:flex; flex-direction:column;
  z-index:9992;
  font-family:'Plus Jakarta Sans','Segoe UI',system-ui,sans-serif;
  transform:translateY(18px) scale(.95);
  opacity:0; pointer-events:none;
  transition:transform .28s cubic-bezier(.34,1.56,.64,1),opacity .2s ease;
}
@media(prefers-color-scheme:dark){
  #chatPopupWidget{
    --cbg:#18100a;--cs:#221508;--cb:#3d2410;--cbl:#331e0d;
    --cp:#fb923c;--cp-d:#f97316;--cp-l:#2a1608;--cp-xl:#231007;
    --ct:#fdf4ec;--ct2:#9a8068;--ct3:#6b5040;
  }
}

/* State: open */
#chatPopupWidget.open {
  transform:translateY(0) scale(1);
  opacity:1; pointer-events:all;
}

/* State: expanded */
#chatPopupWidget.expanded {
  right:50%; bottom:50%;
  transform:translate(50%,50%) !important;
  width:90vw;
  height:90vh;
  border-radius:24px;
  opacity:1; pointer-events:all;
}

/* ── Header ── */
#chatPopupWidget .cw-head {
  height:56px; flex-shrink:0;
  background:linear-gradient(135deg,#f97316,#c2410c);
  display:flex; align-items:center; gap:8px; padding:0 11px;
  box-shadow:0 2px 10px rgba(249,115,22,.22); position:relative;
}
#chatPopupWidget .cw-head::before {
  content:''; position:absolute; inset:0; pointer-events:none;
  background:radial-gradient(circle,rgba(255,255,255,.09) 1px,transparent 1px) 0 0/14px 14px;
}
#chatPopupWidget .cw-hbtn {
  width:29px; height:29px; border-radius:50%;
  border:1.5px solid rgba(255,255,255,.35);
  background:rgba(255,255,255,.12); color:#fff;
  cursor:pointer; display:flex; align-items:center; justify-content:center;
  flex-shrink:0; transition:background .15s,transform .1s; z-index:1;
}
#chatPopupWidget .cw-hbtn:hover  { background:rgba(255,255,255,.26) }
#chatPopupWidget .cw-hbtn:active { transform:scale(.86) }
#chatPopupWidget .cw-hbtn svg    { width:13px; height:13px }

/* Avatar di header — foto profil user yang sedang chat */
#chatPopupWidget .cw-av {
  width:33px; height:33px; border-radius:50%;
  border:2px solid rgba(255,255,255,.55);
  overflow:hidden; flex-shrink:0; z-index:1;
  display:none; background:rgba(255,255,255,.18);
}
#chatPopupWidget .cw-av img {
  width:100%; height:100%; object-fit:cover; display:block;
}

/* Logo default sebelum room dipilih */
#chatPopupWidget .cw-av-logo {
  width:33px; height:33px; border-radius:50%;
  border:2px solid rgba(255,255,255,.55);
  overflow:hidden; flex-shrink:0; z-index:1;
  display:flex; background:rgba(255,255,255,.18);
}
#chatPopupWidget .cw-av-logo img {
  width:100%; height:100%; object-fit:cover; display:block;
}

#chatPopupWidget .cw-hinfo {
  flex:1; min-width:0; z-index:1;
  display:flex; flex-direction:column; justify-content:center; gap:1px;
}
#chatPopupWidget .cw-hinfo strong {
  display:block; font-size:13px; font-weight:700; color:#fff;
  white-space:nowrap; overflow:hidden; text-overflow:ellipsis; letter-spacing:-.2px;
  line-height:1.3;
}
#chatPopupWidget .cw-hinfo small {
  font-size:10.5px; color:rgba(255,255,255,.72); font-weight:500;
  white-space:nowrap; overflow:hidden; text-overflow:ellipsis;
  line-height:1.3;
}
#chatPopupWidget .cw-hacts { display:flex; align-items:center; gap:5px; z-index:1; flex-shrink:0 }

/* ── Body ── */
#chatPopupWidget .cw-body { flex:1; display:flex; overflow:hidden; position:relative }

/* ── Sidebar ── */
#chatPopupWidget .cw-sidebar {
  width:100%; display:flex; flex-direction:column;
  background:var(--cs); border-right:1px solid var(--cb);
  position:absolute; inset:0; z-index:2; transition:transform var(--tr);
}
#chatPopupWidget.room-open .cw-sidebar { transform:translateX(-100%); pointer-events:none }
#chatPopupWidget.expanded .cw-sidebar  {
  position:static; width:220px; transform:none!important; pointer-events:all!important;
}
#chatPopupWidget .cw-sidebar-title {
  padding:10px 12px 6px; font-size:10px; font-weight:800;
  text-transform:uppercase; letter-spacing:.08em; color:var(--cp);
  border-bottom:1px solid var(--cbl); flex-shrink:0;
}
#chatPopupWidget .cw-roomlist { flex:1; overflow-y:auto }
#chatPopupWidget .cw-roomlist::-webkit-scrollbar { width:3px }
#chatPopupWidget .cw-roomlist::-webkit-scrollbar-thumb { background:rgba(249,115,22,.18); border-radius:99px }
#chatPopupWidget .cw-room-ph { text-align:center; padding:22px 12px; color:var(--ct3); font-size:12px; font-weight:500 }

/* Room item — avatar pakai foto profil user (siswa) */
#chatPopupWidget .cw-room {
  display:flex; align-items:center; gap:9px; padding:9px 10px;
  border-bottom:1px solid var(--cbl); border-left:3px solid transparent;
  cursor:pointer; transition:background .15s,border-left-color .15s;
}
#chatPopupWidget .cw-room:hover  { background:var(--cp-xl) }
#chatPopupWidget .cw-room.active { background:var(--cp-l); border-left-color:#f97316; padding-left:7px }

#chatPopupWidget .cw-rav {
  width:36px; height:36px; border-radius:50%; flex-shrink:0;
  overflow:hidden; background:linear-gradient(140deg,#f97316,#c2410c);
  box-shadow:0 2px 8px rgba(249,115,22,.25);
  display:flex; align-items:center; justify-content:center;
}
#chatPopupWidget .cw-rav img      { width:100%; height:100%; object-fit:cover; display:block }
#chatPopupWidget .cw-rav-ini      { color:#fff; font-weight:800; font-size:13px; text-transform:uppercase }

#chatPopupWidget .cw-rinfo { flex:1; min-width:0 }
#chatPopupWidget .cw-rtop  { display:flex; justify-content:space-between; gap:4px; margin-bottom:1px }
#chatPopupWidget .cw-rtop strong { font-size:12px; font-weight:700; color:var(--ct); white-space:nowrap; overflow:hidden; text-overflow:ellipsis }
#chatPopupWidget .cw-rwkt  { font-size:10px; color:var(--ct3); flex-shrink:0 }
#chatPopupWidget .cw-rprev { font-size:11px; color:var(--ct2); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; display:flex; align-items:center; gap:3px }
#chatPopupWidget .cw-badge {
  display:inline-flex; align-items:center; justify-content:center;
  background:#f97316; color:#fff; font-size:9px; font-weight:700;
  border-radius:999px; min-width:15px; height:15px; padding:0 3px; flex-shrink:0;
}

/* ── Chat area ── */
#chatPopupWidget .cw-chatarea {
  flex:1; display:flex; flex-direction:column; overflow:hidden;
  background:var(--cbg); position:absolute; inset:0; z-index:1;
  transform:translateX(100%); transition:transform var(--tr);
}
#chatPopupWidget.room-open .cw-chatarea { transform:translateX(0); z-index:3 }
#chatPopupWidget.expanded  .cw-chatarea { position:static; transform:none!important; flex:1 }

/* Empty state */
#chatPopupWidget .cw-empty {
  flex:1; display:flex; flex-direction:column;
  align-items:center; justify-content:center;
  gap:10px; color:var(--ct2); padding:20px;
}
#chatPopupWidget .cw-empty-ic {
  width:58px; height:58px; border-radius:17px; background:var(--cp-l);
  display:flex; align-items:center; justify-content:center;
}
#chatPopupWidget .cw-empty-ic img { width:36px; height:36px; object-fit:contain }
#chatPopupWidget .cw-empty p     { font-size:13px; font-weight:600; text-align:center }
#chatPopupWidget .cw-empty small { font-size:11px; color:var(--ct3); text-align:center }

/* Messages */
#chatPopupWidget .cw-msgs {
  flex:1; overflow-y:auto; padding:12px 11px 6px;
  display:flex; flex-direction:column; gap:3px; scroll-behavior:smooth;
  background:
    radial-gradient(circle,rgba(249,115,22,.05) 1px,transparent 1px) 0 0/20px 20px,
    var(--cbg);
}
#chatPopupWidget .cw-msgs::-webkit-scrollbar       { width:3px }
#chatPopupWidget .cw-msgs::-webkit-scrollbar-thumb { background:rgba(249,115,22,.18); border-radius:99px }
#chatPopupWidget .cw-msg-ph { text-align:center; color:var(--ct3); font-size:12px; font-weight:500; padding:12px }
#chatPopupWidget .cw-bbl     { display:flex; flex-direction:column; max-width:78%; margin-bottom:2px }
#chatPopupWidget .cw-bbl.r   { align-self:flex-end; align-items:flex-end }
#chatPopupWidget .cw-bbl.l   { align-self:flex-start; align-items:flex-start }
#chatPopupWidget .cw-bbl-nama { font-size:10px; font-weight:700; color:#f97316; margin-bottom:2px; padding-left:3px }
#chatPopupWidget .cw-bbl-isi  { padding:8px 13px; border-radius:14px; font-size:13px; line-height:1.55; word-break:break-word }
#chatPopupWidget .cw-bbl.r .cw-bbl-isi {
  background:linear-gradient(140deg,#fb923c,#f97316 40%,#c2410c);
  color:#fff; border-bottom-right-radius:4px;
  box-shadow:0 3px 12px rgba(249,115,22,.28);
}
#chatPopupWidget .cw-bbl.l .cw-bbl-isi {
  background:#fff; color:#1c1410;
  border:1px solid var(--cb); border-bottom-left-radius:4px;
  box-shadow:0 1px 4px rgba(0,0,0,.06);
}
#chatPopupWidget .cw-bbl-meta { display:flex; align-items:center; gap:3px; margin-top:2px; padding:0 3px }
#chatPopupWidget .cw-bbl-wkt  { font-size:10px; color:var(--ct3); font-weight:500 }
#chatPopupWidget .cw-bbl-rd   { font-size:10px; color:var(--ct3) }
#chatPopupWidget .cw-bbl.r .cw-bbl-rd { color:rgba(255,255,255,.65) }

/* Footer */
#chatPopupWidget .cw-footer {
  display:flex; align-items:flex-end; gap:7px;
  padding:8px 9px; background:var(--cs);
  border-top:1px solid var(--cb); flex-shrink:0;
  transition:padding-bottom .15s;
}
#chatPopupWidget #cwInput {
  flex:1; resize:none; border:1.5px solid var(--cb); border-radius:17px;
  padding:7px 12px; font-size:13px; font-family:inherit; font-weight:500;
  background:var(--cbg); color:var(--ct); outline:none;
  line-height:1.5; max-height:96px; transition:border-color .2s,box-shadow .2s;
}
#chatPopupWidget #cwInput:focus       { border-color:#f97316; box-shadow:0 0 0 3px rgba(249,115,22,.12) }
#chatPopupWidget #cwInput::placeholder { color:var(--ct3) }
#chatPopupWidget .cw-send {
  width:37px; height:37px; border-radius:50%; border:none; flex-shrink:0;
  background:linear-gradient(140deg,#fb923c,#f97316 60%,#c2410c);
  color:#fff; cursor:pointer;
  display:flex; align-items:center; justify-content:center;
  box-shadow:0 3px 12px rgba(249,115,22,.38);
  transition:opacity .15s,transform .1s;
}
#chatPopupWidget .cw-send:hover  { opacity:.88 }
#chatPopupWidget .cw-send:active { transform:scale(.90) }
#chatPopupWidget .cw-send svg    { width:14px; height:14px }

/* ── Mobile fullscreen ── */
@media(max-width:768px){
  #cpOverlay { display:none!important }
  #chatPopupWidget,#chatPopupWidget.expanded {
    right:0; bottom:0; width:100vw; height:100dvh; border-radius:0;
    transform:translateY(100%);
  }
  #chatPopupWidget.open,#chatPopupWidget.expanded.open {
    transform:translateY(0)!important;
  }
  #chatPopupWidget.expanded .cw-sidebar {
    position:absolute; inset:0; width:100%;
    transform:translateX(0)!important; pointer-events:all!important;
  }
  #chatPopupWidget.expanded.room-open .cw-sidebar {
    transform:translateX(-100%)!important; pointer-events:none!important;
  }
  #chatPopupWidget.expanded .cw-chatarea          { position:absolute; inset:0; transform:translateX(100%) }
  #chatPopupWidget.expanded.room-open .cw-chatarea { transform:translateX(0) }
  .cw-btn-expand { display:none!important }
  /* tombol kembali SELALU tampil di mobile */
  #chatPopupWidget .cw-back-btn { display:flex!important }
  #chatPopupWidget .cw-footer   { padding-bottom:max(8px,env(safe-area-inset-bottom,8px)) }
}
@media(min-width:769px){
  /* tombol kembali tersembunyi di desktop kecuali expanded */
  #chatPopupWidget:not(.room-open) .cw-back-btn { display:none!important }
}
</style>

<!-- ── HTML ── -->
<div id="chatPopupWidget">
  <div class="cw-head">
    <!-- Tombol kembali ke daftar room -->
    <button class="cw-hbtn cw-back-btn" id="cwBack" title="Kembali" style="display:none">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>
    </button>

    <!-- Logo default sebelum room dipilih -->
    <span class="cw-av-logo" id="cwAvLogo">
      <img src="../gambar/logo.png" alt="Logo" onerror="this.style.display='none'">
    </span>

    <!-- Avatar foto profil user yang sedang chat -->
    <span class="cw-av" id="cwAv">
      <img id="cwAvImg" src="" alt="">
    </span>

    <div class="cw-hinfo" id="cwInfoRoom" style="display:none">
      <strong id="cwNama">–</strong>
      <small id="cwKantin">–</small>
    </div>
    <div class="cw-hinfo" id="cwInfoDef">
      <strong style="font-size:13.5px">💬 Pesan Masuk</strong>
      <small>E-Canteen ESEMKITA</small>
    </div>
    <div class="cw-hacts">
      <button class="cw-hbtn cw-btn-expand" id="cwBtnExp" title="Perbesar">
        <svg id="cwIcExp" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
          <polyline points="15 3 21 3 21 9"/><polyline points="9 21 3 21 3 15"/>
          <line x1="21" y1="3" x2="14" y2="10"/><line x1="3" y1="21" x2="10" y2="14"/>
        </svg>
      </button>
      <button class="cw-hbtn" id="cwBtnClose" title="Tutup">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
    </div>
  </div>

  <div class="cw-body">
    <!-- Sidebar: daftar siswa yang menghubungi penjual -->
    <aside class="cw-sidebar">
      <div class="cw-sidebar-title">Percakapan Masuk</div>
      <div class="cw-roomlist" id="cwRoomList">
        <div class="cw-room-ph">Memuat percakapan…</div>
      </div>
    </aside>

    <!-- Chat area -->
    <div class="cw-chatarea">
      <div class="cw-empty" id="cwEmpty">
        <div class="cw-empty-ic">
          <img src="../gambar/logo.png" alt="Logo" onerror="this.parentNode.innerHTML='💬'">
        </div>
        <p>Pilih percakapan</p>
        <small>balas pesan dari siswa di sini</small>
      </div>
      <div id="cwChatActive" style="display:none;flex:1;flex-direction:column;overflow:hidden">
        <div class="cw-msgs" id="cwMsgs"></div>
        <footer class="cw-footer" id="cwFooter">
          <textarea id="cwInput" rows="1" placeholder="Tulis balasan…" maxlength="2000"></textarea>
          <button class="cw-send" id="cwSend">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
          </button>
        </footer>
      </div>
    </div>
  </div>
</div>

<script>
(function(){
  const PID  = <?= $_cp_pid ?>;
  const ROLE = 'penjual';
  const API  = '../users/chat_api.php';

  const W          = document.getElementById('chatPopupWidget');
  const overlay    = document.getElementById('cpOverlay');
  const cwBack     = document.getElementById('cwBack');
  const cwBtnClose = document.getElementById('cwBtnClose');
  const cwBtnExp   = document.getElementById('cwBtnExp');
  const cwIcExp    = document.getElementById('cwIcExp');
  const cwRL       = document.getElementById('cwRoomList');
  const cwEmpty    = document.getElementById('cwEmpty');
  const cwActive   = document.getElementById('cwChatActive');
  const cwMsgs     = document.getElementById('cwMsgs');
  const cwFooter   = document.getElementById('cwFooter');
  const cwInput    = document.getElementById('cwInput');
  const cwSend     = document.getElementById('cwSend');
  const cwAv       = document.getElementById('cwAv');
  const cwAvImg    = document.getElementById('cwAvImg');
  const cwAvLogo   = document.getElementById('cwAvLogo');
  const cwInfoR    = document.getElementById('cwInfoRoom');
  const cwInfoD    = document.getElementById('cwInfoDef');
  const cwNama     = document.getElementById('cwNama');
  const cwKantin   = document.getElementById('cwKantin');

  let room=null, lastId=0, timer=null, expanded=false;

  /* ── Open / Close ── */
  window.openChatPopup = function(){
    W.classList.add('open');
    if(window.innerWidth > 768){
      overlay.style.display = 'block';
      document.body.style.overflow = 'hidden';
    }
    loadRooms();
  };

  window.closeChatPopup = function(){
    W.classList.remove('open');
    W.classList.remove('expanded');
    expanded = false;
    cwIcExp.innerHTML = '<polyline points="15 3 21 3 21 9"/><polyline points="9 21 3 21 3 15"/><line x1="21" y1="3" x2="14" y2="10"/><line x1="3" y1="21" x2="10" y2="14"/>';
    cwBtnExp.title = 'Perbesar';
    overlay.style.display = 'none';
    document.body.style.overflow = '';
    stopPoll();
  };

  /* Overlay klik tutup popup (desktop) */
  overlay.addEventListener('click', function(e){
    if(e.target === overlay) closeChatPopup();
  });

  /* Tombol X tutup */
  cwBtnClose.addEventListener('click', closeChatPopup);

  /* ── Expand ── */
  cwBtnExp.addEventListener('click', ()=>{
    expanded = !expanded;
    W.classList.toggle('expanded', expanded);
    cwIcExp.innerHTML = expanded
      ? '<polyline points="4 14 10 14 10 20"/><polyline points="20 10 14 10 14 4"/><line x1="10" y1="14" x2="3" y2="21"/><line x1="21" y1="3" x2="14" y2="10"/>'
      : '<polyline points="15 3 21 3 21 9"/><polyline points="9 21 3 21 3 15"/><line x1="21" y1="3" x2="14" y2="10"/><line x1="3" y1="21" x2="10" y2="14"/>';
    cwBtnExp.title = expanded ? 'Perkecil' : 'Perbesar';
    if(window.innerWidth > 768){
      overlay.style.display = 'block';
    }
  });

  /* ── Back ── */
  cwBack.addEventListener('click', ()=>{
    stopPoll(); room = null;
    W.classList.remove('room-open');
    cwActive.style.display = 'none';
    cwEmpty.style.display  = '';
    // Kembalikan logo default
    cwAv.style.display     = 'none';
    cwAvLogo.style.display = 'flex';
    cwInfoR.style.display  = 'none';
    cwInfoD.style.display  = '';
    cwBack.style.display   = 'none';
  });

  /* ── Load rooms — tampilkan nama & foto SISWA ── */
  async function loadRooms(){
    const d = await req('daftar_room');
    if(!d.rooms) return;
    setBadge(d.rooms.reduce((s,r)=>s+(parseInt(r.belum_baca)||0), 0));
    if(!d.rooms.length){
      cwRL.innerHTML = '<div class="cw-room-ph">Belum ada pesan masuk</div>';
      return;
    }
    cwRL.innerHTML = d.rooms.map(r=>roomHtml(r)).join('');
    cwRL.querySelectorAll('.cw-room').forEach(el=>
      el.addEventListener('click', ()=>openRoom({
        room:   el.dataset.room,
        nama:   el.dataset.nama,
        kantin: el.dataset.kantin,
        foto:   el.dataset.foto
      }))
    );
  }

  function roomHtml(r){
    /* Penjual melihat nama & foto SISWA (nama_user, foto_user) */
    const label = r.nama_user || '?';
    const foto  = r.foto_user || '';
    const ini   = label[0].toUpperCase();
    const prev  = r.pesan_terakhir
      ? esc(r.pesan_terakhir).slice(0,34)+(r.pesan_terakhir.length>34?'…':'')
      : '<em style="color:var(--ct3)">Belum ada pesan</em>';
    const wkt   = r.waktu_terakhir ? fmtWkt(r.waktu_terakhir) : '';
    const bdg   = r.belum_baca>0 ? `<span class="cw-badge">${r.belum_baca}</span>` : '';
    const ak    = room==r.id_room ? ' active' : '';

    /* Avatar: foto profil siswa dari ../gambar/profil/ */
    const avatarHtml = foto
      ? `<img src="../gambar/profil/${esc(foto)}" alt="${esc(label)}"
              onerror="this.style.display='none';this.nextElementSibling.style.display='flex'">
         <span class="cw-rav-ini" style="display:none">${ini}</span>`
      : `<span class="cw-rav-ini">${ini}</span>`;

    return `<div class="cw-room${ak}"
      data-room="${r.id_room}" data-nama="${esc(label)}"
      data-kantin="${esc(r.nama_kantin||'')}" data-foto="${esc(foto)}">
      <div class="cw-rav">${avatarHtml}</div>
      <div class="cw-rinfo">
        <div class="cw-rtop"><strong>${esc(label)}</strong><span class="cw-rwkt">${wkt}</span></div>
        <div class="cw-rprev">${prev}${bdg}</div>
      </div></div>`;
  }

  /* ── Open room ── */
  async function openRoom({room:r, nama, kantin, foto}){
    stopPoll(); room=parseInt(r); lastId=0;
    cwNama.textContent   = nama   || '–';
    cwKantin.textContent = kantin || '–';

    /* Avatar header: foto profil siswa dari ../gambar/profil/ */
    if(foto){
      cwAvImg.src = `../gambar/profil/${foto}`;
      cwAvImg.onerror = function(){ this.src='../gambar/logo.png'; };
    } else {
      cwAvImg.src = '../gambar/logo.png';
    }
    cwAv.style.display     = 'flex';
    cwAvLogo.style.display = 'none';

    cwInfoR.style.display = 'flex';
    cwInfoD.style.display = 'none';
    cwBack.style.display  = 'flex';
    W.classList.add('room-open');
    cwEmpty.style.display  = 'none';
    cwActive.style.display = 'flex';
    cwMsgs.innerHTML = '<div class="cw-msg-ph">Memuat pesan…</div>';
    await new Promise(r => requestAnimationFrame(r));
    await loadMsgs(true);
    markRead();
    loadRooms();
    startPoll();
  }

  async function loadMsgs(first=false){
    const d = await req(`get_pesan&id_room=${room}&setelah=${lastId}`);
    if(!d.pesan) return;
    const atBot = cwMsgs.scrollHeight - cwMsgs.scrollTop - cwMsgs.clientHeight < 80;
    if(first) cwMsgs.innerHTML = '';
    d.pesan.forEach(p=>{
      lastId = Math.max(lastId, parseInt(p.id_pesan));
      cwMsgs.insertAdjacentHTML('beforeend', bubble(p));
    });
    if(first || atBot) cwMsgs.scrollTop = cwMsgs.scrollHeight;
  }

  function bubble(p){
    const mine = p.pengirim_role===ROLE && parseInt(p.pengirim_id)===PID;
    const sisi = mine ? 'r' : 'l';
    const nama = mine ? '' : `<span class="cw-bbl-nama">${esc(p.nama_pengirim)}</span>`;
    const baca = mine ? (p.is_read==1?'✓✓':'✓') : '';
    return `<div class="cw-bbl ${sisi}">${nama}<div class="cw-bbl-isi">${esc(p.pesan).replace(/\n/g,'<br>')}</div><div class="cw-bbl-meta"><span class="cw-bbl-wkt">${fmtWkt(p.created_at,true)}</span>${baca?`<span class="cw-bbl-rd">${baca}</span>`:''}</div></div>`;
  }

  /* ── Kirim ── */
  async function kirim(){
    const pesan = cwInput.value.trim();
    if(!pesan || !room) return;
    cwInput.value = '';
    cwInput.style.height = '';
    await req('kirim_pesan', {id_room:room, pesan});
    await loadMsgs();
    markRead();
  }
  cwSend.addEventListener('click', kirim);
  cwInput.addEventListener('keydown', e=>{
    if(e.key==='Enter' && !e.shiftKey){ e.preventDefault(); kirim(); }
  });
  cwInput.addEventListener('input', function(){
    this.style.height = '';
    this.style.height = Math.min(this.scrollHeight, 96)+'px';
  });

  function markRead(){ if(room) req('tandai_dibaca', {id_room:room}); }

  /* ── Poll ── */
  function startPoll(){
    timer = setInterval(async()=>{ if(!room) return; await loadMsgs(); markRead(); loadRooms(); }, 3000);
  }
  function stopPoll(){ clearInterval(timer); timer=null; }

  /* ── Badge ── */
  function setBadge(n){
    const txt  = n>99?'99+':n>0?String(n):'';
    const show = n>0;
    document.querySelectorAll('[data-chat-badge]').forEach(el=>{
      el.textContent=txt; el.style.display=show?'flex':'none';
    });
  }
  async function pollBadge(){
    const d = await req('daftar_room');
    if(d.rooms) setBadge(d.rooms.reduce((s,r)=>s+(parseInt(r.belum_baca)||0), 0));
  }
  pollBadge();
  setInterval(pollBadge, 8000);

  /* ── Auto-open via ?openchat=1 ── */
  if(new URLSearchParams(location.search).get('openchat')==='1') openChatPopup();

  /* ── Keyboard avoidance (mobile) ── */
  if(window.visualViewport){
    let raf = null;
    const onVP = ()=>{
      if(raf) cancelAnimationFrame(raf);
      raf = requestAnimationFrame(()=>{
        if(window.innerWidth > 768) return;
        const kbH = window.innerHeight - window.visualViewport.height;
        if(kbH > 50){
          W.style.transform = `translateY(-${kbH}px)`;
          setTimeout(()=>{ cwMsgs.scrollTop = cwMsgs.scrollHeight; }, 80);
        } else {
          W.style.transform = '';
        }
      });
    };
    window.visualViewport.addEventListener('resize', onVP);
    window.visualViewport.addEventListener('scroll', onVP);
  }
  cwInput.addEventListener('blur', ()=>{
    if(window.innerWidth <= 768) W.style.transform = '';
  });

  /* ── Utils ── */
  async function req(action, body=null, method=null){
    const url = `${API}?action=${action}`;
    const opt = {method: body||method==='POST' ? 'POST' : 'GET'};
    if(body){
      const fd = new FormData();
      Object.entries(body).forEach(([k,v])=>fd.append(k,v));
      opt.body = fd;
    }
    try{ return await(await fetch(url,opt)).json(); }catch{ return {}; }
  }
  function esc(s){
    return String(s??'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
  }
  function fmtWkt(dt, panjang=false){
    const d = new Date(dt);
    if(isNaN(d)) return '';
    const sama = d.toDateString()===new Date().toDateString();
    if(!panjang && !sama) return d.toLocaleDateString('id-ID',{day:'2-digit',month:'short'});
    return d.toLocaleTimeString('id-ID',{hour:'2-digit',minute:'2-digit'});
  }
})();
</script>