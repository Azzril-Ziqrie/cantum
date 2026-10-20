<?php
// Shown when a demo was deleted by the visitor, expired, or never existed.
require __DIR__ . '/../inc/bootstrap.php';

http_response_code(410);
$deleted = isset($_GET['deleted']);
$waLink = wa_link('Hi Cantum Studio, I would like a website for my business.');
$title  = $deleted ? 'Demo deleted.' : 'Demo ended.';
$text   = $deleted
    ? 'Your logo and photos are removed from our server. Nothing is kept.'
    : 'Demos stay live for two hours. After that, every file you uploaded is deleted automatically.';
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= e(rtrim($title, '.')) ?> | <?= e(SITE_NAME) ?></title>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@300..700&display=swap">
<style>
  * { box-sizing: border-box; margin: 0; }
  body {
    min-height: 100dvh; display: grid; align-content: center; padding: clamp(24px, 8vw, 96px);
    background: #f6f6f7; color: #111;
    font: 400 14px/1.7 "JetBrains Mono", "Roboto Mono", ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
    -webkit-font-smoothing: antialiased;
  }
  main { width: min(560px, 100%); }
  h1 { margin-bottom: 20px; font-size: clamp(44px, 12vw, 88px); font-weight: 500; line-height: .95; letter-spacing: -.07em; }
  p { max-width: 46ch; margin-bottom: 36px; color: #6b6b70; }
  .actions { display: flex; flex-wrap: wrap; gap: 10px; }
  a { display: inline-flex; align-items: center; min-height: 44px; padding: 0 22px; border: 1px solid #111; border-radius: 999px; color: #111; font-weight: 500; text-decoration: none; transition: background-color .15s, color .15s, transform .12s ease-out; }
  a:hover { background: #111; color: #fff; }
  a:active { transform: scale(.97); }
  a.solid { background: #111; color: #fff; }
  a.solid:hover { opacity: .88; }
  a:focus-visible { outline: 2px solid #111; outline-offset: 3px; }
  @media (prefers-reduced-motion: reduce) { a { transition: none; } }
</style>
</head>
<body>
<main>
  <h1><?= e($title) ?></h1>
  <p><?= e($text) ?></p>
  <div class="actions">
    <a class="solid" href="<?= url('demo') ?>">Start a new demo</a>
    <?php if ($waLink): ?><a href="<?= e($waLink) ?>" target="_blank" rel="noopener">Chat with us</a><?php endif; ?>
  </div>
</main>
</body>
</html>
