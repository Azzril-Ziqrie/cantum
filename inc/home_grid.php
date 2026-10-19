<?php
// The project collage of the home page. Shared by / and /demo/ (where the demo pop-up opens on top of it).
$projects = $projects ?? db()->query('SELECT * FROM projects WHERE published = 1 ORDER BY sort_order ASC, year DESC, id DESC')->fetchAll();
$total = count($projects);
?>
<p class="demo-line"><a href="<?= lurl('demo/') ?>" data-demo-open><?= e(t('demo_cta')) ?> <svg viewBox="0 0 14 10" aria-hidden="true"><path d="M0 5h13M9 1l4 4-4 4"/></svg></a></p>
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
    <a class="card <?= $cls ?> reveal" data-vt="p-<?= (int)$p['id'] ?>" href="<?= lurl('project.php?slug=' . urlencode($p['slug'])) ?>">
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
