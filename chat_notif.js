/**
 * chat_notif.js
 * Tempelkan script ini di navbar/header yang sudah ada
 * agar muncul badge jumlah pesan belum dibaca.
 *
 * Syarat: ada elemen dengan id="chat-link" di navbar,
 * misalnya: <a id="chat-link" href="chat_siswa.php">Chat</a>
 *
 * Script ini akan menambahkan badge merah otomatis.
 */

(function () {
  const API = 'php/chat_api.php';

  function cekNotif() {
    fetch(`${API}?action=jumlah_belum_baca`)
      .then(r => r.json())
      .then(data => {
        const n     = parseInt(data.belum_baca) || 0;
        const link  = document.getElementById('chat-link');
        if (!link) return;

        let badge = document.getElementById('chat-notif-badge');
        if (!badge) {
          link.style.position = 'relative';
          badge = document.createElement('span');
          badge.id = 'chat-notif-badge';
          link.appendChild(badge);
        }

        if (n > 0) {
          badge.textContent = n > 99 ? '99+' : n;
          badge.classList.remove('hidden');
        } else {
          badge.classList.add('hidden');
        }
      })
      .catch(() => {});
  }

  // Cek setiap 10 detik (ringan, tidak ganggu performa)
  cekNotif();
  setInterval(cekNotif, 10000);
})();
