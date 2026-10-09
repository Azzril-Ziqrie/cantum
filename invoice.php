<?php
// Client-facing invoice. Access is by unguessable token; never indexed.
require __DIR__ . '/inc/bootstrap.php';
header('X-Robots-Tag: noindex, nofollow');

$st = db()->prepare('SELECT * FROM invoices WHERE token = ?');
$st->execute([preg_replace('/[^a-f0-9]/', '', (string)($_GET['t'] ?? ''))]);
$inv = $st->fetch();
if (!$inv || $inv['status'] === 'draft' && empty($_SESSION['uid'])) {
    http_response_code(404);
    exit('<!doctype html><meta charset="utf-8"><meta name="robots" content="noindex"><title>Not found</title><p style="font:14px system-ui;padding:2rem">Invoice not found.</p>');
}

$items = invoice_items((int)$inv['id']);
$t = invoice_totals($items, (int)$inv['discount'], (float)$inv['tax_rate']);
$status = invoice_status($inv);
$labels = ['draft' => 'Draft', 'sent' => 'Awaiting payment', 'paid' => 'Paid', 'overdue' => 'Overdue'];
$d = fn($x) => $x ? date('j F Y', strtotime($x)) : '';
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Invoice <?= e($inv['number']) ?> — <?= e(SITE_NAME) ?></title>
<style>
  :root { --fg:#0a0a0a; --muted:#8c8c8c; --line:#e6e6e6; }
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body { font: 400 12px/1.5 "Helvetica Neue", Helvetica, Arial, system-ui, sans-serif; color: var(--fg); background: #f5f5f5; -webkit-font-smoothing: antialiased; }
  .sheet { max-width: 800px; margin: 24px auto; background: #fff; padding: 56px; position: relative; }
  .top { display: flex; justify-content: space-between; gap: 2rem; align-items: flex-start; }
  .brand { font-weight: 600; font-size: 14px; letter-spacing: -.01em; text-transform: uppercase; }
  .muted { color: var(--muted); }
  h1 { font-size: 12px; font-weight: 400; color: var(--muted); text-align: right; }
  .num { font-size: 18px; font-weight: 600; letter-spacing: -.02em; text-align: right; }
  .meta { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 1.5rem; margin: 48px 0 40px; }
  .meta h2 { font-size: 12px; font-weight: 400; color: var(--muted); margin-bottom: 4px; }
  table { width: 100%; border-collapse: collapse; }
  th { text-align: left; font-weight: 400; color: var(--muted); padding: 8px 0; border-bottom: 1px solid var(--line); }
  td { padding: 12px 0; border-bottom: 1px solid var(--line); vertical-align: top; }
  .r { text-align: right; white-space: nowrap; }
  .tot { margin: 20px 0 0 auto; width: 280px; }
  .tot div { display: flex; justify-content: space-between; padding: 4px 0; }
  .tot .grand { border-top: 1px solid var(--fg); margin-top: 6px; padding-top: 10px; font-size: 16px; font-weight: 600; letter-spacing: -.02em; }
  .blocks { display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-top: 48px; }
  .blocks h2 { font-size: 12px; font-weight: 400; color: var(--muted); margin-bottom: 4px; }
  .pill { display: inline-block; padding: 2px 10px; border-radius: 999px; background: #eee; font-size: 11px; }
  .pill.paid { background: #e7f6ec; color: #067647; } .pill.overdue { background: #fef3f2; color: #b42318; } .pill.sent { background: #fff4e5; color: #b54708; }
  .actions { max-width: 800px; margin: 24px auto 0; display: flex; gap: 8px; justify-content: flex-end; padding: 0 16px; }
  .actions button { font: inherit; border: 0; background: var(--fg); color: #fff; padding: 9px 18px; border-radius: 999px; cursor: pointer; }
  .foot { margin-top: 56px; color: var(--muted); }
  @media (max-width: 640px) { .sheet { padding: 24px 18px; margin: 0; } .meta, .blocks { grid-template-columns: 1fr; } .tot { width: 100%; } .top { flex-direction: column; } h1, .num { text-align: left; } }
  @media print {
    body { background: #fff; } .sheet { margin: 0; padding: 0; max-width: none; } .actions { display: none; }
    @page { margin: 18mm; }
  }
</style>
</head>
<body>
<div class="actions"><button onclick="window.print()">Print / Save as PDF</button></div>
<div class="sheet">
  <div class="top">
    <div>
      <div class="brand"><?= e(SITE_NAME) ?></div>
      <div class="muted">
        <?= e(SITE_TAGLINE) ?><br>
        <?php if (setting('address')): ?><?= nl2br(e(setting('address'))) ?><br><?php endif; ?>
        <?php if (setting('email')): ?><?= e(setting('email')) ?><br><?php endif; ?>
        <?php if (setting('phone')): ?><?= e(setting('phone')) ?><?php endif; ?>
      </div>
    </div>
    <div>
      <h1>Invoice</h1>
      <div class="num"><?= e($inv['number']) ?></div>
      <div style="text-align:right;margin-top:6px"><span class="pill <?= e($status) ?>"><?= e($labels[$status] ?? $status) ?></span></div>
    </div>
  </div>

  <div class="meta">
    <div>
      <h2>Billed to</h2>
      <strong><?= e($inv['client_name']) ?></strong><br>
      <?php if ($inv['client_address']): ?><?= nl2br(e($inv['client_address'])) ?><br><?php endif; ?>
      <?php if ($inv['client_email']): ?><?= e($inv['client_email']) ?><br><?php endif; ?>
      <?php if ($inv['client_phone']): ?><?= e($inv['client_phone']) ?><?php endif; ?>
    </div>
    <div><h2>Issue date</h2><?= e($d($inv['issue_date'])) ?></div>
    <div><h2>Due date</h2><?= $inv['due_date'] ? e($d($inv['due_date'])) : '—' ?>
      <?php if ($inv['status'] === 'paid' && $inv['paid_at']): ?><br><span class="muted">Paid on <?= e($d($inv['paid_at'])) ?></span><?php endif; ?></div>
  </div>

  <table>
    <thead><tr><th>Description</th><th class="r">Qty</th><th class="r">Price</th><th class="r">Amount</th></tr></thead>
    <tbody>
    <?php foreach ($items as $it): ?>
      <tr>
        <td><?= nl2br(e($it['description'])) ?></td>
        <td class="r"><?= e(rtrim(rtrim(number_format((float)$it['qty'], 2, '.', ','), '0'), '.')) ?></td>
        <td class="r"><?= rupiah($it['price']) ?></td>
        <td class="r"><?= rupiah(round($it['qty'] * $it['price'])) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>

  <div class="tot">
    <div><span class="muted">Subtotal</span><span><?= rupiah($t['subtotal']) ?></span></div>
    <?php if ($t['discount']): ?><div><span class="muted">Discount</span><span>− <?= rupiah($t['discount']) ?></span></div><?php endif; ?>
    <?php if ($t['tax']): ?><div><span class="muted">Tax (<?= e((float)$inv['tax_rate']) ?>%)</span><span><?= rupiah($t['tax']) ?></span></div><?php endif; ?>
    <div class="grand"><span>Total</span><span><?= rupiah($t['total']) ?></span></div>
  </div>

  <div class="blocks">
    <?php if (setting('invoice_payment') && $status !== 'paid'): ?>
      <div><h2>Payment details</h2><?= nl2br(e(setting('invoice_payment'))) ?></div>
    <?php endif; ?>
    <?php if ($inv['notes']): ?>
      <div><h2>Notes</h2><?= nl2br(e($inv['notes'])) ?></div>
    <?php endif; ?>
  </div>

  <div class="foot">Thank you for trusting <?= e(SITE_NAME) ?> with your website.</div>
</div>
</body>
</html>
