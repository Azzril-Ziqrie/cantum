<?php
require __DIR__ . '/../inc/bootstrap.php';
require __DIR__ . '/_layout.php';
require_login();

$id = (int)($_GET['id'] ?? 0);
$inv = [
    'id' => 0, 'number' => next_invoice_number(), 'client_name' => '', 'client_email' => '', 'client_phone' => '', 'client_address' => '',
    'issue_date' => date('Y-m-d'), 'due_date' => date('Y-m-d', strtotime('+14 days')), 'tax_rate' => 0, 'discount' => 0,
    'notes' => setting('invoice_terms'), 'status' => 'draft',
];
$items = [['description' => '', 'qty' => 1, 'price' => 0]];
if ($id) {
    $st = db()->prepare('SELECT * FROM invoices WHERE id = ?');
    $st->execute([$id]);
    $inv = $st->fetch();
    if (!$inv) { http_response_code(404); exit('Invoice not found.'); }
    $items = invoice_items($id) ?: $items;
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $f = fn($k) => trim((string)($_POST[$k] ?? ''));
    $date = fn($v) => preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) ? $v : '';
    $inv = array_merge($inv, [
        'number' => $f('number'), 'client_name' => $f('client_name'), 'client_email' => $f('client_email'),
        'client_phone' => $f('client_phone'), 'client_address' => $f('client_address'),
        'issue_date' => $date($f('issue_date')), 'due_date' => $date($f('due_date')),
        'tax_rate' => max(0, min(100, (float)str_replace(',', '.', $f('tax_rate')))),
        'discount' => max(0, (int)round((float)$f('discount'))),
        'notes' => $f('notes'),
        'status' => in_array($f('status'), ['draft', 'sent', 'paid'], true) ? $f('status') : 'draft',
    ]);
    $items = [];
    foreach ((array)($_POST['desc'] ?? []) as $i => $d) {
        $d = trim((string)$d);
        if ($d === '') continue;
        $items[] = [
            'description' => $d,
            'qty' => max(0, (float)str_replace(',', '.', (string)($_POST['qty'][$i] ?? 1))),
            'price' => max(0, (int)round((float)($_POST['price'][$i] ?? 0))),
        ];
    }

    if ($inv['client_name'] === '') $errors[] = 'Nama client wajib diisi.';
    if ($inv['number'] === '') $errors[] = 'Nomor invoice wajib diisi.';
    if ($inv['issue_date'] === '') $errors[] = 'Tanggal invoice tidak valid.';
    if (!$items) $errors[] = 'Tambahkan minimal satu item.';
    if ($inv['client_email'] !== '' && !filter_var($inv['client_email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'Format email client tidak valid.';
    $dup = db()->prepare('SELECT COUNT(*) FROM invoices WHERE number = ? AND id != ?');
    $dup->execute([$inv['number'], $id]);
    if ($dup->fetchColumn()) $errors[] = 'Nomor invoice sudah dipakai.';

    if (!$errors) {
        $pdo = db();
        $pdo->beginTransaction();
        $paidAt = $inv['status'] === 'paid' ? ($id && !empty($inv['paid_at']) ? $inv['paid_at'] : date('Y-m-d')) : null;
        $vals = [$inv['number'], $inv['client_name'], $inv['client_email'], $inv['client_phone'], $inv['client_address'],
                 $inv['issue_date'], $inv['due_date'] ?: null, $inv['tax_rate'], $inv['discount'], $inv['notes'], $inv['status'], $paidAt];
        if ($id) {
            $pdo->prepare('UPDATE invoices SET number=?, client_name=?, client_email=?, client_phone=?, client_address=?, issue_date=?, due_date=?, tax_rate=?, discount=?, notes=?, status=?, paid_at=? WHERE id=?')
                ->execute(array_merge($vals, [$id]));
            $pdo->prepare('DELETE FROM invoice_items WHERE invoice_id = ?')->execute([$id]);
        } else {
            $pdo->prepare('INSERT INTO invoices (number, client_name, client_email, client_phone, client_address, issue_date, due_date, tax_rate, discount, notes, status, paid_at, token) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)')
                ->execute(array_merge($vals, [bin2hex(random_bytes(12))]));
            $id = (int)$pdo->lastInsertId();
        }
        $ins = $pdo->prepare('INSERT INTO invoice_items (invoice_id, description, qty, price, sort_order) VALUES (?,?,?,?,?)');
        foreach ($items as $n => $it) $ins->execute([$id, $it['description'], $it['qty'], $it['price'], $n]);
        $pdo->commit();
        header('Location: ' . url('admin/invoices.php'));
        exit;
    }
}

admin_head($id ? 'Edit invoice' : 'New invoice');
?>
<h1><?= $inv['id'] || $id ? 'Edit invoice' : 'New invoice' ?></h1>
<?php foreach ($errors as $er): ?><p class="err"><?= e($er) ?></p><?php endforeach; ?>

<form method="post" class="form invform" action="<?= url('admin/invoice_edit.php' . ($id ? '?id=' . $id : '')) ?>">
  <?= csrf_field() ?>
  <div class="cols">
    <label>Nomor invoice<input name="number" value="<?= e($inv['number']) ?>" required></label>
    <label>Tanggal<input type="date" name="issue_date" value="<?= e($inv['issue_date']) ?>" required></label>
    <label>Jatuh tempo<input type="date" name="due_date" value="<?= e($inv['due_date']) ?>"></label>
  </div>

  <fieldset>
    <legend>Client</legend>
    <label>Nama client / perusahaan<input name="client_name" value="<?= e($inv['client_name']) ?>" required></label>
    <div class="cols2">
      <label>Email<input type="email" name="client_email" value="<?= e($inv['client_email']) ?>"></label>
      <label>No. WhatsApp<input name="client_phone" value="<?= e($inv['client_phone']) ?>" placeholder="0812xxxxxxx"></label>
    </div>
    <label>Alamat<textarea name="client_address" rows="2"><?= e($inv['client_address']) ?></textarea></label>
  </fieldset>

  <fieldset>
    <legend>Item</legend>
    <div class="itemhead"><span>Deskripsi</span><span>Qty</span><span>Harga (Rp)</span><span>Jumlah</span><span></span></div>
    <div id="items">
      <?php foreach ($items as $it): ?>
      <div class="item">
        <input name="desc[]" value="<?= e($it['description']) ?>" placeholder="mis. Website company profile (5 halaman)">
        <input name="qty[]" type="number" step="any" min="0" value="<?= e(rtrim(rtrim(number_format((float)$it['qty'], 2, '.', ''), '0'), '.') ?: '0') ?>">
        <input name="price[]" type="number" step="1" min="0" value="<?= (int)$it['price'] ?>">
        <span class="amt">0</span>
        <button type="button" class="link danger rm" aria-label="Hapus item">✕</button>
      </div>
      <?php endforeach; ?>
    </div>
    <div><button type="button" class="btn ghost" id="add">+ Tambah item</button></div>

    <div class="totals">
      <label>Diskon (Rp)<input type="number" name="discount" min="0" step="1" value="<?= (int)$inv['discount'] ?>"></label>
      <label>Pajak / PPN (%)<input type="number" name="tax_rate" min="0" max="100" step="any" value="<?= e((float)$inv['tax_rate']) ?>"></label>
      <dl>
        <div><dt>Subtotal</dt><dd id="t-sub">Rp 0</dd></div>
        <div><dt>Diskon</dt><dd id="t-disc">Rp 0</dd></div>
        <div><dt>Pajak</dt><dd id="t-tax">Rp 0</dd></div>
        <div class="grand"><dt>Total</dt><dd id="t-total">Rp 0</dd></div>
      </dl>
    </div>
  </fieldset>

  <label>Catatan<textarea name="notes" rows="3"><?= e($inv['notes']) ?></textarea></label>
  <label>Status
    <select name="status">
      <?php foreach (['draft' => 'Draft', 'sent' => 'Terkirim', 'paid' => 'Lunas'] as $k => $v): ?>
        <option value="<?= $k ?>" <?= $inv['status'] === $k ? 'selected' : '' ?>><?= $v ?></option>
      <?php endforeach; ?>
    </select>
  </label>
  <p class="muted">Info rekening pembayaran diatur di Settings dan otomatis tampil di invoice.</p>

  <div class="row">
    <button class="btn" type="submit">Simpan invoice</button>
    <a class="btn ghost" href="<?= url('admin/invoices.php') ?>">Batal</a>
  </div>
</form>

<script>
(function () {
  var box = document.getElementById('items');
  var fmt = function (n) { return 'Rp ' + Math.round(n).toLocaleString('id-ID'); };
  function calc() {
    var sub = 0;
    box.querySelectorAll('.item').forEach(function (r) {
      var q = parseFloat(r.querySelector('[name="qty[]"]').value.replace(',', '.')) || 0;
      var p = parseFloat(r.querySelector('[name="price[]"]').value) || 0;
      var a = Math.round(q * p); sub += a;
      r.querySelector('.amt').textContent = fmt(a);
    });
    var d = Math.max(0, Math.min(parseFloat(document.querySelector('[name="discount"]').value) || 0, sub));
    var rate = parseFloat(document.querySelector('[name="tax_rate"]').value) || 0;
    var tax = Math.round((sub - d) * rate / 100);
    document.getElementById('t-sub').textContent = fmt(sub);
    document.getElementById('t-disc').textContent = '− ' + fmt(d);
    document.getElementById('t-tax').textContent = fmt(tax);
    document.getElementById('t-total').textContent = fmt(sub - d + tax);
  }
  document.getElementById('add').addEventListener('click', function () {
    var first = box.querySelector('.item'), c = first.cloneNode(true);
    c.querySelector('[name="desc[]"]').value = ''; c.querySelector('[name="qty[]"]').value = 1; c.querySelector('[name="price[]"]').value = 0;
    box.appendChild(c); c.querySelector('[name="desc[]"]').focus(); calc();
  });
  box.addEventListener('click', function (e) {
    if (!e.target.classList.contains('rm')) return;
    if (box.querySelectorAll('.item').length > 1) e.target.closest('.item').remove();
    else e.target.closest('.item').querySelectorAll('input').forEach(function (i) { i.value = i.name === 'qty[]' ? 1 : (i.name === 'price[]' ? 0 : ''); });
    calc();
  });
  document.querySelector('.invform').addEventListener('input', calc);
  calc();
})();
</script>
<?php admin_foot(); ?>
