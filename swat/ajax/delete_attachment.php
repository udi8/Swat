<?php
/**
 * SWAT Plugin - AJAX: Delete image attachment
 * csrf_compliant = true  →  plugin validates CSRF explicitly.
 */

$__delete_done = false;

ob_start();

register_shutdown_function(function() use (&$__delete_done) {
    if ($__delete_done) return;
    while (ob_get_level() > 0) { ob_end_clean(); }
    header_remove();
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'session_expired']);
});

include('../../../inc/includes.php');

while (ob_get_level() > 0) { ob_end_clean(); }
header('Content-Type: application/json');

if (!Session::haveRight('plugin_swat_form', READ)) {
    $__delete_done = true;
    http_response_code(403);
    echo json_encode(['success' => false]);
    exit;
}

Session::checkCSRF($_POST);

$att_id = (int)($_POST['attachment_id'] ?? 0);
if (!$att_id) {
    $__delete_done = true;
    echo json_encode(['success' => false]);
    exit;
}

global $DB;
$row = null;
foreach ($DB->request(['FROM' => 'glpi_plugin_swat_attachments', 'WHERE' => ['id' => $att_id]]) as $r) {
    $row = $r;
}
if (!$row) {
    $__delete_done = true;
    echo json_encode(['success' => false]);
    exit;
}

$filepath = GLPI_UPLOAD_DIR . '/' . $row['filepath'];
if (file_exists($filepath)) {
    unlink($filepath);
}
$DB->delete('glpi_plugin_swat_attachments', ['id' => $att_id]);

$__delete_done = true;
echo json_encode(['success' => true]);
