/* Layar dapur/bar: tiket per pesanan terbayar, item ditandai "sedang dibuat" lalu "siap". Polling 3 detik. */
(function () {
  'use strict';
  const POLL_MS = 3000;
  const FAILURES_BEFORE_OFFLINE = 2;
  const LATE_AFTER_SECONDS = 15 * 60;

  const config = window.KITCHEN;
  const state = { branch: config.branch, data: null };
  let knownIds = null; // null = polling pertama: jangan bunyi untuk tiket yang sudah ada
  let failures = 0;
  let audio = null;

  const $ = (id) => document.getElementById(id);
  const minutes = (seconds) => Math.floor(seconds / 60) + ' mnt';
  const csrf = () => document.querySelector('meta[name="csrf-token"]').content;

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

  /* ---- suara ---- */
  function enableSound() {
    audio = audio || new (window.AudioContext || window.webkitAudioContext)();
    audio.resume();
    $('soundBtn').textContent = 'Suara aktif';
    beep();
  }

  function beep() {
    if (!audio) return;
    const now = audio.currentTime;
    [880, 1175].forEach((frequency, i) => {
      const osc = audio.createOscillator();
      const gain = audio.createGain();
      osc.frequency.value = frequency;
      gain.gain.setValueAtTime(0.0001, now + i * 0.18);
      gain.gain.exponentialRampToValueAtTime(0.25, now + i * 0.18 + 0.02);
      gain.gain.exponentialRampToValueAtTime(0.0001, now + i * 0.18 + 0.16);
      osc.connect(gain).connect(audio.destination);
      osc.start(now + i * 0.18);
      osc.stop(now + i * 0.18 + 0.18);
    });
  }

  /* ---- jaringan ---- */
  async function post(url, data) {
    const response = await fetch(url, { method: 'POST', headers: { 'X-CSRF-TOKEN': csrf(), Accept: 'application/json' }, body: new URLSearchParams(data) });
    const result = await response.json();
    if (!response.ok) throw new Error(result.error || result.message || 'Gagal.');
    return result;
  }

  async function act(url, data) {
    try { await post(url, data); await poll(); } catch (error) { OnePayUI.toast(error.message, { type: 'error' }); }
  }

  /* ---- tiket ---- */
  const NEXT = { queued: ['making', 'Mulai'], making: ['ready', 'Siap'], ready: ['making', 'Batal siap'] };

  function lineRow(line) {
    const [target, label] = NEXT[line.prep_status];
    return h('li', { class: 'ticket-line' + (line.prep_status === 'ready' ? ' is-ready' : '') },
      h('div', {},
        h('span', { class: 'what' }, line.qty + '× ' + line.name),
        line.modifiers.length ? h('small', {}, line.modifiers.join(', ')) : '',
        line.note ? h('small', {}, 'Catatan: ' + line.note) : ''),
      h('button', { class: 'prep-btn prep-btn--' + line.prep_status, type: 'button',
        onclick: () => act(config.prepBase + '/' + line.id + '/prep', { status: target }) }, label));
  }

  function ticket(order, isNew) {
    const late = order.waiting_seconds > LATE_AFTER_SECONDS;
    return h('article', { class: 'ticket' + (isNew ? ' is-new' : '') + (late ? ' is-late' : '') },
      h('div', { class: 'row' }, h('span', { class: 'table-no' }, order.table), h('span', { class: 'chip' }, minutes(order.waiting_seconds))),
      h('p', { class: 'label' }, order.code),
      h('ul', { class: 'ticket-lines' }, order.lines.map(lineRow)));
  }

  function renderTickets(data) {
    const ids = new Set(data.orders.map((o) => o.id));
    const fresh = knownIds === null ? new Set() : new Set(data.orders.filter((o) => !knownIds.has(o.id)).map((o) => o.id));
    if (fresh.size) beep();
    knownIds = ids;

    $('tickets').replaceChildren(...data.orders.map((order) => ticket(order, fresh.has(order.id))));
    $('emptyMsg').hidden = data.orders.length > 0;
  }

  /* ---- menu habis (hanya menu stasiun ini) ---- */
  function renderMenuPanel(menu) {
    if (!config.canToggle) return;
    const query = $('menuSearch').value.trim().toLowerCase();
    const items = menu.filter((item) => item.station === config.station && item.name.toLowerCase().includes(query));
    $('menuList').replaceChildren(...items.map((item) =>
      h('li', { class: item.is_available ? '' : 'is-out' },
        h('span', { class: 'name' }, item.name, h('small', {}, item.category)),
        h('button', { class: 'btn btn--light btn--small', type: 'button',
          onclick: () => act(config.apiBase + '/branches/' + state.branch + '/items/' + item.id + '/availability', { available: item.is_available ? 0 : 1 }) },
          item.is_available ? 'Habis' : 'Tersedia'))));
  }

  function setConnection(online) {
    $('conn').classList.toggle('is-down', !online);
    $('connText').textContent = online ? 'Terhubung' : 'Terputus, mencoba lagi';
  }

  async function poll() {
    try {
      const query = '?branch=' + state.branch + '&station=' + config.station;
      const response = await fetch(config.snapshotUrl + query, { cache: 'no-store', headers: { Accept: 'application/json' } });
      if (response.status === 401) return (window.location.href = '/staff/login');
      if (!response.ok) throw new Error('http ' + response.status);
      state.data = await response.json();
      renderTickets(state.data);
      renderMenuPanel(state.data.menu);
      failures = 0;
      setConnection(true);
    } catch (error) {
      failures += 1;
      if (failures >= FAILURES_BEFORE_OFFLINE) setConnection(false);
    }
  }

  $('soundBtn').addEventListener('click', enableSound);
  if ($('menuSearch')) $('menuSearch').addEventListener('input', () => { if (state.data) renderMenuPanel(state.data.menu); });
  const tabs = $('branchTabs');
  if (tabs) {
    tabs.querySelectorAll('button').forEach((button) => button.addEventListener('click', () => {
      tabs.querySelectorAll('button').forEach((b) => b.setAttribute('aria-pressed', String(b === button)));
      state.branch = Number(button.dataset.branch);
      knownIds = null;
      poll();
    }));
  }

  if (state.branch) {
    poll();
    setInterval(poll, POLL_MS);
  }
})();
