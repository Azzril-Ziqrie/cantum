/* Gambar meja: garis (table-lines.png) + isian dither (table-fill.png) per status, sama seperti halaman produk OnePay.
   Dipakai denah dashboard. API: TableSprite.create(status) -> elemen sprite; TableSprite.setStatus(sprite, status). */
(function () {
  'use strict';
  const SHIMMER_MS = 140;
  const LOOK = {
    empty:     { L: 0.93, mode: 'bayer', primary: '#c3cbdc', secondary: '#ffffff' },   // hampir putih: tidak ada kegiatan
    pending:   { L: 0.85, mode: 'bayer', primary: '#9fb0d4', secondary: '#ffffff' },   // menunggu bayar
    new:       { L: 0.2,  mode: 'noise', primary: '#0a1f4d', secondary: '#f5f7fc' },   // biru gelap pekat, butiran berkedip: tindak sekarang
    preparing: { L: 0.45, mode: 'bayer', primary: '#0a1f4d', secondary: '#ffffff' },   // kotak halus: sedang diproses
    ready:     { L: 0.6,  mode: 'bayer', primary: '#2f4a8a', secondary: '#ffffff' },   // siap disajikan
    served:    { L: 0.72, mode: 'bayer', primary: '#8c97b3', secondary: '#e6e9f2' },   // titik terang: selesai
  };

  const base = document.currentScript ? document.currentScript.src.replace(/[^/]*$/, '') : '/assets/';
  const sprites = new Set();
  let fillImage = null;
  let timer = null;

  function tinted(width, height, level) {
    const canvas = document.createElement('canvas');
    canvas.width = width; canvas.height = height;
    const context = canvas.getContext('2d');
    context.drawImage(fillImage, 0, 0, width, height);
    context.globalCompositeOperation = 'source-in';
    const channel = Math.round(level * 255);
    context.fillStyle = `rgb(${channel},${channel},${channel})`;
    context.fillRect(0, 0, width, height);
    return canvas;
  }

  function paint(sprite, time) {
    const dither = window.CantumDither;
    const canvas = sprite.querySelector('canvas');
    if (!dither || !fillImage || !canvas) return;
    const width = canvas.offsetWidth, height = canvas.offsetHeight;
    if (!width || !height) return;

    const look = LOOK[sprite.dataset.status] || LOOK.empty;
    const key = sprite.dataset.status + width + 'x' + height;
    if (sprite._key !== key) { sprite._texture = tinted(width, height, look.L); sprite._key = key; }
    dither.render(canvas, sprite._texture, { gridSize: 2, ditherMode: look.mode, colorMode: 'duotone', primaryColor: look.primary, secondaryColor: look.secondary }, time || 0);
  }

  function paintAll() { sprites.forEach((sprite) => paint(sprite)); }

  /** Hanya meja "baru" yang butirannya berkedip; yang lain digambar sekali. */
  function startShimmer() {
    if (timer || window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
    timer = setInterval(() => {
      const now = performance.now() / 1000;
      sprites.forEach((sprite) => { if (sprite.dataset.status === 'new' && sprite.isConnected) paint(sprite, now); });
    }, SHIMMER_MS);
  }

  function create(status) {
    const sprite = document.createElement('span');
    sprite.className = 'itbl-sprite';
    sprite.setAttribute('aria-hidden', 'true');
    sprite.innerHTML = '<canvas class="itbl-fill"></canvas><img class="itbl-lines" alt="" draggable="false" width="480" height="378">';
    sprite.querySelector('img').src = base + 'table-lines.png';
    sprite.dataset.status = status;
    sprites.add(sprite);
    if ('ResizeObserver' in window) new ResizeObserver(() => paint(sprite)).observe(sprite.querySelector('canvas'));
    requestAnimationFrame(() => paint(sprite));
    startShimmer();
    return sprite;
  }

  function forget(root) {
    sprites.forEach((sprite) => { if (!sprite.isConnected || (root && !root.contains(sprite))) sprites.delete(sprite); });
  }

  fillImage = new Image();
  fillImage.onload = paintAll;
  fillImage.src = base + 'table-fill.png';

  window.TableSprite = { create, paintAll, forget, LOOK };
})();
