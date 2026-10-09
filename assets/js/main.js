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
