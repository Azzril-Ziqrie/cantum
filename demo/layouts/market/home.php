<?php
demo_head($d, 'Home');
require __DIR__ . '/_nav.php';
$best = $d['products'];
$tiles = array_slice($d['products'], 0, 2);
?>
<main>
  <section class="mk-banner">
    <div class="mk-banner-copy">
      <p class="mk-label">New arrivals</p>
      <h1 class="mk-h1"><?= e($d['brand']) ?></h1>
      <p class="mk-sub"><?= e($d['tagline']) ?></p>
      <a class="btn btn--solid" href="<?= e(demo_shop_url($d)) ?>">Shop now</a>
    </div>
    <div class="mk-banner-media">
      <?php foreach ($tiles as $i => $t): ?><div class="mk-banner-tile mk-banner-tile--<?= $i ?>"><?php if ($i === 0 && $d['hero']): ?><img src="<?= e($d['hero']) ?>" alt="" decoding="async"><?php else: ?><?= demo_media($t) ?><?php endif; ?></div><?php endforeach; ?>
    </div>
    <?= demo_tip('hero', 'tr') ?>
  </section>

  <section class="mk-usp" aria-label="Store benefits">
    <div><b>Free shipping</b><span>Over Rp 250.000</span><?= demo_tip('shipping', 'inline') ?></div>
    <div><b>Easy payment</b><span>QRIS, bank, e-wallet</span><?= demo_tip('cart', 'inline') ?></div>
    <div><b>7-day returns</b><span>If something is wrong</span></div>
    <div><b>WhatsApp help</b><span>Replies within hours</span><?= demo_tip('whatsapp', 'inline') ?></div>
  </section>

  <section class="mk-sec">
    <div class="mk-sec-head"><h2>Shop by category<?= demo_tip('category', 'inline') ?></h2></div>
    <div class="mk-tiles">
      <?php foreach ($d['cats'] as $c): ?>
        <a class="mk-tile rv" href="<?= e(demo_shop_url($d, $c)) ?>"><b><?= e($c) ?></b><span><?= count(demo_filter($d, $c)) ?> products →</span></a>
      <?php endforeach; ?>
    </div>
  </section>

  <section class="mk-sec">
    <div class="mk-sec-head"><h2>Bestsellers<?= demo_tip('products', 'inline') ?></h2><a class="mk-more" href="<?= e(demo_shop_url($d)) ?>">View all →</a></div>
    <div class="mk-grid"><?php foreach ($best as $card) require __DIR__ . '/_card.php'; ?></div>
  </section>

  <section class="mk-sec">
    <div class="mk-promo rv">
      <div><h2>10% off your first order</h2><p>Enter the code at checkout.</p></div>
      <span class="mk-code">WELCOME10</span>
      <?= demo_tip('promo', 'tr') ?>
    </div>
  </section>
</main>
<?php
demo_site_footer($d);
demo_foot($d);
