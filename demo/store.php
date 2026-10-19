<?php
// Demo storefront front controller: /demo/store?id=<demo>&page=home|shop|product[&p=<n>][&cat=<name>][&sort=<key>][&layout=<name>]
require __DIR__ . '/../inc/bootstrap.php';
require __DIR__ . '/../inc/demo_store.php';

header('X-Robots-Tag: noindex, nofollow');
header('Cache-Control: no-store');

$d = demo_store_model((string)($_GET['id'] ?? 'sample'), isset($_GET['layout']) ? (string)$_GET['layout'] : null);
if (!$d) {
    header('Location: ' . url('demo/ended.php'), true, 302);
    exit;
}

$page = in_array($_GET['page'] ?? 'home', ['home', 'shop', 'product'], true) ? ($_GET['page'] ?? 'home') : 'home';
$cat = in_array($_GET['cat'] ?? '', $d['cats'], true) ? $_GET['cat'] : '';
$sort = isset(DEMO_SORTS[$_GET['sort'] ?? '']) ? $_GET['sort'] : 'featured';

if ($page === 'product') {
    $p = $d['products'][((int)($_GET['p'] ?? 0)) - 1] ?? null;
    if (!$p) $page = 'shop';
}

require __DIR__ . '/layouts/' . $d['layout'] . '/' . $page . '.php';
