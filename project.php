<?php
require __DIR__ . '/inc/bootstrap.php';

$st = db()->prepare('SELECT * FROM projects WHERE slug = ? AND published = 1');
$st->execute([$_GET['slug'] ?? '']);
$p = $st->fetch();
if (!$p) {
    http_response_code(404);
    $pageTitle = 'Not found';
    require __DIR__ . '/inc/header.php';
    echo '<p class="empty">' . e(LANG === 'id' ? 'Proyek tidak ditemukan.' : 'Project not found.') . ' <a href="' . lurl() . '">' . e(t('back_all')) . '</a></p>';
    require __DIR__ . '/inc/footer.php';
    exit;
}

$imgs = db()->prepare('SELECT * FROM images WHERE project_id = ? ORDER BY sort_order, id');
$imgs->execute([$p['id']]);
$images = $imgs->fetchAll();

// prev / next in the same ordering as the home page
$all = db()->query('SELECT slug, title FROM projects WHERE published = 1 ORDER BY sort_order ASC, year DESC, id DESC')->fetchAll();
$idx = array_search($p['slug'], array_column($all, 'slug'));
$next = $all[($idx + 1) % count($all)];
$num = str_pad((string)(count($all) - $idx), 2, '0', STR_PAD_LEFT);

$pageTitle = $p['title'] . ($p['category'] ? ' | ' . $p['category'] : '');
$plain = trim(preg_replace('/\s+/', ' ', (string)$p['description']));
$yr = $p['year'] ? ' (' . $p['year'] . ')' : '';
$metaDesc = $plain !== ''
    ? mb_substr($plain, 0, 155) . (mb_strlen($plain) > 155 ? '…' : '')
    : (LANG === 'id'
        ? $p['title'] . ', proyek ' . strtolower($p['category']) . $yr . ' oleh Cantum Studio, studio pembuatan website di Bogor.'
        : $p['title'] . ', ' . strtolower($p['category']) . ' project' . $yr . ' by Cantum Studio, a web design studio in Bogor.');
$canonicalPath = 'project.php?slug=' . $p['slug'];
$pageUrl = abs_url((LANG === 'id' ? 'id/' : '') . $canonicalPath);
$ogType = 'article';
$cv = project_cover($p);
$link = trim((string)($p['link'] ?? ''));
$linkHost = $link !== '' ? preg_replace('/^www\./', '', (string)parse_url($link, PHP_URL_HOST)) . rtrim((string)parse_url($link, PHP_URL_PATH), '/') : '';
$metaImage = $cv ? abs_url('uploads/' . $cv) : null;
$jsonLd = [[
    '@context' => 'https://schema.org', '@type' => 'CreativeWork', 'inLanguage' => LANG,
    'name' => $p['title'], 'url' => $pageUrl, 'description' => $metaDesc,
    'genre' => $p['category'], 'dateCreated' => $p['year'] ?: null,
    'locationCreated' => $p['location'] ?: null,
    'sameAs' => $link ?: null,
    'creator' => ['@id' => abs_url('#studio')],
    'image' => array_map(fn($im) => abs_url('uploads/' . $im['filename']), $images),
], [
    '@context' => 'https://schema.org', '@type' => 'BreadcrumbList', 'itemListElement' => [
        ['@type' => 'ListItem', 'position' => 1, 'name' => t('bc_work'), 'item' => abs_url(LANG === 'id' ? 'id/' : '')],
        ['@type' => 'ListItem', 'position' => 2, 'name' => $p['title'], 'item' => $pageUrl],
    ],
]];
$jsonLd[0] = array_filter($jsonLd[0], fn($v) => $v !== null && $v !== '' && $v !== []);
require __DIR__ . '/inc/header.php';
?>
<article class="project">
  <header class="phead">
    <h1>[<?= $num ?>] <?= e($p['title']) ?></h1>
    <span class="meta"><?= e($p['category']) ?></span>
    <span class="meta"><?= e($p['location']) ?></span>
    <span class="meta"><?= e($p['year']) ?></span>
  </header>

  <?php if ($p['description']): ?>
    <div class="desc" data-generate><?= nl2br(e($p['description'])) ?></div>
  <?php endif; ?>

  <?php if ($link !== ''): ?>
    <div class="plink"><span class="lbl"><?= e(t('lbl_live')) ?></span><a href="<?= e($link) ?>" target="_blank" rel="noopener"><?= e($linkHost) ?> <span aria-hidden="true">↗</span></a></div>
  <?php endif; ?>

  <div class="gallery" data-pager<?= count($images) > 1 ? ' tabindex="0" role="region" aria-label="' . e($p['title']) . '"' : '' ?>>
    <?php foreach ($images as $k => $im):
        $w = $h = 0; $cls = 'm';
        if ($dim = @getimagesize(UPLOAD_DIR . '/' . $im['filename'])) {
            [$w, $h] = $dim;
            $r = $w / max($h, 1);
            $cls = $r > 1.2 ? 'l' : ($r < 0.9 ? 'p' : 'm');
        }
    ?>
      <figure class="<?= $cls . ($k === 0 ? '' : ' reveal') ?>"<?= $k === 0 ? ' style="view-transition-name:p-' . (int)$p['id'] . '"' : '' ?>><img src="<?= upload_url($im['filename']) ?>" width="<?= $w ?>" height="<?= $h ?>" alt="<?= e($p['title']) ?>"<?= $k === 0 ? '' : ' loading="lazy"' ?>></figure>
    <?php endforeach; ?>
  </div>
  <?php if (count($images) > 1): ?>
    <div class="gcount" aria-hidden="true"><span class="gcn">1 / <?= count($images) ?></span><span class="gbar"><span></span></span></div>
  <?php endif; ?>

  <nav class="pnav">
    <a href="<?= lurl() ?>"><?= e(t('back_all')) ?></a>
    <?php if ($next['slug'] !== $p['slug']): ?>
      <a href="<?= lurl('project.php?slug=' . urlencode($next['slug'])) ?>"><?= e(t('next')) ?><?= e($next['title']) ?> →</a>
    <?php endif; ?>
  </nav>
</article>
<?php require __DIR__ . '/inc/footer.php'; ?>
