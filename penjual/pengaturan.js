/* =========================================
   E-CANTEEN — pengaturan.js
   ========================================= */

/* ── SIDEBAR ── */
const sidebar        = document.getElementById('sidebar');
const hamburgerBtn   = document.getElementById('hamburgerBtn');
const sidebarOverlay = document.getElementById('sidebarOverlay');

function toggleSidebar() {
  sidebar.classList.contains('open') ? closeSidebar() : openSidebar();
}
function openSidebar() {
  sidebar.classList.add('open');
  hamburgerBtn.classList.add('open');
  sidebarOverlay.classList.add('show');
  document.body.style.overflow = 'hidden';
}
function closeSidebar() {
  sidebar.classList.remove('open');
  hamburgerBtn.classList.remove('open');
  sidebarOverlay.classList.remove('show');
  document.body.style.overflow = '';
}
window.addEventListener('resize', () => { if (window.innerWidth > 768) closeSidebar(); });
document.addEventListener('keydown', e => { if (e.key === 'Escape') closeSidebar(); });

/* ── TOAST ── */
let toastTimer = null;
function showToast(msg, isError = false) {
  const t    = document.getElementById('toast');
  const icon = t.querySelector('i');
  document.getElementById('toastMsg').textContent = msg;
  if (isError) {
    icon.className = 'fa-solid fa-circle-xmark';
    icon.style.color = '#f87171';
  } else {
    icon.className = 'fa-solid fa-circle-check';
    icon.style.color = '#4ade80';
  }
  t.classList.add('show');
  clearTimeout(toastTimer);
  toastTimer = setTimeout(() => t.classList.remove('show'), 3000);
}

/* ── FAQ ── */
function toggleFaq(el) { el.classList.toggle('open'); }

/* ── BAHASA ── */
function selectLang(lang) {
  const checkId = document.getElementById('check-id');
  const checkEn = document.getElementById('check-en');
  const rowId   = document.getElementById('lang-id');
  const rowEn   = document.getElementById('lang-en');
  if (lang === 'id') {
    checkId.style.color = '#e8650a'; checkEn.style.color = '#ddd';
    rowId.classList.add('active-lang-row'); rowEn.classList.remove('active-lang-row');
    showToast('Bahasa diubah ke Bahasa Indonesia');
  } else {
    checkEn.style.color = '#e8650a'; checkId.style.color = '#ddd';
    rowEn.classList.add('active-lang-row'); rowId.classList.remove('active-lang-row');
    showToast('Language changed to English');
  }
}

/* ═══════════════════════════════════════
   GANTI PASSWORD — Modal Logic
   ═══════════════════════════════════════ */

/** Buka modal ganti password */
function openGantiPassword() {
  const modal = document.getElementById('gantiPasswordModal');
  modal.classList.add('open');
  // reset form
  document.getElementById('formGantiPassword').reset();
  clearPwErrors();
  // reset strength bar
  const bar   = document.getElementById('strengthBar');
  const label = document.getElementById('strengthLabel');
  if (bar)   { bar.style.width = '0'; bar.style.background = ''; }
  if (label) { label.textContent = ''; }
  // set visibility tombol mata ke default
  ['pwLama','pwBaru','pwUlang'].forEach(id => {
    const input = document.getElementById(id);
    const btn   = input?.parentElement.querySelector('.pw-eye-btn i');
    if (input) input.type = 'password';
    if (btn)   btn.className = 'fa-solid fa-eye';
  });
}

/** Tutup modal ganti password */
function closeGantiPassword() {
  document.getElementById('gantiPasswordModal').classList.remove('open');
}

/**
 * Toggle show/hide password
 * Dipanggil dari HTML: onclick="togglePwEye('pwLama', this)"
 * @param {string} inputId  – id dari <input>
 * @param {HTMLElement} btn – elemen <button> yang diklik (this)
 */
function togglePwEye(inputId, btn) {
  const input = document.getElementById(inputId);
  const icon  = btn.querySelector('i');
  if (!input || !icon) return;
  if (input.type === 'password') {
    input.type     = 'text';
    icon.className = 'fa-solid fa-eye-slash';
  } else {
    input.type     = 'password';
    icon.className = 'fa-solid fa-eye';
  }
}

/** Strength bar */
function updateStrength(val) {
  const bar   = document.getElementById('strengthBar');
  const label = document.getElementById('strengthLabel');
  if (!bar) return;
  if (!val) { bar.style.width = '0'; if (label) label.textContent = ''; return; }
  let score = 0;
  if (val.length >= 6)               score++;
  if (val.length >= 10)              score++;
  if (/[A-Z]/.test(val))            score++;
  if (/[0-9]/.test(val))            score++;
  if (/[^A-Za-z0-9]/.test(val))    score++;
  const levels = [
    { pct:'20%', color:'#ef4444', text:'Sangat lemah' },
    { pct:'40%', color:'#f97316', text:'Lemah' },
    { pct:'60%', color:'#facc15', text:'Cukup' },
    { pct:'80%', color:'#84cc16', text:'Kuat' },
    { pct:'100%',color:'#22c55e', text:'Sangat kuat' },
  ];
  const lv = levels[Math.max(0, score - 1)];
  bar.style.width      = lv.pct;
  bar.style.background = lv.color;
  if (label) { label.textContent = lv.text; label.style.color = lv.color; }
}

function clearPwErrors() {
  ['pwLamaErr','pwBaruErr','pwUlangErr'].forEach(id => {
    const el = document.getElementById(id);
    if (el) el.textContent = '';
  });
  ['pwLama','pwBaru','pwUlang'].forEach(id => {
    document.getElementById(id)?.classList.remove('pw-error');
  });
}

function setPwError(fieldId, msg) {
  const input = document.getElementById(fieldId);
  const errEl = document.getElementById(fieldId + 'Err');
  if (input) input.classList.add('pw-error');
  if (errEl) errEl.textContent = msg;
}

/** Submit ganti password via fetch */
async function submitGantiPassword() {
  clearPwErrors();

  const pwLama  = document.getElementById('pwLama').value.trim();
  const pwBaru  = document.getElementById('pwBaru').value;
  const pwUlang = document.getElementById('pwUlang').value;
  // ✅ FIX: ID disesuaikan dengan HTML (id="btnSimpanPw")
  const btn     = document.getElementById('btnSimpanPw');

  // Client-side validation
  let valid = true;
  if (!pwLama)  { setPwError('pwLama',  'Password lama wajib diisi.');            valid = false; }
  if (!pwBaru)  { setPwError('pwBaru',  'Password baru wajib diisi.');             valid = false; }
  else if (pwBaru.length < 6) { setPwError('pwBaru', 'Minimal 6 karakter.');      valid = false; }
  if (!pwUlang) { setPwError('pwUlang', 'Konfirmasi password wajib diisi.');       valid = false; }
  else if (pwBaru && pwBaru !== pwUlang) { setPwError('pwUlang', 'Password tidak cocok.'); valid = false; }
  if (!valid) return;

  // Loading state
  if (btn) {
    btn.disabled  = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Menyimpan…';
  }

  try {
    const fd = new FormData();
    fd.append('pw_lama',  pwLama);
    fd.append('pw_baru',  pwBaru);
    fd.append('pw_ulang', pwUlang);

    const res  = await fetch('pengaturan.php', { method: 'POST', body: fd });
    const data = await res.json();

    if (data.success) {
      closeGantiPassword();
      showToast('Password berhasil diubah!');
      // Update tanggal di UI jika elemen tersedia
      const elDate = document.getElementById('pwLastChanged');
      const elRow  = document.getElementById('pwLastChangedRow');
      if (elDate && data.update_pw_at) {
        const d = new Date(data.update_pw_at);
        elDate.textContent = d.toLocaleDateString('id-ID', {
          day:'2-digit', month:'long', year:'numeric', hour:'2-digit', minute:'2-digit'
        });
        if (elRow) elRow.style.display = 'flex';
      }
    } else {
      showToast(data.message || 'Gagal mengubah password.', true);
      // Arahkan error ke field yang sesuai
      if (data.message && data.message.toLowerCase().includes('lama')) {
        setPwError('pwLama', data.message);
      } else if (data.message && data.message.toLowerCase().includes('cocok')) {
        setPwError('pwUlang', data.message);
      }
    }
  } catch {
    showToast('Terjadi kesalahan jaringan.', true);
  } finally {
    if (btn) {
      btn.disabled  = false;
      btn.innerHTML = '<i class="fa-solid fa-floppy-disk"></i> Simpan Password';
    }
  }
}

/* ── Init on DOMContentLoaded ── */
document.addEventListener('DOMContentLoaded', () => {

  /* Tutup modal ganti password kalau klik overlay */
  document.getElementById('gantiPasswordModal')
    ?.addEventListener('click', function (e) {
      if (e.target === this) closeGantiPassword();
    });

  /* Strength meter realtime — oninput di HTML sudah memanggil updateStrength(),
     tapi kita daftarkan juga di sini sebagai fallback */
  document.getElementById('pwBaru')
    ?.addEventListener('input', e => updateStrength(e.target.value));
});