// Scroll reveal. Content stays visible if JS or IntersectionObserver is unavailable.
(function () {
  var els = document.querySelectorAll('.reveal');
  if (!('IntersectionObserver' in window)) { els.forEach(function (el) { el.classList.add('in'); }); return; }
  var io = new IntersectionObserver(function (entries) {
    entries.forEach(function (en) {
      if (en.isIntersecting) { en.target.classList.add('in'); io.unobserve(en.target); }
    });
  }, { rootMargin: '0px 0px -8% 0px', threshold: 0.05 });
  els.forEach(function (el) { io.observe(el); });
})();

// Dropdowns (.acc details): animate height + opacity, interruptible.
// Each click starts from the *current* on-screen height, so grabbing a half-open dropdown reverses it smoothly.
// Without JS (or with reduced motion) the native <details> toggle is used.
(function () {
  var reduce = window.matchMedia('(prefers-reduced-motion: reduce)');
  var EASE = 'cubic-bezier(.2, .9, .25, 1)';
  document.querySelectorAll('.acc details').forEach(function (d) {
    var sum = d.querySelector(':scope > summary');
    if (!sum || !d.animate) return;

    var body = document.createElement('div');
    body.className = 'acc-body';
    while (sum.nextSibling) body.appendChild(sum.nextSibling);
    d.appendChild(body);
    d.classList.add('js');
    if (d.open) d.classList.add('is-open');

    var anim = null;
    function run(from, to, fromO, toO, done) {
      body.style.overflow = 'hidden';
      var a = anim = body.animate(
        { height: [from + 'px', to + 'px'], opacity: [fromO, toO] },
        { duration: 480, easing: EASE }
      );
      a.onfinish = function () {
        if (anim !== a) return;
        anim = null; body.style.overflow = ''; done();
      };
    }

    sum.addEventListener('click', function (e) {
      e.preventDefault();
      var opening = !d.classList.contains('is-open');
      if (reduce.matches) { d.open = opening; d.classList.toggle('is-open', opening); return; }

      var inFlight = !!anim;                                       // grabbed mid-animation?
      var cur = body.getBoundingClientRect().height;               // live value, mid-flight or at rest
      var curO = parseFloat(getComputedStyle(body).opacity);
      if (anim) { anim.cancel(); anim = null; }
      d.classList.toggle('is-open', opening);

      if (opening) {
        d.open = true;
        run(inFlight ? cur : 0, body.scrollHeight, inFlight ? curO : 0, 1, function () {});
      } else {
        run(cur, 0, inFlight ? curO : 1, 0, function () { d.open = false; });
      }
    });
  });
})();

// Swipe pages (project gallery, pricing plans): the strip itself is native scroll-snap; this only mirrors its position
// into the "n / total" counter and a progress line that moves continuously with the finger (transform only, rAF-throttled).
document.querySelectorAll('[data-pager]').forEach(function (g) {
  var c = g.nextElementSibling;
  if (!c || !c.classList.contains('gcount')) return;
  var n = g.children.length, txt = c.querySelector('.gcn'), fill = c.querySelector('.gbar span');
  var idx = -1, queued = false;
  function update() {
    queued = false;
    var max = g.scrollWidth - g.clientWidth;
    if (max <= 0) return;                                          // desktop grid: nothing scrolls
    var p = Math.min(Math.max(g.scrollLeft / max, 0), 1);          // rubber-band overscroll clamps to 0..1
    fill.style.transform = 'scaleX(' + (1 / n + (1 - 1 / n) * p) + ')';
    var i = Math.min(n - 1, Math.round(p * (n - 1)));
    if (i !== idx) { idx = i; txt.textContent = (i + 1) + ' / ' + n; }
  }
  function queue() { if (!queued) { queued = true; requestAnimationFrame(update); } }
  g.addEventListener('scroll', queue, { passive: true });
  window.addEventListener('resize', queue);
  update();
});

// Floating Navbar (Aceternity): hide on scroll down, reveal on scroll up. Always visible near the top,
// while a nav link has focus, and when the user prefers reduced motion.
(function () {
  var bar = document.querySelector('.bar.site');
  if (!bar) return;
  var reduce = window.matchMedia('(prefers-reduced-motion: reduce)');
  var last = window.scrollY, queued = false;
  function update() {
    queued = false;
    var y = Math.max(window.scrollY, 0), d = y - last;             // ignore iOS rubber-band (negative) scroll
    if (reduce.matches || y < 40 || d < -6 || bar.contains(document.activeElement)) bar.classList.remove('away');
    else if (d > 6) bar.classList.add('away');
    if (Math.abs(d) > 6) last = y;
  }
  window.addEventListener('scroll', function () { if (!queued) { queued = true; requestAnimationFrame(update); } }, { passive: true });
  bar.addEventListener('focusin', function () { bar.classList.remove('away'); });
})();

// Text Generate Effect (Aceternity): [data-generate] text resolves word by word when it scrolls into view.
// Words are wrapped in place, so the text stays in the HTML for search engines and screen readers.
(function () {
  var els = document.querySelectorAll('[data-generate]');
  if (!els.length || !('IntersectionObserver' in window) || window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
  els.forEach(function (el) {
    var n = 0;
    (function wrap(node) {
      Array.prototype.slice.call(node.childNodes).forEach(function (c) {
        if (c.nodeType === 3) {
          var frag = document.createDocumentFragment();
          c.nodeValue.split(/(\s+)/).forEach(function (part) {
            if (!part) return;
            if (/^\s+$/.test(part)) { frag.appendChild(document.createTextNode(part)); return; }
            var s = document.createElement('span');
            s.className = 'w'; s.textContent = part;
            s.style.setProperty('--d', Math.min(n++ * 45, 1400) + 'ms');   // cap total stagger so long text never drags
            frag.appendChild(s);
          });
          node.replaceChild(frag, c);
        } else if (c.nodeType === 1 && c.tagName !== 'BR') wrap(c);
      });
    })(el);
    el.classList.add('gen');
  });
  var io = new IntersectionObserver(function (entries) {
    entries.forEach(function (en) { if (en.isIntersecting) { en.target.classList.add('in'); io.unobserve(en.target); } });
  }, { threshold: 0.15 });
  els.forEach(function (el) { io.observe(el); });
})();
