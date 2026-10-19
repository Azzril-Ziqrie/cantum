<?php
require __DIR__ . '/inc/bootstrap.php';

$projects = db()->query('SELECT * FROM projects WHERE published = 1 ORDER BY sort_order ASC, year DESC, id DESC')->fetchAll();
$total = count($projects);

$active = 'work';
$fullTitle = t('title_home');
$metaDesc = t('desc_home');
$canonicalPath = '';
$firstCover = $projects ? project_cover($projects[0]) : null;
$metaImage = $firstCover ? abs_url('uploads/' . $firstCover) : null;
$jsonLd = [[
    '@context' => 'https://schema.org', '@type' => 'CollectionPage', 'name' => t('collection_name'),
    'url' => abs_url((LANG === 'id' ? 'id/' : '')), 'isPartOf' => ['@id' => abs_url('#website')], 'inLanguage' => LANG,
    'mainEntity' => ['@type' => 'ItemList', 'itemListElement' => array_map(fn($x, $n) => [
        '@type' => 'ListItem', 'position' => $n + 1, 'url' => abs_url((LANG === 'id' ? 'id/' : '') . 'project.php?slug=' . $x['slug']), 'name' => $x['title'],
    ], $projects, array_keys($projects))],
]];
require __DIR__ . '/inc/header.php';
?>
<h1 class="sr-only"><?= e(LANG === 'en' ? setting('headline', t('h1_home')) : t('h1_home')) ?></h1>
<p class="sr-only"><?= e(t('sr_home')) ?></p>
<?php require __DIR__ . '/inc/home_grid.php'; ?>
<?php require __DIR__ . '/inc/demo_flow.php'; ?>
<?php require __DIR__ . '/inc/footer.php'; ?>
