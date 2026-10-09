const hamburgerBtn  = document.getElementById('hamburgerBtn');
    const sidebar       = document.getElementById('sidebar');
    const sidebarOverlay= document.getElementById('sidebarOverlay');

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

    /* Close drawer when a nav link is tapped on mobile */
    sidebar.querySelectorAll('a').forEach(link => {
      link.addEventListener('click', () => {
        if (window.innerWidth <= 768) closeSidebar();
      });
    });

    /* ── Tab filter ── */
    function filterOrders(status, el) {
      document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
      el.classList.add('active');
      document.querySelectorAll('.order-card').forEach(card => {
        card.style.display = (status === 'semua' || card.dataset.status === status) ? '' : 'none';
      });
    }

    /* ── Status transitions ── */
    function setMasak(btn) {
      const card = btn.closest('.order-card');
      card.dataset.status = 'dimasak';
      card.className = 'order-card status-dimasak';
      card.querySelector('.status-badge').className = 'status-badge badge-dimasak';
      card.querySelector('.status-badge').innerHTML = '<i class="fa-solid fa-fire-burner"></i> Dimasak';
      btn.closest('.order-actions').innerHTML = `
        <button class="btn btn-detail"><i class="fa-regular fa-eye"></i> Detail</button>
        <button class="btn btn-siap" onclick="setSiap(this)"><i class="fa-solid fa-bell"></i> Siap</button>
      `;
    }

    function setSiap(btn) {
      const card = btn.closest('.order-card');
      card.dataset.status = 'siap';
      card.className = 'order-card status-siap';
      card.querySelector('.status-badge').className = 'status-badge badge-siap';
      card.querySelector('.status-badge').innerHTML = '<i class="fa-solid fa-bell"></i> Siap';
      btn.closest('.order-actions').innerHTML = `
        <button class="btn btn-detail"><i class="fa-regular fa-eye"></i> Detail</button>
        <button class="btn btn-selesai" onclick="setSelesai(this)"><i class="fa-solid fa-circle-check"></i> Selesai</button>
      `;
    }

    function setSelesai(btn) {
      const card = btn.closest('.order-card');
      card.dataset.status = 'selesai';
      card.className = 'order-card status-selesai';
      card.querySelector('.status-badge').className = 'status-badge badge-selesai';
      card.querySelector('.status-badge').innerHTML = '<i class="fa-solid fa-circle-check"></i> Selesai';
      btn.closest('.order-actions').innerHTML = `
        <button class="btn btn-detail"><i class="fa-regular fa-eye"></i> Detail</button>
        <span style="font-size:13px;color:#22c55e;font-weight:700;display:flex;align-items:center;gap:6px;">
          <i class="fa-solid fa-check-double"></i> Pesanan Selesai
        </span>
      `;
    }

    function setTolak(btn) {
      const card = btn.closest('.order-card');
      card.dataset.status = 'tolak';
      card.className = 'order-card status-tolak';
      card.querySelector('.status-badge').className = 'status-badge';
      card.querySelector('.status-badge').style.cssText = 'background:#fee2e2;color:#dc2626';
      card.querySelector('.status-badge').innerHTML = '<i class="fa-solid fa-xmark"></i> Dibatalkan';
      btn.closest('.order-actions').innerHTML = `
        <button class="btn btn-detail"><i class="fa-regular fa-eye"></i> Detail</button>
        <span style="font-size:13px;color:#dc2626;font-weight:700;display:flex;align-items:center;gap:6px;">
          <i class="fa-solid fa-ban"></i> Pesanan Ditolak
        </span>
      `;
    }