<?php
// Shown when a demo was deleted by the visitor, expired, or never existed.
require __DIR__ . '/../inc/bootstrap.php';

http_response_code(410);
$deleted = isset($_GET['deleted']);
$waLink = wa_link('Halo Cantum Studio, saya ingin membuat website.');
?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Demo berakhir | <?= e(SITE_NAME) ?></title>
<style>
  body { margin: 0; min-height: 100vh; display: grid; place-items: center; padding: 24px; background: #f6f7f9; color: #111; font: 400 15px/1.6 "Helvetica Neue", Helvetica, Arial, system-ui, sans-serif; }
  main { max-width: 440px; text-align: center; }
  h1 { font-size: 22px; letter-spacing: -.02em; margin-bottom: 8px; }
  p { color: #555; margin-bottom: 22px; }
  a { display: inline-block; margin: 4px; padding: 11px 22px; border-radius: 999px; border: 1px solid #111; color: #111; text-decoration: none; font-weight: 600; }
  a.solid { background: #111; color: #fff; }
</style>
</head>
<body>
<main>
  <h1><?= $deleted ? 'Demo sudah dihapus' : 'Demo sudah berakhir' ?></h1>
  <p><?= $deleted
      ? 'Logo dan foto yang Anda upload sudah dihapus dari server kami.'
      : 'Demo hanya aktif sementara, dan file yang Anda upload otomatis dihapus.' ?></p>
  <a class="solid" href="<?= url('demo') ?>">Buat demo baru</a>
  <?php if ($waLink): ?><a href="<?= e($waLink) ?>" target="_blank" rel="noopener">Hubungi kami</a><?php endif; ?>
</main>
</body>
</html>
