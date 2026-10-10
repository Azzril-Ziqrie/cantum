<?php
// Per-page inputs (all optional):
//   $pageTitle     short title; full <title> becomes "<title> — Cantum Studio"
//   $fullTitle     overrides the whole <title> (used on the home page)
//   $metaDesc      meta description
//   $metaImage     absolute URL for og:image
//   $canonicalPath path relative to site root ('' = home)
//   $ogType        website | article
//   $jsonLd        extra schema arrays
$fullTitle = $fullTitle ?? (isset($pageTitle) ? $pageTitle . ' | ' . SITE_NAME : t('title_home'));
$metaDesc = $metaDesc ?? t('desc_home');
$canonical = abs_url((LANG === 'id' ? 'id/' : '') . ($canonicalPath ?? ''));
$altEn = abs_url($canonicalPath ?? '');
$altId = abs_url('id/' . ($canonicalPath ?? ''));
$ogType = $ogType ?? 'website';
$metaImage = $metaImage ?? null;
if (!$metaImage) {   // share previews for pages without their own image: the studio's first project cover
    $first = db()->query('SELECT * FROM projects WHERE published = 1 ORDER BY sort_order ASC, year DESC, id DESC LIMIT 1')->fetch();
    if ($first && ($c = project_cover($first))) $metaImage = abs_url('uploads/' . $c);
}
$schemas = array_merge([org_schema(), [
    '@context' => 'https://schema.org', '@type' => 'WebSite', '@id' => abs_url('#website'),
    'url' => SITE_URL . '/', 'name' => SITE_NAME, 'inLanguage' => LANG,
    'publisher' => ['@id' => abs_url('#studio')],
]], $jsonLd ?? []);
?>
<!doctype html>
<html lang="<?= LANG ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($fullTitle) ?></title>
<meta name="description" content="<?= e($metaDesc) ?>">
<link rel="canonical" href="<?= e($canonical) ?>">
<link rel="alternate" hreflang="en" href="<?= e($altEn) ?>">
<link rel="alternate" hreflang="id" href="<?= e($altId) ?>">
<link rel="alternate" hreflang="x-default" href="<?= e($altEn) ?>">
<meta name="robots" content="index, follow, max-image-preview:large, max-snippet:-1">
<meta name="author" content="<?= e(SITE_NAME) ?>">
<meta name="theme-color" content="#ffffff">

<meta name="geo.region" content="<?= e(GEO['region_code']) ?>">
<meta name="geo.placename" content="<?= e(GEO['city']) ?>">
<meta name="geo.position" content="<?= GEO['lat'] ?>;<?= GEO['lng'] ?>">
<meta name="ICBM" content="<?= GEO['lat'] ?>, <?= GEO['lng'] ?>">

<meta property="og:site_name" content="<?= e(SITE_NAME) ?>">
<meta property="og:locale" content="<?= LANG === 'id' ? 'id_ID' : 'en_US' ?>">
<meta property="og:locale:alternate" content="<?= LANG === 'id' ? 'en_US' : 'id_ID' ?>">
<meta property="og:type" content="<?= e($ogType) ?>">
<meta property="og:title" content="<?= e($fullTitle) ?>">
<meta property="og:description" content="<?= e($metaDesc) ?>">
<meta property="og:url" content="<?= e($canonical) ?>">
<?php if ($metaImage): ?><meta property="og:image" content="<?= e($metaImage) ?>">
<?php endif; ?>
<meta name="twitter:card" content="<?= $metaImage ? 'summary_large_image' : 'summary' ?>">
<meta name="twitter:title" content="<?= e($fullTitle) ?>">
<meta name="twitter:description" content="<?= e($metaDesc) ?>">
<?php if ($metaImage): ?><meta name="twitter:image" content="<?= e($metaImage) ?>">
<?php endif; ?>

<link rel="stylesheet" href="<?= url('assets/css/style.css') ?>?v=<?= filemtime(__DIR__ . '/../assets/css/style.css') ?>">
<?= json_ld($schemas) ?>
</head>
<body>
<header class="bar site">
  <a class="brand" href="<?= lurl() ?>"><?= e(SITE_NAME) ?></a>
  <nav>
    <a href="<?= lurl('about.php') ?>"><?= e(t('nav_info')) ?></a>
    <a href="<?= lurl('index-list.php') ?>"><?= e(t('nav_index')) ?></a>
    <span class="end">
      <a href="<?= lurl('pricing.php') ?>"><?= e(t('nav_pricing')) ?></a>
      <a href="<?= lurl('contact.php') ?>"><?= e(t('nav_contact')) ?></a>
    </span>
  </nav>
</header>
<main>
