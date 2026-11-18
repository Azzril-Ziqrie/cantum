// Solutions menu in the Cantum OnePay nav: click to open, Escape / outside click / choosing an entry to close.
(function () {
  var box = document.querySelector('.cd-drop');
  if (!box) return;
  var btn = box.querySelector('.cd-drop-btn'), panel = box.querySelector('.cd-drop-panel');
  var out = null;
  function set(open) {
    clearTimeout(out);
    btn.setAttribute('aria-expanded', open ? 'true' : 'false');
    if (open) { panel.hidden = false; requestAnimationFrame(function () { box.classList.add('is-open'); }); }
    else { box.classList.remove('is-open'); out = setTimeout(function () { panel.hidden = true; }, 220); }
  }
  btn.addEventListener('click', function () { set(btn.getAttribute('aria-expanded') !== 'true'); });
  document.addEventListener('pointerdown', function (e) { if (!box.contains(e.target)) set(false); });
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && btn.getAttribute('aria-expanded') === 'true') { set(false); btn.focus(); } });
  panel.addEventListener('click', function (e) { if (e.target.closest('a')) set(false); });
})();
