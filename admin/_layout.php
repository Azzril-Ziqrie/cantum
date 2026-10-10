<?php
function admin_head(string $title): void { ?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title><?= e($title) ?> | Dashboard</title>
<link rel="stylesheet" href="<?= url('assets/css/style.css') ?>?v=<?= filemtime(__DIR__ . '/../assets/css/style.css') ?>">
<link rel="stylesheet" href="<?= url('assets/css/admin.css') ?>?v=<?= filemtime(__DIR__ . '/../assets/css/admin.css') ?>">
</head>
<body class="admin">
<header class="bar">
  <a class="brand" href="<?= url('admin/') ?>"><?= e(SITE_NAME) ?> · Dashboard</a>
  <nav>
    <a href="<?= url('admin/') ?>">Projects</a>
    <a href="<?= url('admin/edit.php') ?>">New project</a>
    <a href="<?= url('admin/invoices.php') ?>">Invoices</a>
    <a href="<?= url('admin/settings.php') ?>">Settings</a>
    <a href="<?= url() ?>" target="_blank">View site ↗</a>
    <a href="<?= url('admin/logout.php') ?>">Log out</a>
  </nav>
</header>
<main class="wrap">
<?php }
function admin_foot(): void { ?>
</main>
</body>
</html>
<?php }
