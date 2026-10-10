<?php
require __DIR__ . '/inc/bootstrap.php';
$pageTitle = t('title_contact');
$metaDesc = t('desc_contact');
$canonicalPath = 'contact.php';
$jsonLd = [['@context' => 'https://schema.org', '@type' => 'ContactPage', 'url' => abs_url((LANG === 'id' ? 'id/' : '') . 'contact.php'), 'name' => t('contact_page_name'), 'inLanguage' => LANG, 'about' => ['@id' => abs_url('#studio')]]];
require __DIR__ . '/inc/header.php';

$waNum = wa_phone();
$rows = array_filter([
    t('lbl_whatsapp')  => $waNum ? '<a href="' . e(wa_link(t('wa_msg_general'))) . '" target="_blank" rel="noopener" data-wa="contact">' . e(setting('whatsapp') ?: setting('phone')) . '</a>' : '',
    t('lbl_email')     => setting('email')     ? '<a href="mailto:' . e(setting('email')) . '">' . e(setting('email')) . '</a>' : '',
    t('lbl_phone')     => setting('phone') && wa_number(setting('phone')) !== $waNum ? '<a href="tel:' . e(preg_replace('/[^\d+]/', '', setting('phone'))) . '">' . e(setting('phone')) . '</a>' : '',
    t('lbl_address')   => setting('address')   ? nl2br(e(setting('address'))) : '',
    t('lbl_instagram') => setting('instagram') ? '<a href="' . e(setting('instagram')) . '" target="_blank" rel="noopener">' . e(preg_replace('#^https?://(www\.)?#', '', rtrim(setting('instagram'), '/'))) . '</a>' : '',
]);
?>
<section class="contactpage">
  <div class="crow"><span class="lbl"><?= e(t('lbl_studio')) ?></span><span><?= e(t('contact_studio')) ?></span></div>
  <?php if (!$rows): ?>
    <p class="empty" style="padding-top:1rem"><?= e(t('contact_empty')) ?></p>
  <?php else: foreach ($rows as $label => $html): ?>
    <div class="crow"><span class="lbl"><?= e($label) ?></span><span><?= $html ?></span></div>
  <?php endforeach; endif; ?>
</section>
<?php require __DIR__ . '/inc/footer.php'; ?>
