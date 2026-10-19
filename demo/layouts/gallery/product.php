<?php
demo_head($d, $p['name']);
require __DIR__ . '/_nav.php';
?>
<main>
  <div class="gl-pdp">
    <div class="gl-pdp-media">
      <div class="gl-frame"><?= demo_media($p) ?><?= demo_tip('products', 'tr') ?></div>
      <div class="gl-thumbs">
        <div class="gl-frame"><?= demo_media(['n' => $p['n'] + 1] + $p, 'alt-1') ?></div>
        <div class="gl-frame"><?= demo_media(['n' => $p['n'] + 2] + $p, 'alt-2') ?></div>
      </div>
    </div>

    <div class="gl-pdp-info">
      <p class="gl-crumb"><a href="<?= e(demo_shop_url($d)) ?>">Shop</a> / <a href="<?= e(demo_shop_url($d, $p['cat'])) ?>"><?= e($p['cat']) ?></a></p>
      <h1 class="gl-pname"><?= e($p['name']) ?></h1>
      <p class="gl-pprice"><span><?= e(demo_money($p['price'])) ?></span><?php if ($p['old']): ?><s><?= e(demo_money($p['old'])) ?></s><?php endif; ?></p>
      <p class="gl-blurb"><?= e(DEMO_BLURB) ?></p>

      <div class="gl-sizes" role="group" aria-label="Select size">
        <?php foreach (['S', 'M', 'L', 'XL'] as $i => $size): ?><button type="button" class="size-btn<?= $i === 1 ? ' active' : '' ?>" aria-pressed="<?= $i === 1 ? 'true' : 'false' ?>"><?= $size ?></button><?php endforeach; ?>
      </div>

      <div class="gl-buy">
        <?= demo_qty() ?>
        <?= demo_add_button($p, 'Add to cart', 'btn btn--solid') ?>
        <?= demo_tip('cart', 'tr') ?>
      </div>

      <div class="gl-acc">
        <details open><summary>Details</summary><p>Cotton blend, pre-washed. Edit every detail from your dashboard.</p></details>
        <details><summary>Shipping<?= demo_tip('shipping', 'inline') ?></summary><p>Orders ship within 1-2 working days after payment. Shipping is calculated at checkout.</p></details>
        <details><summary>Payment<?= demo_tip('invoice', 'inline') ?></summary><p>QRIS, bank transfer and e-wallets. A PDF invoice arrives by email.</p></details>
      </div>
    </div>
  </div>

  <section class="gl-sec">
    <div class="gl-sec-head"><h2>You may also like</h2></div>
    <div class="gl-grid gl-grid--related"><?php foreach (demo_related($d, $p, 3) as $r) echo demo_card($d, $r); ?></div>
  </section>
</main>
<?php
demo_site_footer($d);
demo_foot($d);
