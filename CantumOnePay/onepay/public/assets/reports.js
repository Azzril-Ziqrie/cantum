/* Laporan: tooltip untuk kolom grafik (hover dan fokus keyboard). Data lengkap selalu ada di tabel. */
(function () {
  'use strict';
  const tip = document.getElementById('rpTip');
  if (!tip) return;

  function show(target) {
    const box = target.getBoundingClientRect();
    tip.textContent = target.dataset.tip;
    tip.hidden = false;
    // Jaga agar tidak keluar dari layar di tepi kiri/kanan.
    const half = tip.offsetWidth / 2;
    const x = Math.min(window.innerWidth - half - 8, Math.max(half + 8, box.left + box.width / 2));
    tip.style.left = x + 'px';
    tip.style.top = box.top + 'px';
  }

  const hide = () => { tip.hidden = true; };

  document.querySelectorAll('[data-tip]').forEach((node) => {
    node.addEventListener('pointerenter', () => show(node));
    node.addEventListener('pointerleave', hide);
    node.addEventListener('focus', () => show(node));
    node.addEventListener('blur', hide);
  });
  window.addEventListener('scroll', hide, { passive: true });
})();
