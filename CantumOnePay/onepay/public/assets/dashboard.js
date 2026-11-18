/* Dashboard staf: denah meja realtime (polling 3 detik, ambil ulang seluruh data tiap siklus), antrian, suara, indikator koneksi. */
(function () {
  'use strict';
  const POLL_MS = 3000;
  const FAILURES_BEFORE_OFFLINE = 2;
  const ISO_WIDTH = 1120;  // lebar denah 900x620 setelah dimiringkan dan diputar
  const MIN_SCALE = 0.55;  // skala terkecil denah di HP
  const ISO_HEIGHT = 680;  // termasuk meja yang berdiri di atas lantai
  const NEXT_LABEL = { paid: 'Proses', preparing: 'Sajikan', served: 'Selesai' };
  const STATUS_LABEL = { paid: 'Baru dibayar', preparing: 'Diproses', served: 'Disajikan' };
  const TABLE_LABEL = { empty: 'Kosong', pending: 'Menunggu bayar', new: 'Pesanan baru', preparing: 'Diproses', ready: 'Siap disajikan', served: 'Disajikan' };

  const dash = window.DASH;
  const state = { branch: dash.branch, tableId: null, statusFilter: 'all', data: null };
  let knownIds = null; // null = polling pertama: jangan bunyi untuk pesanan yang sudah ada
  let failures = 0;
  let audio = null;

  const $ = (id) => document.getElementById(id);
  const rupiah = (n) => 'Rp ' + Math.round(n).toLocaleString('id-ID');
  const minutes = (seconds) => Math.floor(seconds / 60) + ' mnt';

  function h(tag, attrs = {}, ...children) {
    const node = document.createElement(tag);
    for (const [key, value] of Object.entries(attrs)) {
      if (key === 'class') node.className = value;
      else if (key === 'style') node.style.cssText = value;
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
  const csrf = () => document.querySelector('meta[name="csrf-token"]').content;

  async function post(path, data = {}, base = dash.actionBase) {
    const response = await fetch(base + path, {
      method: 'POST',
      headers: { 'X-CSRF-TOKEN': csrf(), Accept: 'application/json' },
      body: new URLSearchParams(data),
    });
    const result = await response.json();
    if (!response.ok) throw new Error(result.error || 'Gagal.');
    return result;
  }

  /** Jalankan aksi lalu ambil ulang data; kegagalan tampil sebagai toast, bukan dialog browser. */
  async function act(path, data, base, doneMessage) {
    try {
      await post(path, data, base);
      await poll();
      if (doneMessage) OnePayUI.toast(doneMessage);
    } catch (error) {
      OnePayUI.toast(error.message, { type: 'error' });
    }
  }

  const advance = (order) => act('/orders/' + order.id + '/advance');
  const askReason = (title, message, confirmLabel = 'Kirim') => OnePayUI.ask({ title, message, label: 'Alasan', confirmLabel });

  async function cancel(order) {
    const reason = await askReason('Batalkan dan refund?', 'Meja ' + order.table + ' · ' + order.code + '. Pesanan dibatalkan dan dana dicatat untuk dikembalikan.', 'Batalkan pesanan');
    if (reason) act('/orders/' + order.id + '/cancel', { reason }, undefined, 'Pesanan dibatalkan, refund dicatat.');
  }

  /** Kasir tidak membatalkan sendiri: ia mengajukan, manager atau owner memutuskan. */
  async function requestCancel(order) {
    const reason = await askReason('Ajukan pembatalan?', 'Meja ' + order.table + ' · ' + order.code + '. Manager atau owner yang memutuskan.', 'Ajukan');
    if (reason) act('/orders/' + order.id + '/cancel-request', { reason }, dash.staffBase, 'Pengajuan dikirim ke manager.');
  }

  async function decideCancel(order, approve) {
    const note = approve ? '' : await askReason('Tolak pembatalan?', 'Meja ' + order.table + ' · ' + order.code, 'Tolak');
    if (!approve && !note) return;
    act('/cancellations/' + order.cancel_request.id + '/decide', { approve: approve ? 1 : 0, note }, dash.staffBase, approve ? 'Pembatalan disetujui.' : 'Pembatalan ditolak.');
  }

  /* ---- denah ---- */
  /** Satu denah per cabang: seluruh meja dan elemen digabung. */
  const currentFloor = () => ({
    tables: state.data.floor.flatMap((area) => area.tables),
    elements: state.data.floor.flatMap((area) => area.elements),
  });

  /** Meja bergambar: bantalan di lantai + sprite yang berdiri, sama dengan halaman produk. */
  function tableButton(table) {
    const sprite = window.TableSprite.create(table.status);
    sprite.append(h('b', { class: 'itbl-no' }, table.label));

    return h('button', {
      type: 'button',
      class: 'itbl itbl--' + table.status + (table.shape === 'round' ? ' itbl--round' : '') + (table.id === state.tableId ? ' is-selected' : ''),
      style: `--s:${Math.min(table.width, table.height)}px;left:${table.pos_x}px;top:${table.pos_y}px;width:${table.width}px;height:${table.height}px`,
      'aria-label': 'Meja ' + table.label + ', ' + TABLE_LABEL[table.status],
      onclick: () => { state.tableId = table.id; renderFloor(); renderDetail(); },
    }, h('span', { class: 'itbl-pad', style: `transform:rotateZ(${table.rotation}deg)` }), h('span', { class: 'itbl-shadow' }), sprite);
  }

  function renderFloor() {
    const area = currentFloor();
    const floor = $('floor');

    const elements = area.elements.map((el) => h('div', {
      class: 'el el--' + el.type,
      style: `left:${el.pos_x}px;top:${el.pos_y}px;width:${el.width}px;height:${el.height}px;transform:rotate(${el.rotation}deg)`,
    }, el.label || ''));

    const tables = area.tables.map(tableButton);

    floor.replaceChildren(...elements, ...tables);
    window.TableSprite?.forget();
    fitFloor();
  }

  /** Denah punya koordinat logis tetap; diskalakan agar muat di lebar layar. */
  function fitFloor() {
    const wrap = $('floorWrap');
    // Di layar sempit denah tidak diperkecil terus (meja jadi sulit disentuh); ia dibiarkan bisa digeser mendatar.
    const scale = Math.max(MIN_SCALE, Math.min(1, wrap.clientWidth / ISO_WIDTH));
    const contentWidth = Math.max(wrap.clientWidth, ISO_WIDTH * scale);
    $('floor').style.left = contentWidth / 2 + 'px';
    $('floor').style.transform = `scale(${scale}) rotateX(60deg) rotateZ(45deg)`;
    wrap.style.height = Math.ceil(ISO_HEIGHT * scale) + 'px';
  }

  /* ---- detail meja ---- */
  function renderDetail() {
    const table = currentFloor().tables.find((t) => t.id === state.tableId);
    $('detailEmpty').hidden = Boolean(table);
    $('detailBody').hidden = !table;
    if (!table) return;

    const orders = state.data.orders.filter((o) => o.table_id === table.id);
    const body = [
      h('div', { class: 'row' }, h('h3', {}, 'Meja ' + table.label), h('span', { class: 'chip' }, TABLE_LABEL[table.status])),
      h('p', { class: 'label' }, table.seats + ' kursi'),
    ];
    if (orders.length === 0) body.push(h('p', { class: 'muted' }, table.status === 'pending' ? 'Pelanggan sedang membayar.' : 'Tidak ada pesanan aktif.'));
    orders.forEach((order) => body.push(orderMini(order)));
    $('detailBody').replaceChildren(...body);
  }

  function requestNote(order) {
    if (!order.cancel_request) return '';
    return h('p', { class: 'request' }, h('b', {}, 'Pengajuan pembatalan oleh ' + order.cancel_request.by), order.cancel_request.reason);
  }

  function orderMini(order) {
    return h('div', { class: 'order-mini stack' },
      h('div', { class: 'row' }, h('span', { class: 'chip status-' + order.status }, STATUS_LABEL[order.status]), h('span', { class: 'label' }, minutes(order.waiting_seconds))),
      h('ul', {}, order.lines.map(lineItem)),
      requestNote(order),
      h('div', { class: 'row' }, h('b', { class: 'num' }, rupiah(order.total)), h('span', { class: 'label' }, order.code)),
      h('div', { class: 'actions' }, orderActions(order)));
  }

  const lineItem = (line) => h('li', {},
    line.qty + '× ' + line.name,
    line.modifiers.length ? h('small', {}, line.modifiers.join(', ')) : '',
    line.note ? h('small', {}, 'Catatan: ' + line.note) : '');

  function orderActions(order) {
    const buttons = [];
    if (NEXT_LABEL[order.status]) buttons.push(h('button', { class: 'btn btn--small', type: 'button', onclick: () => advance(order) }, NEXT_LABEL[order.status]));
    if (dash.canCancel && order.cancel_request) {
      buttons.push(
        h('button', { class: 'btn btn--small', type: 'button', onclick: () => decideCancel(order, true) }, 'Setujui batal'),
        h('button', { class: 'btn btn--light btn--small', type: 'button', onclick: () => decideCancel(order, false) }, 'Tolak'));
    } else if (dash.canCancel) {
      buttons.push(h('button', { class: 'btn btn--light btn--small', type: 'button', onclick: () => cancel(order) }, 'Batalkan + refund'));
    } else if (dash.canRequestCancel && !order.cancel_request) {
      buttons.push(h('button', { class: 'btn btn--light btn--small', type: 'button', onclick: () => requestCancel(order) }, 'Ajukan pembatalan'));
    }
    return buttons;
  }

  /* ---- antrian ---- */
  function renderQueue() {
    const orders = state.data.orders.filter((o) => state.statusFilter === 'all' || o.status === state.statusFilter);
    $('board').replaceChildren(...orders.map((order) => h('article', { class: 'card order' + (state.fresh?.has(order.id) ? ' is-new' : '') },
      h('div', { class: 'row' }, h('span', { class: 'table-no' }, order.table), h('span', { class: 'chip status-' + order.status }, STATUS_LABEL[order.status])),
      h('p', { class: 'label' }, order.code + ' · ' + minutes(order.waiting_seconds) + (order.customer ? ' · ' + order.customer : '')),
      h('ul', {}, order.lines.map(lineItem)),
      requestNote(order),
      h('div', { class: 'row' }, h('b', { class: 'num' }, rupiah(order.total)), h('div', { class: 'actions' }, orderActions(order))))));
    $('emptyMsg').hidden = orders.length > 0;
  }

  function renderReview(rows) {
    $('reviewBox').hidden = rows.length === 0;
    $('refundsLink').hidden = !dash.refundsUrl;
    if (dash.refundsUrl) $('refundsLink').href = dash.refundsUrl;
    $('reviewList').replaceChildren(...rows.map((row) =>
      h('li', { class: 'card row' }, h('span', {}, 'Meja ' + row.table + ' · ' + row.code), h('b', { class: 'num' }, rupiah(row.total)))));
  }

  /* ---- buka/tutup cabang dan menu habis ---- */
  function renderBranchState(data) {
    $('closedBanner').hidden = data.is_open;
    renderRules(data.rules || []);
    const button = $('openBtn');
    button.hidden = !dash.canOpenClose;
    button.textContent = data.is_open ? 'Tutup cabang' : 'Buka cabang';
    button.onclick = () => act('/branches/' + state.branch + '/open', { value: data.is_open ? 0 : 1 });
  }

  function renderMenuPanel(menu) {
    $('menuPanel').hidden = !dash.canToggle;
    if (!dash.canToggle) return;
    const query = $('menuSearch').value.trim().toLowerCase();
    $('menuList').replaceChildren(...menu.filter((item) => item.name.toLowerCase().includes(query)).map((item) =>
      h('li', { class: item.is_available ? '' : 'is-out' },
        h('span', { class: 'name' }, item.name, h('small', {}, item.category)),
        h('button', { class: 'btn btn--light btn--small', type: 'button',
          onclick: () => act('/branches/' + state.branch + '/items/' + item.id + '/availability', { available: item.is_available ? 0 : 1 }) },
          item.is_available ? 'Habis' : 'Tersedia'))));
  }

  /** Aturan cabang yang berbeda dari brand (mis. "Service 0%"), supaya staf tahu total pelanggan bisa berbeda. */
  function renderRules(rules) {
    const list = $('ruleChips');
    list.hidden = rules.length === 0;
    list.replaceChildren(...rules.map((rule) => h('li', { class: 'chip' }, rule)));
  }

  function render(data) {
    state.data = data;
    const ids = new Set(data.orders.map((o) => o.id));
    state.fresh = new Set(knownIds === null ? [] : data.orders.filter((o) => !knownIds.has(o.id)).map((o) => o.id));
    if (state.fresh.size) beep();
    knownIds = ids;

    renderFloor();
    renderDetail();
    renderQueue();
    renderReview(data.review);
    renderBranchState(data);
    renderMenuPanel(data.menu);
  }

  function setConnection(online) {
    $('conn').classList.toggle('is-down', !online);
    $('connText').textContent = online ? 'Terhubung' : 'Terputus, mencoba lagi';
  }

  async function poll() {
    try {
      const response = await fetch(dash.snapshotUrl + '?branch=' + state.branch, { cache: 'no-store', headers: { Accept: 'application/json' } });
      if (response.status === 401) return (window.location.href = '/staff/login');
      if (!response.ok) throw new Error('http ' + response.status);
      render(await response.json());
      failures = 0;
      setConnection(true);
    } catch (error) {
      failures += 1;
      if (failures >= FAILURES_BEFORE_OFFLINE) setConnection(false);
    }
  }

  function pressTabs(container, onSelect) {
    container.querySelectorAll('button').forEach((button) => button.addEventListener('click', () => {
      container.querySelectorAll('button').forEach((b) => b.setAttribute('aria-pressed', String(b === button)));
      onSelect(button);
    }));
  }

  $('soundBtn').addEventListener('click', enableSound);
  $('menuSearch').addEventListener('input', () => { if (state.data) renderMenuPanel(state.data.menu); });
  window.addEventListener('resize', fitFloor);
  pressTabs($('branchTabs'), (button) => { state.branch = Number(button.dataset.branch); state.tableId = null; knownIds = null; poll(); });
  pressTabs($('statusTabs'), (button) => { state.statusFilter = button.dataset.status; if (state.data) renderQueue(); });

  if (state.branch) {
    poll();
    setInterval(poll, POLL_MS);
  }
})();
