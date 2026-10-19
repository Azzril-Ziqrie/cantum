<?php
// The "demo your website" pop-up: brand name, logo, products, layout, one glass card per step.
// Rendered on the home page and on /demo/ (where it opens by itself). assets/js/demo-flow.js drives the steps and the
// form posts to /demo/, which creates the demo (see demo/index.php).
require_once __DIR__ . '/demo.php';
/** @var array $demoErrors @var string $demoBrand @var string $demoLayout @var bool $demoAutoOpen */
$demoErrors = $demoErrors ?? [];
$demoBrand = $demoBrand ?? '';
$demoLayout = $demoLayout ?? DEMO_LAYOUTS[0];
$demoAutoOpen = $demoAutoOpen ?? false;
$asset = fn(string $f) => url('assets/' . $f) . '?v=' . filemtime(ROOT . '/assets/' . $f);
$steps = 4;
$cross = '<svg viewBox="0 0 14 14" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.2" stroke-linecap="round" aria-hidden="true"><path d="M2 2l10 10M12 2L2 12"/></svg>';
?>
<link rel="stylesheet" href="<?= e($asset('css/demo-flow.css')) ?>">
<dialog class="flow" id="demoFlow" aria-label="<?= e(t('h1_demo')) ?>"
        data-step-label="<?= e(t('demo_step')) ?>" data-next="<?= e(t('demo_next')) ?>" data-submit="<?= e(t('demo_submit')) ?>" data-submitting="<?= e(t('demo_submitting')) ?>"
        data-err-brand="<?= e(t('demo_err_brand')) ?>" data-err-file="<?= e(t('demo_err_file')) ?>" data-err-many="<?= e(t('demo_err_many')) ?>"
        data-remove="<?= e(t('demo_remove')) ?>"<?= $demoAutoOpen ? ' data-autoopen="1"' : '' ?>>
  <form method="post" action="<?= e(lurl('demo/')) ?>" enctype="multipart/form-data" novalidate>
    <div class="flow-card">
      <button type="button" class="flow-x" data-flow-close aria-label="<?= e(t('demo_close')) ?>"><?= $cross ?></button>
      <div class="flow-progress" aria-hidden="true"><?php for ($i = 0; $i < $steps; $i++): ?><i></i><?php endfor; ?></div>
      <p class="flow-error" role="alert" aria-live="assertive"<?= $demoErrors ? '' : ' hidden' ?>><?= e(implode(' ', $demoErrors)) ?></p>

      <section class="step" data-step="brand">
        <p class="flow-eyebrow"><?= e(sprintf(t('demo_step'), 1, $steps)) ?></p>
        <h2><?= e(t('demo_q_brand')) ?></h2>
        <input class="flow-input" type="text" name="brand" value="<?= e($demoBrand) ?>" placeholder="<?= e(t('demo_brand_ph')) ?>" maxlength="40" autocomplete="off" autocapitalize="words" spellcheck="false" aria-label="<?= e(t('demo_brand_ph')) ?>" data-focus>
        <p class="flow-hint"><?= e(t('demo_hint_enter')) ?> <kbd>↵</kbd></p>
      </section>

      <section class="step" data-step="logo" hidden>
        <p class="flow-eyebrow"><?= e(sprintf(t('demo_step'), 2, $steps)) ?></p>
        <h2><?= e(t('demo_q_logo')) ?></h2>
        <label class="flow-drop" data-drop="logo"><input type="file" name="logo" accept="image/png,image/jpeg" data-focus><span class="flow-drop-title"><?= e(t('demo_drop_logo')) ?></span><span class="flow-hint"><?= e(t('demo_logo_hint')) ?></span></label>
        <div class="flow-preview" id="logoPreview" hidden><img alt=""><span class="flow-file-name"></span><button type="button" class="flow-link" data-remove-logo><?= e(t('demo_remove')) ?></button></div>
      </section>

      <section class="step" data-step="products" hidden>
        <p class="flow-eyebrow"><?= e(sprintf(t('demo_step'), 3, $steps)) ?></p>
        <h2><?= e(t('demo_q_products')) ?></h2>
        <label class="flow-drop" data-drop="products"><input type="file" name="products[]" accept="image/png,image/jpeg" multiple data-focus><span class="flow-drop-title"><?= e(t('demo_drop_products')) ?></span><span class="flow-hint"><?= e(t('demo_products_hint')) ?></span></label>
        <div class="flow-thumbs" id="productThumbs" hidden></div>
      </section>

      <section class="step" data-step="layout" hidden>
        <p class="flow-eyebrow"><?= e(sprintf(t('demo_step'), 4, $steps)) ?></p>
        <h2><?= e(t('demo_q_layout')) ?></h2>
        <div class="flow-layouts" role="radiogroup" aria-label="<?= e(t('demo_q_layout')) ?>">
          <?php foreach (DEMO_LAYOUTS as $key): ?>
            <label class="flow-layout<?= $key === $demoLayout ? ' is-on' : '' ?>">
              <input type="radio" name="layout" value="<?= e($key) ?>"<?= $key === $demoLayout ? ' checked' : '' ?><?= $key === $demoLayout ? ' data-focus' : '' ?>>
              <span class="wf wf-<?= e($key) ?>" aria-hidden="true"><?php for ($i = 0; $i < 9; $i++): ?><i></i><?php endfor; ?></span>
              <strong><?= e(t('demo_layout_' . $key)) ?></strong>
              <small><?= e(t('demo_layout_' . $key . '_d')) ?></small>
            </label>
          <?php endforeach; ?>
        </div>
        <p class="flow-privacy"><?= e(t('demo_privacy')) ?></p>
      </section>

      <input type="text" name="website" class="flow-hp" tabindex="-1" autocomplete="off" aria-hidden="true">
      <div class="flow-nav">
        <button type="button" class="flow-btn flow-btn--ghost" data-flow-back hidden><?= e(t('demo_back')) ?></button>
        <span class="flow-spacer"></span>
        <button type="button" class="flow-btn flow-btn--plain" data-flow-skip hidden><?= e(t('demo_skip')) ?></button>
        <button type="button" class="flow-btn flow-btn--solid" data-flow-next><?= e(t('demo_next')) ?></button>
      </div>
    </div>
  </form>
</dialog>
<noscript><style>.flow { display: block; position: static; margin: 24px auto; } .flow .step[hidden], .flow .flow-card { opacity: 1; transform: none; filter: none; } .flow .step { display: grid; } .flow .step[hidden] { display: grid; }</style></noscript>
<script src="<?= e($asset('js/demo-flow.js')) ?>" defer></script>
