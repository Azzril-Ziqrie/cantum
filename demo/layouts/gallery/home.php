<?php
demo_head($d, 'Home');
require __DIR__ . '/_nav.php';
$lead = $d['products'][0];
$featured = array_slice($d['products'], 0, 4);
?>
<main>
  <section class="gl-hero">
    <div class="gl-hero-copy">
      <p class="gl-label">Collection 01</p>
      <h1 class="gl-title"><?= e($d['brand']) ?></h1>
      <p class="gl-lead"><?= e($d['tagline']) ?></p>
      <a class="gl-link" href="<?= e(demo_shop_url($d)) ?>">Browse the collection <span aria-hidden="true">→</span></a>
    </div>
    <figure class="gl-hero-media">
      <div class="gl-frame"><?php if ($d['hero']): ?><img src="<?= e($d['hero']) ?>" alt="<?= e($d['brand']) ?>" decoding="async"><?php else: ?><?= demo_media($lead) ?><?php endif; ?><?= demo_tip('hero', 'tr') ?></div>
      <figcaption><span>[01]</span><span><?= e($lead['name']) ?></span></figcaption>
    </figure>
  </section>

  <section class="gl-sec">
    <div class="gl-sec-head"><h2>Index<?= demo_tip('products', 'inline') ?></h2><a class="gl-link" href="<?= e(demo_shop_url($d)) ?>">All products <span aria-hidden="true">→</span></a></div>
    <ul class="gl-index">
      <?php foreach ($d['products'] as $p): ?>
        <li class="rv"><a href="<?= e(demo_product_url($d, $p)) ?>">
          <span class="gl-i-n"><?= str_pad((string)$p['n'], 2, '0', STR_PAD_LEFT) ?></span>
          <span class="gl-i-name"><?= e($p['name']) ?></span>
          <span class="gl-i-cat"><?= e($p['cat']) ?></span>
          <span class="gl-i-price"><?= e(demo_money($p['price'])) ?></span>
          <span class="gl-i-thumb"><?= demo_media($p) ?></span>
        </a></li>
      <?php endforeach; ?>
    </ul>
  </section>

  <section class="gl-sec">
    <div class="gl-sec-head"><h2>Selected</h2><nav class="chips" aria-label="Categories"><?php foreach ($d['cats'] as $c): ?><a href="<?= e(demo_shop_url($d, $c)) ?>"><?= e($c) ?></a><?php endforeach; ?><?= demo_tip('category', 'inline') ?></nav></div>
    <div class="gl-grid"><?php foreach ($featured as $p) echo demo_card($d, $p); ?></div>
  </section>

  <section class="gl-statement rv"><p>Simple things, made well.</p></section>
</main>
<?php
demo_site_footer($d);
demo_foot($d);
