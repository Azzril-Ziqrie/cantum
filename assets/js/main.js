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

// Project gallery (mobile): the strip itself is native scroll-snap; this only mirrors its position into the
// "n / total" counter and a progress line that moves continuously with the finger (transform only, rAF-throttled).
(function () {
  var g = document.querySelector('.gallery'), c = document.querySelector('.gcount');
  if (!g || !c) return;
  var figs = g.querySelectorAll('figure'), n = figs.length;
  var txt = c.querySelector('.gcn'), fill = c.querySelector('.gbar span');
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
})();
