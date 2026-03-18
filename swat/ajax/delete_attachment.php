<?php
/**
 * SWAT Plugin - AJAX: Delete image attachment
 * csrf_compliant = true → GLPI bootstrap already validated CSRF.
 */

$__done = false;

ob_start(); // Buffer 1: bootstrap

register_shutdown_function(function () use (&$__done) {
    if ($__done) return;
    while (ob_get_level() > 0) { ob_end_clean(); }
    if (!headers_sent()) {
        header_remove();
        header('Content-Type: application/json');
    }
    echo json_encode(['success' => false, 'error' => 'auth_failed']);
});

include('../../../inc/includes.php');

ob_end_clean(); // End Buffer 1
ob_start();     // Buffer 2: our code

header('Content-Type: application/json');

if (!Session::haveRight('plugin_swat_form', READ)) {
    ob_end_clean();
    $__done = true;
    http_response_code(403);
    echo json_encode(['success' => false]);
    exit;
}

$att_id = (int)($_POST['attachment_id'] ?? 0);
if (!$att_id) {
    ob_end_clean();
    $__done = true;
    echo json_encode(['success' => false]);
    exit;
}

global $DB;
$row = null;
foreach ($DB->request(['FROM' => 'glpi_plugin_swat_attachments', 'WHERE' => ['id' => $att_id]]) as $r) {
    $row = $r;
}
if (!$row) {
    ob_end_clean();
    $__done = true;
    echo json_encode(['success' => false]);
    exit;
}

$filepath = GLPI_UPLOAD_DIR . '/' . $row['filepath'];
if (file_exists($filepath)) {
    unlink($filepath);
}
$DB->delete('glpi_plugin_swat_attachments', ['id' => $att_id]);

ob_end_clean();
$__done = true;
echo json_encode(['success' => true]);
