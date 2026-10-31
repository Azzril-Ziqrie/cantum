<?php
// CantumDine-IN: a product page that stands on its own (no Cantum Studio header or footer, own loading screen).
require __DIR__ . '/inc/bootstrap.php';

$cd = t('cd');
$fullTitle = t('title_cd');
$metaDesc = t('desc_cd');
$canonicalPath = 'cantumdine.php';
$canonical = abs_url((LANG === 'id' ? 'id/' : '') . $canonicalPath);
$altEn = abs_url($canonicalPath);
$altId = abs_url('id/' . $canonicalPath);
if (http_response_code() === 200) stats_pageview(LANG . ':' . $canonicalPath);

$schemas = [org_schema(), [
    '@context' => 'https://schema.org', '@type' => 'Product', 'name' => 'CantumDine-IN', 'description' => $metaDesc,
    'brand' => ['@id' => abs_url('#studio')], 'inLanguage' => LANG, 'url' => $canonical,
    'image' => abs_url('assets/img/table-lines.png'), 'category' => 'Restaurant ordering software',
]];

$asset = fn(string $f) => url('assets/' . $f) . '?v=' . filemtime(ROOT . '/assets/' . $f);
$wa = wa_link(t('wa_msg_cd'));
$logo = url('assets/img/table-lines.png');
$fillUrl = url('assets/img/table-fill.png');
$otherLang = url($cd['lang_path']);
$cdSel = $cd['tables'][3];   // table 4 is the "new order" table shown by default
$arrow = '<svg viewBox="0 0 14 10" aria-hidden="true"><path d="M0 5h13M9 1l4 4-4 4"/></svg>';
$icons = [
    '<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="7" y="3" width="10" height="18" rx="2.5"/><path d="M10.5 18h3"/></svg>',
    '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7l2 2 3-3.5M4 14l2 2 3-3.5M12 7.5h8M12 14.5h8"/></svg>',
    '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="6" r="2.3"/><circle cx="5.5" cy="17.5" r="2.3"/><circle cx="18.5" cy="17.5" r="2.3"/><path d="M12 8.3v3.2M12 11.5l-5 4M12 11.5l5 4"/></svg>',
];
$m = $cd['mini'];
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
<meta name="theme-color" content="#e9e9e9">
<link rel="icon" type="image/png" href="<?= e($logo) ?>">
<meta property="og:site_name" content="CantumDine-IN">
<meta property="og:locale" content="<?= LANG === 'id' ? 'id_ID' : 'en_US' ?>">
<meta property="og:type" content="website">
<meta property="og:title" content="<?= e($fullTitle) ?>">
<meta property="og:description" content="<?= e($metaDesc) ?>">
<meta property="og:url" content="<?= e($canonical) ?>">
<meta property="og:image" content="<?= e(abs_url('assets/img/table-lines.png')) ?>">
<meta name="twitter:card" content="summary">
<meta name="twitter:title" content="<?= e($fullTitle) ?>">
<meta name="twitter:description" content="<?= e($metaDesc) ?>">
<script>document.documentElement.classList.add('js', 'is-loading'); setTimeout(function () { var r = document.documentElement.classList; r.remove('is-loading'); r.add('is-ready'); }, 7000);</script>
<link rel="stylesheet" href="<?= e($asset('css/cantumdine.css')) ?>">
<?= json_ld($schemas) ?>
</head>
<body>

<div class="cd-load" id="cdLoad" data-fill="<?= e($fillUrl) ?>" aria-hidden="true">
  <p class="cd-load-brand"><?= e($cd['notif_app']) ?></p>
  <div class="cd-load-table">
    <canvas class="cd-load-fill"></canvas>
    <img src="<?= e($logo) ?>" width="480" height="378" alt="" draggable="false">
  </div>
  <p class="cd-load-count"><span id="cdCount">1</span><small>%</small></p>
  <p class="cd-load-label"><?= e($cd['load']) ?></p>
</div>

<div class="cd-app">
  <header class="cd-nav">
    <a class="cd-logo" href="<?= lurl('cantumdine.php') ?>" aria-label="CantumDine-IN"><img src="<?= e($logo) ?>" width="480" height="378" alt=""><span>CantumDine-IN</span></a>
    <nav class="cd-links" aria-label="CantumDine-IN">
      <?php foreach ($cd['nav'] as [$label, $href]): ?><a href="<?= e($href) ?>"><?= e($label) ?></a><?php endforeach; ?>
    </nav>
    <div class="cd-nav-end">
      <a class="cd-quiet" href="<?= e($otherLang) ?>" hreflang="<?= LANG === 'id' ? 'en' : 'id' ?>"><?= e($cd['lang_label']) ?></a>
      <a class="cd-outline" href="<?= e($wa ?: lurl('contact.php')) ?>"<?= $wa ? ' target="_blank" rel="noopener" data-wa="cantumdine:nav"' : '' ?>><?= e($cd['demo']) ?></a>
    </div>
  </header>

  <main>
    <section class="cd-hero">
      <aside class="fc fc-note" aria-hidden="true"><span class="pin"></span><p><?= e($cd['note']) ?></p></aside>
      <aside class="fc fc-wait" aria-hidden="true">
        <svg viewBox="0 0 40 40"><circle cx="20" cy="20" r="15" fill="#fff" stroke="#0b0b0b" stroke-width="2.5"/><path d="M20 11v9l6 4" fill="none" stroke="#0b0b0b" stroke-width="2.5" stroke-linecap="round"/></svg>
        <div><b><?= e($cd['wait_title']) ?></b><span><?= e($cd['wait_text']) ?></span></div>
      </aside>
      <aside class="fc fc-orders" aria-hidden="true">
        <b><?= e($cd['orders_title']) ?></b>
        <?php foreach ($cd['orders'] as [$tb, $what, $pct, $st]): ?>
          <div class="ord"><span><?= e($tb) ?> · <?= e($what) ?></span><i><u style="width:<?= (int)$pct ?>%"></u></i><em><?= e($st) ?> <?= (int)$pct ?>%</em></div>
        <?php endforeach; ?>
      </aside>
      <aside class="fc fc-pay" aria-hidden="true">
        <b><?= e($cd['pay_title']) ?></b>
        <div><?php foreach ($cd['pay'] as $pm): ?><span><?= e($pm) ?></span><?php endforeach; ?></div>
      </aside>

      <div class="cd-hero-in">
        <span class="cd-tile" data-in style="--i:0"><img src="<?= e($logo) ?>" width="480" height="378" alt=""></span>
        <h1 class="cd-h1" data-in style="--i:1"><?= e($cd['h1a']) ?><br><span><?= e($cd['h1b']) ?></span></h1>
        <p class="cd-lead" data-in style="--i:2"><?= e($cd['lead']) ?></p>
        <p class="cd-actions" data-in style="--i:3">
          <a class="cd-btn" href="<?= e($wa ?: lurl('contact.php')) ?>"<?= $wa ? ' target="_blank" rel="noopener" data-wa="cantumdine"' : '' ?>><?= e($wa ? $cd['cta_wa'] : $cd['cta_contact']) ?></a>
        </p>
      </div>
    </section>

    <section class="cd-sec" id="solutions">
      <div class="cd-head rv"><span class="cd-pill"><?= e($cd['sol_pill']) ?></span><h2><?= e($cd['sol_h']) ?></h2></div>
      <div class="cd-sol rv">
        <?php foreach ($cd['sol'] as $i => $txt): ?><div><?= $icons[$i] ?><p><?= e($txt) ?></p></div><?php endforeach; ?>
      </div>

      <div class="cd-panel rv" aria-label="<?= e($cd['stage_label']) ?>">
        <div class="cd-say" id="cdToast" role="status" aria-live="polite" data-words="<?= e(json_encode($cd['says'], JSON_UNESCAPED_UNICODE)) ?>">
          <div class="bubble" aria-hidden="true"><span class="dots"><i></i><i></i><i></i></span><div class="say"><b data-n="word"></b><ul data-n="items"></ul></div></div>
          <span class="cd-sr" data-n="text"></span>
        </div>
        <span class="cd-badge cd-badge--ok" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg></span>
        <span class="cd-badge cd-badge--no" aria-hidden="true">QRIS</span>

        <div class="cd-stage">
          <div class="cd-phone" aria-hidden="true">
            <div class="cd-phone-top"><strong><?= e($cd['phone_brand']) ?></strong><span><?= e($cd['phone_table']) ?></span></div>
            <ul class="cd-phone-list">
              <?php foreach ($cd['phone_items'] as [$n, $p]): ?><li><span><?= e($n) ?></span><span><?= e($p) ?></span></li><?php endforeach; ?>
            </ul>
            <div class="cd-phone-bar"><span><?= e($cd['phone_sum']) ?></span><b><?= e($cd['phone_pay']) ?></b></div>
          </div>

          <div class="cd-board" id="cdBoard" data-empty="<?= e($cd['empty_table']) ?>" data-table="<?= e($cd['table']) ?>" data-fill="<?= e($fillUrl) ?>"
               data-new="<?= e($cd['new_order']) ?>" data-now="<?= e($cd['now']) ?>" data-pool="<?= e(json_encode($cd['pool'], JSON_UNESCAPED_UNICODE)) ?>" data-labels="<?= e(json_encode($cd['status'], JSON_UNESCAPED_UNICODE)) ?>">
            <div class="cd-board-head"><p class="cd-board-title"><?= e($cd['floor_title']) ?></p></div>
            <div class="cd-iso"><div class="cd-tables" role="group" aria-label="<?= e($cd['floor_title']) ?>">
              <?php foreach ($cd['tables'] as [$no, $st, $when, $items]): ?>
                <button type="button" class="cd-tbl is-<?= e($st) ?><?= $no === 4 ? ' is-on' : '' ?>" aria-pressed="<?= $no === 4 ? 'true' : 'false' ?>"
                        data-no="<?= $no ?>" data-status="<?= e($st) ?>" data-label="<?= e($cd['status'][$st]) ?>" data-when="<?= e($when) ?>" data-items="<?= e(json_encode($items, JSON_UNESCAPED_UNICODE)) ?>">
                  <span class="cd-pad" aria-hidden="true"></span>
                  <span class="cd-shadow" aria-hidden="true"></span>
                  <span class="cd-sprite" aria-hidden="true">
                    <canvas class="cd-fill"></canvas>
                    <img class="cd-lines" src="<?= e($logo) ?>" width="480" height="378" alt="" draggable="false">
                    <b class="cd-tbl-no"><?= $no ?></b>
                  </span>
                  <span class="cd-sr"><?= e($cd['table']) ?> <?= $no ?>, <?= e($cd['status'][$st]) ?></span>
                </button>
              <?php endforeach; ?>
            </div></div>
            <div class="cd-detail" id="cdDetail" aria-live="polite">
              <p class="cd-detail-head"><strong><?= e($cd['table']) ?> <span data-d="no"><?= (int)$cdSel[0] ?></span></strong><span data-d="meta"><?= e($cd['status'][$cdSel[1]]) ?> · <?= e($cdSel[2]) ?></span></p>
              <ul data-d="items"><?php foreach ($cdSel[3] as $it): ?><li><?= e($it) ?></li><?php endforeach; ?></ul>
            </div>
          </div>
        </div>
      </div>
    </section>

    <section class="cd-sec" id="how">
      <div class="cd-head rv"><span class="cd-pill"><?= e($cd['how_pill']) ?></span><h2><?= e($cd['how_h']) ?></h2></div>
      <ol class="cd-flow">
        <?php foreach ($cd['flow'] as $i => [$n, $d]): ?>
          <li class="rv" style="--d:<?= $i * 70 ?>ms"><span class="cd-num">0<?= $i + 1 ?></span><h3><?= e($n) ?></h3><p><?= e($d) ?></p></li>
        <?php endforeach; ?>
      </ol>
    </section>

    <section class="cd-sec cd-sec--panel" id="features">
      <div class="cd-head rv"><span class="cd-pill"><?= e($cd['feat_pill']) ?></span><h2><?= e($cd['feat_h']) ?></h2><p><?= e($cd['feat_sub']) ?></p></div>
      <div class="cd-feat">
        <?php foreach ($cd['feat'] as $i => [$title, $text]): ?>
          <article class="cd-card rv" style="--d:<?= ($i % 3) * 70 ?>ms">
            <div class="cd-art cd-art--<?= $i ?>" aria-hidden="true">
              <?php if ($i === 0): ?>
                <div class="a-phone"><i></i><i></i><i></i><b></b></div>
              <?php elseif ($i === 1): ?>
                <div class="a-floor"><i></i><i></i><i class="hot"></i><i></i><i></i><i></i></div>
              <?php elseif ($i === 2): ?>
                <div class="a-kds"><div><small><?= e($m[0]) ?></small><i></i><i></i></div><div><small><?= e($m[1]) ?></small><i></i></div></div>
              <?php elseif ($i === 3): ?>
                <div class="a-ticket"><small><?= e($m[2]) ?></small><b><?= e($m[3]) ?></b></div>
              <?php elseif ($i === 4): ?>
                <div class="a-branch"><span><?= e($m[4]) ?></span><span><?= e($m[5]) ?></span><span><?= e($m[6]) ?></span></div>
              <?php else: ?>
                <div class="a-bars"><i style="height:38%"></i><i style="height:62%"></i><i style="height:84%"></i><i style="height:52%"></i><i style="height:72%"></i></div>
              <?php endif; ?>
            </div>
            <h3><?= e($title) ?></h3>
            <p><?= e($text) ?></p>
          </article>
        <?php endforeach; ?>
      </div>
      <p class="cd-more rv"><?= e($cd['feat_more']) ?></p>
    </section>

    <section class="cd-sec" id="model">
      <div class="cd-head rv"><span class="cd-pill"><?= e($cd['model_pill']) ?></span><h2><?= e($cd['model_h']) ?></h2></div>
      <div class="cd-model">
        <?php foreach ($cd['model'] as $i => [$n, $d]): ?>
          <div class="rv" style="--d:<?= $i * 70 ?>ms"><h3><?= e($n) ?></h3><p><?= e($d) ?></p></div>
        <?php endforeach; ?>
      </div>
    </section>

    <section class="cd-end rv">
      <img src="<?= e($logo) ?>" width="480" height="378" alt="">
      <h2><?= e($cd['end_title']) ?></h2>
      <p><?= e($cd['end_text']) ?></p>
      <p class="cd-actions">
        <a class="cd-btn cd-btn--light" href="<?= e($wa ?: lurl('contact.php')) ?>"<?= $wa ? ' target="_blank" rel="noopener" data-wa="cantumdine:end"' : '' ?>><?= e($wa ? $cd['cta_wa'] : $cd['cta_contact']) ?></a>
        <a class="cd-link" href="<?= lurl('contact.php') ?>"><?= e($cd['cta_contact']) ?> <?= $arrow ?></a>
      </p>
    </section>
  </main>

  <footer class="cd-foot">
    <span><?= e($cd['foot']) ?></span>
    <span><a href="<?= lurl() ?>"><?= e($cd['back']) ?></a> · <a href="<?= e($otherLang) ?>" hreflang="<?= LANG === 'id' ? 'en' : 'id' ?>"><?= e($cd['lang_label']) ?></a></span>
  </footer>
</div>

<script src="<?= e($asset('js/dither.js')) ?>" defer></script>
<script src="<?= e($asset('js/cantumdine-loader.js')) ?>" defer></script>
<script src="<?= e($asset('js/cantumdine.js')) ?>" defer></script>
<script src="<?= e($asset('js/main.js')) ?>" data-track="<?= e(url('track.php')) ?>" defer></script>
</body>
</html>
