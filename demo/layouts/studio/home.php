<?php
demo_head($d, 'Home');
$overlay = $d['hero'] !== null;
require __DIR__ . '/_nav.php';
$selected = array_slice($d['products'], 0, 5);
?>
<main>
  <section class="hero<?= $overlay ? '' : ' is-plain' ?>" aria-label="Featured">
    <div class="hero-slider">
      <div class="hero-media"><?php if ($overlay): ?><img src="<?= e($d['hero']) ?>" alt="<?= e($d['brand']) ?>" fetchpriority="high" decoding="async"><?php endif; ?></div>
      <div class="hero-brand">
        <?php if ($d['logo']): ?><span class="hero-logo"><img src="<?= e($d['logo']) ?>" alt=""></span><?php endif; ?>
        <h1 class="hero-name"><?= e($d['brand']) ?></h1>
      </div>
      <div class="hero-copy"><div class="hero-sub">New collection</div><div class="hero-title"><?= e($d['tagline']) ?></div></div>
      <a href="<?= e(demo_shop_url($d)) ?>" class="hero-shop link-u">SHOP NOW</a>
      <?= demo_tip('hero', 'tr') ?>
    </div>
  </section>

  <section class="intro" aria-label="About">
    <div class="intro-copy"><?php foreach ($d['about'] as $line): ?><p><?= e($line) ?></p><?php endforeach; ?></div>
  </section>

  <section class="media" aria-label="Selected">
    <div class="strip">
      <?php foreach ($selected as $p): ?>
        <a class="strip-item rv" href="<?= e(demo_product_url($d, $p)) ?>">
          <div class="ptile"><?= demo_media($p) ?></div>
          <div class="strip-cap"><span class="strip-title"><?= e($p['name']) ?></span><span class="strip-sub"><?= e(demo_money($p['price'])) ?></span></div>
        </a>
      <?php endforeach; ?>
    </div>
  </section>

  <section class="shelf" id="products" aria-label="Products">
    <div class="shelf-head">
      <div class="shelf-title">Products (<?= count($d['products']) ?>)<?= demo_tip('products', 'inline') ?></div>
      <nav class="chips" aria-label="Categories">
        <a href="<?= e(demo_url($d)) ?>#products" aria-current="true">All</a>
        <?php foreach ($d['cats'] as $c): ?><a href="<?= e(demo_shop_url($d, $c)) ?>"><?= e($c) ?></a><?php endforeach; ?>
        <?= demo_tip('category', 'inline') ?>
      </nav>
    </div>
    <div class="product-grid"><?php foreach ($d['products'] as $p) echo demo_card($d, $p); ?></div>
  </section>
</main>
<?php
demo_site_footer($d);
demo_foot($d);
