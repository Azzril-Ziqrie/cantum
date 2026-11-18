/* Editor meja dan denah: geser dan ubah ukuran dengan Pointer Events (1:1 dengan jari/kursor), simpan otomatis setelah setiap perubahan. */
(function () {
  'use strict';

  const cfg = window.EDITOR;
  const CANVAS = { w: 900, h: 620 };
  const SNAP = 10;
  const DRAG_THRESHOLD = 4;
  const MIN_TABLE = 30;
  const NUDGE_SAVE_MS = 400;
  const SHAPE_SIZE = { square: [80, 80], round: [80, 80], long: [140, 70] };
  const SHAPE_LABEL = { square: 'Persegi', round: 'Bulat', long: 'Panjang' };
  const ELEMENT_DEFAULT = {
    wall: { label: '', width: 200, height: 14 },
    door: { label: 'Pintu', width: 100, height: 16 },
    cashier: { label: 'Kasir', width: 120, height: 44 },
    bar: { label: 'Bar', width: 240, height: 44 },
  };
  const ELEMENT_NAME = { wall: 'Dinding', door: 'Pintu', cashier: 'Kasir', bar: 'Bar' };

  const state = { branch: cfg.branch, tables: [], elements: [], selection: null };
  const nodes = new Map(); // "table:1" | "element:3" -> elemen DOM

  const $ = (id) => document.getElementById(id);
  const floor = $('floor');
  const wrap = $('floorWrap');

  function h(tag, attrs = {}, ...children) {
    const node = document.createElement(tag);
    for (const [key, value] of Object.entries(attrs)) {
      if (value === false || value === null || value === undefined) continue;
      if (key === 'class') node.className = value;
      else if (key === 'style') node.style.cssText = value;
      else if (key.startsWith('on')) node.addEventListener(key.slice(2), value);
      else node.setAttribute(key, value === true ? '' : value);
    }
    for (const child of children.flat()) node.append(child instanceof Node ? child : document.createTextNode(String(child)));
    return node;
  }

  const clamp = (value, min, max) => Math.min(Math.max(value, min), Math.max(min, max));
  const snap = (value) => Math.round(value / SNAP) * SNAP;
  const csrf = () => document.querySelector('meta[name="csrf-token"]').content;

  /* ---------- jaringan dan umpan balik ---------- */
  let inFlight = 0;
  function setSaveState(kind) {
    const label = $('saveState');
    label.className = 'saved' + (kind === 'busy' ? ' is-busy' : kind === 'error' ? ' is-error' : '');
    label.textContent = { busy: 'Menyimpan…', error: 'Gagal menyimpan', ok: 'Tersimpan otomatis' }[kind];
  }

  const toast = (message, type = 'info') => window.OnePayUI.toast(message, { type });

  async function api(method, url, body) {
    inFlight += 1;
    setSaveState('busy');
    try {
      const response = await fetch(url, {
        method,
        headers: { 'X-CSRF-TOKEN': csrf(), Accept: 'application/json', 'Content-Type': 'application/json' },
        body: body === undefined ? undefined : JSON.stringify(body),
      });
      const data = await response.json().catch(() => ({}));
      if (!response.ok) throw new Error(data.error || firstValidationMessage(data) || 'Permintaan gagal.');
      return data;
    } catch (error) {
      setSaveState('error');
      throw error;
    } finally {
      inFlight -= 1;
      if (inFlight === 0 && $('saveState').classList.contains('is-busy')) setSaveState('ok');
    }
  }

  function firstValidationMessage(data) {
    const first = data.errors && Object.values(data.errors)[0];
    return first ? first[0] : data.message;
  }

  const base = cfg.base;
  const branchUrl = (suffix) => `${base}/branches/${state.branch}${suffix}`;
  const tableUrl = (id, suffix = '') => `${base}/table/${id}${suffix}`;
  const elementUrl = (id) => `${base}/element/${id}`;

  async function loadLayout(keepSelection = false) {
    const data = await api('GET', `${cfg.layoutUrl}?branch=${state.branch}`);
    state.tables = data.tables;
    state.elements = data.elements;
    if (!keepSelection || !findSelected()) state.selection = null;
    renderAll();
  }

  /* ---------- model ---------- */
  const listFor = (kind) => (kind === 'table' ? state.tables : state.elements);

  function findSelected() {
    return state.selection ? modelOf(state.selection.kind, state.selection.id) || null : null;
  }

  function modelOf(kind, id) {
    return listFor(kind).find((m) => m.id === id);
  }

  /** Simpan sebagian field; bila server menolak, muat ulang denah supaya tampilan kembali ke data sebenarnya. */
  async function persist(kind, model, fields) {
    const payload = Object.fromEntries(fields.map((f) => [f, model[f]]));
    const url = kind === 'table' ? tableUrl(model.id) : elementUrl(model.id);
    try {
      await api('PATCH', url, payload);
    } catch (error) {
      toast(error.message, 'error');
      await loadLayout(true);
    }
  }

  /* ---------- render ---------- */
  function renderAll() {
    renderBranchTabs();
    renderCount();
    renderFloor();
    renderProps();
    $('sheetLink').href = `${cfg.sheetUrl}?branch=${state.branch}`;
  }

  function renderBranchTabs() {
    const tabs = $('branchTabs');
    if (!tabs) return;
    tabs.querySelectorAll('button').forEach((b) => b.setAttribute('aria-pressed', String(Number(b.dataset.branch) === state.branch)));
  }

  function renderCount() {
    const seats = state.tables.filter((t) => t.is_active).reduce((sum, t) => sum + t.seats, 0);
    $('countLabel').textContent = `${state.tables.length} meja · ${seats} kursi aktif`;
  }

  function applyGeometry(node, model) {
    node.style.left = model.pos_x + 'px';
    node.style.top = model.pos_y + 'px';
    node.style.width = model.width + 'px';
    node.style.height = model.height + 'px';
    node.style.setProperty('--rot', model.rotation + 'deg');
    node.style.setProperty('--s', Math.min(model.width, model.height) + 'px');
  }

  const LOCK_ICON = '<svg viewBox="0 0 24 24"><rect x="5" y="11" width="14" height="9" rx="2"/><path d="M8 11V8a4 4 0 0 1 8 0v3"/></svg>';

  /** Meja digambar seperti di halaman produk: bantalan + gambar meja berdiri di tengahnya + pil nomor. */
  function tableNode(table) {
    const sprite = window.TableSprite.create('empty');
    sprite.append(h('b', { class: 'etbl-no' }, table.label));
    const lock = table.locked ? h('span', { class: 'lock-badge', title: 'Posisi terkunci', 'aria-hidden': 'true' }) : null;
    if (lock) lock.innerHTML = LOCK_ICON;

    const node = h('button', {
      type: 'button',
      class: 'etbl' + (table.shape === 'round' ? ' etbl--round' : '') + (table.shape === 'long' ? ' etbl--long' : '')
        + (table.locked ? ' is-locked' : '') + (table.is_active ? '' : ' is-inactive'),
      'data-kind': 'table',
      'data-id': table.id,
      'aria-label': `Meja ${table.label}, ${table.seats} kursi${table.locked ? ', posisi terkunci' : ''}${table.is_active ? '' : ', nonaktif'}`,
    }, h('span', { class: 'etbl-pad' }), sprite, h('span', { class: 'etbl-sub' }, table.seats + ' kursi'), lock);
    applyGeometry(node, table);
    return node;
  }

  function elementNode(element) {
    const node = h('div', { class: 'el el--' + element.type, 'data-kind': 'element', 'data-id': element.id, tabindex: '0', role: 'button',
      'aria-label': `${ELEMENT_NAME[element.type]} ${element.label || ''}`.trim() }, element.label || '');
    applyGeometry(node, element);
    return node;
  }

  function renderFloor() {
    nodes.clear();
    const items = [
      ...state.elements.map((m) => ['element', m, elementNode(m)]),
      ...state.tables.map((m) => ['table', m, tableNode(m)]),
    ];
    items.forEach(([kind, model, node]) => nodes.set(`${kind}:${model.id}`, node));
    const content = items.map(([, , node]) => node);
    if (content.length === 0) {
      content.push(h('p', { class: 'muted floor-empty' }, 'Denah masih kosong. Tambah meja dengan "+ Meja" atau "+ Banyak meja", lalu atur posisinya.'));
    }
    floor.replaceChildren(...content);
    window.TableSprite.forget();
    markSelected();
    fitFloor();
  }

  /** Tandai item terpilih tanpa membuat ulang DOM (agar pointer capture saat drag tidak putus). */
  function markSelected() {
    floor.querySelectorAll('.handle').forEach((handle) => handle.remove());
    nodes.forEach((node, key) => {
      const selected = state.selection && key === `${state.selection.kind}:${state.selection.id}`;
      node.classList.toggle('is-selected', Boolean(selected));
      if (selected && !isLocked(state.selection.kind, state.selection.id)) {
        node.append(h('span', { class: 'handle', 'data-handle': 'resize', 'aria-hidden': 'true' }));
      }
    });
  }

  const isLocked = (kind, id) => kind === 'table' && Boolean(modelOf(kind, id)?.locked);

  function fitFloor() {
    const scale = Math.min(1, wrap.clientWidth / CANVAS.w);
    floor.style.transform = `scale(${scale})`;
    wrap.style.height = Math.ceil(CANVAS.h * scale) + 'px';
  }

  function select(kind, id) {
    const same = state.selection && state.selection.kind === kind && state.selection.id === id;
    state.selection = kind ? { kind, id } : null;
    markSelected();
    if (!same) renderProps();
  }

  /* ---------- geser dan ubah ukuran ---------- */
  function pointerToCanvas(event) {
    const rect = floor.getBoundingClientRect();
    const scale = rect.width / CANVAS.w;
    return { x: (event.clientX - rect.left) / scale, y: (event.clientY - rect.top) / scale };
  }

  function startGesture(event, node, kind, model, mode) {
    node.setPointerCapture(event.pointerId);
    const origin = { x: model.pos_x, y: model.pos_y, w: model.width, h: model.height };
    const start = pointerToCanvas(event);
    const minW = kind === 'table' ? MIN_TABLE : 10;
    const minH = kind === 'table' ? MIN_TABLE : 8;
    let moved = false;

    const onMove = (e) => {
      const point = pointerToCanvas(e);
      const dx = point.x - start.x;
      const dy = point.y - start.y;
      if (!moved && Math.hypot(dx, dy) < DRAG_THRESHOLD) return; // hysteresis: ketukan kecil bukan geseran
      moved = true;
      node.classList.add('is-dragging');
      if (mode === 'move') {
        model.pos_x = clamp(snap(origin.x + dx), 0, CANVAS.w - model.width);
        model.pos_y = clamp(snap(origin.y + dy), 0, CANVAS.h - model.height);
      } else {
        model.width = clamp(snap(origin.w + dx), minW, CANVAS.w - model.pos_x);
        model.height = clamp(snap(origin.h + dy), minH, CANVAS.h - model.pos_y);
      }
      applyGeometry(node, model);
    };

    const onEnd = () => {
      node.removeEventListener('pointermove', onMove);
      node.removeEventListener('pointerup', onEnd);
      node.removeEventListener('pointercancel', onEnd);
      node.classList.remove('is-dragging');
      if (!moved) return;
      persist(kind, model, mode === 'move' ? ['pos_x', 'pos_y'] : ['width', 'height']);
      if (state.selection && state.selection.id === model.id) renderProps();
    };

    node.addEventListener('pointermove', onMove);
    node.addEventListener('pointerup', onEnd);
    node.addEventListener('pointercancel', onEnd);
  }

  floor.addEventListener('pointerdown', (event) => {
    const node = event.target.closest('[data-kind]');
    if (!node) { select(null); return; }
    const kind = node.dataset.kind;
    const model = modelOf(kind, Number(node.dataset.id));
    if (!model) return;
    select(kind, model.id);

    const resizing = Boolean(event.target.closest('.handle'));
    if (resizing) return startGesture(event, node, kind, model, 'resize');
    if (kind === 'table' && model.locked) return; // terkunci: hanya bisa dipilih
    startGesture(event, node, kind, model, 'move');
  });

  /* Papan ketik: panah menggeser 10 titik, Escape melepas pilihan. */
  let nudgeTimer = null;
  floor.addEventListener('keydown', (event) => {
    const node = event.target.closest('[data-kind]');
    if (event.key === 'Escape') return select(null);
    const delta = { ArrowLeft: [-SNAP, 0], ArrowRight: [SNAP, 0], ArrowUp: [0, -SNAP], ArrowDown: [0, SNAP] }[event.key];
    if (!node || !delta) return;
    const kind = node.dataset.kind;
    const model = modelOf(kind, Number(node.dataset.id));
    if (!model || (kind === 'table' && model.locked)) return;
    event.preventDefault();
    select(kind, model.id);
    model.pos_x = clamp(model.pos_x + delta[0], 0, CANVAS.w - model.width);
    model.pos_y = clamp(model.pos_y + delta[1], 0, CANVAS.h - model.height);
    applyGeometry(node, model);
    clearTimeout(nudgeTimer);
    nudgeTimer = setTimeout(() => persist(kind, model, ['pos_x', 'pos_y']), NUDGE_SAVE_MS);
  });

  /* ---------- panel properti ---------- */
  function renderProps() {
    const model = findSelected();
    $('propsEmpty').hidden = Boolean(model);
    $('propsBody').hidden = !model;
    if (!model) return;
    $('propsBody').replaceChildren(...(state.selection.kind === 'table' ? tableProps(model) : elementProps(model)));
  }

  /** Field angka/teks yang menyimpan saat nilai berubah. Bila server menolak, nilai lama dikembalikan. */
  function inputField(kind, model, field, label, attrs = {}) {
    const input = h('input', { value: model[field], ...attrs });
    input.addEventListener('change', async () => {
      const old = model[field];
      const raw = input.value.trim();
      model[field] = attrs.type === 'number' ? Number(raw) : raw;
      try {
        await api('PATCH', kind === 'table' ? tableUrl(model.id) : elementUrl(model.id), { [field]: model[field] });
        renderFloor();
      } catch (error) {
        model[field] = old;
        input.value = old;
        toast(error.message, 'error');
      }
    });
    return h('label', { class: 'field' }, h('span', { class: 'label' }, label), input);
  }

  async function patchAndRefresh(kind, model, fields) {
    await persist(kind, model, fields);
    renderFloor();
    renderProps();
  }

  function checkField(kind, model, field, text) {
    const box = h('input', { type: 'checkbox' });
    box.checked = Boolean(model[field]);
    box.addEventListener('change', async () => {
      model[field] = box.checked;
      await patchAndRefresh(kind, model, [field]);
    });
    return h('label', { class: 'check' }, box, text);
  }

  function rotationField(kind, model) {
    const step = (delta) => async () => {
      model.rotation = ((model.rotation + delta + 540) % 360) - 180;
      await patchAndRefresh(kind, model, ['rotation']);
    };
    return h('div', { class: 'stack' },
      inputField(kind, model, 'rotation', 'Rotasi (derajat)', { type: 'number', min: -360, max: 360, step: 5 }),
      h('div', { class: 'actions' },
        h('button', { class: 'btn btn--light btn--small', type: 'button', onclick: step(-15) }, '↺ 15°'),
        h('button', { class: 'btn btn--light btn--small', type: 'button', onclick: step(15) }, '↻ 15°')));
  }

  function shapeButtons(table) {
    return h('div', { class: 'seg', role: 'group', 'aria-label': 'Bentuk meja' }, Object.entries(SHAPE_LABEL).map(([shape, text]) =>
      h('button', { type: 'button', 'aria-pressed': String(table.shape === shape), onclick: async () => {
        table.shape = shape;
        const wasDefault = table.width === table.height || (table.width === 140 && table.height === 70);
        if (wasDefault) [table.width, table.height] = SHAPE_SIZE[shape];
        await patchAndRefresh('table', table, ['shape', 'width', 'height']);
      } }, text)));
  }

  function tableProps(table) {
    return [
      h('div', { class: 'row' }, h('h3', {}, 'Meja ' + table.label), h('span', { class: 'chip' }, table.is_active ? 'Aktif' : 'Nonaktif')),
      inputField('table', table, 'label', 'Nomor atau nama', { maxlength: 30 }),
      inputField('table', table, 'seats', 'Kapasitas kursi', { type: 'number', min: 1, max: 50 }),
      h('span', { class: 'label' }, 'Bentuk'), shapeButtons(table),
      h('div', { class: 'grid2' },
        inputField('table', table, 'width', 'Lebar', { type: 'number', min: MIN_TABLE, max: 400, step: SNAP }),
        inputField('table', table, 'height', 'Tinggi', { type: 'number', min: MIN_TABLE, max: 400, step: SNAP })),
      rotationField('table', table),
      checkField('table', table, 'locked', 'Kunci posisi (tidak tergeser tidak sengaja)'),
      checkField('table', table, 'is_active', 'Aktif (bisa dipesan)'),
      h('hr'),
      ...qrSection(table),
      h('hr'),
      h('div', { class: 'actions' },
        h('button', { class: 'btn btn--light btn--small', type: 'button', onclick: () => duplicateTable(table) }, 'Salin meja'),
        h('button', { class: 'btn btn--light btn--small', type: 'button', onclick: () => regenerateToken(table) }, 'Ganti token QR'),
        h('button', { class: 'btn btn--danger btn--small', type: 'button', onclick: () => deleteTable(table) }, 'Hapus')),
    ];
  }

  function elementProps(element) {
    return [
      h('div', { class: 'row' }, h('h3', {}, ELEMENT_NAME[element.type]), h('span', { class: 'chip' }, 'Dekorasi')),
      h('p', { class: 'muted' }, 'Elemen dekoratif tidak bisa dipesan; hanya agar denah mirip ruang aslinya.'),
      inputField('element', element, 'label', 'Teks', { maxlength: 30 }),
      h('div', { class: 'grid2' },
        inputField('element', element, 'width', 'Lebar', { type: 'number', min: 10, max: 900, step: SNAP }),
        inputField('element', element, 'height', 'Tinggi', { type: 'number', min: 8, max: 620, step: SNAP })),
      rotationField('element', element),
      h('button', { class: 'btn btn--danger btn--small', type: 'button', onclick: () => deleteElement(element) }, 'Hapus elemen'),
    ];
  }

  /* ---------- QR ---------- */
  function qrSection(table) {
    const qrSrc = tableUrl(table.id, '/qr.svg') + '?t=' + encodeURIComponent(table.url);
    return [
      h('span', { class: 'label' }, 'QR dan NFC'),
      h('div', { class: 'qr-preview' }, h('img', { src: qrSrc, alt: 'QR meja ' + table.label, width: 180, height: 180 })),
      h('p', { class: 'label' }, 'URL untuk tag NFC'),
      h('div', { class: 'url-box num' }, table.url),
      h('div', { class: 'actions' },
        h('button', { class: 'btn btn--light btn--small', type: 'button', onclick: (e) => copyText(table.url, e.currentTarget) }, 'Salin URL'),
        h('a', { class: 'btn btn--light btn--small', href: tableUrl(table.id, '/qr.svg'), download: `qr-meja-${table.label}.svg` }, 'Unduh SVG'),
        h('button', { class: 'btn btn--light btn--small', type: 'button', onclick: () => downloadPng(table) }, 'Unduh PNG'),
        h('a', { class: 'btn btn--light btn--small', href: tableUrl(table.id, '/sticker.svg'), download: `stiker-meja-${table.label}.svg` }, 'Stiker SVG'),
        h('a', { class: 'btn btn--light btn--small', href: `${cfg.sheetUrl}?branch=${state.branch}&ids[]=${table.id}`, target: '_blank', rel: 'noopener' }, 'Cetak stiker')),
    ];
  }

  async function copyText(text, button) {
    const original = button.textContent;
    try { await navigator.clipboard.writeText(text); button.textContent = 'Tersalin'; } catch (error) { button.textContent = 'Gagal'; }
    setTimeout(() => { button.textContent = original; }, 1500);
  }

  /** PNG dirender di browser dari SVG server (latar putih agar mudah dipindai). */
  async function downloadPng(table) {
    const size = 1024;
    try {
      const svg = await (await fetch(tableUrl(table.id, '/qr.svg'))).text();
      const url = URL.createObjectURL(new Blob([svg], { type: 'image/svg+xml' }));
      const image = await new Promise((resolve, reject) => { const img = new Image(); img.onload = () => resolve(img); img.onerror = reject; img.src = url; });
      const canvas = h('canvas', { width: size, height: size });
      const context = canvas.getContext('2d');
      context.fillStyle = '#fff';
      context.fillRect(0, 0, size, size);
      context.drawImage(image, 0, 0, size, size);
      URL.revokeObjectURL(url);
      canvas.toBlob((blob) => {
        const link = h('a', { href: URL.createObjectURL(blob), download: `qr-meja-${table.label}.png` });
        link.click();
        setTimeout(() => URL.revokeObjectURL(link.href), 1000);
      }, 'image/png');
    } catch (error) {
      toast('Gagal membuat PNG. Unduh SVG sebagai gantinya.', 'error');
    }
  }

  /* ---------- aksi meja ---------- */
  async function duplicateTable(table) {
    try {
      const copy = await api('POST', tableUrl(table.id, '/duplicate'));
      await loadLayout();
      select('table', copy.id);
    } catch (error) { toast(error.message, 'error'); }
  }

  async function regenerateToken(table) {
    const sure = await OnePayUI.confirm({
      title: `Ganti token meja ${table.label}?`,
      message: 'QR dan tag NFC yang sudah tercetak langsung tidak berlaku dan harus dicetak ulang. Perubahan dicatat di log audit.',
      confirmLabel: 'Ganti token', danger: true,
    });
    if (!sure) return;
    try {
      await api('POST', tableUrl(table.id, '/token'));
      await loadLayout(true);
      toast(`Token meja ${table.label} diganti. Cetak ulang QR-nya.`);
    } catch (error) { toast(error.message, 'error'); }
  }

  async function deleteTable(table) {
    const sure = await OnePayUI.confirm({
      title: `Hapus meja ${table.label}?`,
      message: 'Bila sudah punya pesanan, meja hanya dinonaktifkan supaya riwayatnya tetap ada.',
      confirmLabel: 'Hapus', danger: true,
    });
    if (!sure) return;
    try {
      const { result } = await api('DELETE', tableUrl(table.id));
      state.selection = null;
      await loadLayout();
      if (result === 'deactivated') toast(`Meja ${table.label} sudah punya pesanan, jadi dinonaktifkan (bukan dihapus).`);
    } catch (error) { toast(error.message, 'error'); }
  }

  async function deleteElement(element) {
    try {
      await api('DELETE', elementUrl(element.id));
      state.selection = null;
      await loadLayout();
    } catch (error) { toast(error.message, 'error'); }
  }

  /* ---------- dialog ---------- */
  const dialog = $('dialog');
  let dialogHandler = null;

  function openDialog({ title, okLabel = 'Simpan', fields, onSubmit }) {
    $('dialogTitle').textContent = title;
    $('dialogOk').textContent = okLabel;
    $('dialogError').hidden = true;
    $('dialogFields').replaceChildren(...fields.map((field) => {
      const control = field.options
        ? h('select', { name: field.name, class: 'select' }, field.options.map(([value, text]) => h('option', { value }, text)))
        : h('input', { name: field.name, type: field.type || 'text', value: field.value ?? '', min: field.min, max: field.max, maxlength: field.maxlength, required: field.required !== false, autocomplete: 'off' });
      return h('label', { class: 'field' }, h('span', { class: 'label' }, field.label), control);
    }));
    dialogHandler = onSubmit;
    dialog.showModal();
    dialog.querySelector('input, select')?.focus();
  }

  $('dialogForm').addEventListener('submit', async (event) => {
    event.preventDefault();
    const values = Object.fromEntries(new FormData($('dialogForm')));
    try {
      await dialogHandler(values);
      dialog.close();
    } catch (error) {
      $('dialogError').textContent = error.message;
      $('dialogError').hidden = false;
    }
  });
  $('dialogCancel').addEventListener('click', () => dialog.close());

  /* ---------- toolbar ---------- */
  function nextTableLabel() {
    const labels = state.tables.map((t) => t.label);
    const prefix = (labels.at(-1) || 'A1').replace(/\d+$/, '') || 'A';
    let n = labels.length + 1;
    while (labels.includes(prefix + n)) n += 1;
    return prefix + n;
  }

  $('addTable').addEventListener('click', () => {
    const placed = state.tables.length;
    openDialog({
      title: 'Tambah meja',
      fields: [{ name: 'label', label: 'Nomor atau nama meja', value: nextTableLabel(), maxlength: 30 }, { name: 'seats', label: 'Kapasitas kursi', type: 'number', value: 4, min: 1, max: 50 }],
      onSubmit: async ({ label, seats }) => {
        const table = await api('POST', branchUrl('/tables'), {
          label, seats: Number(seats), pos_x: 40 + (placed % 6) * 130, pos_y: 100 + Math.floor(placed / 6) * 130,
        });
        await loadLayout();
        select('table', table.id);
      },
    });
  });

  $('addBulk').addEventListener('click', () => {
    openDialog({
      title: 'Tambah banyak meja',
      fields: [
        { name: 'prefix', label: 'Awalan (mis. A)', value: 'A', required: false, maxlength: 10 },
        { name: 'from', label: 'Dari nomor', type: 'number', value: 1, min: 0 },
        { name: 'to', label: 'Sampai nomor', type: 'number', value: 10, min: 0 },
      ],
      onSubmit: async ({ prefix, from, to }) => {
        await api('POST', branchUrl('/tables/bulk'), { prefix, from: Number(from), to: Number(to) });
        await loadLayout();
      },
    });
  });

  $('addElement').addEventListener('change', async (event) => {
    const type = event.target.value;
    event.target.value = '';
    if (!type) return;
    try {
      const created = await api('POST', branchUrl('/elements'), { type, pos_x: 40, pos_y: 40, ...ELEMENT_DEFAULT[type] });
      await loadLayout();
      select('element', created.id);
    } catch (error) { toast(error.message, 'error'); }
  });

  const copyButton = $('copyLayout');
  if (copyButton) {
    copyButton.addEventListener('click', () => {
      const others = cfg.branches.filter((b) => b.id !== state.branch);
      openDialog({
        title: 'Salin denah dari cabang lain', okLabel: 'Salin',
        fields: [{ name: 'source', label: 'Cabang sumber (meja dan elemen disalin; token meja baru)', options: others.map((b) => [b.id, b.name]) }],
        onSubmit: async ({ source }) => {
          const { copied } = await api('POST', `${base}/copy-layout`, { branch_id: state.branch, source_branch_id: Number(source) });
          await loadLayout();
          toast(`${copied} meja disalin. Sesuaikan lalu cetak QR barunya.`);
        },
      });
    });
  }

  const branchTabs = $('branchTabs');
  if (branchTabs) {
    branchTabs.querySelectorAll('button').forEach((button) => button.addEventListener('click', async () => {
      state.branch = Number(button.dataset.branch);
      state.selection = null;
      await loadLayout();
    }));
  }

  window.addEventListener('resize', fitFloor);
  window.addEventListener('beforeunload', (event) => { if (inFlight > 0) event.preventDefault(); });

  if (state.branch) loadLayout().catch((error) => toast(error.message, 'error'));
})();
