<?php
demo_head($d, 'Shop');
require __DIR__ . '/_nav.php';
$list = demo_sort(demo_filter($d, $cat), $sort);
?>
<main>
  <section class="shop" aria-label="Shop">
    <h1 class="sr-only">Shop</h1>
    <div class="shop-bar">
      <span class="shop-count"><?= count($list) ?> <?= count($list) === 1 ? 'product' : 'products' ?></span>
      <details class="dd">
        <summary aria-label="Filter by category"><span>Category</span><?php if ($cat !== ''): ?><span class="dd-value"><?= e($cat) ?></span><?php endif; ?><svg class="dd-chev" viewBox="0 0 12 12" aria-hidden="true"><path d="M2 4.5l4 4 4-4"/></svg></summary>
        <div class="dd-panel">
          <a href="<?= e(demo_url($d, 'shop', ['sort' => $sort])) ?>"<?= $cat === '' ? ' aria-current="true"' : '' ?>><span>All</span><span class="dd-n"><?= count($d['products']) ?></span></a>
          <?php foreach ($d['cats'] as $c): ?>
            <a href="<?= e(demo_url($d, 'shop', ['cat' => $c, 'sort' => $sort])) ?>"<?= $cat === $c ? ' aria-current="true"' : '' ?>><span><?= e($c) ?></span><span class="dd-n"><?= count(demo_filter($d, $c)) ?></span></a>
          <?php endforeach; ?>
        </div>
      </details>
      <?= demo_tip('category', 'inline') ?>
      <details class="dd">
        <summary aria-label="Sort products"><span>Sort</span><svg class="dd-chev" viewBox="0 0 12 12" aria-hidden="true"><path d="M2 4.5l4 4 4-4"/></svg></summary>
        <div class="dd-panel">
          <?php foreach (DEMO_SORTS as $key => $label): ?>
            <a href="<?= e(demo_url($d, 'shop', array_filter(['cat' => $cat, 'sort' => $key]))) ?>"<?= $key === $sort ? ' aria-current="true"' : '' ?>><?= e($label) ?></a>
          <?php endforeach; ?>
        </div>
      </details>
    </div>
    <div class="shop-grid"><?php foreach ($list as $p) echo demo_card($d, $p); ?></div>
  </section>
</main>
<?php
demo_site_footer($d);
demo_foot($d);
