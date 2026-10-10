<?php
// Beacon endpoint for WhatsApp click counts. POST only, answers 204, stores nothing personal (see inc/stats.php).
require __DIR__ . '/inc/stats.php';
if (isset($_COOKIE[session_name()])) session_start();
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $s = (string)($_POST['s'] ?? '');
    if (preg_match('/^[a-z]{3,12}(:[a-z0-9._\-]{1,40})?$/i', $s)) stats_click($s);
}
http_response_code(204);
