<?php
// Cantum Studio — demo storefront: model + helpers shared by the three layouts in demo/layouts/<layout>/.
// A layout page is: demo_head($d, 'Title'); <layout nav>; ...markup...; demo_site_footer($d); demo_foot($d);
// ($d comes from demo_store_model()). The visual language follows the client shop in simple/: monochrome,
// small monospace type, translucent glass chrome, hairlines, 3:4 gray product tiles.
require_once __DIR__ . '/demo.php';

const DEMO_CATS = ['Bestsellers', 'New', 'Gifts'];
const DEMO_LAYOUT_NAMES = ['studio' => 'Studio', 'gallery' => 'Gallery', 'market' => 'Market'];
const DEMO_BLURB = 'Made from materials we picked for how they feel and how long they last. Packed by hand, and ready to give as a gift.';
const DEMO_FONTS = 'https://fonts.googleapis.com/css2?family=JetBrains+Mono:ital,wght@0,100..800;1,100..800&display=swap';

/**
 * Everything a layout needs. The store always shows at least six products: photos the visitor uploaded first,
 * then placeholders, so a demo with two photos still looks like a real shop. ?layout= previews another layout.
 */
function demo_store_model(string $id, ?string $layoutOverride = null): ?array
{
    $cfg = demo_load($id);
    if (!$cfg) return null;

    $override = in_array($layoutOverride, DEMO_LAYOUTS, true);
    $layout = $override ? $layoutOverride : (in_array($cfg['layout'], DEMO_LAYOUTS, true) ? $cfg['layout'] : DEMO_LAYOUTS[0]);

    $products = [];
    foreach (array_merge($cfg['products'], array_slice(demo_sample_products(), count($cfg['products']))) as $i => $item) {
        $products[] = [
            'n'     => $i + 1,
            'name'  => $item['name'],
            'price' => (int)$item['price'],
            'old'   => $i % 3 === 0 ? (int)(round($item['price'] * 1.2 / 1000) * 1000) : null,
            'cat'   => DEMO_CATS[$i % count(DEMO_CATS)],
            'image' => demo_asset_url($id, $item['image'] ?? null),
            'badge' => $i === 0 ? 'Bestseller' : ($i === 2 ? 'New' : null),
        ];
    }

    return [
        'id'       => $id,
        'brand'    => $cfg['brand'],
        'initial'  => mb_strtoupper(mb_substr($cfg['brand'], 0, 1)),
        'logo'     => demo_asset_url($id, $cfg['logo']),
        'hero'     => demo_asset_url($id, $cfg['hero']) ?: ($products[0]['image'] ?? null),   // null = no photo at all
        'layout'   => $layout,
        'override' => $override,
        'products' => $products,
        'cats'     => DEMO_CATS,
        'tagline'  => 'Everyday things, made well.',
        'about'    => [
            $cfg['brand'] . ' makes everyday essentials.',
            'We pick materials for how they feel and how long they last, then pack every order by hand.',
            'Simple things, made well.',
        ],
        'sample'   => $id === 'sample',
        'expires'  => $id === 'sample' ? null : demo_clock(demo_expires_at($cfg)),
        'wa'       => wa_link('Hi Cantum Studio, I tried the demo store for "' . $cfg['brand'] . '" and would like a website like this.'),
    ];
}

/** Link to a store page; keeps ?layout= while the visitor is previewing another layout. */
function demo_url(array $d, string $page = 'home', array $q = []): string
{
    $params = ['id' => $d['id']];
    if ($d['override']) $params['layout'] = $d['layout'];
    if ($page !== 'home') $params['page'] = $page;
    return url('demo/store.php?' . http_build_query($params + $q));
}

function demo_product_url(array $d, array $p): string { return demo_url($d, 'product', ['p' => $p['n']]); }
function demo_shop_url(array $d, string $cat = ''): string { return demo_url($d, 'shop', $cat !== '' ? ['cat' => $cat] : []); }
function demo_money(int $amount): string { return rupiah($amount); }

/** Products of one category ('' = all). */
function demo_filter(array $d, string $cat = ''): array
{
    return array_values(array_filter($d['products'], fn(array $p) => $cat === '' || $p['cat'] === $cat));
}

/** Other products to suggest on a product page. */
function demo_related(array $d, array $p, int $limit): array
{
    return array_slice(array_values(array_filter($d['products'], fn(array $x) => $x['n'] !== $p['n'])), 0, $limit);
}

/** Discount in percent for products with an old price, else null. */
function demo_discount(array $p): ?int
{
    return $p['old'] ? (int)round((1 - $p['price'] / $p['old']) * 100) : null;
}

const DEMO_SORTS = ['featured' => 'Featured', 'price_asc' => 'Price: low to high', 'price_desc' => 'Price: high to low'];

/** Orders a product list by one of DEMO_SORTS; "featured" keeps the catalogue order. */
function demo_sort(array $list, string $sort): array
{
    if ($sort === 'price_asc') usort($list, fn($a, $b) => $a['price'] <=> $b['price']);
    if ($sort === 'price_desc') usort($list, fn($a, $b) => $b['price'] <=> $a['price']);
    return $list;
}

/** Product photo, or a gray tile with a monogram when there is none. */
function demo_media(array $p, string $class = ''): string
{
    if ($p['image']) {
        return '<img class="' . e($class) . '" src="' . e($p['image']) . '" alt="' . e($p['name']) . '" loading="lazy" decoding="async">';
    }
    return '<div class="ph ' . e($class) . '" role="img" aria-label="' . e($p['name']) . '"><span>' . e(mb_strtoupper(mb_substr($p['name'], 0, 1))) . '</span></div>';
}

/** The brand as a logo image when one was uploaded, else as text. */
function demo_wordmark(array $d): string
{
    return $d['logo'] ? '<img src="' . e($d['logo']) . '" alt="' . e($d['brand']) . '">' : '<span>' . e($d['brand']) . '</span>';
}

/** Product card shared by every layout: gray tile, name, price. */
function demo_card(array $d, array $p, string $class = ''): string
{
    $badges = '';
    if ($p['badge']) $badges .= '<span class="badge">' . e($p['badge']) . '</span>';
    if (demo_discount($p)) $badges .= '<span class="badge badge--ink">-' . demo_discount($p) . '%</span>';
    return '<a class="pcard rv ' . e($class) . '" href="' . e(demo_product_url($d, $p)) . '">'
        . '<div class="ptile">' . ($badges ? '<div class="badges">' . $badges . '</div>' : '') . demo_media($p) . '</div>'
        . '<div class="pinfo"><span class="pname">' . e($p['name']) . '</span><span class="pprice">'
        . ($p['old'] ? '<s>' . demo_money($p['old']) . '</s>' : '') . '<span>' . demo_money($p['price']) . '</span></span></div></a>';
}

/** Button that pretends to add to cart; demo-store.js keeps the cart in sessionStorage. */
function demo_add_button(array $p, string $label, string $class = ''): string
{
    return '<button type="button" class="' . e($class) . '" data-add="' . e($p['name']) . '" data-n="' . (int)$p['n']
        . '" data-price="' . (int)$p['price'] . '" data-img="' . e((string)$p['image']) . '">' . e($label) . '</button>';
}

/** "- 1 +" stepper read by demo-store.js. */
function demo_qty(): string
{
    return '<div class="qty"><button type="button" data-step="-1" aria-label="Decrease">−</button>'
        . '<input type="number" value="1" min="1" max="99" data-qty-input aria-label="Quantity">'
        . '<button type="button" data-step="1" aria-label="Increase">+</button></div>';
}

/** Feature hotspot; the parent element needs position:relative. $pos: tl | tr | bl | br | c | inline. */
function demo_tip(string $key, string $pos = 'tr'): string
{
    return '<button type="button" class="tip-dot tip-' . e($pos) . '" data-tip="' . e($key) . '" aria-label="About this feature"></button>';
}

/**
 * What each hotspot says. Many visitors don't know a shop's banner, products or prices are theirs to change,
 * so the copy says it plainly. The third item is the dashboard panel the link opens.
 */
function demo_tips(): array
{
    return [
        'hero'       => ['Change this banner yourself', 'Upload a new photo or headline from your dashboard whenever you run a promo. It takes a minute and you don\'t need a developer.', 'hero'],
        'products'   => ['Manage products yourself', 'Add products, edit prices, swap photos and mark items sold out from your dashboard. The store updates the moment you save.', 'products'],
        'category'   => ['Categories are yours', 'Create the categories your shop needs, like Bestsellers, New or Gifts. Customers filter by them.', 'products'],
        'cart'       => ['Local payment methods', 'Customers pay with QRIS, bank transfer (virtual account) or e-wallets through an Indonesian payment gateway. The order status changes by itself once the money arrives.', 'payments'],
        'shipping'   => ['Shipping cost calculates itself', 'The store works out the cost from the destination city and the parcel weight. You never calculate it by hand.', 'orders'],
        'promo'      => ['Make your own promo codes', 'Create a discount code, set an expiry date and share it. You control it from the dashboard.', 'promo'],
        'orders'     => ['Orders arrive in your dashboard', 'Each order is recorded for you. Add a tracking number and the customer gets a notification.', 'orders'],
        'whatsapp'   => ['WhatsApp goes to your number', 'Customers message you before they buy, straight from the store.', 'overview'],
        'seo'        => ['Built for Google', 'Every page has its own title and description, and loads fast on phones.', 'overview'],
        'domain'     => ['Your own domain', 'The store runs on your address, like yourbrand.com.', 'overview'],
        'invoice'    => ['Invoices send themselves', 'When a payment arrives, the store emails a PDF invoice to the customer.', 'orders'],
        'newsletter' => ['Collect customer emails', 'Use the emails you collect to announce new products or promos from the dashboard.', 'promo'],
    ];
}

/** Opens the document, loads fonts + shared + layout CSS, prints the demo bar, then the overlay and the menu/cart panels. */
function demo_head(array $d, string $title): void
{
    $css = fn(string $f) => url('assets/css/' . $f) . '?v=' . filemtime(ROOT . '/assets/css/' . $f);
    ?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="robots" content="noindex, nofollow">
<title><?= e($title) ?> | <?= e($d['brand']) ?> (Demo)</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="<?= e(DEMO_FONTS) ?>">
<link rel="stylesheet" href="<?= e($css('demo-store.css')) ?>">
<link rel="stylesheet" href="<?= e($css('demo-' . $d['layout'] . '.css')) ?>">
</head>
<body class="store store--<?= e($d['layout']) ?>">
<div class="dbar">
  <span class="dbar-l"><strong>Demo</strong><?= $d['expires'] ? ' · live until ' . e($d['expires']) : '' ?></span>
  <span class="dbar-layouts" role="group" aria-label="Choose a layout">
    <?php foreach (DEMO_LAYOUT_NAMES as $key => $label): ?>
      <a href="<?= e(url('demo/store.php?' . http_build_query(['id' => $d['id'], 'layout' => $key] + (($_GET['page'] ?? '') === 'shop' ? ['page' => 'shop'] : [])))) ?>"<?= $key === $d['layout'] ? ' aria-current="true"' : '' ?>><?= e($label) ?></a>
    <?php endforeach; ?>
  </span>
  <span class="dbar-r">
    <a href="<?= e(url('demo/dashboard.php?id=' . $d['id'])) ?>">Dashboard preview</a>
    <label class="dbar-tips"><input type="checkbox" id="tipsToggle" checked> Feature tips</label>
    <?php if (!$d['sample']): ?>
    <form method="post" action="<?= e(url('demo/delete.php')) ?>" data-confirm="Delete this demo and every file you uploaded now?">
      <input type="hidden" name="id" value="<?= e($d['id']) ?>"><button type="submit">Delete my demo</button>
    </form>
    <?php endif; ?>
  </span>
</div>
<div class="overlay" id="overlay"></div>
<aside class="panel menu-panel" id="menuPanel" aria-label="Main menu" aria-hidden="true">
  <div class="menu-body">
    <form class="menu-search" role="search" data-demo-form="Search works in your live store">
      <input type="text" placeholder="Search" aria-label="Search products"><button type="submit" aria-label="Search">→</button>
    </form>
    <nav class="menu-nav">
      <a href="<?= e(demo_url($d)) ?>">Home</a>
      <div class="dropdown">
        <button type="button" class="dropdown-toggle" aria-expanded="false">Shop</button>
        <div class="dropdown-menu">
          <a href="<?= e(demo_shop_url($d)) ?>">All</a>
          <?php foreach ($d['cats'] as $c): ?><a href="<?= e(demo_shop_url($d, $c)) ?>"><?= e($c) ?></a><?php endforeach; ?>
        </div>
      </div>
      <a href="#" data-soon>About</a>
      <?php if ($d['wa']): ?><a href="<?= e($d['wa']) ?>" target="_blank" rel="noopener">WhatsApp</a><?php endif; ?>
    </nav>
  </div>
</aside>
<aside class="panel cart-panel" id="cartPanel" aria-label="Shopping cart" aria-hidden="true">
  <div class="panel-head"><span class="panel-label">Cart (<span data-cart-count>0</span>)</span><button type="button" class="panel-close" data-close-panel aria-label="Close cart">×</button></div>
  <div class="cart-body" id="cartBody"></div>
  <div class="cart-foot" id="cartFoot" hidden>
    <div class="cart-total"><span>Total</span><strong id="cartTotal">Rp 0</strong></div>
    <p class="cart-note">Shipping is calculated at checkout.</p>
    <div class="cart-checkout"><button type="button" class="btn btn--solid btn--block" data-checkout>Checkout</button><?= demo_tip('cart', 'tr') ?></div>
  </div>
</aside>
<?php
}

/** Footer shared by the three layouts, same plain structure as the client shop. */
function demo_site_footer(array $d): void
{
    ?>
<footer class="site-footer" aria-label="Site footer">
  <div class="footer-inner">
    <nav class="footer-links-row" aria-label="Information">
      <div class="footer-group"><a href="#" data-soon>Shipping &amp; Returns</a><a href="#" data-soon>FAQ</a><a href="#" data-soon>Privacy</a><a href="#" data-soon>Terms of Service</a></div>
      <div class="footer-group"><a href="#" data-soon>About</a><a href="#" data-soon>Contact Us</a><?php if ($d['wa']): ?><a href="<?= e($d['wa']) ?>" target="_blank" rel="noopener">WhatsApp</a><?php endif; ?></div>
    </nav>
    <form class="footer-news" data-demo-form="Subscriptions work in your live store">
      <label class="footer-news__title" for="footerEmail">Subscribe to the newsletter</label>
      <div class="footer-news__row">
        <input class="footer-news__input" id="footerEmail" type="email" placeholder="Email address" required><button class="footer-news__btn" type="submit">Subscribe</button>
      </div>
      <?= demo_tip('newsletter', 'tr') ?>
    </form>
    <div class="footer"><span class="footer-copy">© <?= date('Y') ?> <?= e($d['brand']) ?>. All rights reserved. <span class="footer-domain">yourbrand.com<?= demo_tip('domain', 'inline') ?></span> · Demo by Cantum Studio.</span></div>
  </div>
</footer>
<?php
}

/** Floating WhatsApp button, toast, tip data and script; closes the document. */
function demo_foot(array $d): void
{
    $tips = [];
    foreach (demo_tips() as $key => [$title, $text, $tab]) {
        $tips[$key] = ['title' => $title, 'text' => $text, 'href' => url('demo/dashboard.php?id=' . $d['id']) . '#' . $tab];
    }
    ?>
<?php if ($d['wa']): ?>
<a class="wa-pill" href="<?= e($d['wa']) ?>" target="_blank" rel="noopener" data-wa="demo-store">Want a store like this? Chat with us</a>
<?php endif; ?>
<div class="toast" id="demoToast" role="status" aria-live="polite"></div>
<script type="application/json" id="demoTips"><?= json_encode($tips, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?></script>
<script src="<?= e(url('assets/js/demo-store.js')) ?>?v=<?= filemtime(ROOT . '/assets/js/demo-store.js') ?>"></script>
</body>
</html>
<?php
}
