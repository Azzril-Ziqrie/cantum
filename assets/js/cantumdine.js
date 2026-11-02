// Cantum OnePay landing: a sample floor plan that behaves like the real dashboard.
//  - tap a table to read its order (works without JS: table 4 is rendered into the page)
//  - while on screen, new paid orders keep "arriving": a free table gets an order, the table drops in with a ping and
//    a speech bubble from the table, then the order moves on by itself (new -> preparing -> served -> free), like staff working through it.
(function () {
  var board = document.getElementById('cdBoard');
  var detail = document.getElementById('cdDetail');
  var toast = document.getElementById('cdToast');
  if (!board || !detail) return;

  var reduce = window.matchMedia('(prefers-reduced-motion: reduce)');
  var tables = Array.prototype.slice.call(board.querySelectorAll('.cd-tbl'));
  var elNo = detail.querySelector('[data-d="no"]');
  var elMeta = detail.querySelector('[data-d="meta"]');
  var elItems = detail.querySelector('[data-d="items"]');
  var labels = JSON.parse(board.dataset.labels || '{}');
  var pool = JSON.parse(board.dataset.pool || '[]');
  var tableWord = board.dataset.table || 'Table';
  var swap = null, toastTimer = null, lastTap = 0, selected = board.querySelector('.cd-tbl.is-on');

  // when each table entered its current status, for the automatic progression
  var since = new Map();
  tables.forEach(function (b) { since.set(b, Date.now() - (b.dataset.status === 'new' ? 0 : 4000)); });

  // ---- table fill (assets/js/dither.js). The drawing itself is shown as-is; only the body of each table is dithered,
  // in a look per status. table-fill.png is the drawing's enclosed area; we tint it a flat gray and let the dither pattern it.
  var D = window.CantumDither;
  var LOOK = {   // L = tint brightness; the dither turns it into dots of `primary` on `secondary`
    free:   { L: 0.93, mode: 'bayer', primary: '#c4c4c4', secondary: '#ffffff' },    // almost white: nothing happening
    making: { L: 0.45, mode: 'bayer', primary: '#0a0a0a', secondary: '#ffffff' },    // fine checker: in progress
    served: { L: 0.72, mode: 'bayer', primary: '#8c8c8c', secondary: '#e6e6e6' },    // light dots: done
    new:    { L: 0.2,  mode: 'noise', primary: '#000000', secondary: '#f5f5f5' }     // solid black, grain shimmers: act now
  };
  var fillImg = null, fills = new Map();   // fills: button -> { canvas, key, tex }

  function tinted(w, h, L) {
    var c = document.createElement('canvas'); c.width = w; c.height = h;
    var x = c.getContext('2d');
    x.drawImage(fillImg, 0, 0, w, h);
    x.globalCompositeOperation = 'source-in';
    x.fillStyle = 'rgb(' + [1, 1, 1].map(function () { return Math.round(L * 255); }).join(',') + ')';
    x.fillRect(0, 0, w, h);
    return c;
  }

  function paint(btn, time) {
    var f = fills.get(btn);
    if (!D || !f || !fillImg) return;
    var w = f.canvas.offsetWidth, h = f.canvas.offsetHeight;
    if (!w || !h) return;
    var st = btn.dataset.status, look = LOOK[st] || LOOK.free, key = st + w + 'x' + h;
    if (f.key !== key) { f.tex = tinted(w, h, look.L); f.key = key; }
    D.render(f.canvas, f.tex, { gridSize: 2, ditherMode: look.mode, colorMode: 'duotone', primaryColor: look.primary, secondaryColor: look.secondary }, time || 0);
  }

  function paintAll() { tables.forEach(function (b) { paint(b); }); }

  if (D && board.dataset.fill) {
    tables.forEach(function (btn) {
      var canvas = btn.querySelector('.cd-fill');
      if (!canvas) return;
      fills.set(btn, { canvas: canvas });
      if ('ResizeObserver' in window) new ResizeObserver(function () { paint(btn); }).observe(canvas);
    });
    fillImg = new Image();
    fillImg.onload = paintAll;
    fillImg.src = board.dataset.fill;
  }

  var NEXT = { new: ['making', 6500], making: ['served', 9000], served: ['free', 8000] };

  function items(btn) { try { return JSON.parse(btn.dataset.items || '[]'); } catch (e) { return []; } }
  function metaText(btn) { return btn.dataset.label + (btn.dataset.when ? ' · ' + btn.dataset.when : ''); }

  function fill(btn) {
    var list = items(btn);
    elNo.textContent = btn.dataset.no;
    elMeta.textContent = metaText(btn);
    elItems.textContent = '';
    (list.length ? list : [board.dataset.empty]).forEach(function (t) {
      var li = document.createElement('li'); li.textContent = t; elItems.appendChild(li);
    });
  }

  function show(btn) {
    tables.forEach(function (b) {
      var on = b === btn;
      b.classList.toggle('is-on', on);
      b.setAttribute('aria-pressed', on ? 'true' : 'false');
    });
    selected = btn;
    clearTimeout(swap);                       // a newer change replaces an unfinished swap, never queues behind it
    if (reduce.matches) { fill(btn); return; }
    detail.classList.add('is-swap');
    swap = setTimeout(function () { fill(btn); detail.classList.remove('is-swap'); }, 120);
  }

  function setStatus(btn, st, list, when) {
    ['free', 'new', 'making', 'served'].forEach(function (s) { btn.classList.remove('is-' + s); });
    btn.classList.add('is-' + st);
    btn.dataset.status = st;
    btn.dataset.label = labels[st] || st;
    btn.dataset.when = when || '';
    if (list) btn.dataset.items = JSON.stringify(list);
    var sr = btn.querySelector('.cd-sr');
    if (sr) sr.textContent = tableWord + ' ' + btn.dataset.no + ', ' + btn.dataset.label;
    since.set(btn, Date.now());
    paint(btn);
    if (btn === selected) fill(btn);          // keep an open detail card truthful
  }

  // the table speaks: a small bubble above the table that just got an order. Typing dots first, then a short line and the menu ordered.
  // Another order while it is up restarts it over the new table.
  var says = []; try { says = JSON.parse((toast && toast.dataset.words) || '[]'); } catch (e) {}
  var sWord = toast && toast.querySelector('[data-n="word"]');
  var sItems = toast && toast.querySelector('[data-n="items"]');
  var sText = toast && toast.querySelector('[data-n="text"]');
  var sayIx = 0, panel = board.closest('.cd-panel'), typeTimer = null;

  function ping(text, btn, list) {
    if (!toast || !panel || !btn) return;
    var sp = btn.querySelector('.cd-sprite').getBoundingClientRect(), pr = panel.getBoundingClientRect();
    var x = sp.left + sp.width / 2 - pr.left, y = sp.top - pr.top - 2;       // just above the table top
    x = Math.max(40, Math.min(pr.width - 270, x));                              // keep the bubble inside the panel
    y = Math.max(130, y);
    toast.style.left = x + 'px'; toast.style.top = y + 'px';
    if (sWord) sWord.textContent = says.length ? says[sayIx++ % says.length] : text;
    if (sItems) {                                              // the table reads out what was ordered
      sItems.textContent = '';
      (list || []).forEach(function (it) { var li = document.createElement('li'); li.textContent = it; sItems.appendChild(li); });
    }
    if (sText) sText.textContent = text + ': ' + (list || []).join(', ');
    clearTimeout(toastTimer); clearTimeout(typeTimer);
    toast.classList.remove('is-on', 'is-out', 'is-typing'); void toast.offsetWidth;   // restart from scratch
    toast.classList.add('is-on');
    if (!reduce.matches) {
      toast.classList.add('is-typing');
      typeTimer = setTimeout(function () { toast.classList.remove('is-typing'); }, 650);
    }
    toastTimer = setTimeout(function () {
      toast.classList.add('is-out');
      setTimeout(function () { toast.classList.remove('is-on', 'is-out'); }, 260);
    }, 3400);
  }

  function incoming() {
    var free = tables.filter(function (b) { return b.dataset.status === 'free'; });
    if (!free.length || !pool.length) return;
    var btn = free[Math.floor(Math.random() * free.length)];
    var order = pool[Math.floor(Math.random() * pool.length)];
    setStatus(btn, 'new', order, board.dataset.now);
    btn.classList.remove('is-drop'); void btn.offsetWidth;      // restart the drop even if it ran recently
    btn.classList.add('is-drop');
    setTimeout(function () { btn.classList.remove('is-drop'); }, 1000);
    ping((board.dataset.new || '%d').replace('%d', btn.dataset.no), btn, order);
    if (Date.now() - lastTap > 9000) show(btn);               // follow the new order unless the visitor is reading another table
  }

  function progress() {
    var now = Date.now();
    tables.forEach(function (b) {
      var rule = NEXT[b.dataset.status];
      if (!rule || now - (since.get(b) || now) < rule[1]) return;
      var st = rule[0];
      setStatus(b, st, st === 'free' ? [] : null, st === 'free' ? '' : b.dataset.when);
    });
  }

  tables.forEach(function (btn) {
    btn.addEventListener('click', function () { lastTap = Date.now(); show(btn); });
  });

  // run only while the floor plan is on screen and the tab is visible
  var visible = false, tick = null, spawn = null;
  function loop() {
    spawn = setTimeout(function () { incoming(); if (running()) loop(); }, 3600 + Math.random() * 2600);
  }
  function running() { return visible && !document.hidden; }
  var shimmer = null, t0 = Date.now();
  function sync() {
    clearInterval(tick); clearTimeout(spawn); clearInterval(shimmer);
    if (!running()) return;
    tick = setInterval(progress, 1000);
    if (!reduce.matches) shimmer = setInterval(function () {      // new orders shimmer, a few frames a second
      var time = (Date.now() - t0) / 4000;
      tables.forEach(function (b) { if (b.dataset.status === 'new') paint(b, time); });
    }, 120);
    spawn = setTimeout(function () { incoming(); if (running()) loop(); }, 1800);   // first order arrives quickly
  }
  if ('IntersectionObserver' in window) {
    new IntersectionObserver(function (en) { visible = en[0].isIntersecting; sync(); }, { threshold: 0.35 }).observe(board);
  }
  document.addEventListener('visibilitychange', sync);
})();

// scroll reveal for the page sections; content stays visible if JS or IntersectionObserver is missing
(function () {
  var els = document.querySelectorAll('.rv');
  if (!('IntersectionObserver' in window)) { els.forEach(function (el) { el.classList.add('in'); }); return; }
  var io = new IntersectionObserver(function (entries) {
    entries.forEach(function (en) { if (en.isIntersecting) { en.target.classList.add('in'); io.unobserve(en.target); } });
  }, { rootMargin: '0px 0px -8% 0px', threshold: 0.08 });
  els.forEach(function (el) { io.observe(el); });
})();
