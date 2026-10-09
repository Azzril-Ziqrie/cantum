<?php
require __DIR__ . '/../inc/bootstrap.php';
require __DIR__ . '/_layout.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $id = (int)($_POST['id'] ?? 0);
    $action = $_POST['action'] ?? '';
    if ($action === 'delete' && $id) {
        $st = db()->prepare('SELECT filename FROM images WHERE project_id = ?');
        $st->execute([$id]);
        foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $f) delete_image_file($f);
        db()->prepare('DELETE FROM projects WHERE id = ?')->execute([$id]); // images cascade
    } elseif ($action === 'toggle' && $id) {
        db()->prepare('UPDATE projects SET published = 1 - published WHERE id = ?')->execute([$id]);
    } elseif ($action === 'order') {
        $st = db()->prepare('UPDATE projects SET sort_order = ? WHERE id = ?');
        foreach ((array)($_POST['order'] ?? []) as $pid => $n) $st->execute([(int)$n, (int)$pid]);
    }
    header('Location: ' . url('admin/'));
    exit;
}

$projects = db()->query('SELECT * FROM projects ORDER BY sort_order ASC, year DESC, id DESC')->fetchAll();
admin_head('Projects');
?>
<div class="row between">
  <h1>Projects <small><?= count($projects) ?></small></h1>
  <a class="btn" href="<?= url('admin/edit.php') ?>">+ New project</a>
</div>

<?php if (!$projects): ?>
  <p class="muted">Nothing here yet. Add your first project.</p>
<?php else: ?>
<form method="post" id="orderform">
  <?= csrf_field() ?><input type="hidden" name="action" value="order">
  <table class="list">
    <thead><tr><th></th><th>Title</th><th>Category</th><th>Year</th><th>Status</th><th>Order</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($projects as $p): $c = project_cover($p); ?>
      <tr>
        <td class="th"><?php if ($c): ?><img src="<?= upload_url($c) ?>" alt=""><?php endif; ?></td>
        <td><a href="<?= url('admin/edit.php?id=' . $p['id']) ?>"><strong><?= e($p['title']) ?></strong></a></td>
        <td><?= e($p['category']) ?></td>
        <td><?= e($p['year']) ?></td>
        <td><span class="pill <?= $p['published'] ? 'ok' : '' ?>"><?= $p['published'] ? 'Published' : 'Draft' ?></span></td>
        <td><input class="num" type="number" name="order[<?= $p['id'] ?>]" value="<?= (int)$p['sort_order'] ?>"></td>
        <td class="acts">
          <a href="<?= url('admin/edit.php?id=' . $p['id']) ?>">Edit</a>
          <button form="t<?= $p['id'] ?>" class="link"><?= $p['published'] ? 'Unpublish' : 'Publish' ?></button>
          <button form="d<?= $p['id'] ?>" class="link danger" onclick="return confirm('Delete this project and all its images?')">Delete</button>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <button class="btn ghost" type="submit">Save order</button>
  <span class="muted"> Lower numbers appear first on the site.</span>
</form>
<?php foreach ($projects as $p): ?>
  <form id="t<?= $p['id'] ?>" method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $p['id'] ?>"><input type="hidden" name="action" value="toggle"></form>
  <form id="d<?= $p['id'] ?>" method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $p['id'] ?>"><input type="hidden" name="action" value="delete"></form>
<?php endforeach; ?>
<?php endif; ?>
<?php admin_foot(); ?>
