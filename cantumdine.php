<?php
// Old address of Cantum OnePay: permanent redirect to the new page, keeping the language.
require __DIR__ . '/inc/bootstrap.php';
header('Location: ' . abs_url((LANG === 'id' ? 'id/' : '') . 'cantum-onepay'), true, 301);
exit;
