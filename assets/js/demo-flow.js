// "Demo your website" pop-up: walks through brand name, logo, products and layout, one glass card per step, then
// submits the form. Return advances, Escape or a tap outside dismisses, and every transition can be interrupted.
(function () {
  var dlg = document.getElementById('demoFlow');
  if (!dlg) return;
  var form = dlg.querySelector('form');
  var steps = Array.prototype.slice.call(dlg.querySelectorAll('.step'));
  var bars = dlg.querySelectorAll('.flow-progress i');
  var back = dlg.querySelector('[data-flow-back]');
  var skip = dlg.querySelector('[data-flow-skip]');
  var next = dlg.querySelector('[data-flow-next]');
  var errBox = dlg.querySelector('.flow-error');
  var brand = form.elements.brand;
  var logoInput = form.elements.logo;
  var prodInput = form.elements['products[]'];
  var reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  var MAX_FILE = 5 * 1024 * 1024, MAX_PRODUCTS = 6, TYPES = ['image/jpeg', 'image/png'];
  var index = 0, moving = false, submitted = false, urls = [];

  // Browsers without <dialog>: show the card in the page, the form still works.
  if (typeof dlg.showModal !== 'function') { dlg.setAttribute('open', ''); dlg.classList.add('is-open'); }

  function say(msg) {
    errBox.textContent = msg || '';
    errBox.hidden = !msg;
    if (msg && !reduce) {
      dlg.classList.remove('is-shake');
      void dlg.offsetWidth;   // restart the animation
      dlg.classList.add('is-shake');
    }
  }

  function drawChrome() {
    for (var i = 0; i < bars.length; i++) bars[i].classList.toggle('is-done', i <= index);
    back.hidden = index === 0;
    skip.hidden = !(index === 1 || index === 2);
    next.textContent = index === steps.length - 1 ? dlg.dataset.submit : dlg.dataset.next;
  }

  function focusStep(step) {
    var el = step.querySelector('[data-focus]');
    if (el) el.focus({ preventScroll: true });
  }

  // Leaves the current card the way it came in and brings the next one in from the direction of travel.
  function go(to, dir) {
    if (moving || to < 0 || to >= steps.length || to === index) return;
    var from = steps[index], target = steps[to];
    index = to;
    say('');
    drawChrome();
    if (reduce || !from.animate) { from.hidden = true; target.hidden = false; focusStep(target); return; }
    moving = true;
    from.animate(
      [{ opacity: 1, transform: 'none', filter: 'blur(0)' }, { opacity: 0, transform: 'translateX(' + (-dir * 28) + 'px) scale(.97)', filter: 'blur(6px)' }],
      { duration: 180, easing: 'cubic-bezier(.4, 0, 1, 1)', fill: 'forwards' }
    ).finished.then(function () {
      from.hidden = true;
      target.hidden = false;
      target.animate(
        [{ opacity: 0, transform: 'translateX(' + (dir * 28) + 'px) scale(.97)', filter: 'blur(8px)' }, { opacity: 1, transform: 'none', filter: 'blur(0)' }],
        { duration: 480, easing: 'cubic-bezier(.32, .72, 0, 1)' }
      );
      focusStep(target);
      moving = false;
    }).catch(function () { moving = false; });
  }

  function badFile(file) { return TYPES.indexOf(file.type) === -1 || file.size > MAX_FILE; }

  function validate() {
    if (index === 0) {
      var len = brand.value.trim().length;
      if (len < 2 || len > 40) { say(dlg.dataset.errBrand); brand.focus(); return false; }
    }
    if (index === 1 && logoInput.files[0] && badFile(logoInput.files[0])) {
      say(logoInput.files[0].name + ' ' + dlg.dataset.errFile); clearLogo(); return false;
    }
    if (index === 2) {
      var files = Array.prototype.slice.call(prodInput.files);
      if (files.length > MAX_PRODUCTS) { say(dlg.dataset.errMany); return false; }
      for (var i = 0; i < files.length; i++) if (badFile(files[i])) { say(files[i].name + ' ' + dlg.dataset.errFile); return false; }
    }
    return true;
  }

  function advance() {
    if (moving || submitted || !validate()) return;
    if (index < steps.length - 1) { go(index + 1, 1); return; }
    submitted = true;
    next.disabled = true;
    next.textContent = dlg.dataset.submitting;
    form.submit();
  }

  // ── logo ──
  var logoBox = document.getElementById('logoPreview');
  function clearLogo() {
    logoInput.value = '';
    logoBox.hidden = true;
  }
  function showLogo() {
    var f = logoInput.files[0];
    if (!f) { logoBox.hidden = true; return; }
    if (badFile(f)) { say(f.name + ' ' + dlg.dataset.errFile); clearLogo(); return; }
    say('');
    var url = URL.createObjectURL(f);
    urls.push(url);
    logoBox.querySelector('img').src = url;
    logoBox.querySelector('.flow-file-name').textContent = f.name;
    logoBox.hidden = false;
  }
  logoInput.addEventListener('change', showLogo);
  dlg.querySelector('[data-remove-logo]').addEventListener('click', clearLogo);

  // ── products: new picks are added to the ones already chosen ──
  var thumbs = document.getElementById('productThumbs');
  var chosen = [];
  function syncProducts() {
    if (window.DataTransfer) {
      var dt = new DataTransfer();
      chosen.forEach(function (f) { dt.items.add(f); });
      prodInput.files = dt.files;
    }
    thumbs.textContent = '';
    chosen.forEach(function (f, i) {
      var url = URL.createObjectURL(f);
      urls.push(url);
      var cell = document.createElement('div'); cell.className = 'flow-thumb';
      var img = document.createElement('img'); img.src = url; img.alt = '';
      var rm = document.createElement('button'); rm.type = 'button'; rm.textContent = '×'; rm.setAttribute('aria-label', dlg.dataset.remove + ' ' + f.name);
      rm.addEventListener('click', function () { chosen.splice(i, 1); syncProducts(); });
      cell.append(img, rm);
      thumbs.appendChild(cell);
    });
    thumbs.hidden = !chosen.length;
  }
  function addProducts(list) {
    var incoming = Array.prototype.slice.call(list);
    for (var i = 0; i < incoming.length; i++) {
      if (badFile(incoming[i])) { say(incoming[i].name + ' ' + dlg.dataset.errFile); return; }
    }
    if (chosen.length + incoming.length > MAX_PRODUCTS) { say(dlg.dataset.errMany); incoming = incoming.slice(0, MAX_PRODUCTS - chosen.length); } else { say(''); }
    chosen = chosen.concat(incoming);
    syncProducts();
  }
  prodInput.addEventListener('change', function () { var picked = Array.prototype.slice.call(prodInput.files); chosen = chosen.filter(function (f) { return picked.indexOf(f) === -1; }); addProducts(picked); });

  // ── drag and drop ──
  Array.prototype.forEach.call(dlg.querySelectorAll('[data-drop]'), function (zone) {
    ['dragenter', 'dragover'].forEach(function (t) { zone.addEventListener(t, function (e) { e.preventDefault(); zone.classList.add('is-over'); }); });
    ['dragleave', 'drop'].forEach(function (t) { zone.addEventListener(t, function () { zone.classList.remove('is-over'); }); });
    zone.addEventListener('drop', function (e) {
      e.preventDefault();
      var files = e.dataTransfer && e.dataTransfer.files;
      if (!files || !files.length) return;
      if (zone.dataset.drop === 'logo') { logoInput.files = files; showLogo(); } else { addProducts(files); }
    });
  });

  // ── layout choice ──
  Array.prototype.forEach.call(dlg.querySelectorAll('.flow-layout input'), function (radio) {
    radio.addEventListener('change', function () {
      Array.prototype.forEach.call(dlg.querySelectorAll('.flow-layout'), function (l) { l.classList.toggle('is-on', l.contains(radio) && radio.checked); });
    });
  });

  // ── open / close ──
  function open() {
    if (typeof dlg.showModal === 'function' && !dlg.open) dlg.showModal();
    document.documentElement.classList.add('flow-open');
    requestAnimationFrame(function () { requestAnimationFrame(function () { dlg.classList.add('is-open'); }); });
    drawChrome();
    focusStep(steps[index]);
  }
  function close() {
    dlg.classList.remove('is-open');
    document.documentElement.classList.remove('flow-open');
    setTimeout(function () { if (dlg.open && typeof dlg.close === 'function') dlg.close(); }, reduce ? 0 : 340);
    urls.forEach(function (u) { URL.revokeObjectURL(u); });
    urls = [];
  }

  document.addEventListener('click', function (e) {
    var opener = e.target.closest('[data-demo-open]');
    if (opener) { e.preventDefault(); open(); return; }
    if (e.target === dlg || e.target.closest('[data-flow-close]')) close();
  });
  dlg.addEventListener('cancel', function (e) { e.preventDefault(); close(); });
  next.addEventListener('click', advance);
  back.addEventListener('click', function () { go(index - 1, -1); });
  skip.addEventListener('click', function () {
    if (index === 1) clearLogo(); else { chosen = []; syncProducts(); }
    go(index + 1, 1);
  });
  dlg.addEventListener('keydown', function (e) {
    if (e.key !== 'Enter' || e.isComposing) return;
    var t = e.target;
    if (t === brand || (t.type === 'radio') || t === dlg) { e.preventDefault(); advance(); }
  });
  form.addEventListener('submit', function (e) { if (!submitted) e.preventDefault(); });

  if (dlg.dataset.autoopen === '1') open();
})();
