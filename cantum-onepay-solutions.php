<?php
// Cantum OnePay solutions: one section per kind of business (coffee shop, restaurant, bar...). Same standalone look as the product page.
require __DIR__ . '/inc/bootstrap.php';

$cd = t('cd');
$cs = t('cds');
$fullTitle = t('title_cds');
$metaDesc = t('desc_cds');
$canonicalPath = 'cantum-onepay-solutions.php';
$canonical = abs_url((LANG === 'id' ? 'id/' : '') . $canonicalPath);
$altEn = abs_url($canonicalPath);
$altId = abs_url('id/' . $canonicalPath);
if (http_response_code() === 200) stats_pageview(LANG . ':' . $canonicalPath);

$schemas = [org_schema(), [
    '@context' => 'https://schema.org', '@type' => 'CollectionPage', 'name' => $fullTitle, 'description' => $metaDesc,
    'inLanguage' => LANG, 'url' => $canonical, 'isPartOf' => ['@type' => 'WebSite', 'name' => 'Cantum OnePay', 'url' => abs_url('cantum-onepay.php')],
]];

$asset = fn(string $f) => url('assets/' . $f) . '?v=' . filemtime(ROOT . '/assets/' . $f);
$wa = wa_link(t('wa_msg_cd'));
$logo = url('assets/img/table-lines.png');
$product = lurl('cantum-onepay.php');
$otherLang = url(LANG === 'id' ? $canonicalPath : 'id/' . $canonicalPath);
$arrow = '<svg viewBox="0 0 14 10" aria-hidden="true"><path d="M0 5h13M9 1l4 4-4 4"/></svg>';
$nav = [[$cd['nav'][0][0], lurl($canonicalPath)], [$cd['nav'][1][0], $product . '#how'], [$cd['nav'][2][0], $product . '#features'], [$cd['nav'][3][0], $product . '#model']];
?>
<!doctype html>
<html lang="<?= LANG ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?= e($fullTitle) ?></title>
<meta name="description" content="<?= e($metaDesc) ?>">
<link rel="canonical" href="<?= e($canonical) ?>">
<link rel="alternate" hreflang="en" href="<?= e($altEn) ?>">
<link rel="alternate" hreflang="id" href="<?= e($altId) ?>">
<link rel="alternate" hreflang="x-default" href="<?= e($altEn) ?>">
<meta name="robots" content="index, follow, max-image-preview:large, max-snippet:-1">
<meta name="theme-color" content="#fafafa">
<link rel="icon" type="image/png" href="<?= e($logo) ?>">
<meta property="og:site_name" content="Cantum OnePay">
<meta property="og:locale" content="<?= LANG === 'id' ? 'id_ID' : 'en_US' ?>">
<meta property="og:type" content="website">
<meta property="og:title" content="<?= e($fullTitle) ?>">
<meta property="og:description" content="<?= e($metaDesc) ?>">
<meta property="og:url" content="<?= e($canonical) ?>">
<meta property="og:image" content="<?= e(abs_url('assets/img/table-lines.png')) ?>">
<meta name="twitter:card" content="summary">
<meta name="twitter:title" content="<?= e($fullTitle) ?>">
<meta name="twitter:description" content="<?= e($metaDesc) ?>">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:ital,wght@0,400;0,500;1,400&display=swap">
<link rel="stylesheet" href="<?= e($asset('css/cantumdine.css')) ?>">
<?= json_ld($schemas) ?>
</head>
<body class="cs-page">
<div class="cd-app">
  <header class="cd-nav">
    <a class="cd-logo" href="<?= e($product) ?>" aria-label="Cantum OnePay"><img src="<?= e($logo) ?>" width="480" height="378" alt=""><span>Cantum OnePay</span></a>
    <nav class="cd-links" aria-label="Cantum OnePay">
      <?php $dropCurrent = true; foreach ($nav as $i => [$label, $href]): if ($i === 0) { include __DIR__ . '/inc/onepay_solutions_menu.php'; continue; } ?><a href="<?= e($href) ?>"><?= e($label) ?></a><?php endforeach; ?>
    </nav>
    <div class="cd-nav-end">
      <a class="cd-quiet" href="<?= e($otherLang) ?>" hreflang="<?= LANG === 'id' ? 'en' : 'id' ?>"><?= e($cd['lang_label']) ?></a>
      <a class="cd-outline" href="<?= e($wa ?: lurl('contact.php')) ?>"<?= $wa ? ' target="_blank" rel="noopener" data-wa="cantum-onepay-solutions:nav"' : '' ?>><?= e($cd['demo']) ?></a>
    </div>
  </header>

  <main>
    <section class="cs-hero">
      <span class="cd-pill"><?= e($cs['pill']) ?></span>
      <h1 class="cd-h1"><?= e($cs['h1']) ?></h1>
      <p class="cd-lead"><?= e($cs['lead']) ?></p>
      <nav class="cs-chips" aria-label="<?= e($cs['pill']) ?>">
        <?php foreach ($cs['cats'] as [$slug, $name]): ?><a href="#<?= e($slug) ?>"><?= e($name) ?></a><?php endforeach; ?>
      </nav>
    </section>

    <?php foreach ($cs['cats'] as $i => [$slug, $name, $tag, $text, $points, $table, $items]): ?>
    <section class="cs-cat" id="<?= e($slug) ?>">
      <div class="cs-copy">
        <span class="cd-num">0<?= $i + 1 ?></span>
        <h2><?= e($name) ?></h2>
        <p class="cs-tag"><?= e($tag) ?></p>
        <p><?= e($text) ?></p>
        <ul class="cs-points"><?php foreach ($points as $pt): ?><li><?= e($pt) ?></li><?php endforeach; ?></ul>
        <a class="cd-link" href="<?= e($product) ?>#how"><?= e($cs['more']) ?> <?= $arrow ?></a>
      </div>
      <aside class="cs-card" aria-label="<?= e($cs['sample']) ?>">
        <p class="cs-card-top"><strong><?= e($table) ?></strong><span><?= e($cs['sample']) ?></span></p>
        <ul><?php foreach ($items as [$n, $p]): ?><li><span><?= e($n) ?></span><span><?= e($p) ?></span></li><?php endforeach; ?></ul>
      </aside>
    </section>
    <?php endforeach; ?>

    <section class="cd-end">
      <img src="<?= e($logo) ?>" width="480" height="378" alt="">
      <h2><?= e($cs['end_title']) ?></h2>
      <p><?= e($cs['end_text']) ?></p>
      <p class="cd-actions">
        <a class="cd-btn cd-btn--light" href="<?= e($wa ?: lurl('contact.php')) ?>"<?= $wa ? ' target="_blank" rel="noopener" data-wa="cantum-onepay-solutions:end"' : '' ?>><?= e($wa ? $cd['cta_wa'] : $cd['cta_contact']) ?></a>
        <a class="cd-link" href="<?= lurl('contact.php') ?>"><?= e($cd['cta_contact']) ?> <?= $arrow ?></a>
      </p>
    </section>
  </main>

  <footer class="cd-foot">
    <span><?= e($cd['foot']) ?></span>
    <span><a href="<?= e($product) ?>">Cantum OnePay</a> · <a href="<?= lurl() ?>"><?= e($cd['back']) ?></a> · <a href="<?= e($otherLang) ?>" hreflang="<?= LANG === 'id' ? 'en' : 'id' ?>"><?= e($cd['lang_label']) ?></a></span>
  </footer>
</div>
<script src="<?= e($asset('js/cantumdine-menu.js')) ?>" defer></script>
<script src="<?= e($asset('js/main.js')) ?>" data-track="<?= e(url('track.php')) ?>" defer></script>
</body>
</html>
