<?php
require __DIR__ . '/../inc/bootstrap.php';
require __DIR__ . '/_layout.php';
require_login();

$msg = '';
$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    if (($_POST['form'] ?? '') === 'site') {
        foreach (['headline', 'tagline', 'about_title', 'about_text', 'email', 'phone', 'whatsapp', 'address', 'instagram', 'invoice_payment', 'invoice_terms', 'about_text_id'] as $k) {
            save_setting($k, trim($_POST[$k] ?? ''));
        }
        $msg = 'Settings saved.';
    } elseif (($_POST['form'] ?? '') === 'password') {
        $st = db()->prepare('SELECT * FROM users WHERE id = ?');
        $st->execute([$_SESSION['uid']]);
        $u = $st->fetch();
        $new = $_POST['new'] ?? '';
        if (!$u || !password_verify($_POST['current'] ?? '', $u['password'])) $err = 'Current password is wrong.';
        elseif (strlen($new) < 8) $err = 'New password must be at least 8 characters.';
        elseif ($new !== ($_POST['confirm'] ?? '')) $err = 'Passwords do not match.';
        else {
            $username = trim($_POST['username'] ?? '') ?: $u['username'];
            db()->prepare('UPDATE users SET username = ?, password = ? WHERE id = ?')
                ->execute([$username, password_hash($new, PASSWORD_DEFAULT), $u['id']]);
            $msg = 'Login updated.';
        }
    }
}
$me = db()->query('SELECT username FROM users WHERE id = ' . (int)$_SESSION['uid'])->fetchColumn();

// settings() caches per request; re-read after save
function sv(string $k, string $d = ''): string {
    $st = db()->prepare('SELECT v FROM settings WHERE k = ?'); $st->execute([$k]);
    $v = $st->fetchColumn();
    return $v === false ? $d : $v;
}

admin_head('Settings');
?>
<h1>Settings</h1>
<?php if ($msg): ?><p class="ok"><?= e($msg) ?></p><?php endif; ?>
<?php if ($err): ?><p class="err"><?= e($err) ?></p><?php endif; ?>

<form method="post" class="form">
  <?= csrf_field() ?><input type="hidden" name="form" value="site">
  <label>Home headline<textarea name="headline" rows="2"><?= e(sv('headline', 'Cantum Studio designs spaces, objects and identities with quiet intent.')) ?></textarea></label>
  <label>Tagline (footer)<input name="tagline" value="<?= e(sv('tagline', 'Selected works')) ?>"></label>
  <label>About title<input name="about_title" value="<?= e(sv('about_title', 'About the studio')) ?>"></label>
  <label>About text (English, shown on the main site)<textarea name="about_text" rows="6"><?= e(sv('about_text')) ?></textarea></label>
  <label>About text (Indonesian, shown at /id/ for Indonesian search)<textarea name="about_text_id" rows="6"><?= e(sv('about_text_id')) ?></textarea></label>
  <div class="cols">
    <label>Email<input type="email" name="email" value="<?= e(sv('email')) ?>"></label>
    <label>Phone<input name="phone" value="<?= e(sv('phone')) ?>"></label>
    <label>WhatsApp<input name="whatsapp" value="<?= e(sv('whatsapp')) ?>" placeholder="0812xxxxxxx" inputmode="tel"></label>
    <label>Instagram URL<input name="instagram" value="<?= e(sv('instagram')) ?>"></label>
  </div>
  <label>Address<textarea name="address" rows="2"><?= e(sv('address')) ?></textarea></label>
  <label>Invoice: payment details (bank, account number, name)<textarea name="invoice_payment" rows="3" placeholder="BCA 1234567890 a.n. Nama Kamu"><?= e(sv('invoice_payment')) ?></textarea></label>
  <label>Invoice: default notes / terms<textarea name="invoice_terms" rows="3" placeholder="Pembayaran paling lambat pada tanggal jatuh tempo."><?= e(sv('invoice_terms')) ?></textarea></label>
  <div class="row"><button class="btn" type="submit">Save settings</button></div>
</form>

<h2>Login</h2>
<form method="post" class="form">
  <?= csrf_field() ?><input type="hidden" name="form" value="password">
  <label>Username<input name="username" value="<?= e($me) ?>" autocomplete="username"></label>
  <label>Current password<input type="password" name="current" autocomplete="current-password" required></label>
  <div class="cols">
    <label>New password<input type="password" name="new" autocomplete="new-password" required minlength="8"></label>
    <label>Confirm<input type="password" name="confirm" autocomplete="new-password" required></label>
  </div>
  <div class="row"><button class="btn" type="submit">Update login</button></div>
</form>
<?php admin_foot(); ?>
