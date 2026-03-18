<?php
include('../../../inc/includes.php');
if (!Session::haveRight('plugin_swat_form', READ)) { Html::displayRightError(); exit; }
$att_id = (int)($_GET['id'] ?? 0);
if (!$att_id) Html::displayErrorAndDie('Invalid ID');
global $DB;
$row = null;
foreach ($DB->request(['FROM'=>'glpi_plugin_swat_attachments','WHERE'=>['id'=>$att_id]]) as $r) { $row=$r; }
if (!$row) Html::displayErrorAndDie('Attachment not found');
$filepath = GLPI_UPLOAD_DIR . '/' . $row['filepath'];
if (!file_exists($filepath)) Html::displayErrorAndDie('File not found');
header('Content-Type: ' . $row['mime_type']);
header('Content-Disposition: inline; filename="' . $row['filename'] . '"');
header('Content-Length: ' . filesize($filepath));
readfile($filepath);
exit;
