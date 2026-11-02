// Cantum OnePay loading screen: counts 1 to 100 over 3 seconds while the table drawing fills with dither
// (assets/js/dither.js), then lifts away and lets the hero animate in. Reduced motion skips it.
(function () {
  var root = document.documentElement;
  var el = document.getElementById('cdLoad');

  function ready() {
    root.classList.remove('is-loading');
    root.classList.add('is-ready');
  }
  if (!el || window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
    if (el) el.hidden = true;
    ready();
    return;
  }

  var DURATION = 3000;
  var num = document.getElementById('cdCount');
  var canvas = el.querySelector('.cd-load-fill');
  var D = window.CantumDither;
  var img = new Image();
  var start = null, shown = 0;

  // slow at the start and the end, quick in the middle
  function ease(p) { return p < 0.5 ? 4 * p * p * p : 1 - Math.pow(-2 * p + 2, 3) / 2; }

  // a gradient front climbs the table; the dither turns it into a rising tide of dots
  function paint(p) {
    if (!D || !img.complete || !img.naturalWidth) return;
    var w = canvas.offsetWidth, h = canvas.offsetHeight;
    if (!w || !h) return;
    var src = document.createElement('canvas');
    src.width = w; src.height = h;
    var x = src.getContext('2d');
    var y0 = h * (1.5 * (1 - p) - 0.5);
    var g = x.createLinearGradient(0, y0, 0, y0 + h * 0.5);
    g.addColorStop(0, '#ffffff');
    g.addColorStop(1, '#0b0b0b');
    x.fillStyle = g;
    x.fillRect(0, 0, w, h);
    x.globalCompositeOperation = 'destination-in';
    x.drawImage(img, 0, 0, w, h);                      // keep only the table's body
    D.render(canvas, src, { gridSize: 2, ditherMode: 'bayer', colorMode: 'duotone', primaryColor: '#0b0b0b', secondaryColor: '#ffffff' }, 0);
  }

  function leave() {
    el.classList.add('is-out');
    ready();
    setTimeout(function () { el.hidden = true; }, 1400);
  }

  function frame(ts) {
    if (start === null) start = ts;
    var p = Math.min((ts - start) / DURATION, 1);
    var e = ease(p);
    var n = Math.max(1, Math.round(e * 100));
    if (n !== shown) { shown = n; num.textContent = n; paint(e); }
    if (p < 1) requestAnimationFrame(frame);
    else setTimeout(leave, 350);                        // hold on 100 for a beat
  }

  img.onload = function () { paint(0); };
  img.src = el.dataset.fill;
  requestAnimationFrame(frame);
})();
