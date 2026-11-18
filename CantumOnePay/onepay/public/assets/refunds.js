/* Halaman refund: persetujuan pembatalan kasir, pesanan yang perlu ditinjau, dan refund tertunda. Selalu minta alasan/catatan. */
(function () {
  'use strict';
  const POLL_MS = 5000;
  const FAILURES_BEFORE_OFFLINE = 2;

  const config = window.REFUNDS;
  const state = { branch: config.branch };
  let failures = 0;

  const $ = (id) => document.getElementById(id);
  const rupiah = (n) => 'Rp ' + Math.round(n).toLocaleString('id-ID');
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

  async function post(path, data) {
    const response = await fetch(config.base + path, { method: 'POST', headers: { 'X-CSRF-TOKEN': csrf(), Accept: 'application/json' }, body: new URLSearchParams(data) });
    const result = await response.json();
    if (!response.ok) throw new Error(result.error || 'Gagal.');
    return result;
  }

  async function act(path, data, doneMessage) {
    try {
      await post(path, data);
      await poll();
      if (doneMessage) OnePayUI.toast(doneMessage);
    } catch (error) {
      OnePayUI.toast(error.message, { type: 'error' });
    }
  }

  const ask = (title, message, label, confirmLabel) => OnePayUI.ask({ title, message, label, confirmLabel });

  const row = (title, meta, amount, ...buttons) => h('article', { class: 'card refund-row' },
    h('div', {}, h('b', {}, title), h('p', { class: 'meta' }, meta)),
    h('b', { class: 'num' }, rupiah(amount)),
    h('div', { class: 'actions' }, buttons));

  const empty = (text) => h('p', { class: 'empty' }, text);
  const fill = (id, rows, emptyText) => $(id).replaceChildren(...(rows.length ? rows : [empty(emptyText)]));

  function render(data) {
    fill('requests', data.requests.map((r) => row('Meja ' + r.table + ' · ' + r.code, r.by + ' · ' + r.at + ' · ' + r.reason, r.total,
      h('button', { class: 'btn btn--small', type: 'button', onclick: () => act('/cancellations/' + r.id + '/decide', { approve: 1 }, 'Pembatalan disetujui.') }, 'Setujui batal'),
      h('button', { class: 'btn btn--light btn--small', type: 'button', onclick: async () => {
        const note = await ask('Tolak pembatalan?', 'Meja ' + r.table + ' · ' + r.code, 'Alasan', 'Tolak');
        if (note) act('/cancellations/' + r.id + '/decide', { approve: 0, note }, 'Pembatalan ditolak.');
      } }, 'Tolak'))), 'Tidak ada pengajuan.');

    fill('review', data.review.map((o) => row('Meja ' + o.table + ' · ' + o.code, 'Dibayar ' + o.paid_at + ' · pesanan sudah ' + o.status, o.total,
      h('button', { class: 'btn btn--small', type: 'button', onclick: async () => {
        const note = await ask('Dana sudah dikembalikan?', 'Meja ' + o.table + ' · ' + o.code, 'Catatan (mis. nomor referensi Kasera)', 'Tandai selesai');
        if (note) act('/refunds/review/' + o.id + '/resolve', { note }, 'Ditandai sudah dikembalikan.');
      } }, 'Dana sudah dikembalikan'))), 'Tidak ada yang perlu ditinjau.');

    fill('pending', data.pending.map((r) => row('Meja ' + r.table + ' · ' + r.code, r.at + ' · ' + r.reason, r.amount,
      h('button', { class: 'btn btn--small', type: 'button', onclick: async () => {
        const note = await ask('Refund selesai?', 'Meja ' + r.table + ' · ' + r.code, 'Catatan (mis. nomor referensi Kasera)', 'Tandai selesai');
        if (note) act('/refunds/' + r.id + '/complete', { note }, 'Refund ditandai selesai.');
      } }, 'Tandai selesai'))), 'Tidak ada refund tertunda.');

    fill('done', data.done.map((r) => row('Meja ' + r.table + ' · ' + r.code, r.at + ' · ' + r.reason + (r.note ? ' · ' + r.note : ''), r.amount)), 'Belum ada riwayat.');
  }

  function setConnection(online) {
    $('conn').classList.toggle('is-down', !online);
    $('connText').textContent = online ? 'Terhubung' : 'Terputus, mencoba lagi';
  }

  async function poll() {
    try {
      const response = await fetch(config.dataUrl + '?branch=' + state.branch, { cache: 'no-store', headers: { Accept: 'application/json' } });
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

  const tabs = $('branchTabs');
  if (tabs) {
    tabs.querySelectorAll('button').forEach((button) => button.addEventListener('click', () => {
      tabs.querySelectorAll('button').forEach((b) => b.setAttribute('aria-pressed', String(b === button)));
      state.branch = Number(button.dataset.branch);
      poll();
    }));
  }

  if (state.branch) {
    poll();
    setInterval(poll, POLL_MS);
  }
})();
