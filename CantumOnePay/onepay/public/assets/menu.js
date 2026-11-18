/* Menu pelanggan: keranjang, pilihan modifier, dan pembuatan pesanan. Harga di sini hanya tampilan; server menghitung ulang. */
(function () {
  'use strict';
  const config = window.ONEPAY;
  const itemsById = new Map(config.menu.map((item) => [item.id, item]));
  const cart = []; // {item, modifiers: [{id,name,price_delta}], qty, note}

  const el = (id) => document.getElementById(id);
  const sheet = el('sheet'), sheetBody = el('sheetBody'), sheetTitle = el('sheetTitle');
  const cartbar = el('cartbar');

  const rupiah = (n) => 'Rp ' + Math.round(n).toLocaleString('id-ID');

  function h(tag, attrs = {}, ...children) {
    const node = document.createElement(tag);
    for (const [key, value] of Object.entries(attrs)) {
      if (key === 'class') node.className = value;
      else if (key.startsWith('on')) node.addEventListener(key.slice(2), value);
      else node.setAttribute(key, value);
    }
    for (const child of children.flat()) node.append(child instanceof Node ? child : document.createTextNode(String(child)));
    return node;
  }

  const unitPrice = (line) => Number(line.item.price) + line.modifiers.reduce((sum, m) => sum + m.price_delta, 0);
  const subtotal = () => cart.reduce((sum, line) => sum + unitPrice(line) * line.qty, 0);

  /* Promo: potongan datang dari server (POST /promo/check). Di sini hanya ditampilkan; server menghitung ulang saat pesan. */
  const promo = { code: '', applied: false, discount: 0, label: '' };
  let quoteTimer = null;

  function totals() {
    const base = subtotal();
    const discount = Math.min(promo.discount, base);
    const service = Math.round((base - discount) * config.service / 100);
    const tax = Math.round((base - discount + service) * config.tax / 100);
    const exact = base - discount + service + tax;
    const total = Math.round(exact / config.rounding) * config.rounding;
    return { base, discount, service, tax, total };
  }

  const cartPayload = () => cart.map((line) => ({ item_id: line.item.id, qty: line.qty, note: line.note, modifier_ids: line.modifiers.map((m) => m.id) }));
  const csrfToken = () => document.querySelector('meta[name="csrf-token"]').content;

  async function requestQuote(code) {
    const response = await fetch(config.promoUrl, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrfToken() },
      body: JSON.stringify({ token: config.token, promo_code: code, cart: cartPayload() }),
    });
    const data = await response.json();
    if (!response.ok) throw new Error(data.error || 'Promo tidak bisa dipakai.');
    return data;
  }

  function scheduleQuote() {
    clearTimeout(quoteTimer);
    quoteTimer = setTimeout(refreshQuote, 250);
  }

  async function refreshQuote() {
    if (cart.length === 0) { promo.discount = 0; promo.label = ''; return; }
    try {
      const data = await requestQuote(promo.applied ? promo.code : '');
      promo.discount = data.discount;
      promo.label = data.label || '';
    } catch (error) {
      promo.discount = 0;
      promo.label = '';
      promo.applied = false; // kode yang tadinya sah kini tidak memenuhi syarat; server akan menolaknya juga
      setPromoNote(error.message, false);
    }
    showTotals();
  }

  async function applyCode(input) {
    const code = input.value.trim();
    promo.applied = false;
    if (code === '') { setPromoNote('', true); return refreshQuote(); }
    try {
      const data = await requestQuote(code);
      Object.assign(promo, { code, applied: true, discount: data.discount, label: data.label || '' });
      setPromoNote(data.discount > 0 ? 'Promo terpasang: ' + data.label : 'Kode dikenali, belum ada potongan.', true);
    } catch (error) {
      setPromoNote(error.message, false);
      await refreshQuote();
    }
    showTotals();
  }

  function setPromoNote(text, ok) {
    const note = el('promoNote');
    if (!note) return;
    note.textContent = text;
    note.className = 'promo-note ' + (ok ? 'promo-note--ok' : 'promo-note--bad');
  }

  function summaryNode() {
    const t = totals();
    return [
      row('Subtotal', rupiah(t.base)),
      t.discount ? row('Diskon' + (promo.label ? ' · ' + promo.label : ''), '− ' + rupiah(t.discount)) : '',
      t.service ? row('Service', rupiah(t.service)) : '',
      t.tax ? row('Pajak', rupiah(t.tax)) : '',
      h('div', { class: 'row' }, h('b', {}, 'Total'), h('b', {}, rupiah(t.total))),
      deliveryNote(t.total),
    ];
  }

  /** Di atas ambang, Kasera mewajibkan ketentuan layanan ditampilkan sebelum pelanggan membayar. */
  function deliveryNote(total) {
    const terms = config.deliveryTerms;
    return terms && total > terms.threshold ? h('p', { class: 'notice', role: 'note' }, terms.text) : '';
  }

  /** Perbarui tampilan total (bar keranjang, ringkasan, tombol bayar) tanpa membangun ulang seluruh keranjang. */
  function showTotals() {
    el('cartTotal').textContent = rupiah(totals().total);
    const summary = el('summary');
    if (summary) summary.replaceChildren(...summaryNode());
    const pay = el('payButton');
    if (pay) pay.textContent = 'Bayar ' + rupiah(totals().total);
  }

  function refreshCartbar() {
    const count = cart.reduce((sum, line) => sum + line.qty, 0);
    el('cartCount').textContent = count;
    el('cartTotal').textContent = rupiah(totals().total);
    cartbar.hidden = count === 0;
    scheduleQuote();
  }

  const openSheet = () => { sheet.hidden = false; };
  const closeSheet = () => { sheet.hidden = true; };

  /* ---- pilih modifier ---- */
  function addItem(item) {
    if (item.groups.length === 0) return pushLine(item, []);
    openModifierPicker(item);
  }

  function pushLine(item, modifiers) {
    const key = item.id + ':' + modifiers.map((m) => m.id).sort().join(',');
    const same = cart.find((line) => line.key === key && !line.note);
    if (same) same.qty += 1;
    else cart.push({ key, item, modifiers, qty: 1, note: '' });
    refreshCartbar();
  }

  function openModifierPicker(item) {
    sheetTitle.textContent = item.name;
    const chosen = new Map();
    const confirm = h('button', { class: 'btn btn--block', type: 'button' }, 'Tambah ke pesanan');
    const requiredMissing = () => item.groups.some((g) => g.required && !chosen.has(g.id));
    const sync = () => { confirm.disabled = requiredMissing(); };

    const groups = item.groups.map((group) => h('fieldset', { class: 'stack', style: 'border:0' },
      h('legend', { class: 'label' }, group.name + (group.required ? ' · wajib' : '')),
      group.options.map((option) => h('label', { class: 'opt' },
        h('span', {}, option.name + (option.price_delta ? ' (+' + rupiah(option.price_delta) + ')' : '')),
        h('input', { type: 'radio', name: 'g' + group.id, onchange: () => { chosen.set(group.id, option); sync(); } })
      ))
    ));
    confirm.addEventListener('click', () => { pushLine(item, [...chosen.values()]); closeSheet(); });
    sheetBody.replaceChildren(...groups, confirm);
    sync();
    openSheet();
  }

  /* ---- keranjang dan checkout ---- */
  function renderCart() {
    sheetTitle.textContent = 'Pesanan';
    const t = totals();
    const lines = cart.map((line, index) => h('div', { class: 'card stack' },
      h('div', { class: 'row' },
        h('div', {}, h('b', {}, line.item.name), h('div', { class: 'muted' }, line.modifiers.map((m) => m.name).join(', '))),
        h('span', { class: 'num' }, rupiah(unitPrice(line) * line.qty))),
      h('div', { class: 'row' },
        h('div', { class: 'qty' },
          h('button', { type: 'button', 'aria-label': 'Kurangi', onclick: () => changeQty(index, -1) }, '−'),
          h('span', { class: 'num' }, line.qty),
          h('button', { type: 'button', 'aria-label': 'Tambah', onclick: () => changeQty(index, 1) }, '+')),
        h('input', { class: 'note', 'aria-label': 'Catatan untuk ' + line.item.name, placeholder: 'Catatan (opsional)', maxlength: 200, value: line.note,
          oninput: (event) => { line.note = event.target.value; } }))
    ));

    const methodNames = Object.keys(config.methods);
    const methods = h('div', { class: 'methods' }, methodNames.map((code, i) => h('label', {},
      h('input', Object.assign({ type: 'radio', name: 'method', value: code }, i === 0 ? { checked: '' } : {})),
      h('span', {}, config.methods[code].label))));

    const summary = h('div', { class: 'card stack num', id: 'summary' }, ...summaryNode());

    const codeInput = h('input', { id: 'promoCode', 'aria-label': 'Kode promo', maxlength: 40, placeholder: 'Kode promo (opsional)', value: promo.code, autocomplete: 'off' });
    const promoRow = h('div', { class: 'stack' },
      h('div', { class: 'promo-code' }, codeInput, h('button', { class: 'btn btn--light', type: 'button', onclick: () => applyCode(codeInput) }, 'Pakai')),
      h('p', { id: 'promoNote', class: 'promo-note', role: 'status' }));

    const error = h('p', { class: 'notice notice--warn', hidden: '', id: 'checkoutError', role: 'alert' });
    const pay = h('button', { class: 'btn btn--block', type: 'button', id: 'payButton' }, 'Bayar ' + rupiah(t.total));
    pay.disabled = !config.isOpen;
    pay.addEventListener('click', () => submitOrder(pay, error));

    sheetBody.replaceChildren(...lines, promoRow, summary,
      h('label', { class: 'field' }, h('span', { class: 'label' }, 'Nama (opsional)'), h('input', { id: 'custName', maxlength: 80, autocomplete: 'name' })),
      h('label', { class: 'field' }, h('span', { class: 'label' }, 'No. HP (opsional)'), h('input', { id: 'custPhone', inputmode: 'tel', maxlength: 30, autocomplete: 'tel' })),
      h('span', { class: 'label' }, 'Metode pembayaran'), methods,
      config.isOpen ? '' : h('p', { class: 'notice notice--warn', role: 'status' }, (config.closedMessage || 'Cabang sedang tutup.') + ' Pesanan belum bisa dibayar.'),
      error, pay);
  }

  const row = (name, value) => h('div', { class: 'row' }, h('span', { class: 'muted' }, name), h('span', {}, value));

  function changeQty(index, delta) {
    cart[index].qty += delta;
    if (cart[index].qty <= 0) cart.splice(index, 1);
    refreshCartbar();
    if (cart.length === 0) return closeSheet();
    renderCart();
  }

  async function submitOrder(button, errorBox) {
    button.disabled = true; // cegah pesanan ganda
    errorBox.hidden = true;
    const payload = {
      token: config.token,
      name: el('custName').value,
      phone: el('custPhone').value,
      method: document.querySelector('input[name="method"]:checked').value,
      promo_code: promo.applied ? promo.code : '',
      cart: cartPayload(),
    };
    try {
      const response = await fetch(config.endpoint, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
        body: JSON.stringify(payload),
      });
      const data = await response.json();
      if (!response.ok) throw new Error(data.error || 'Gagal membuat pesanan.');
      window.location.href = data.redirect;
    } catch (error) {
      errorBox.textContent = error.message;
      errorBox.hidden = false;
      button.disabled = false;
    }
  }

  document.querySelectorAll('.add').forEach((button) =>
    button.addEventListener('click', () => addItem(itemsById.get(Number(button.dataset.id)))));
  el('openCart').addEventListener('click', () => { renderCart(); openSheet(); });
  el('closeSheet').addEventListener('click', closeSheet);
  sheet.addEventListener('click', (event) => { if (event.target === sheet) closeSheet(); });
  document.addEventListener('keydown', (event) => { if (event.key === 'Escape') closeSheet(); });
  window.addEventListener('pageshow', (event) => { if (event.persisted) { const b = el('payButton'); if (b) b.disabled = false; } });
})();
