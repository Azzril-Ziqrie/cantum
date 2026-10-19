<?php
demo_head($d, 'Shop');
require __DIR__ . '/_nav.php';
$list = demo_sort(demo_filter($d, $cat), $sort);
?>
<main>
  <section class="gl-shop-head">
    <h1 class="gl-title gl-title--page">Shop</h1>
    <p class="gl-count"><?= count($list) ?> <?= count($list) === 1 ? 'product' : 'products' ?></p>
  </section>
  <div class="gl-filter">
    <nav class="chips" aria-label="Categories">
      <a href="<?= e(demo_url($d, 'shop', ['sort' => $sort])) ?>"<?= $cat === '' ? ' aria-current="true"' : '' ?>>All</a>
      <?php foreach ($d['cats'] as $c): ?><a href="<?= e(demo_url($d, 'shop', ['cat' => $c, 'sort' => $sort])) ?>"<?= $cat === $c ? ' aria-current="true"' : '' ?>><?= e($c) ?></a><?php endforeach; ?>
      <?= demo_tip('category', 'inline') ?>
    </nav>
    <details class="dd">
      <summary aria-label="Sort products"><span>Sort</span><svg class="dd-chev" viewBox="0 0 12 12" aria-hidden="true"><path d="M2 4.5l4 4 4-4"/></svg></summary>
      <div class="dd-panel"><?php foreach (DEMO_SORTS as $key => $label): ?><a href="<?= e(demo_url($d, 'shop', array_filter(['cat' => $cat, 'sort' => $key]))) ?>"<?= $key === $sort ? ' aria-current="true"' : '' ?>><?= e($label) ?></a><?php endforeach; ?></div>
    </details>
  </div>
  <section class="gl-sec gl-sec--shop"><div class="gl-grid gl-grid--shop"><?php foreach ($list as $p) echo demo_card($d, $p); ?></div></section>
</main>
<?php
demo_site_footer($d);
demo_foot($d);
