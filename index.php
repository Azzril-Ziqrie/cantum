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
<?php if (!$projects): ?>
  <p class="empty"><?= e(t('empty_projects')) ?></p>
<?php else: ?>
<section class="grid">
  <?php foreach ($projects as $i => $p):
    $cover = project_cover($p);
    $w = $h = 0; $cls = 'm';
    if ($cover && ($dim = @getimagesize(UPLOAD_DIR . '/' . $cover))) {
        [$w, $h] = $dim;
        $r = $w / max($h, 1);
        $cls = $r > 1.2 ? 'l' : ($r < 0.9 ? 'p' : 'm');
    }
  ?>
    <a class="card <?= $cls ?> reveal" href="<?= lurl('project.php?slug=' . urlencode($p['slug'])) ?>">
      <div class="thumb">
        <?php if ($cover): ?>
          <img src="<?= upload_url($cover) ?>" width="<?= $w ?>" height="<?= $h ?>" alt="<?= e($p['title']) ?>" loading="lazy">
        <?php else: ?>
          <div class="ph"></div>
        <?php endif; ?>
      </div>
      <div class="cap">
        <span class="n">[<?= str_pad((string)($total - $i), 2, '0', STR_PAD_LEFT) ?>]</span>
        <span class="t"><?= e($p['title']) ?></span>
      </div>
    </a>
  <?php endforeach; ?>
</section>
<?php endif; ?>
<?php require __DIR__ . '/inc/footer.php'; ?>
