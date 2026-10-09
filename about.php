<?php
require __DIR__ . '/inc/bootstrap.php';

$faq = t('faq');
$services = LANG === 'id' ? SERVICES_ID : SERVICES;

$pageTitle = t('title_about');
$metaDesc = t('desc_about');
$canonicalPath = 'about.php';
$jsonLd = [[
    '@context' => 'https://schema.org', '@type' => 'FAQPage', 'inLanguage' => LANG,
    'mainEntity' => array_map(fn($f) => ['@type' => 'Question', 'name' => $f[0], 'acceptedAnswer' => ['@type' => 'Answer', 'text' => $f[1]]], $faq),
], [
    '@context' => 'https://schema.org', '@type' => 'AboutPage', 'url' => abs_url((LANG === 'id' ? 'id/' : '') . 'about.php'),
    'name' => t('about_name'), 'inLanguage' => LANG, 'about' => ['@id' => abs_url('#studio')],
]];
require __DIR__ . '/inc/header.php';

// Editable text from the dashboard: English in "About text", Indonesian in "About text (Indonesian)".
$aboutText = LANG === 'id' ? (setting('about_text_id') ?: t('about_default')) : (setting('about_text') ?: t('about_default'));
?>
<section class="about">
  <div class="lead">
    <h1><?= e(LANG === 'en' && setting('about_title') ? setting('about_title') : t('nav_info')) ?></h1>
    <div class="prose"><?= nl2br(e($aboutText)) ?></div>

    <div class="acc">
      <details>
        <summary><?= e(t('h_services')) ?></summary>
        <ul class="plain">
          <?php foreach ($services as $s): ?><li><?= e($s) ?></li><?php endforeach; ?>
        </ul>
      </details>

      <details>
        <summary><?= e(t('h_faq')) ?></summary>
        <div class="faq">
          <?php foreach ($faq as $f): ?>
          <details>
            <summary><?= e($f[0]) ?></summary>
            <p><?= e($f[1]) ?></p>
          </details>
          <?php endforeach; ?>
        </div>
      </details>

      <details>
        <summary><?= e(t('h_based')) ?></summary>
        <p class="txt"><?= e(t('based_text')) ?></p>
      </details>
    </div>
  </div>
</section>
<?php require __DIR__ . '/inc/footer.php'; ?>
