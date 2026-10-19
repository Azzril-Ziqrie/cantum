// Mock dashboard behaviour: tab switching, tips toggle, live banner preview, add-product row. Nothing is sent to the server.
(function () {
  var tabs = document.querySelectorAll('[data-tab]');
  var panels = document.querySelectorAll('[data-panel]');
  var title = document.getElementById('panelTitle');

  function show(key) {
    var found = false;
    panels.forEach(function (p) { if (p.dataset.panel === key) found = true; });
    if (!found) key = 'overview';
    panels.forEach(function (p) { p.hidden = p.dataset.panel !== key; });
    tabs.forEach(function (t) {
      var on = t.dataset.tab === key;
      t.classList.toggle('active', on);
      if (on) title.textContent = t.textContent;
    });
  }
  tabs.forEach(function (t) {
    t.addEventListener('click', function () { location.hash = t.dataset.tab; });
  });
  window.addEventListener('hashchange', function () { show(location.hash.slice(1)); });
  show(location.hash.slice(1));

  // Tips on/off, remembered per visitor
  var toggle = document.getElementById('tipsToggle');
  function applyTips(on) { document.body.classList.toggle('no-tips', !on); toggle.checked = on; }
  var saved = null;
  try { saved = localStorage.getItem('demoTips'); } catch (e) {}
  applyTips(saved !== 'off');
  toggle.addEventListener('change', function () {
    applyTips(toggle.checked);
    try { localStorage.setItem('demoTips', toggle.checked ? 'on' : 'off'); } catch (e) {}
  });

  // Forms that ask before they do something irreversible (delete demo)
  document.addEventListener('submit', function (ev) {
    var msg = ev.target.dataset.confirm;
    if (msg && !confirm(msg)) ev.preventDefault();
  });

  // Switches
  document.addEventListener('click', function (ev) {
    var sw = ev.target.closest('.switch');
    if (sw) sw.setAttribute('aria-checked', sw.getAttribute('aria-checked') === 'true' ? 'false' : 'true');
  });

  // Banner preview: the chosen image only lives in this browser tab
  var file = document.getElementById('heroFile');
  var preview = document.getElementById('heroPreview');
  var headline = document.getElementById('heroHeadline');
  var objectUrl = null;
  file.addEventListener('change', function () {
    var f = file.files[0];
    if (!f || f.type.indexOf('image/') !== 0) return;
    if (objectUrl) URL.revokeObjectURL(objectUrl);
    objectUrl = URL.createObjectURL(f);
    preview.style.backgroundImage = 'url("' + objectUrl + '")';
  });
  document.getElementById('heroInput').addEventListener('input', function (ev) {
    headline.textContent = ev.target.value;
  });

  // Add product: appends an editable sample row
  var body = document.querySelector('#productTable tbody');
  var count = document.getElementById('productCount');
  document.getElementById('addProduct').addEventListener('click', function () {
    var tr = document.createElement('tr');
    tr.innerHTML = '<td class="prod"><span class="thumb">+</span><span contenteditable="true">New product</span></td>' +
      '<td contenteditable="true">Rp 100.000</td><td>10</td>' +
      '<td><button type="button" class="switch" role="switch" aria-checked="true" aria-label="Show new product in the store"></button></td>';
    body.insertBefore(tr, body.firstChild);
    count.textContent = body.rows.length;
    tr.querySelector('[contenteditable]').focus();
  });
})();
