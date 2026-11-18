/* UI bersama: toast dan dialog (OnePayUI.toast / confirm / ask) serta perilaku kecil lewat atribut data-*.
   Halaman tidak memakai handler inline (CSP melarangnya):
   form[data-confirm="pesan"], [data-print], [data-reload], [data-back], [data-refresh], select[data-autosubmit]. */
(function () {
  'use strict';
  const TOAST_MS = 4000;
  const ERROR_TOAST_MS = 7000;
  const LEAVE_MS = 220;

  function el(tag, className, text) {
    const node = document.createElement(tag);
    if (className) node.className = className;
    if (text !== undefined) node.textContent = text;
    return node;
  }

  /* ---- toast ---- */
  function toastHost() {
    let host = document.querySelector('.ui-toasts');
    if (!host) {
      host = el('div', 'ui-toasts');
      host.setAttribute('role', 'status');
      host.setAttribute('aria-live', 'polite');
      document.body.append(host);
    }
    return host;
  }

  function toast(message, options = {}) {
    const isError = options.type === 'error';
    const node = el('div', 'ui-toast' + (isError ? ' ui-toast--error' : ''));
    const close = el('button', 'ui-toast-x', '×');
    close.type = 'button';
    close.setAttribute('aria-label', 'Tutup');
    node.append(el('span', 'ui-toast-text', String(message)), close);
    if (isError) node.setAttribute('role', 'alert');

    const dismiss = () => {
      node.classList.add('is-leaving');
      setTimeout(() => node.remove(), LEAVE_MS);
    };
    close.addEventListener('click', dismiss);
    toastHost().append(node);
    requestAnimationFrame(() => node.classList.add('is-in'));
    setTimeout(dismiss, isError ? ERROR_TOAST_MS : TOAST_MS);
  }

  /* ---- dialog ---- */
  /** Dialog modal bawaan browser (fokus terkunci, Escape menutup). `build` mengisi isian dan mengembalikan pembaca nilai. */
  function dialog({ title, message, confirmLabel = 'Lanjutkan', cancelLabel = 'Batal', danger = false, field = null }) {
    return new Promise((resolve) => {
      const box = el('dialog', 'ui-dialog');
      const body = el('form', 'ui-dialog-body');
      body.method = 'dialog';
      body.append(el('h2', 'ui-dialog-title', title || 'Konfirmasi'));
      if (message) body.append(el('p', 'ui-dialog-msg', message));

      let input = null;
      let error = null;
      if (field) {
        const wrap = el('label', 'ui-field');
        wrap.append(el('span', 'label', field.label || ''));
        input = el('textarea', 'ui-input');
        input.rows = 3;
        input.maxLength = 300;
        error = el('p', 'ui-dialog-error');
        error.hidden = true;
        error.setAttribute('role', 'alert');
        wrap.append(input);
        body.append(wrap, error);
      }

      const actions = el('div', 'ui-dialog-actions');
      const cancel = el('button', 'btn btn--light', cancelLabel);
      cancel.type = 'button';
      const ok = el('button', 'btn' + (danger ? ' btn--danger' : ''), confirmLabel);
      ok.type = 'submit';
      actions.append(cancel, ok);
      body.append(actions);
      box.append(body);
      document.body.append(box);

      const finish = (value) => {
        box.classList.remove('is-open');
        setTimeout(() => { box.close(); box.remove(); resolve(value); }, LEAVE_MS);
      };
      cancel.addEventListener('click', () => finish(field ? null : false));
      box.addEventListener('cancel', (event) => { event.preventDefault(); finish(field ? null : false); });
      body.addEventListener('submit', (event) => {
        event.preventDefault();
        if (!field) return finish(true);
        const text = input.value.trim();
        if (text === '') {
          error.textContent = field.required === false ? '' : 'Wajib diisi.';
          error.hidden = false;
          return input.focus();
        }
        finish(text);
      });

      box.showModal();
      requestAnimationFrame(() => box.classList.add('is-open'));
      (input || ok).focus();
    });
  }

  const confirmDialog = (options) => dialog(options);
  const ask = ({ title, message, label, confirmLabel }) => dialog({ title, message, confirmLabel, field: { label } });

  window.OnePayUI = { toast, confirm: confirmDialog, ask };

  /* ---- perilaku lewat atribut data-* ---- */
  document.addEventListener('submit', async (event) => {
    const form = event.target;
    const message = form.dataset?.confirm;
    if (!message || form.dataset.confirmed === '1') return;

    event.preventDefault();
    const agreed = await confirmDialog({
      title: form.dataset.confirmTitle || 'Lanjutkan?', message,
      confirmLabel: form.dataset.confirmLabel || 'Ya, lanjutkan', danger: form.dataset.confirmDanger !== undefined,
    });
    if (agreed) {
      form.dataset.confirmed = '1';
      form.requestSubmit(event.submitter ?? undefined);
    }
  });

  document.addEventListener('click', (event) => {
    const button = event.target.closest('[data-print], [data-reload], [data-back], [data-refresh]');
    if (!button) return;
    if (button.hasAttribute('data-print')) window.print();
    else if (button.hasAttribute('data-back')) window.history.back();
    else if (button.hasAttribute('data-refresh')) window.location.replace(document.referrer || window.location.pathname);
    else window.location.reload();
  });

  document.addEventListener('change', (event) => {
    if (event.target.matches('select[data-autosubmit]')) event.target.form?.submit();
  });
})();
