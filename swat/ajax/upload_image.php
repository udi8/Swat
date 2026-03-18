<?php
include('../../../inc/includes.php');
if (!Session::haveRight('plugin_swat_form', READ)) {
    http_response_code(403);
    echo json_encode(['success'=>false,'error'=>'Forbidden']);
    exit;
}
Session::checkCSRF($_POST);
$form_id = (int)($_POST['form_id'] ?? 0);
if (!$form_id) { echo json_encode(['success'=>false,'error'=>'No form ID']); exit; }
$file = $_FILES['image'] ?? null;
if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success'=>false,'error'=>'Upload failed']);
    exit;
}
// Validate type
$allowed = ['image/jpeg','image/png','image/gif','image/webp'];
$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime = $finfo->file($file['tmp_name']);
if (!in_array($mime, $allowed)) {
    echo json_encode(['success'=>false,'error'=>'Invalid file type']);
    exit;
}
// Max 5MB
if ($file['size'] > 5 * 1024 * 1024) {
    echo json_encode(['success'=>false,'error'=>'File too large (max 5MB)']);
    exit;
}
$upload_dir = GLPI_UPLOAD_DIR . '/plugin_swat/';
if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
$ext = pathinfo($file['name'], PATHINFO_EXTENSION);
$newname = 'swat_' . $form_id . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . strtolower($ext);
$dest = $upload_dir . $newname;
if (!move_uploaded_file($file['tmp_name'], $dest)) {
    echo json_encode(['success'=>false,'error'=>'Could not save file']);
    exit;
}
global $DB;
$DB->insert('glpi_plugin_swat_attachments', [
    'forms_id'      => $form_id,
    'users_id'      => (int)Session::getLoginUserID(),
    'filename'      => $file['name'],
    'filepath'      => 'plugin_swat/' . $newname,
    'mime_type'     => $mime,
    'filesize'      => $file['size'],
    'date_creation' => date('Y-m-d H:i:s'),
]);
$att_id = $DB->insertId();
echo json_encode(['success'=>true,'attachment_id'=>$att_id,'filename'=>$file['name'],'newname'=>$newname]);
exit;
