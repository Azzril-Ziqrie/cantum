<?php
// "Delete my demo": removes the visitor's demo and uploaded files immediately. POST only; the unguessable id is the capability.
require __DIR__ . '/../inc/bootstrap.php';
require __DIR__ . '/../inc/demo.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit;
}
demo_delete((string)($_POST['id'] ?? ''));
header('Location: ' . url('demo/ended.php?deleted=1'), true, 303);
