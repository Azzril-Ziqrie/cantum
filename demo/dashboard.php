<?php
// Mock store dashboard shown inside a live demo: nothing here is saved, it only shows what the owner can manage.
require __DIR__ . '/../inc/bootstrap.php';
require __DIR__ . '/../inc/demo.php';

$id = (string)($_GET['id'] ?? 'sample');
$demo = demo_load($id);
if (!$demo) {
    header('Location: ' . url('demo/ended.php'), true, 302);
    exit;
}

$brand    = $demo['brand'];
$products = array_merge($demo['products'], array_slice(demo_sample_products(), count($demo['products'])));
$logo     = demo_asset_url($id, $demo['logo']);
$hero     = demo_asset_url($id, $demo['hero']) ?: demo_asset_url($id, $demo['products'][0]['image'] ?? null);
$initial  = mb_strtoupper(mb_substr($brand, 0, 1));

$days   = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
$sales  = [38, 52, 44, 67, 59, 88, 74];   // relative height of each bar, %
$orders = [
    ['INV-1042', 'Rina Putri',   'QRIS',          $products[0]['price'] * 2,         'Paid'],
    ['INV-1041', 'Budi Santoso', 'BCA VA',        $products[1]['price'],             'Packing'],
    ['INV-1040', 'Dewi Lestari', 'GoPay',         $products[2]['price'] + 15000,     'Shipped'],
    ['INV-1039', 'Andi Pratama', 'Bank transfer', $products[3]['price'],             'Awaiting payment'],
];
$methods = [
    ['QRIS', 'Every e-wallet and mobile banking app', true],
    ['Virtual account', 'BCA, BNI, BRI, Mandiri, Permata', true],
    ['E-wallet', 'GoPay, OVO, DANA, ShopeePay', true],
    ['Credit card', 'Visa and Mastercard', false],
];
$tabs = [
    'overview' => 'Overview',
    'products' => 'Products',
    'hero'     => 'Banner',
    'orders'   => 'Orders',
    'payments' => 'Payments',
    'promo'    => 'Promo codes',
];
$waLink = wa_link('Hi Cantum Studio, I tried the demo for "' . $brand . '" and would like a website like this.');
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Dashboard | <?= e($brand) ?> (Demo)</title>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@300..700&display=swap">
<link rel="stylesheet" href="<?= url('assets/css/demo-dashboard.css') ?>?v=<?= filemtime(__DIR__ . '/../assets/css/demo-dashboard.css') ?>">
</head>
<body>
<div class="app">
  <aside class="side">
    <div class="side-brand">
      <?php if ($logo): ?><img src="<?= e($logo) ?>" alt="<?= e($brand) ?>"><?php else: ?><span class="mono"><?= e($initial) ?></span><?php endif; ?>
      <strong><?= e($brand) ?></strong>
    </div>
    <nav class="side-nav" aria-label="Dashboard menu">
      <?php foreach ($tabs as $key => $label): ?>
        <button type="button" data-tab="<?= $key ?>"><?= e($label) ?></button>
      <?php endforeach; ?>
    </nav>
  </aside>

  <div class="content">
    <header class="top">
      <h1 id="panelTitle">Overview</h1>
      <span class="pill">Demo<?= $id !== 'sample' ? ' · live until ' . e(demo_clock(demo_expires_at($demo))) : '' ?></span>
      <label class="tips-toggle"><input type="checkbox" id="tipsToggle" checked> Show tips</label>
      <a class="btn btn--small" href="<?= e(url('demo/store.php?id=' . $id)) ?>">View store</a>
      <?php if ($id !== 'sample'): ?>
      <form method="post" action="<?= url('demo/delete.php') ?>" data-confirm="Delete this demo and every file you uploaded now?">
        <input type="hidden" name="id" value="<?= e($id) ?>">
        <button type="submit" class="btn btn--small">Delete my demo</button>
      </form>
      <?php endif; ?>
    </header>

    <section class="panel" data-panel="overview">
      <p class="tip">These numbers are samples. In your own dashboard they show your store's orders and visitors as they happen.</p>
      <div class="stats">
        <div class="stat"><span>Sales today</span><strong><?= rupiah(1285000) ?></strong></div>
        <div class="stat"><span>New orders</span><strong>12</strong></div>
        <div class="stat"><span>Visitors</span><strong>486</strong></div>
        <div class="stat"><span>Conversion</span><strong>2.5%</strong></div>
      </div>
      <div class="card">
        <h2>Sales, last 7 days</h2>
        <div class="bars" role="img" aria-label="Sales over the last 7 days">
          <?php foreach ($sales as $i => $h): ?>
            <div class="bar-col"><span class="bar" style="height:<?= $h ?>%"></span><small><?= $days[$i] ?></small></div>
          <?php endforeach; ?>
        </div>
      </div>
    </section>

    <section class="panel" data-panel="products" hidden>
      <p class="tip">Add products, change prices or mark items sold out here. Your store updates as soon as you save.</p>
      <div class="card">
        <div class="card-head"><h2>Products (<span id="productCount"><?= count($products) ?></span>)</h2><button type="button" class="btn" id="addProduct">+ Add product</button></div>
        <table class="table" id="productTable">
          <thead><tr><th>Product</th><th>Price</th><th>Stock</th><th>Visible</th></tr></thead>
          <tbody>
          <?php foreach ($products as $i => $p): $img = demo_asset_url($id, $p['image']); ?>
            <tr>
              <td class="prod"><?php if ($img): ?><img src="<?= e($img) ?>" alt=""><?php else: ?><span class="thumb"><?= e(mb_strtoupper(mb_substr($p['name'], 0, 1))) ?></span><?php endif; ?><?= e($p['name']) ?></td>
              <td><?= rupiah($p['price']) ?></td>
              <td><?= 8 + ($i * 7) % 23 ?></td>
              <td><button type="button" class="switch" role="switch" aria-checked="true" aria-label="Show <?= e($p['name']) ?> in the store"></button></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </section>

    <section class="panel" data-panel="hero" hidden>
      <p class="tip">Change your store banner whenever you like. Pick an image and the preview updates. Try it with one of your own photos.</p>
      <div class="card">
        <h2>Main banner</h2>
        <div class="hero-preview" id="heroPreview"<?= $hero ? ' style="background-image:url(\'' . e($hero) . '\')"' : '' ?>>
          <div class="hero-text">
            <small>New collection</small>
            <strong id="heroHeadline"><?= e($brand) ?></strong>
            <span class="hero-cta">Shop now</span>
          </div>
        </div>
        <div class="form-row">
          <label class="btn" for="heroFile">Upload banner</label>
          <input type="file" id="heroFile" accept="image/*" hidden>
          <input type="text" id="heroInput" class="input" value="<?= e($brand) ?>" maxlength="60" aria-label="Banner headline">
        </div>
      </div>
    </section>

    <section class="panel" data-panel="orders" hidden>
      <p class="tip">Orders arrive here with the payment method. Add a tracking number and the customer gets notified.</p>
      <div class="card">
        <h2>Latest orders</h2>
        <table class="table">
          <thead><tr><th>No.</th><th>Customer</th><th>Payment</th><th>Total</th><th>Status</th></tr></thead>
          <tbody>
          <?php foreach ($orders as [$no, $who, $via, $total, $status]): ?>
            <tr><td><?= e($no) ?></td><td><?= e($who) ?></td><td><?= e($via) ?></td><td><?= rupiah($total) ?></td><td><span class="status status--<?= $status === 'Awaiting payment' ? 'wait' : 'ok' ?>"><?= e($status) ?></span></td></tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </section>

    <section class="panel" data-panel="payments" hidden>
      <p class="tip">Your store connects to a local Indonesian payment gateway. Customers pick how they pay, and each order updates itself once paid.</p>
      <div class="card">
        <h2>Payment methods</h2>
        <ul class="methods">
          <?php foreach ($methods as [$name, $note, $on]): ?>
            <li><div><strong><?= e($name) ?></strong><small><?= e($note) ?></small></div><button type="button" class="switch" role="switch" aria-checked="<?= $on ? 'true' : 'false' ?>" aria-label="Accept <?= e($name) ?>"></button></li>
          <?php endforeach; ?>
        </ul>
      </div>
    </section>

    <section class="panel" data-panel="promo" hidden>
      <p class="tip">Create a discount code, set when it expires, and see how often customers use it.</p>
      <div class="card">
        <h2>Promo codes</h2>
        <table class="table">
          <thead><tr><th>Code</th><th>Discount</th><th>Expires</th><th>Used</th></tr></thead>
          <tbody>
            <tr><td><code>WELCOME10</code></td><td>10%</td><td>31 Dec <?= date('Y') ?></td><td>24 times</td></tr>
            <tr><td><code>FREESHIP</code></td><td>Free shipping</td><td>30 Nov <?= date('Y') ?></td><td>9 times</td></tr>
          </tbody>
        </table>
      </div>
    </section>

    <footer class="cta">
      <span>Like this dashboard? We build the real one for <strong><?= e($brand) ?></strong>.</span>
      <?php if ($waLink): ?><a class="btn btn--solid" href="<?= e($waLink) ?>" target="_blank" rel="noopener" data-wa="demo">Chat with us on WhatsApp</a><?php endif; ?>
    </footer>
  </div>
</div>
<script src="<?= url('assets/js/demo-dashboard.js') ?>?v=<?= filemtime(__DIR__ . '/../assets/js/demo-dashboard.js') ?>"></script>
</body>
</html>
