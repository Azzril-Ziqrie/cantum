<div class="mk-announce">Free shipping over Rp 250.000 · Pay with QRIS, bank transfer or e-wallet</div>
<header class="mk-head">
  <div class="mk-bar">
    <button type="button" class="mk-menu" data-open="menu" aria-label="Open menu">Menu</button>
    <a class="mk-brand" href="<?= e(demo_url($d)) ?>" aria-label="<?= e($d['brand']) ?>"><?= demo_wordmark($d) ?></a>
    <form class="mk-search" role="search" data-demo-form="Search works in your live store"><input type="search" placeholder="Search <?= e($d['brand']) ?>" aria-label="Search products"><button type="submit">Search</button></form>
    <button type="button" class="mk-cart" data-open="cart">Cart <span class="mk-count" data-cart-count>0</span></button>
  </div>
  <nav class="mk-cats chips" aria-label="Categories">
    <a href="<?= e(demo_shop_url($d)) ?>"<?= ($page ?? '') === 'shop' && ($cat ?? '') === '' ? ' aria-current="true"' : '' ?>>All</a>
    <?php foreach ($d['cats'] as $c): ?><a href="<?= e(demo_shop_url($d, $c)) ?>"<?= ($page ?? '') === 'shop' && ($cat ?? '') === $c ? ' aria-current="true"' : '' ?>><?= e($c) ?></a><?php endforeach; ?>
  </nav>
</header>
