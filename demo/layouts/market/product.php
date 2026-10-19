<?php
demo_head($d, $p['name']);
require __DIR__ . '/_nav.php';
$views = [$p, ['n' => $p['n'] + 1] + $p, ['n' => $p['n'] + 2] + $p];
?>
<main class="mk-pdp">
  <nav class="mk-crumb" aria-label="Breadcrumb"><a href="<?= e(demo_url($d)) ?>">Home</a> / <a href="<?= e(demo_shop_url($d)) ?>">Shop</a> / <a href="<?= e(demo_shop_url($d, $p['cat'])) ?>"><?= e($p['cat']) ?></a> / <?= e($p['name']) ?></nav>
  <div class="mk-pdp-grid">
    <div class="mk-gallery">
      <div class="mk-thumbs"><?php foreach ($views as $i => $v): ?><div class="mk-thumb<?= $i === 0 ? ' is-on' : '' ?>"><?= demo_media($v, 'alt-' . $i) ?></div><?php endforeach; ?></div>
      <div class="mk-main"><?= demo_media($p) ?><?php if ($p['badge']): ?><span class="badge"><?= e($p['badge']) ?></span><?php endif; ?><?= demo_tip('products', 'tr') ?></div>
    </div>

    <div class="mk-pinfo">
      <h1><?= e($p['name']) ?></h1>
      <p class="mk-rating"><span aria-hidden="true">★★★★★</span> 4.8 · 126 reviews</p>
      <div class="mk-pricebox">
        <span class="mk-pprice"><?= e(demo_money($p['price'])) ?></span>
        <?php if ($p['old']): ?><s><?= e(demo_money($p['old'])) ?></s><span class="badge badge--ink">-<?= demo_discount($p) ?>%</span><?php endif; ?>
      </div>
      <p class="mk-stock">In stock, <?= 6 + ($p['n'] * 5) % 19 ?> left<?= demo_tip('products', 'inline') ?></p>
      <p class="mk-blurb"><?= e(DEMO_BLURB) ?></p>
      <div class="mk-sizes" role="group" aria-label="Select size"><?php foreach (['S', 'M', 'L', 'XL'] as $i => $size): ?><button type="button" class="size-btn<?= $i === 1 ? ' active' : '' ?>" aria-pressed="<?= $i === 1 ? 'true' : 'false' ?>"><?= $size ?></button><?php endforeach; ?></div>
      <div class="mk-buy">
        <?= demo_qty() ?>
        <?= demo_add_button($p, 'Add to cart', 'btn btn--solid') ?>
        <button type="button" class="btn" data-checkout>Buy now</button>
      </div>
      <div class="mk-info">
        <div><b>Shipping<?= demo_tip('shipping', 'inline') ?></b><span>Ships in 1-2 working days. Cost is calculated at checkout.</span></div>
        <div><b>Payment<?= demo_tip('cart', 'inline') ?></b><span>QRIS, bank transfer and e-wallets. PDF invoice by email.<?= demo_tip('invoice', 'inline') ?></span></div>
      </div>
    </div>
  </div>

  <section class="mk-sec mk-sec--flush">
    <div class="mk-sec-head"><h2>Similar products</h2></div>
    <div class="mk-grid mk-grid--related"><?php foreach (demo_related($d, $p, 4) as $card) require __DIR__ . '/_card.php'; ?></div>
  </section>
</main>
<?php
demo_site_footer($d);
demo_foot($d);
