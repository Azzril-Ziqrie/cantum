</main>
<footer class="foot">
  <a href="<?= e(setting('instagram') ?: 'https://www.instagram.com/cantumstudio/') ?>" target="_blank" rel="noopener"><?= e(SITE_NAME) ?></a>
  <span><?= date('Y') ?></span>
</footer>
<script src="<?= url('assets/js/main.js') ?>?v=<?= filemtime(__DIR__ . '/../assets/js/main.js') ?>" data-track="<?= url('track.php') ?>"></script>
</body>
</html>
