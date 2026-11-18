/* Ringkasan semua cabang: kartu per cabang yang diperbarui di tempat tiap 5 detik, dengan indikator terputus. */
(function () {
  'use strict';
  const POLL_MS = 5000;
  const FAILURES_BEFORE_OFFLINE = 2;
  const STAGES = [['new', 'Baru'], ['preparing', 'Diproses'], ['ready', 'Siap'], ['served', 'Disajikan']];

  const config = window.OVERVIEW;
  const grid = document.getElementById('grid');
  const cards = new Map(); // branch id -> { root, refs }
  let failures = 0;

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

  /** Kartu dibuat sekali per cabang, lalu hanya teksnya yang diperbarui supaya fokus dan tombol tidak hilang. */
  function buildCard(branch) {
    const refs = {
      name: h('a', { class: 'ov-name', href: config.dashboardUrl + '?branch=' + branch.id }),
      state: h('span', { class: 'chip' }),
      toggle: h('button', { class: 'btn btn--light btn--small', type: 'button' }),
      revenue: h('b', { class: 'ov-revenue num' }),
      tables: h('span', { class: 'label' }),
      review: h('p', { class: 'notice notice--warn', hidden: '' }),
      stages: Object.fromEntries(STAGES.map(([key]) => [key, h('b', { class: 'num' })])),
    };
    refs.toggle.addEventListener('click', () => toggleOpen(branch.id, refs));

    const root = h('article', { class: 'card ov-card' },
      h('div', { class: 'row' }, refs.name, refs.state),
      h('div', { class: 'ov-money' }, h('span', { class: 'label' }, 'Omzet bersih hari ini'), refs.revenue),
      h('ul', { class: 'ov-stages' }, STAGES.map(([key, label]) => h('li', {}, refs.stages[key], h('span', { class: 'label' }, label)))),
      h('div', { class: 'row' }, refs.tables, refs.toggle),
      refs.review);
    return { root, refs };
  }

  function update(card, branch) {
    const { root, refs } = card;
    refs.name.textContent = branch.name;
    refs.state.textContent = branch.is_open ? 'Buka' : 'Tutup';
    refs.toggle.textContent = branch.is_open ? 'Tutup cabang' : 'Buka cabang';
    refs.toggle.dataset.next = branch.is_open ? '0' : '1';
    refs.revenue.textContent = rupiah(branch.revenue_today);
    refs.tables.textContent = branch.tables_occupied + ' dari ' + branch.tables_total + ' meja terisi';
    STAGES.forEach(([key]) => { refs.stages[key].textContent = branch.counts[key]; });
    refs.review.hidden = branch.review === 0;
    refs.review.textContent = branch.review + ' pembayaran perlu ditinjau';
    root.classList.toggle('is-closed', !branch.is_open);
    root.classList.toggle('is-hot', branch.counts.new > 0);
  }

  function render(branches) {
    const ids = new Set(branches.map((b) => b.id));
    cards.forEach((card, id) => { if (!ids.has(id)) { card.root.remove(); cards.delete(id); } });
    branches.forEach((branch) => {
      if (!cards.has(branch.id)) {
        const card = buildCard(branch);
        cards.set(branch.id, card);
        grid.append(card.root);
      }
      update(cards.get(branch.id), branch);
    });
    document.getElementById('emptyMsg').hidden = branches.length > 0;
  }

  async function toggleOpen(id, refs) {
    refs.toggle.disabled = true;
    try {
      const response = await fetch(config.openUrl.replace(':id', id), {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': csrf(), Accept: 'application/json' },
        body: new URLSearchParams({ value: refs.toggle.dataset.next }),
      });
      if (!response.ok) throw new Error((await response.json()).error || 'Gagal.');
      await poll();
    } catch (error) {
      OnePayUI.toast(error.message, { type: 'error' });
    } finally {
      refs.toggle.disabled = false;
    }
  }

  function setConnection(online) {
    document.getElementById('conn').classList.toggle('is-down', !online);
    document.getElementById('connText').textContent = online ? 'Terhubung' : 'Terputus, mencoba lagi';
  }

  async function poll() {
    try {
      const response = await fetch(config.dataUrl, { cache: 'no-store', headers: { Accept: 'application/json' } });
      if (response.status === 401) return (window.location.href = '/staff/login');
      if (!response.ok) throw new Error('http ' + response.status);
      render((await response.json()).branches);
      failures = 0;
      setConnection(true);
    } catch (error) {
      failures += 1;
      if (failures >= FAILURES_BEFORE_OFFLINE) setConnection(false);
    }
  }

  render(config.initial);
  setConnection(true);
  setInterval(poll, POLL_MS);
})();
