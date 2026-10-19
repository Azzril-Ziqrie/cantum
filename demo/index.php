<?php
// /demo/: the pop-up flow (inc/demo_flow.php) posts here. A valid post creates a temporary demo (see inc/demo.php)
// and redirects into its store; anything else shows the home collage with the pop-up open on top of it.
require __DIR__ . '/../inc/bootstrap.php';
require __DIR__ . '/../inc/demo.php';

$demoErrors = [];
$demoBrand = '';
$demoLayout = DEMO_LAYOUTS[0];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Cache-Control: no-store');
    // Past post_max_size PHP drops $_POST and $_FILES altogether.
    if (!$_POST && (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
        $demoErrors[] = t('demo_err_big');
    } elseif (!empty($_POST['website'])) {   // honeypot: real visitors never see this field
        http_response_code(400);
        exit;
    } else {
        $demoBrand = trim(preg_replace('/\s+/u', ' ', (string)($_POST['brand'] ?? '')));
        $demoLayout = in_array($_POST['layout'] ?? '', DEMO_LAYOUTS, true) ? $_POST['layout'] : DEMO_LAYOUTS[0];
        $visitor = demo_visitor();
        [$liveDemos, $mine] = demo_active_counts($visitor);

        if (mb_strlen($demoBrand) < 2 || mb_strlen($demoBrand) > 40) $demoErrors[] = t('demo_err_brand');
        if ($liveDemos >= DEMO_MAX_ACTIVE) $demoErrors[] = t('demo_err_busy');
        elseif ($mine >= DEMO_MAX_PER_VISITOR) $demoErrors[] = t('demo_err_limit');

        $productFiles = [];
        foreach ((array)($_FILES['products']['name'] ?? []) as $i => $name) {
            if (($_FILES['products']['error'][$i] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) $productFiles[$i] = $name;
        }
        if (count($productFiles) > DEMO_MAX_PRODUCTS) $demoErrors[] = t('demo_err_many');

        if (!$demoErrors) {
            $id = bin2hex(random_bytes(6));
            $dir = demo_upload_dir($id);
            $cfg = demo_defaults();
            $cfg['brand'] = $demoBrand;
            $cfg['layout'] = $demoLayout;
            $cfg['visitor'] = $visitor;
            $cfg['products'] = [];

            if (!is_dir($dir)) mkdir($dir, 0775, true);

            // Decodes one uploaded file; reports it by name when it is not a usable image.
            $read = function (array $file, string $label) use (&$demoErrors) {
                if ($file['error'] !== UPLOAD_ERR_OK || $file['size'] > DEMO_MAX_FILE || !is_uploaded_file($file['tmp_name'])
                    || !($im = demo_read_image($file['tmp_name']))) {
                    $demoErrors[] = $label . ' ' . t('demo_err_file');
                    return null;
                }
                return $im;
            };

            $logo = $_FILES['logo'] ?? null;
            if ($logo && $logo['error'] !== UPLOAD_ERR_NO_FILE && ($im = $read($logo, $logo['name']))) {
                $cfg['logo'] = demo_store_image($im, $dir, 600, true);
            }
            $n = 0;
            foreach ($productFiles as $i => $name) {
                $file = ['name' => $name, 'error' => $_FILES['products']['error'][$i], 'size' => $_FILES['products']['size'][$i], 'tmp_name' => $_FILES['products']['tmp_name'][$i]];
                if ($im = $read($file, $name)) {
                    $n++;
                    $cfg['products'][] = ['name' => demo_product_name($name, $n), 'price' => [149000, 199000, 129000, 249000, 179000, 229000][($n - 1) % 6], 'image' => demo_store_image($im, $dir, 1000, false)];
                }
            }

            if (!$demoErrors) {
                try {
                    demo_save($id, $cfg);
                    header('Location: ' . url('demo/store.php?id=' . $id), true, 303);
                    exit;
                } catch (Throwable $e) {
                    $demoErrors[] = t('demo_err_save');
                }
            }
            demo_delete($id);   // nothing is kept from a demo that was not created
        }
    }
}

$demoAutoOpen = true;
$fullTitle = t('title_demo');
$metaDesc = t('desc_demo');
$canonicalPath = 'demo/';
$active = 'demo';
require __DIR__ . '/../inc/header.php';
?>
<h1 class="sr-only"><?= e(t('h1_demo')) ?></h1>
<?php
require __DIR__ . '/../inc/home_grid.php';
require __DIR__ . '/../inc/demo_flow.php';
require __DIR__ . '/../inc/footer.php';
