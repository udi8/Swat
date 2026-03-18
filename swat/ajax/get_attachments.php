<?php
include('../../../inc/includes.php');
if (!Session::haveRight('plugin_swat_form', READ)) {
    http_response_code(403); exit;
}
$form_id = (int)($_GET['form_id'] ?? 0);
if (!$form_id) { echo json_encode([]); exit; }
global $DB;
$atts = [];
foreach ($DB->request(['FROM'=>'glpi_plugin_swat_attachments','WHERE'=>['forms_id'=>$form_id],'ORDER'=>'date_creation ASC']) as $r) {
    $atts[] = ['id'=>$r['id'],'filename'=>$r['filename'],'mime_type'=>$r['mime_type'],'filesize'=>$r['filesize']];
}
echo json_encode($atts);
exit;
