<?php /** @var array $d  @var bool $overlay navbar floats transparent over a photo hero */ ?>
<header class="navbar<?= !empty($overlay) ? ' navbar--overlay' : ' is-solid' ?>" data-nav>
  <div class="nav-left"><button type="button" class="menu-icon-wrap" data-open="menu" aria-label="Open menu"></button></div>
  <div class="nav-center"><a href="<?= e(demo_url($d)) ?>" class="brand-mark" aria-label="<?= e($d['brand']) ?>"><?= demo_wordmark($d) ?></a></div>
  <div class="nav-right">
    <form class="search-form" role="search" data-demo-form="Search works in your live store"><input type="text" placeholder="Search" aria-label="Search products"></form>
    <a class="nav-btn nav-shop" href="<?= e(demo_shop_url($d)) ?>">Shop</a>
    <button type="button" class="nav-btn" data-open="cart">Cart (<span data-cart-count>0</span>)</button>
  </div>
</header>
