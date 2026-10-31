// Dither shader, vanilla JS. A port of Aceternity UI's <DitherShader> (React/Tailwind) with the same algorithms and
// options (bayer / halftone / noise / crosshatch; original / grayscale / duotone / custom), minus the React wrapper
// and the image loader: it dithers any canvas or image you hand it.
//
//   CantumDither.render(canvas, source, options, time)
//     canvas   the visible <canvas>; its CSS size is read from offsetWidth/offsetHeight (transform-safe)
//     source   a canvas or loaded <img> that is drawn to fill the canvas ("fill"); pass the same one again to reuse it
//     options  gridSize, ditherMode, colorMode, invert, pixelRatio, primaryColor, secondaryColor, customPalette,
//              brightness, contrast, backgroundColor, threshold  (defaults match the React component)
//     time     seconds-ish counter, only used by the "noise" mode to make the grain shimmer
(function (root) {
  var B4 = [[0, 8, 2, 10], [12, 4, 14, 6], [3, 11, 1, 9], [15, 7, 13, 5]];
  var B8 = [
    [0, 32, 8, 40, 2, 34, 10, 42], [48, 16, 56, 24, 50, 18, 58, 26], [12, 44, 4, 36, 14, 46, 6, 38], [60, 28, 52, 20, 62, 30, 54, 22],
    [3, 35, 11, 43, 1, 33, 9, 41], [51, 19, 59, 27, 49, 17, 57, 25], [15, 47, 7, 39, 13, 45, 5, 37], [63, 31, 55, 23, 61, 29, 53, 21]
  ];
  var DEFAULTS = {
    gridSize: 4, ditherMode: 'bayer', colorMode: 'original', invert: false, pixelRatio: 1,
    primaryColor: '#000000', secondaryColor: '#ffffff', customPalette: ['#000000', '#ffffff'],
    brightness: 0, contrast: 1, backgroundColor: 'transparent', threshold: 0.5
  };
  var parsed = {};

  function parseColor(c) {
    if (parsed[c]) return parsed[c];
    var out = [0, 0, 0], m;
    if (c.charAt(0) === '#') {
      var h = c.slice(1);
      if (h.length === 3) out = [parseInt(h[0] + h[0], 16), parseInt(h[1] + h[1], 16), parseInt(h[2] + h[2], 16)];
      else out = [parseInt(h.slice(0, 2), 16), parseInt(h.slice(2, 4), 16), parseInt(h.slice(4, 6), 16)];
    } else if ((m = c.match(/rgb\((\d+)\s*,\s*(\d+)\s*,\s*(\d+)\)/i))) {
      out = [+m[1], +m[2], +m[3]];
    }
    return (parsed[c] = out);
  }
  function clamp(v, a, b) { return Math.max(a, Math.min(b, v)); }

  // pixels of the source, resampled to the canvas size, cached on the canvas until the source or size changes
  function sourceData(canvas, source, w, h) {
    var c = canvas._dither || (canvas._dither = {});
    if (c.src === source && c.w === w && c.h === h && c.data) return c.data;
    var off = document.createElement('canvas');
    off.width = w; off.height = h;
    var octx = off.getContext('2d');
    octx.drawImage(source, 0, 0, w, h);
    c.data = octx.getImageData(0, 0, w, h);
    c.src = source; c.w = w; c.h = h;
    return c.data;
  }

  function render(canvas, source, options, time) {
    var o = {}, k;
    for (k in DEFAULTS) o[k] = DEFAULTS[k];
    for (k in (options || {})) o[k] = options[k];
    var w = canvas.offsetWidth, h = canvas.offsetHeight;
    if (!w || !h) return;
    var dpr = root.devicePixelRatio || 1;
    var pw = Math.floor(w * dpr), ph = Math.floor(h * dpr);
    if (canvas.width !== pw || canvas.height !== ph) { canvas.width = pw; canvas.height = ph; }
    var ctx = canvas.getContext('2d');
    ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
    var img = sourceData(canvas, source, w, h);
    var data = img.data, sw = img.width, sh = img.height;

    if (o.backgroundColor !== 'transparent') { ctx.fillStyle = o.backgroundColor; ctx.fillRect(0, 0, w, h); }
    else ctx.clearRect(0, 0, w, h);

    var px = Math.max(1, Math.floor(o.gridSize * o.pixelRatio));
    var msize = o.gridSize <= 4 ? 4 : 8, bayer = msize === 4 ? B4 : B8, mscale = msize === 4 ? 16 : 64;
    var p1 = parseColor(o.primaryColor), p2 = parseColor(o.secondaryColor), pal = o.customPalette.map(parseColor);
    var t = time || 0, gs = o.gridSize;

    for (var y = 0; y < h; y += px) {
      for (var x = 0; x < w; x += px) {
        var si = (Math.floor((y / h) * sh) * sw + Math.floor((x / w) * sw)) * 4;
        if ((data[si + 3] || 0) < 10) continue;
        var r = clamp((data[si] - 128) * o.contrast + 128 + o.brightness * 255, 0, 255);
        var g = clamp((data[si + 1] - 128) * o.contrast + 128 + o.brightness * 255, 0, 255);
        var b = clamp((data[si + 2] - 128) * o.contrast + 128 + o.brightness * 255, 0, 255);
        var lum = (0.299 * r + 0.587 * g + 0.114 * b) / 255;

        var mx = Math.floor(x / gs) % msize, my = Math.floor(y / gs) % msize, d;
        if (o.ditherMode === 'halftone') {
          var ang = Math.PI / 4, sc = gs * 2;
          var rx = x * Math.cos(ang) + y * Math.sin(ang), ry = -x * Math.sin(ang) + y * Math.cos(ang);
          d = (Math.sin(rx / sc) + Math.sin(ry / sc) + 2) / 4;
        } else if (o.ditherMode === 'noise') {
          var n = Math.sin(x * 12.9898 + y * 78.233 + t * 100) * 43758.5453;
          d = n - Math.floor(n);
        } else if (o.ditherMode === 'crosshatch') {
          var l1 = (x + y) % (gs * 2) < gs ? 1 : 0, l2 = (x - y + gs * 4) % (gs * 2) < gs ? 1 : 0;
          d = (l1 + l2) / 2;
        } else {
          d = bayer[my][mx] / mscale;
        }
        d = d * (1 - o.threshold) + o.threshold * 0.5;

        var c, dark = lum < d;
        if (o.colorMode === 'grayscale') c = dark ? [0, 0, 0] : [255, 255, 255];
        else if (o.colorMode === 'duotone') c = dark ? p1 : p2;
        else if (o.colorMode === 'custom') {
          if (pal.length === 2) c = dark ? pal[0] : pal[1];
          else c = pal[Math.floor(clamp(lum + (d - 0.5) * 0.5, 0, 1) * (pal.length - 1))];
        } else {
          var da = d - 0.5, q = 255 / 4;
          c = [Math.round(clamp(r + da * 64, 0, 255) / q) * q, Math.round(clamp(g + da * 64, 0, 255) / q) * q, Math.round(clamp(b + da * 64, 0, 255) / q) * q];
        }
        if (o.invert) c = [255 - c[0], 255 - c[1], 255 - c[2]];
        ctx.fillStyle = 'rgb(' + c[0] + ',' + c[1] + ',' + c[2] + ')';
        ctx.fillRect(x, y, px, px);
      }
    }
  }

  root.CantumDither = { render: render, defaults: DEFAULTS };
})(window);
