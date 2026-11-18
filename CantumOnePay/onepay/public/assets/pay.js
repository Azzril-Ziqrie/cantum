/* Halaman bayar: render QR, hitung mundur, salin, dan polling status (tiap ketiga memicu sync ke gateway). */
(function () {
  'use strict';
  const POLL_MS = 4000;
  const SYNC_EVERY = 3;
  const URGENT_SECONDS = 300;
  const { status: initialStatus, endpoint } = window.PAY;

  function renderQr() {
    const box = document.getElementById('qrBox');
    if (!box) return;
    if (typeof QRCode === 'undefined') {
      box.textContent = box.dataset.qr; // cadangan bila library gagal dimuat
      return;
    }
    new QRCode(box, { text: box.dataset.qr, width: 240, height: 240, correctLevel: QRCode.CorrectLevel.M });
  }

  function startCountdown() {
    const node = document.getElementById('countdown');
    if (!node || !node.dataset.expires) return;
    const expiresAt = Number(node.dataset.expires) * 1000;
    const tick = () => {
      const left = Math.max(0, Math.round((expiresAt - Date.now()) / 1000));
      node.textContent = left === 0 ? 'Waktu habis' : String(Math.floor(left / 60)).padStart(2, '0') + ':' + String(left % 60).padStart(2, '0');
      node.classList.toggle('is-urgent', left > 0 && left < URGENT_SECONDS);
      if (left === 0) showExpired();
    };
    tick();
    setInterval(tick, 1000);
  }

  /** Waktu habis: tampilkan pesan dan redupkan kode bayar supaya tidak dipakai lagi. */
  function showExpired() {
    const notice = document.getElementById('expiredNotice');
    if (notice) notice.hidden = false;
    document.getElementById('payCard')?.classList.add('is-expired');
  }

  /** Unduh gambar QR (PNG) dari kanvas yang dirender di browser. */
  function wireQrDownload() {
    const button = document.getElementById('qrDownload');
    if (!button) return;
    button.addEventListener('click', () => {
      const canvas = document.querySelector('#qrBox canvas');
      if (!canvas) { button.textContent = 'QR belum siap'; return; }
      const link = document.createElement('a');
      link.download = 'qris-' + (document.title.split('·').pop() || 'pesanan').trim() + '.png';
      link.href = canvas.toDataURL('image/png');
      link.click();
    });
  }

  /** Tombol "Coba lagi" hanya bisa ditekan sekali, supaya tidak memicu banyak permintaan buat-tagihan. */
  function guardRetryButton() {
    document.querySelectorAll('[data-reload]').forEach((button) => button.addEventListener('click', () => {
      button.disabled = true;
      button.textContent = 'Mencoba lagi…';
    }));
  }

  function wireCopyButtons() {
    document.querySelectorAll('[data-copy]').forEach((button) => button.addEventListener('click', async () => {
      const original = button.textContent;
      try {
        await navigator.clipboard.writeText(button.dataset.copy);
        button.textContent = 'Tersalin';
      } catch (error) {
        button.textContent = 'Gagal';
      }
      setTimeout(() => { button.textContent = original; }, 1500);
    }));
  }

  function startPolling() {
    let polls = 0;
    setInterval(async () => {
      polls += 1;
      const query = polls % SYNC_EVERY === 0 ? '?sync=1' : '';
      try {
        const response = await fetch(endpoint + query, { cache: 'no-store', headers: { Accept: 'application/json' } });
        const data = await response.json();
        if (data.status !== initialStatus) window.location.reload();
      } catch (error) { /* jaringan putus sebentar: coba lagi di siklus berikutnya */ }
    }, POLL_MS);
  }

  renderQr();
  wireQrDownload();
  guardRetryButton();
  startCountdown();
  wireCopyButtons();
  startPolling();
})();
