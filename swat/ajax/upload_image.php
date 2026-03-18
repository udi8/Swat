<?php
/**
 * SWAT Plugin - AJAX: Upload image attachment
 * csrf_compliant = true  →  plugin validates CSRF explicitly.
 * Shutdown-function pattern ensures JSON is always returned.
 */

$__upload_done = false;

ob_start();

register_shutdown_function(function() use (&$__upload_done) {
    if ($__upload_done) return;
    while (ob_get_level() > 0) { ob_end_clean(); }
    header_remove();
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'session_expired',
        'message' => 'Session expired or access denied.']);
});

include('../../../inc/includes.php');

while (ob_get_level() > 0) { ob_end_clean(); }
header('Content-Type: application/json');

if (!Session::haveRight('plugin_swat_form', READ)) {
    $__upload_done = true;
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Forbidden']);
    exit;
}

// CSRF token is sent via FormData field _glpi_csrf_token
Session::checkCSRF($_POST);

$form_id = (int)($_POST['form_id'] ?? 0);
if (!$form_id) {
    $__upload_done = true;
    echo json_encode(['success' => false, 'error' => 'No form ID']);
    exit;
}

$file = $_FILES['image'] ?? null;
if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
    $__upload_done = true;
    $php_err = $file['error'] ?? -1;
    $php_err_labels = [
        UPLOAD_ERR_INI_SIZE   => 'File exceeds php.ini upload_max_filesize',
        UPLOAD_ERR_FORM_SIZE  => 'File exceeds MAX_FILE_SIZE',
        UPLOAD_ERR_PARTIAL    => 'File only partially uploaded',
        UPLOAD_ERR_NO_FILE    => 'No file was uploaded',
        UPLOAD_ERR_NO_TMP_DIR => 'Missing temp folder',
        UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
        UPLOAD_ERR_EXTENSION  => 'Upload stopped by extension',
    ];
    echo json_encode(['success' => false, 'error' => 'Upload failed',
        'detail' => $php_err_labels[$php_err] ?? "PHP error $php_err"]);
    exit;
}

// Validate MIME type
$allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
$finfo   = new finfo(FILEINFO_MIME_TYPE);
$mime    = $finfo->file($file['tmp_name']);
if (!in_array($mime, $allowed)) {
    $__upload_done = true;
    echo json_encode(['success' => false, 'error' => 'Invalid file type', 'mime' => $mime]);
    exit;
}

// Max 5 MB
if ($file['size'] > 5 * 1024 * 1024) {
    $__upload_done = true;
    echo json_encode(['success' => false, 'error' => 'File too large (max 5 MB)']);
    exit;
}

$upload_dir = GLPI_UPLOAD_DIR . '/plugin_swat/';
if (!is_dir($upload_dir) && !mkdir($upload_dir, 0755, true)) {
    $__upload_done = true;
    echo json_encode(['success' => false, 'error' => 'Cannot create upload directory',
        'path' => $upload_dir]);
    exit;
}

$ext     = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
$newname = 'swat_' . $form_id . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
$dest    = $upload_dir . $newname;

if (!move_uploaded_file($file['tmp_name'], $dest)) {
    $__upload_done = true;
    echo json_encode(['success' => false, 'error' => 'Could not save file',
        'dest' => $dest, 'writable' => is_writable($upload_dir)]);
    exit;
}

global $DB;
$DB->insert('glpi_plugin_swat_attachments', [
    'forms_id'      => $form_id,
    'users_id'      => (int) Session::getLoginUserID(),
    'filename'      => $file['name'],
    'filepath'      => 'plugin_swat/' . $newname,
    'mime_type'     => $mime,
    'filesize'      => $file['size'],
    'date_creation' => date('Y-m-d H:i:s'),
]);
$att_id = $DB->insertId();

$__upload_done = true;
echo json_encode(['success' => true, 'attachment_id' => $att_id,
    'filename' => $file['name'], 'newname' => $newname]);
