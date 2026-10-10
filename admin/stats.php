<?php
require __DIR__ . '/../inc/bootstrap.php';
require __DIR__ . '/_layout.php';
require_login();

$range = (int)($_GET['d'] ?? 30);
if (!in_array($range, [7, 30, 90], true)) $range = 30;
$s = stats_range($range);
$tot = $s['totals'];
$visitors = $tot['visitors'] ?? 0;
$views = $tot['views'] ?? 0;
$wa = $tot['wa'] ?? 0;

/** Top rows of a key family (prefix "page:", "ref:", "wa:"), as [label => n]. */
function top_of(array $totals, string $prefix, int $limit = 8): array
{
    $out = [];
    foreach ($totals as $k => $n) {
        if (strpos($k, $prefix) === 0 && $k !== rtrim($prefix, ':')) $out[substr($k, strlen($prefix))] = $n;
    }
    arsort($out);
    return array_slice($out, 0, $limit, true);
}
$titles = [];
foreach (db()->query('SELECT slug, title FROM projects') as $r) $titles[$r['slug']] = $r['title'];
function page_label(string $key, array $titles): string
{
    [$lang, $path] = explode(':', $key, 2) + [1 => '/'];
    if (preg_match('/^project\.php\?slug=(.+)$/', $path, $m)) $path = ($titles[$m[1]] ?? $m[1]);
    elseif ($path === '/') $path = 'Home';
    else $path = ucfirst(preg_replace('/\.php$/', '', $path));
    return $path . ($lang === 'id' ? ' (id)' : '');
}
$pages = top_of($tot, 'page:', 10);
$refs = top_of($tot, 'ref:');
$clicks = top_of($tot, 'wa:');
$max = max(1, ...array_column($s['days'], 'visitors'));

admin_head('Stats');
?>
<div class="row between">
  <h1>Stats <small>last <?= $range ?> days</small></h1>
  <nav class="seg">
    <?php foreach ([7, 30, 90] as $d): ?>
      <a href="<?= url('admin/stats.php?d=' . $d) ?>"<?= $d === $range ? ' aria-current="true"' : '' ?>><?= $d ?>d</a>
    <?php endforeach; ?>
  </nav>
</div>

<?php if (!stats_db()): ?>
  <p class="err">Statistics need the SQLite extension on this server.</p>
<?php endif; ?>

<div class="stats four">
  <div><span class="muted">Visitors</span><strong><?= number_format($visitors, 0, ',', '.') ?></strong></div>
  <div><span class="muted">Page views</span><strong><?= number_format($views, 0, ',', '.') ?></strong></div>
  <div><span class="muted">WhatsApp clicks</span><strong><?= number_format($wa, 0, ',', '.') ?></strong></div>
  <div><span class="muted">Click rate</span><strong><?= $visitors ? number_format($wa / $visitors * 100, 1, ',', '.') . '%' : '-' ?></strong></div>
</div>

<div class="bars" role="img" aria-label="Visitors per day">
  <?php foreach ($s['days'] as $day => $v): ?>
    <span title="<?= e($day) ?>: <?= $v['visitors'] ?> visitors, <?= $v['wa'] ?> WhatsApp"><i style="height:<?= max(2, round($v['visitors'] / $max * 100)) ?>%"<?= $v['wa'] ? ' class="wa"' : '' ?>></i></span>
  <?php endforeach; ?>
</div>
<p class="muted bars-note">Daily visitors. Darker bars are days with a WhatsApp click. Your own visits while logged in are not counted.</p>

<div class="cols two">
  <section>
    <h2>Pages</h2>
    <?php if (!$pages): ?><p class="muted">No visits yet.</p><?php else: ?>
    <table class="list"><tbody>
      <?php foreach ($pages as $k => $n): ?><tr><td><?= e(page_label($k, $titles)) ?></td><td class="r"><?= $n ?></td></tr><?php endforeach; ?>
    </tbody></table>
    <?php endif; ?>
  </section>
  <section>
    <h2>WhatsApp clicks by source</h2>
    <?php if (!$clicks): ?><p class="muted">No clicks yet.</p><?php else: ?>
    <table class="list"><tbody>
      <?php foreach ($clicks as $k => $n): ?><tr><td><?= e(str_replace(':', ' / ', $k)) ?></td><td class="r"><?= $n ?></td></tr><?php endforeach; ?>
    </tbody></table>
    <?php endif; ?>
  </section>
  <section>
    <h2>Where visitors come from</h2>
    <?php if (!$refs): ?><p class="muted">No visits yet.</p><?php else: ?>
    <table class="list"><tbody>
      <?php foreach ($refs as $k => $n): ?><tr><td><?= e($k) ?></td><td class="r"><?= $n ?></td></tr><?php endforeach; ?>
    </tbody></table>
    <?php endif; ?>
  </section>
</div>
<?php admin_foot(); ?>
