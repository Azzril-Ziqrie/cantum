<?php
demo_head($d, 'Shop');
require __DIR__ . '/_nav.php';
$list = demo_sort(demo_filter($d, $cat), $sort);
?>
<main class="mk-shop">
  <aside class="mk-side" aria-label="Filters">
    <h2>Category<?= demo_tip('category', 'inline') ?></h2>
    <a href="<?= e(demo_url($d, 'shop', ['sort' => $sort])) ?>"<?= $cat === '' ? ' aria-current="true"' : '' ?>><span>All products</span><small><?= count($d['products']) ?></small></a>
    <?php foreach ($d['cats'] as $c): ?>
      <a href="<?= e(demo_url($d, 'shop', ['cat' => $c, 'sort' => $sort])) ?>"<?= $cat === $c ? ' aria-current="true"' : '' ?>><span><?= e($c) ?></span><small><?= count(demo_filter($d, $c)) ?></small></a>
    <?php endforeach; ?>
  </aside>
  <section class="mk-results">
    <div class="mk-results-bar">
      <div><h1><?= $cat !== '' ? e($cat) : 'All products' ?></h1><p><?= count($list) ?> <?= count($list) === 1 ? 'product' : 'products' ?></p></div>
      <details class="dd">
        <summary aria-label="Sort products"><span>Sort</span><svg class="dd-chev" viewBox="0 0 12 12" aria-hidden="true"><path d="M2 4.5l4 4 4-4"/></svg></summary>
        <div class="dd-panel"><?php foreach (DEMO_SORTS as $key => $label): ?><a href="<?= e(demo_url($d, 'shop', array_filter(['cat' => $cat, 'sort' => $key]))) ?>"<?= $key === $sort ? ' aria-current="true"' : '' ?>><?= e($label) ?></a><?php endforeach; ?></div>
      </details>
    </div>
    <div class="mk-grid mk-grid--shop"><?php foreach ($list as $card) require __DIR__ . '/_card.php'; ?></div>
  </section>
</main>
<?php
demo_site_footer($d);
demo_foot($d);
