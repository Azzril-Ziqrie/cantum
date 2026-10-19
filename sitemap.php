<?php
require __DIR__ . '/inc/bootstrap.php';
header('Content-Type: application/xml; charset=utf-8');

// path, lastmod, priority, [image files], image title — each emitted for /en (root) and /id/ with hreflang alternates.
$mod = fn(string $f) => date('Y-m-d', filemtime(__DIR__ . '/' . $f));
$latest = db()->query('SELECT MAX(created_at) FROM projects WHERE published = 1')->fetchColumn();
$pages = [
    ['', max($mod('index.php'), substr((string)$latest, 0, 10)), '1.0', [], ''],
    ['about.php', $mod('about.php'), '0.8', [], ''],
    ['demo/', $mod('demo/index.php'), '0.8', [], ''],
    ['pricing.php', $mod('pricing.php'), '0.8', [], ''],
    ['index-list.php', $mod('index-list.php'), '0.7', [], ''],
    ['contact.php', $mod('contact.php'), '0.7', [], ''],
];
$imgs = db()->prepare('SELECT filename FROM images WHERE project_id = ? ORDER BY sort_order, id');
foreach (db()->query('SELECT * FROM projects WHERE published = 1 ORDER BY id DESC')->fetchAll() as $p) {
    $imgs->execute([$p['id']]);
    $pages[] = ['project.php?slug=' . $p['slug'], substr($p['created_at'], 0, 10), '0.9', $imgs->fetchAll(PDO::FETCH_COLUMN), $p['title']];
}
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml" xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">
<?php foreach ($pages as [$path, $lastmod, $prio, $files, $title]):
    $en = abs_url($path); $id = abs_url('id/' . $path);
    foreach ([$en, $id] as $loc): ?>
  <url>
    <loc><?= e($loc) ?></loc>
<?php if ($lastmod): ?>    <lastmod><?= e($lastmod) ?></lastmod>
<?php endif; ?>
    <xhtml:link rel="alternate" hreflang="en" href="<?= e($en) ?>"/>
    <xhtml:link rel="alternate" hreflang="id" href="<?= e($id) ?>"/>
    <xhtml:link rel="alternate" hreflang="x-default" href="<?= e($en) ?>"/>
    <priority><?= $prio ?></priority>
<?php foreach ($files as $f): ?>
    <image:image><image:loc><?= e(abs_url('uploads/' . $f)) ?></image:loc><image:title><?= e($title) ?></image:title></image:image>
<?php endforeach; ?>
  </url>
<?php endforeach; endforeach; ?>
</urlset>
