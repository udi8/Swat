<?php
include('../../../inc/includes.php');
if (!Session::haveRight('plugin_swat_form', READ)) {
    http_response_code(403);
    echo json_encode(['success'=>false]); exit;
}
// CSRF auto-validated by GLPI bootstrap (csrf_compliant plugin)
$att_id = (int)($_POST['attachment_id'] ?? 0);
if (!$att_id) { echo json_encode(['success'=>false]); exit; }
global $DB;
$row = null;
foreach ($DB->request(['FROM'=>'glpi_plugin_swat_attachments','WHERE'=>['id'=>$att_id]]) as $r) { $row=$r; }
if (!$row) { echo json_encode(['success'=>false]); exit; }
$filepath = GLPI_UPLOAD_DIR . '/' . $row['filepath'];
if (file_exists($filepath)) unlink($filepath);
$DB->delete('glpi_plugin_swat_attachments', ['id'=>$att_id]);
echo json_encode(['success'=>true]);
exit;
