// Demo storefront behaviour shared by all layouts: panels, working cart, feature hotspots, toast, nav/hero motion.
// Nothing is sent to the server; the cart lives in sessionStorage for this tab only.
(function () {
  var root = document.documentElement;
  var body = document.body;
  var reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  function $(id) { return document.getElementById(id); }
  function each(sel, fn) { Array.prototype.forEach.call(document.querySelectorAll(sel), fn); }

  // ── toast ──
  var toast = $('demoToast');
  var toastTimer = null;
  function say(msg) {
    toast.textContent = msg;
    toast.classList.add('show');
    clearTimeout(toastTimer);
    toastTimer = setTimeout(function () { toast.classList.remove('show'); }, 2600);
  }

  // ── feature tips on/off, remembered per visitor ──
  var tipsToggle = $('tipsToggle');
  function applyTips(on) { root.classList.toggle('no-tips', !on); if (tipsToggle) tipsToggle.checked = on; }
  var savedTips = null;
  try { savedTips = localStorage.getItem('demoTips'); } catch (e) {}
  applyTips(savedTips !== 'off');
  if (tipsToggle) tipsToggle.addEventListener('change', function () {
    applyTips(tipsToggle.checked);
    if (!tipsToggle.checked) closePop();
    try { localStorage.setItem('demoTips', tipsToggle.checked ? 'on' : 'off'); } catch (e) {}
  });

  // ── hotspot popovers ──
  var tips = {};
  try { tips = JSON.parse($('demoTips').textContent); } catch (e) {}
  var pop = null, current = null;
  function closePop() { if (pop) pop.remove(); pop = current = null; }
  function openPop(dot) {
    var t = tips[dot.dataset.tip];
    if (!t) return;
    closePop();
    pop = document.createElement('div');
    pop.className = 'tip-pop';
    pop.setAttribute('role', 'dialog');
    var title = document.createElement('strong'); title.textContent = t.title;
    var text = document.createElement('p'); text.textContent = t.text;
    var link = document.createElement('a'); link.href = t.href; link.textContent = 'See the dashboard →';
    pop.append(title, text, link);
    body.appendChild(pop);
    var r = dot.getBoundingClientRect();
    var left = r.left + window.scrollX + r.width / 2 - pop.offsetWidth / 2;
    left = Math.max(8, Math.min(left, window.scrollX + root.clientWidth - pop.offsetWidth - 8));
    var below = r.bottom + pop.offsetHeight + 14 < window.innerHeight;
    pop.style.left = left + 'px';
    pop.style.top = (below ? r.bottom + window.scrollY + 10 : r.top + window.scrollY - pop.offsetHeight - 10) + 'px';
    // grow out of the dot it belongs to, not from the popover's own center
    pop.style.transformOrigin = (r.left + window.scrollX + r.width / 2 - left) + 'px ' + (below ? '-10px' : (pop.offsetHeight + 10) + 'px');
    current = dot;
  }

  // ── menu + cart panels ──
  var overlay = $('overlay'), menuPanel = $('menuPanel'), cartPanel = $('cartPanel');
  function closePanels() {
    [menuPanel, cartPanel].forEach(function (p) { p.classList.remove('active'); p.setAttribute('aria-hidden', 'true'); });
    overlay.classList.remove('active');
    body.classList.remove('lock-scroll');
  }
  function openPanel(panel) {
    closePanels();
    closePop();
    panel.classList.add('active');
    panel.setAttribute('aria-hidden', 'false');
    overlay.classList.add('active');
    body.classList.add('lock-scroll');
  }
  overlay.addEventListener('click', closePanels);

  // ── cart ──
  var items = [];
  try { items = JSON.parse(sessionStorage.getItem('demoCartItems')) || []; } catch (e) {}
  function rp(n) { return 'Rp ' + Math.round(n).toLocaleString('id-ID'); }
  function saveCart() { try { sessionStorage.setItem('demoCartItems', JSON.stringify(items)); } catch (e) {} }
  function renderCart() {
    var count = 0, total = 0;
    items.forEach(function (it) { count += it.qty; total += it.qty * it.price; });
    each('[data-cart-count]', function (el) { el.textContent = count; });
    var bodyEl = $('cartBody');
    bodyEl.textContent = '';
    if (!items.length) {
      var empty = document.createElement('div');
      empty.className = 'cart-empty';
      var msg = document.createElement('span'); msg.textContent = 'Your cart is empty';
      var link = document.createElement('a'); link.className = 'link-u'; link.href = '#'; link.textContent = 'Continue shopping';
      link.setAttribute('data-close-panel', '');
      empty.append(msg, link);
      bodyEl.appendChild(empty);
    }
    items.forEach(function (it, i) {
      var row = document.createElement('div'); row.className = 'cart-item';
      var thumb = document.createElement('div'); thumb.className = 'cart-item-img';
      if (it.img) { var img = document.createElement('img'); img.src = it.img; img.alt = ''; thumb.appendChild(img); }
      else { var ph = document.createElement('div'); ph.className = 'ph'; var sp = document.createElement('span'); sp.textContent = it.name.charAt(0).toUpperCase(); ph.appendChild(sp); thumb.appendChild(ph); }
      var info = document.createElement('div');
      var nm = document.createElement('div'); nm.className = 'cart-item-name'; nm.textContent = it.name;
      var pr = document.createElement('div'); pr.className = 'cart-item-price'; pr.textContent = rp(it.price);
      var qty = document.createElement('div'); qty.className = 'cart-item-qty';
      var minus = document.createElement('button'); minus.type = 'button'; minus.textContent = '−'; minus.setAttribute('aria-label', 'Decrease'); minus.dataset.cartStep = '-1'; minus.dataset.i = i;
      var num = document.createElement('span'); num.textContent = it.qty;
      var plus = document.createElement('button'); plus.type = 'button'; plus.textContent = '+'; plus.setAttribute('aria-label', 'Increase'); plus.dataset.cartStep = '1'; plus.dataset.i = i;
      qty.append(minus, num, plus);
      info.append(nm, pr, qty);
      var rm = document.createElement('button'); rm.type = 'button'; rm.className = 'cart-item-remove'; rm.textContent = '×'; rm.setAttribute('aria-label', 'Remove'); rm.dataset.cartRemove = i;
      row.append(thumb, info, rm);
      bodyEl.appendChild(row);
    });
    $('cartFoot').hidden = !items.length;
    $('cartTotal').textContent = rp(total);
  }
  function addToCart(btn, qty) {
    var n = parseInt(btn.dataset.n, 10);
    var found = items.filter(function (it) { return it.n === n; })[0];
    if (found) found.qty += qty;
    else items.push({ n: n, name: btn.dataset.add, price: parseInt(btn.dataset.price, 10), img: btn.dataset.img, qty: qty });
    saveCart();
    renderCart();
    if (btn.classList.contains('btn')) {   // brief confirmation on the main button, like the client shop
      var label = btn.dataset.label || btn.textContent;
      btn.dataset.label = label;
      btn.textContent = 'Added ✓';
      clearTimeout(btn._t);
      btn._t = setTimeout(function () { btn.textContent = label; }, 1400);
    }
    openPanel(cartPanel);
  }
  renderCart();

  // ── clicks ──
  document.addEventListener('click', function (ev) {
    var t = ev.target;

    var dot = t.closest('.tip-dot');
    if (dot) { ev.preventDefault(); ev.stopPropagation(); if (current === dot) closePop(); else openPop(dot); return; }
    if (!t.closest('.tip-pop')) closePop();

    var open = t.closest('[data-open]');
    if (open) { ev.preventDefault(); openPanel(open.dataset.open === 'cart' ? cartPanel : menuPanel); return; }
    if (t.closest('[data-close-panel]')) { ev.preventDefault(); closePanels(); return; }

    var toggle = t.closest('.dropdown-toggle');
    if (toggle) {
      var dd = toggle.parentElement;
      dd.classList.toggle('open');
      toggle.setAttribute('aria-expanded', dd.classList.contains('open'));
      return;
    }

    var add = t.closest('[data-add]');
    if (add) {
      ev.preventDefault();
      var qtyInput = document.querySelector('[data-qty-input]');
      addToCart(add, qtyInput ? Math.max(1, parseInt(qtyInput.value, 10) || 1) : 1);
      return;
    }
    var step = t.closest('[data-step]');
    if (step) {
      var input = step.parentElement.querySelector('[data-qty-input]');
      input.value = Math.max(1, (parseInt(input.value, 10) || 1) + parseInt(step.dataset.step, 10));
      return;
    }
    var cs = t.closest('[data-cart-step]');
    if (cs) {
      var idx = parseInt(cs.dataset.i, 10);
      items[idx].qty += parseInt(cs.dataset.cartStep, 10);
      if (items[idx].qty < 1) items.splice(idx, 1);
      saveCart(); renderCart();
      return;
    }
    var size = t.closest('.size-btn');
    if (size) {
      each('.size-btn', function (b) { b.classList.remove('active'); b.setAttribute('aria-pressed', 'false'); });
      size.classList.add('active');
      size.setAttribute('aria-pressed', 'true');
      return;
    }
    var rm = t.closest('[data-cart-remove]');
    if (rm) { items.splice(parseInt(rm.dataset.cartRemove, 10), 1); saveCart(); renderCart(); return; }
    if (t.closest('[data-checkout]')) { ev.preventDefault(); say('In a live store this opens payment: QRIS, bank transfer or e-wallet'); return; }
    if (t.closest('[data-soon]')) { ev.preventDefault(); say('This page is part of your live store'); return; }

    each('.dd[open]', function (d) { if (!d.contains(t)) d.removeAttribute('open'); });
  });
  document.addEventListener('keydown', function (ev) { if (ev.key === 'Escape') { closePop(); closePanels(); } });

  // pretend forms (search, newsletter) and the delete-demo confirmation
  document.addEventListener('submit', function (ev) {
    var form = ev.target;
    if (form.dataset.confirm) { if (!confirm(form.dataset.confirm)) ev.preventDefault(); }
    else if (form.hasAttribute('data-demo-form')) { ev.preventDefault(); say(form.dataset.demoForm); }
  });
  document.addEventListener('change', function (ev) {
    if (ev.target.matches('[data-demo-select]')) say('Sorting works in your live store');
  });

  // ── scroll reveal ──
  if (!reduce && 'IntersectionObserver' in window) {
    root.classList.add('js');
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (en) { if (en.isIntersecting) { en.target.classList.add('in'); io.unobserve(en.target); } });
    }, { rootMargin: '0px 0px -6% 0px', threshold: 0.05 });
    each('.rv', function (el, i) { el.style.transitionDelay = (i % 4) * 60 + 'ms'; io.observe(el); });
    // safety net: anything already on screen is shown even if the observer never reports it
    setTimeout(function () {
      each('.rv:not(.in)', function (el) { if (el.getBoundingClientRect().top < window.innerHeight) el.classList.add('in'); });
    }, 1200);
  }

  // ── nav turns solid after the hero; hero photo grows and the brand lifts while scrolling ──
  var nav = document.querySelector('[data-nav]');
  var hero = document.querySelector('.hero');
  var heroMedia = document.querySelector('.hero-media');
  var heroBrand = document.querySelector('.hero-brand');
  var ticking = false;
  var wa = document.querySelector('.wa-pill');
  function onScroll() {
    ticking = false;
    var y = window.scrollY;
    if (wa) wa.classList.toggle('show', y > 240);
    if (nav) nav.classList.toggle('is-solid', !hero || hero.classList.contains('is-plain') || y > hero.offsetHeight - 90);
    if (hero && !reduce && y < hero.offsetHeight) {
      var p = y / hero.offsetHeight;
      if (heroMedia) heroMedia.style.transform = 'scale(' + (1 + p * 0.1) + ')';
      if (heroBrand) { heroBrand.style.transform = 'translateY(' + (-60 * Math.min(1, p / 0.7)) + 'px)'; heroBrand.style.opacity = 1 - Math.min(1, p / 0.7); }
    }
  }
  window.addEventListener('scroll', function () { if (!ticking) { ticking = true; requestAnimationFrame(onScroll); } }, { passive: true });
  onScroll();
})();
