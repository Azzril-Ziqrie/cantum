<?php
require __DIR__ . '/../inc/bootstrap.php';
require __DIR__ . '/_layout.php';
require_login();

$id = (int)($_GET['id'] ?? 0);
$p = ['id' => 0, 'title' => '', 'category' => 'Architecture', 'year' => date('Y'), 'location' => '', 'description' => '', 'link' => '', 'cover_id' => null, 'sort_order' => 0, 'published' => 1];
if ($id) {
    $st = db()->prepare('SELECT * FROM projects WHERE id = ?');
    $st->execute([$id]);
    $p = $st->fetch();
    if (!$p) { http_response_code(404); exit('Project not found.'); }
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $title = trim($_POST['title'] ?? '');
    $category = in_array($_POST['category'] ?? '', CATEGORIES, true) ? $_POST['category'] : 'Other';
    $year = trim($_POST['year'] ?? '');
    $location = trim($_POST['location'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $link = trim($_POST['link'] ?? '');
    if ($link !== '' && !preg_match('#^https?://#i', $link)) $link = 'https://' . $link;   // "example.com" works too
    $published = isset($_POST['published']) ? 1 : 0;
    if ($title === '') $errors[] = 'Title is required.';
    if ($link !== '' && !filter_var($link, FILTER_VALIDATE_URL)) $errors[] = 'That link does not look like a valid web address.';

    if (!$errors) {
        $pdo = db();
        if ($id) {
            $pdo->prepare('UPDATE projects SET title=?, category=?, year=?, location=?, description=?, link=?, published=? WHERE id=?')
                ->execute([$title, $category, $year, $location, $description, $link, $published, $id]);
        } else {
            $first = (int)$pdo->query('SELECT COALESCE(MIN(sort_order), 1) FROM projects')->fetchColumn() - 1; // new projects go first
            $pdo->prepare('INSERT INTO projects (title, slug, category, year, location, description, link, published, sort_order)
                           VALUES (?,?,?,?,?,?,?,?,?)')
                ->execute([$title, unique_slug($title), $category, $year, $location, $description, $link, $published, $first]);
            $id = (int)$pdo->lastInsertId();
        }

        // remove selected images
        foreach ((array)($_POST['remove'] ?? []) as $rid) {
            $s = $pdo->prepare('SELECT filename FROM images WHERE id = ? AND project_id = ?');
            $s->execute([(int)$rid, $id]);
            if ($f = $s->fetchColumn()) {
                delete_image_file($f);
                $pdo->prepare('DELETE FROM images WHERE id = ?')->execute([(int)$rid]);
            }
        }
        // per-image order
        foreach ((array)($_POST['img_order'] ?? []) as $iid => $n) {
            $pdo->prepare('UPDATE images SET sort_order = ? WHERE id = ? AND project_id = ?')->execute([(int)$n, (int)$iid, $id]);
        }
        // new uploads (appended after existing)
        [$saved, $upErrors] = save_uploads('images');
        $errors = array_merge($errors, $upErrors);
        $max = (int)$pdo->query('SELECT COALESCE(MAX(sort_order), 0) FROM images WHERE project_id = ' . $id)->fetchColumn();
        foreach ($saved as $fn) {
            $pdo->prepare('INSERT INTO images (project_id, filename, sort_order) VALUES (?,?,?)')->execute([$id, $fn, ++$max]);
        }
        // cover
        $cover = (int)($_POST['cover'] ?? 0);
        $s = $pdo->prepare('SELECT COUNT(*) FROM images WHERE id = ? AND project_id = ?');
        $s->execute([$cover, $id]);
        $pdo->prepare('UPDATE projects SET cover_id = ? WHERE id = ?')->execute([$s->fetchColumn() ? $cover : null, $id]);

        if (!$errors) { header('Location: ' . url('admin/')); exit; }
        // upload problems: stay on the edit page so they can be seen
        $st = $pdo->prepare('SELECT * FROM projects WHERE id = ?'); $st->execute([$id]); $p = $st->fetch();
    } else {
        $p = array_merge($p, compact('title', 'category', 'year', 'location', 'description', 'link', 'published'));
    }
}

$images = [];
if ($p['id'] || $id) {
    $st = db()->prepare('SELECT * FROM images WHERE project_id = ? ORDER BY sort_order, id');
    $st->execute([$p['id'] ?: $id]);
    $images = $st->fetchAll();
}

admin_head($p['id'] ? 'Edit project' : 'New project');
?>
<h1><?= $p['id'] ? 'Edit project' : 'New project' ?></h1>
<?php foreach ($errors as $er): ?><p class="err"><?= e($er) ?></p><?php endforeach; ?>

<form method="post" enctype="multipart/form-data" class="form" action="<?= url('admin/edit.php' . ($p['id'] ? '?id=' . $p['id'] : '')) ?>">
  <?= csrf_field() ?>
  <label>Title<input name="title" value="<?= e($p['title']) ?>" required></label>
  <div class="cols">
    <label>Category
      <select name="category">
        <?php foreach (CATEGORIES as $c): ?><option <?= $p['category'] === $c ? 'selected' : '' ?>><?= e($c) ?></option><?php endforeach; ?>
      </select>
    </label>
    <label>Year<input name="year" value="<?= e($p['year']) ?>" maxlength="9"></label>
    <label>Location<input name="location" value="<?= e($p['location']) ?>"></label>
  </div>
  <label>Description<textarea name="description" rows="6"><?= e($p['description']) ?></textarea></label>
  <label>Website link <span class="muted">(optional, shown on the project page)</span><input name="link" inputmode="url" autocapitalize="off" spellcheck="false" placeholder="https://client-website.com" value="<?= e($p['link'] ?? '') ?>"></label>

  <label class="check"><input type="checkbox" name="published" <?= $p['published'] ? 'checked' : '' ?>> Published (visible on the site)</label>

  <fieldset>
    <legend>Images</legend>
    <?php if ($images): ?>
    <div class="imgs">
      <?php foreach ($images as $im): ?>
        <div class="imgcard">
          <img src="<?= upload_url($im['filename']) ?>" alt="">
          <label class="check"><input type="radio" name="cover" value="<?= $im['id'] ?>" <?= (int)$p['cover_id'] === (int)$im['id'] ? 'checked' : '' ?>> Cover</label>
          <label>Order <input class="num" type="number" name="img_order[<?= $im['id'] ?>]" value="<?= (int)$im['sort_order'] ?>"></label>
          <label class="check danger"><input type="checkbox" name="remove[]" value="<?= $im['id'] ?>"> Remove</label>
        </div>
      <?php endforeach; ?>
    </div>
    <p class="muted">If no cover is chosen, the first image is used.</p>
    <?php endif; ?>
    <label>Add images<input type="file" name="images[]" id="files" accept="image/jpeg,image/png,image/webp,image/gif" multiple></label>
    <div id="preview" class="imgs"></div>
    <?php $lim = min(ini_bytes(ini_get('upload_max_filesize')), 15 * 1024 * 1024); $tot = ini_bytes(ini_get('post_max_size')); ?>
    <p class="muted">JPG, PNG, WebP, atau GIF · maks <?= round($lim / 1048576) ?>MB per foto · total sekali simpan maks <?= round($tot / 1048576) ?>MB. Boleh pilih banyak foto sekaligus.</p>
    <p class="err" id="toobig" hidden></p>
  </fieldset>

  <div class="row">
    <button class="btn" type="submit">Save project</button>
    <a class="btn ghost" href="<?= url('admin/') ?>">Cancel</a>
  </div>
</form>
<script>
var MAX_FILE = <?= (int)$lim ?>, MAX_TOTAL = <?= (int)$tot ?>;
document.getElementById('files').addEventListener('change', function () {
  var warn = document.getElementById('toobig'), total = 0, big = [];
  Array.prototype.forEach.call(this.files, function (f) { total += f.size; if (f.size > MAX_FILE) big.push(f.name); });
  var msg = '';
  if (big.length) msg = 'Terlalu besar (maks ' + Math.round(MAX_FILE / 1048576) + 'MB per foto): ' + big.join(', ') + '. ';
  if (total > MAX_TOTAL * 0.95) msg += 'Total ukuran melebihi batas server, upload sebagian-sebagian.';
  warn.hidden = !msg; warn.textContent = msg;
  var box = document.getElementById('preview'); box.innerHTML = '';
  Array.prototype.forEach.call(this.files, function (f) {
    var d = document.createElement('div'); d.className = 'imgcard';
    var i = document.createElement('img'); i.src = URL.createObjectURL(f); d.appendChild(i); box.appendChild(d);
  });
});
</script>
<?php admin_foot(); ?>
