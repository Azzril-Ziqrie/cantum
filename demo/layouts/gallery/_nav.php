<header class="gl-nav">
  <nav class="gl-links" aria-label="Shop">
    <a href="<?= e(demo_shop_url($d)) ?>">Shop</a>
    <a href="<?= e(demo_shop_url($d, 'New')) ?>">New</a>
    <a href="<?= e(demo_shop_url($d, 'Gifts')) ?>">Gifts</a>
  </nav>
  <a class="gl-brand" href="<?= e(demo_url($d)) ?>" aria-label="<?= e($d['brand']) ?>"><?= demo_wordmark($d) ?></a>
  <div class="gl-right">
    <button type="button" class="gl-btn" data-open="menu">Menu</button>
    <button type="button" class="gl-btn" data-open="cart">Cart (<span data-cart-count>0</span>)</button>
  </div>
</header>
