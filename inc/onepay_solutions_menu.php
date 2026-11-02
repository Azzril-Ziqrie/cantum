<?php
// Nav item "Solutions": a button that opens a small menu with one entry per kind of business.
// Expects $cd (product strings), $cs (solutions strings) and optionally $dropCurrent (true on the solutions page).
$dropBase = lurl('cantum-onepay-solutions.php');
?>
<div class="cd-drop">
  <button type="button" class="cd-drop-btn" aria-expanded="false" aria-haspopup="true" aria-controls="cdDrop"<?= !empty($dropCurrent) ? ' aria-current="page"' : '' ?>>
    <?= e($cd['nav'][0][0]) ?><svg viewBox="0 0 10 6" aria-hidden="true"><path d="M1 1l4 4 4-4"/></svg>
  </button>
  <div class="cd-drop-panel" id="cdDrop" hidden>
    <?php foreach ($cs['cats'] as [$slug, $name, $tag]): ?>
      <a href="<?= e($dropBase . '#' . $slug) ?>"><strong><?= e($name) ?></strong><span><?= e($tag) ?></span></a>
    <?php endforeach; ?>
  </div>
</div>
