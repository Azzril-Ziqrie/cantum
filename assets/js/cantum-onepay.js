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
    // measure the floor tile, not the sprite: the sprite is still falling in when the order lands
    var pad = btn.querySelector('.cd-pad').getBoundingClientRect(), pr = panel.getBoundingClientRect();
    var tx = pad.left + pad.width / 2 - pr.left;
    var y = Math.max(112, pad.top + pad.height / 2 - pr.top - btn.querySelector('.cd-sprite').offsetHeight - 4);   // just above the standing table
    toast.style.top = y + 'px';
    if (sWord) sWord.textContent = says.length ? says[sayIx++ % says.length] : text;
    if (sItems) {                                              // the table reads out what was ordered
      sItems.textContent = '';
      (list || []).forEach(function (it) { var li = document.createElement('li'); li.textContent = it; sItems.appendChild(li); });
    }
    if (sText) sText.textContent = text + ': ' + (list || []).join(', ');
    var bw = toast.querySelector('.bubble').offsetWidth || 220;                  // keep the whole bubble inside the panel
    var bl = Math.max(8, Math.min(pr.width - bw - 8, tx - 28));
    toast.style.left = (bl + 28) + 'px';
    toast.style.setProperty('--tail', Math.max(14, Math.min(bw - 30, tx - bl - 6)) + 'px');
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

// a string hangs from the hero logo down to the service icons; it is measured from the real layout, so it follows any screen size
(function () {
  var main = document.querySelector('main'), tile = document.querySelector('.cd-tile');
  var icons = Array.prototype.slice.call(document.querySelectorAll('.cd-sol-ic'));
  if (!main || !tile || !icons.length || !('createElementNS' in document)) return;
  var NS = 'http://www.w3.org/2000/svg';
  var svg = document.createElementNS(NS, 'svg');
  svg.setAttribute('class', 'cd-string'); svg.setAttribute('aria-hidden', 'true');
  main.insertBefore(svg, main.firstChild);

  var started = false, still = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  function draw() {
    var m = main.getBoundingClientRect(), t = tile.getBoundingClientRect();
    var x0 = t.left + t.width / 2 - m.left, y0 = t.top + t.height - m.top;
    var small = window.matchMedia('(max-width: 760px)').matches;
    var lines = '', dots = '', kx = x0, ky = y0;
    function add(d, i) {
      lines += '<path pathLength="1" style="--k:' + i + '" d="' + d + '"/>';
      if (!still) dots += '<circle class="pulse" r="3.5" style="--k:' + i + '"><animateMotion dur="' + (3.4 + i * .5) + 's" begin="' + (2.4 + i * .45) + 's" repeatCount="indefinite" calcMode="spline" keyTimes="0;1" keySplines=".45 0 .55 1" path="' + d + '"/></circle>';
    }
    if (small) {
      // phone: one trunk leaves the logo sideways and runs down the left margin (never crossing the text),
      // and a branch curls off it into every service icon, so all of them are connected
      kx = t.left - m.left; ky = t.top + t.height / 2 - m.top;
      var pts = icons.map(function (ic) {
        var r = ic.getBoundingClientRect();
        return { x: r.left + r.width / 2 - m.left, y: r.top + r.height / 2 - m.top };
      });
      var last = pts[pts.length - 1];
      if (pts[0].y > ky + 140) {
        add('M' + kx + ' ' + ky + ' C' + (kx - 34) + ' ' + ky + ' 9 ' + ky + ' 9 ' + (ky + 70) + ' L9 ' + (last.y - 60), 0);
        pts.forEach(function (q, i) {
          add('M9 ' + (q.y - 60) + ' C9 ' + q.y + ' ' + (q.x - 90) + ' ' + q.y + ' ' + (q.x - 33) + ' ' + q.y, i + 3);
        });
      }
    } else {
      icons.forEach(function (ic, i) {
        var r = ic.getBoundingClientRect(), x1 = r.left + r.width / 2 - m.left, y1 = r.top - m.top, dy = y1 - y0;
        if (dy <= 0) return;
        add('M' + x0 + ' ' + y0 + ' C' + x0 + ' ' + (y0 + dy * .72) + ' ' + x1 + ' ' + (y1 - dy * .26) + ' ' + x1 + ' ' + y1, i);
      });
    }
    svg.setAttribute('viewBox', '0 0 ' + m.width + ' ' + m.height);
    svg.innerHTML = lines + dots + '<circle class="knot" cx="' + kx + '" cy="' + ky + '" r="4.5"/>';
    if (started) svg.classList.add('is-on');
  }
  // the first draw waits for the hero entrance, so the string grows out of the logo once it has settled
  function start() {
    if (started) return;
    started = true; draw(); void svg.getBoundingClientRect();
    requestAnimationFrame(function () { svg.classList.add('is-on'); });
  }
  var raf = 0;
  function soon() { cancelAnimationFrame(raf); raf = requestAnimationFrame(draw); }
  window.addEventListener('resize', soon);
  window.addEventListener('load', function () { soon(); setTimeout(start, 2600); });
  tile.addEventListener('animationend', function () { soon(); setTimeout(start, 400); });
  if (document.fonts && document.fonts.ready) document.fonts.ready.then(soon);
  if ('ResizeObserver' in window) new ResizeObserver(soon).observe(main);
  soon();
})();

// phone: page dots under the swipeable feature cards (they follow the scroll, and a tap moves there)
(function () {
  var row = document.querySelector('.cd-feat');
  if (!row) return;
  var cards = Array.prototype.slice.call(row.children);
  var dots = document.createElement('div');
  dots.className = 'cd-feat-dots'; dots.setAttribute('aria-hidden', 'true');
  cards.forEach(function (card, i) {
    var b = document.createElement('button'); b.type = 'button'; b.tabIndex = -1;
    b.addEventListener('click', function () {
      var smooth = !window.matchMedia('(prefers-reduced-motion: reduce)').matches;
      row.scrollTo({ left: card.offsetLeft - parseFloat(getComputedStyle(row).paddingLeft), behavior: smooth ? 'smooth' : 'auto' });
    });
    dots.appendChild(b);
  });
  row.parentNode.insertBefore(dots, row.nextSibling);
  var buttons = dots.children, cur = -1;
  function sync() {
    var pad = parseFloat(getComputedStyle(row).paddingLeft) || 0, best = 0, dist = 1e9;
    cards.forEach(function (card, i) {
      var d = Math.abs(card.offsetLeft - pad - row.scrollLeft);
      if (d < dist) { dist = d; best = i; }
    });
    if (row.scrollLeft + row.clientWidth >= row.scrollWidth - 4) best = cards.length - 1;   // the last card can never reach the start edge
    if (best === cur) return;
    cur = best;
    Array.prototype.forEach.call(buttons, function (b, i) { b.classList.toggle('is-on', i === best); });
  }
  row.addEventListener('scroll', function () { requestAnimationFrame(sync); }, { passive: true });
  window.addEventListener('resize', sync);
  sync();
})();
