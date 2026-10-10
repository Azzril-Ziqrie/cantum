<?php
require __DIR__ . '/../inc/bootstrap.php';

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    // basic brute-force throttle
    $_SESSION['fails'] = $_SESSION['fails'] ?? 0;
    if ($_SESSION['fails'] >= 5 && time() - ($_SESSION['fail_t'] ?? 0) < 60) {
        $error = 'Too many attempts. Wait a minute.';
    } else {
        $st = db()->prepare('SELECT * FROM users WHERE username = ?');
        $st->execute([trim($_POST['username'] ?? '')]);
        $u = $st->fetch();
        if ($u && password_verify($_POST['password'] ?? '', $u['password'])) {
            session_regenerate_id(true);
            $_SESSION['uid'] = $u['id'];
            $_SESSION['fails'] = 0;
            header('Location: ' . url('admin/'));
            exit;
        }
        $_SESSION['fails']++;
        $_SESSION['fail_t'] = time();
        $error = 'Wrong username or password.';
    }
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title>Log in | <?= e(SITE_NAME) ?></title>
<link rel="stylesheet" href="<?= url('assets/css/style.css') ?>">
<link rel="stylesheet" href="<?= url('assets/css/admin.css') ?>">
</head>
<body class="admin login">
<form method="post" class="loginbox">
  <h1><?= e(SITE_NAME) ?></h1>
  <p class="sub">Dashboard</p>
  <?php if ($error): ?><p class="err"><?= e($error) ?></p><?php endif; ?>
  <?= csrf_field() ?>
  <label>Username<input name="username" autocomplete="username" required autofocus></label>
  <label>Password<input type="password" name="password" autocomplete="current-password" required></label>
  <button class="btn" type="submit">Log in</button>
</form>
</body>
</html>
