<?php
require __DIR__ . '/inc/bootstrap.php';

$pr = t('pricing');
$rp = fn(int $n): string => number_format($n, 0, ',', '.');

$pageTitle = t('title_pricing');
$metaDesc = t('desc_pricing');
$canonicalPath = 'pricing.php';
$jsonLd = [[
    '@context' => 'https://schema.org', '@type' => 'OfferCatalog', 'name' => $pr['title'] . ' — ' . SITE_NAME, 'inLanguage' => LANG,
    'itemListElement' => array_map(fn($x) => [
        '@type' => 'Offer', 'price' => $x[1], 'priceCurrency' => 'IDR',
        'itemOffered' => ['@type' => 'Service', 'name' => $x[0], 'provider' => ['@id' => abs_url('#studio')]],
    ], array_merge($pr['plans'], $pr['extras'])),
]];
require __DIR__ . '/inc/header.php';
$n = count($pr['plans']);
?>
<section class="about">
  <div class="lead">
    <h1><?= e($pr['title']) ?></h1>
    <div class="prose" data-generate><?= e($pr['lead']) ?></div>
  </div>
</section>

<section class="pricing">
  <div class="fine">
    <h2><?= e($pr['incl_title']) ?></h2>
    <ul><?php foreach ($pr['incl'] as $i): ?><li><?= e($i) ?></li><?php endforeach; ?></ul>
  </div>

  <div class="plans" data-pager tabindex="0" role="region" aria-label="<?= e($pr['title']) ?>">
    <?php foreach ($pr['plans'] as $pl): ?>
    <article class="plan">
      <h2><?= e($pl[0]) ?></h2>
      <p class="price"><span class="cur"><?= e($pr['from']) ?></span><?= $rp($pl[1]) ?></p>
      <p class="blurb"><?= e($pl[3]) ?></p>
      <ul><?php foreach ($pl[4] as $f): ?><li><?= e($f) ?></li><?php endforeach; ?></ul>
      <a class="go" href="<?= lurl('contact.php') ?>"><?= e($pr['cta']) ?></a>
    </article>
    <?php endforeach; ?>
  </div>
  <div class="gcount" aria-hidden="true"><span class="gcn">1 / <?= $n ?></span><span class="gbar"><span></span></span></div>

  <ul class="block extras">
    <?php foreach ($pr['extras'] as $x): ?>
    <li>
      <span class="n"><?= e($x[0]) ?></span>
      <span class="d"><?= e($x[3]) ?></span>
      <span class="p"><?= e($pr['from']) ?> <?= $rp($x[1]) ?> <small><?= e($x[2]) ?></small></span>
    </li>
    <?php endforeach; ?>
    <li>
      <span class="n"><?= e($pr['custom'][0]) ?></span>
      <span class="d"><?= e($pr['custom'][1]) ?></span>
      <span class="p"><a href="<?= lurl('contact.php') ?>"><?= e($pr['custom'][2]) ?> →</a></span>
    </li>
  </ul>

  <div class="block acc">
    <details>
      <summary><?= e($pr['terms_title']) ?></summary>
      <ul class="plain"><?php foreach ($pr['terms'] as $tm): ?><li><?= e($tm) ?></li><?php endforeach; ?></ul>
      <p class="txt"><?= e($pr['terms_note']) ?></p>
    </details>
  </div>
</section>
<?php require __DIR__ . '/inc/footer.php'; ?>
