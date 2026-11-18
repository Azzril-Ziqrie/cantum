/* Admin menu: tombol "habis" per cabang (langsung, tanpa muat ulang) dan baris isi paket. */
(function () {
  'use strict';
  const csrf = () => document.querySelector('meta[name="csrf-token"]').content;

  /* ---- ketersediaan dan harga cabang ---- */
  async function saveBranchItem(row, available) {
    const price = row.querySelector('.am-price').value;
    const body = new URLSearchParams({ _method: 'PUT', is_available: available ? '1' : '0', price_override: price });
    const response = await fetch(row.dataset.url, { method: 'POST', headers: { 'X-CSRF-TOKEN': csrf(), Accept: 'application/json' }, body });
    if (!response.ok) throw new Error((await response.json()).error || 'Gagal menyimpan.');
  }

  function paint(button, available) {
    button.dataset.available = available ? '1' : '0';
    button.setAttribute('aria-pressed', String(available));
    button.textContent = available ? 'Tersedia' : 'Habis';
    button.classList.toggle('btn--light', !available);
    button.closest('.am-row').classList.toggle('is-sold-out', !available);
  }

  document.querySelectorAll('#branchList .am-row').forEach((row) => {
    const button = row.querySelector('.am-toggle');
    row.classList.toggle('is-sold-out', button.dataset.available === '0');

    // Respons langsung saat ditekan: tampilan berubah dulu, dikembalikan bila server menolak.
    button.addEventListener('click', async () => {
      const next = button.dataset.available !== '1';
      paint(button, next);
      button.classList.add('is-saving');
      try { await saveBranchItem(row, next); } catch (error) { paint(button, !next); OnePayUI.toast(error.message, { type: 'error' }); }
      button.classList.remove('is-saving');
    });

    row.querySelector('.am-price').addEventListener('change', async () => {
      try { await saveBranchItem(row, button.dataset.available === '1'); } catch (error) { OnePayUI.toast(error.message, { type: 'error' }); }
    });
  });

  /* ---- isi paket ---- */
  const bundle = document.getElementById('isBundle');
  if (bundle) {
    const box = document.getElementById('bundleBox');
    const rows = document.getElementById('bundleRows');
    const template = document.getElementById('componentTemplate');
    let index = rows.children.length;

    bundle.addEventListener('change', () => { box.hidden = !bundle.checked; });
    document.getElementById('addComponent').addEventListener('click', () => {
      const row = template.content.firstElementChild.cloneNode(true);
      row.querySelectorAll('[data-name]').forEach((field) => { field.name = field.dataset.name.replace('__i__', index); });
      index += 1;
      rows.append(row);
    });
    rows.addEventListener('click', (event) => { if (event.target.closest('.am-remove')) event.target.closest('.am-inline').remove(); });
  }
  document.addEventListener('click', (event) => { const remove = event.target.closest('#bundleBox .am-remove'); if (remove) remove.closest('.am-inline').remove(); });
})();
