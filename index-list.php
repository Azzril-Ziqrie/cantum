<?php
require __DIR__ . '/inc/bootstrap.php';

$projects = db()->query('SELECT * FROM projects WHERE published = 1 ORDER BY sort_order ASC, year DESC, id DESC')->fetchAll();
$total = count($projects);

$pageTitle = t('title_index');
$metaDesc = t('desc_index');
$canonicalPath = 'index-list.php';
require __DIR__ . '/inc/header.php';
?>
<?php if (!$projects): ?>
  <p class="empty"><?= e(t('empty_projects')) ?></p>
<?php else: ?>
<ul class="index">
  <?php foreach ($projects as $i => $p): ?>
  <li><a href="<?= lurl('project.php?slug=' . urlencode($p['slug'])) ?>">
    <span>[<?= str_pad((string)($total - $i), 2, '0', STR_PAD_LEFT) ?>] <?= e($p['title']) ?></span>
    <span class="hide"><?= e($p['category']) ?></span>
    <span class="hide"><?= e($p['location']) ?></span>
    <span class="r"><?= e($p['year']) ?></span>
  </a></li>
  <?php endforeach; ?>
</ul>
<?php endif; ?>
<?php require __DIR__ . '/inc/footer.php'; ?>
