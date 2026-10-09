const hamburgerBtn   = document.getElementById('hamburgerBtn');
    const sidebar        = document.getElementById('sidebar');
    const sidebarOverlay = document.getElementById('sidebarOverlay');

    function openSidebar() {
      sidebar.classList.add('open');
      sidebarOverlay.classList.add('show');
      document.body.style.overflow = 'hidden';
    }
    function closeSidebar() {
      sidebar.classList.remove('open');
      sidebarOverlay.classList.remove('show');
      document.body.style.overflow = '';
    }

    hamburgerBtn.addEventListener('click', openSidebar);
    sidebarOverlay.addEventListener('click', closeSidebar);

    sidebar.querySelectorAll('a').forEach(link => {
      link.addEventListener('click', () => {
        if (window.innerWidth <= 768) closeSidebar();
      });
    });

    /* ── FAQ accordion ── */
    function toggleFaq(item) {
      const isOpen = item.classList.contains('open');
      // Close all
      document.querySelectorAll('.faq-item').forEach(f => f.classList.remove('open'));
      // Open clicked (if it was closed)
      if (!isOpen) item.classList.add('open');
    }

    /* ── Kirim laporan ── */
    function kirimLaporan() {
      const kategori  = document.getElementById('kategoriSelect').value;
      const deskripsi = document.getElementById('deskripsiTextarea').value.trim();

      if (!kategori) {
        confirmPopup({
        judul: 'Validasi',
        pesan: 'Silahkan pilih kategori dulu!',
        tipe: 'warning'
      });
      return;
      }
      if (deskripsi.length < 10) {
       confirmPopup({
        judul: 'Validasi',
        pesan: 'Deskripsi masalah terlalu singkat!',
        tipe: 'warning'
      });
      return;
      }

      document.getElementById('suksesOverlay').classList.add('open');

      // Reset form
      document.getElementById('kategoriSelect').value = '';
      document.getElementById('deskripsiTextarea').value = '';
    }

    function tutupSukses() {
      document.getElementById('suksesOverlay').classList.remove('open');
    }

    // Close sukses overlay by clicking outside the box
    document.getElementById('suksesOverlay').addEventListener('click', function(e) {
      if (e.target === this) tutupSukses();
    });