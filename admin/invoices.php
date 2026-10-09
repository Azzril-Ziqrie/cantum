<?php
require __DIR__ . '/../inc/bootstrap.php';
require __DIR__ . '/_layout.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $id = (int)($_POST['id'] ?? 0);
    $action = $_POST['action'] ?? '';
    if ($action === 'delete' && $id) {
        db()->prepare('DELETE FROM invoices WHERE id = ?')->execute([$id]); // items cascade
    } elseif ($action === 'status' && $id && in_array($_POST['status'] ?? '', ['draft', 'sent', 'paid'], true)) {
        $s = $_POST['status'];
        db()->prepare('UPDATE invoices SET status = ?, paid_at = ? WHERE id = ?')
            ->execute([$s, $s === 'paid' ? date('Y-m-d') : null, $id]);
    }
    header('Location: ' . url('admin/invoices.php'));
    exit;
}

$invoices = db()->query('SELECT * FROM invoices ORDER BY issue_date DESC, id DESC')->fetchAll();
$rows = [];
$outstanding = $paid = $overdueN = 0;
foreach ($invoices as $inv) {
    $t = invoice_totals(invoice_items((int)$inv['id']), (int)$inv['discount'], (float)$inv['tax_rate']);
    $st = invoice_status($inv);
    if ($st === 'paid') $paid += $t['total'];
    elseif ($st !== 'draft') $outstanding += $t['total'];
    if ($st === 'overdue') $overdueN++;
    $rows[] = [$inv, $t['total'], $st];
}

admin_head('Invoices');
?>
<div class="row between">
  <h1>Invoices <small><?= count($invoices) ?></small></h1>
  <a class="btn" href="<?= url('admin/invoice_edit.php') ?>">+ New invoice</a>
</div>

<div class="stats">
  <div><span class="muted">Outstanding</span><strong><?= rupiah($outstanding) ?></strong></div>
  <div><span class="muted">Overdue</span><strong><?= $overdueN ?></strong></div>
  <div><span class="muted">Paid</span><strong><?= rupiah($paid) ?></strong></div>
</div>

<?php if (!$rows): ?>
  <p class="muted">Belum ada invoice. Buat yang pertama.</p>
<?php else: ?>
<table class="list">
  <thead><tr><th>Number</th><th>Client</th><th>Due</th><th>Total</th><th>Status</th><th></th></tr></thead>
  <tbody>
  <?php foreach ($rows as [$inv, $total, $st]):
      $link = invoice_link($inv);
      $msg = "Halo {$inv['client_name']}, berikut tagihan {$inv['number']} dari " . SITE_NAME . " sebesar " . rupiah($total)
           . ($inv['due_date'] ? ", jatuh tempo " . id_date($inv['due_date']) : '') . ".\nDetail & pembayaran: " . $link . "\nTerima kasih!";
      $wa = 'https://wa.me/' . wa_number((string)$inv['client_phone']) . '?text=' . rawurlencode($msg);
      $mail = 'mailto:' . rawurlencode((string)$inv['client_email']) . '?subject=' . rawurlencode('Invoice ' . $inv['number'] . ' — ' . SITE_NAME) . '&body=' . rawurlencode($msg);
  ?>
    <tr>
      <td><a href="<?= url('admin/invoice_edit.php?id=' . $inv['id']) ?>"><strong><?= e($inv['number']) ?></strong></a><br><span class="muted"><?= e(id_date($inv['issue_date'])) ?></span></td>
      <td><?= e($inv['client_name']) ?></td>
      <td><?= e(id_date($inv['due_date'])) ?></td>
      <td><?= rupiah($total) ?></td>
      <td><span class="pill s-<?= $st ?>"><?= ucfirst($st) ?></span></td>
      <td class="acts">
        <a href="<?= url('invoice.php?t=' . $inv['token']) ?>" target="_blank">View</a>
        <a href="<?= url('admin/invoice_edit.php?id=' . $inv['id']) ?>">Edit</a>
        <button type="button" class="link" data-copy="<?= e($link) ?>">Copy link</button>
        <a href="<?= e($wa) ?>" target="_blank" rel="noopener">WhatsApp</a>
        <?php if ($inv['client_email']): ?><a href="<?= e($mail) ?>">Email</a><?php endif; ?>
        <?php if ($st !== 'paid'): ?>
          <?php if ($inv['status'] === 'draft'): ?><button form="s<?= $inv['id'] ?>-sent" class="link">Mark sent</button><?php endif; ?>
          <button form="s<?= $inv['id'] ?>-paid" class="link">Mark paid</button>
        <?php else: ?>
          <button form="s<?= $inv['id'] ?>-sent" class="link">Undo paid</button>
        <?php endif; ?>
        <button form="d<?= $inv['id'] ?>" class="link danger" onclick="return confirm('Hapus invoice <?= e($inv['number']) ?>?')">Delete</button>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
<?php foreach ($rows as [$inv]): ?>
  <form id="s<?= $inv['id'] ?>-sent" method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $inv['id'] ?>"><input type="hidden" name="action" value="status"><input type="hidden" name="status" value="sent"></form>
  <form id="s<?= $inv['id'] ?>-paid" method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $inv['id'] ?>"><input type="hidden" name="action" value="status"><input type="hidden" name="status" value="paid"></form>
  <form id="d<?= $inv['id'] ?>" method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $inv['id'] ?>"><input type="hidden" name="action" value="delete"></form>
<?php endforeach; ?>
<p class="muted">"Copy link" membuat link invoice yang bisa dibuka client tanpa login. Hanya orang yang punya link yang bisa melihatnya.</p>
<script>
document.querySelectorAll('[data-copy]').forEach(function (b) {
  b.addEventListener('click', function () {
    var t = b.getAttribute('data-copy'), old = b.textContent;
    var done = function () { b.textContent = 'Copied'; setTimeout(function () { b.textContent = old; }, 1500); };
    if (navigator.clipboard) navigator.clipboard.writeText(t).then(done, function () { prompt('Copy link:', t); });
    else prompt('Copy link:', t);
  });
});
</script>
<?php endif; ?>
<?php admin_foot(); ?>
