<?php
demo_head($d, $p['name']);
require __DIR__ . '/_nav.php';
?>
<main>
  <div class="pdp">
    <div class="pdp-gallery" aria-label="Product photos">
      <?php foreach ([0, 1, 2] as $i): ?>
        <figure class="pdp-slide"><?= demo_media(['n' => $p['n'] + $i] + $p, 'alt-' . $i) ?><?= $i === 0 ? demo_tip('products', 'tr') : '' ?></figure>
      <?php endforeach; ?>
    </div>

    <div class="pdp-info">
      <h1 class="pdp-title"><?= e($p['name']) ?></h1>
      <div class="pdp-price"><?php if ($p['old']): ?><span class="was"><?= e(demo_money($p['old'])) ?></span><?php endif; ?><span><?= e(demo_money($p['price'])) ?></span></div>

      <div class="pdp-field">
        <div class="pdp-sizes" role="group" aria-label="Select size">
          <?php foreach (['S', 'M', 'L', 'XL'] as $i => $size): ?><button type="button" class="size-btn<?= $i === 1 ? ' active' : '' ?>" aria-pressed="<?= $i === 1 ? 'true' : 'false' ?>"><?= $size ?></button><?php endforeach; ?>
        </div>
      </div>

      <p class="pdp-desc"><?= e(DEMO_BLURB) ?></p>

      <div class="pdp-buy">
        <?= demo_add_button($p, 'Add to cart', 'btn btn--solid btn--block btn--square') ?>
        <?= demo_tip('cart', 'tr') ?>
      </div>

      <details class="pdp-more" open>
        <summary>Information</summary>
        <div class="pdp-body">
          <p><strong>Materials</strong><br>Cotton blend, pre-washed. Details are edited from your dashboard.</p>
          <p><strong>Shipping &amp; returns</strong><?= demo_tip('shipping', 'inline') ?><br>Shipping is calculated at checkout. Orders ship within 1-2 working days after payment.</p>
          <p><strong>Invoice</strong><?= demo_tip('invoice', 'inline') ?><br>A PDF invoice arrives by email once you pay.</p>
        </div>
      </details>
    </div>
  </div>

  <section class="pdp-related" aria-labelledby="relatedTitle">
    <h2 class="pdp-related__title" id="relatedTitle">Related products</h2>
    <div class="product-grid"><?php foreach (demo_related($d, $p, 4) as $r) echo demo_card($d, $r); ?></div>
  </section>
</main>
<?php
demo_site_footer($d);
demo_foot($d);
