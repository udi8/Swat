<?php
/**
 * SWAT Plugin - AJAX: Upload image attachment
 *
 * csrf_compliant = true → GLPI bootstrap already validated CSRF.
 * Do NOT call Session::checkCSRF again.
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
    echo json_encode(['success' => false, 'error' => 'auth_failed',
        'message' => 'Session expired or access denied.']);
});

include('../../../inc/includes.php');

ob_end_clean(); // End Buffer 1
ob_start();     // Buffer 2: our code

header('Content-Type: application/json');

if (!Session::haveRight('plugin_swat_form', READ)) {
    ob_end_clean();
    $__done = true;
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Forbidden']);
    exit;
}

$form_id = (int)($_POST['form_id'] ?? 0);
if (!$form_id) {
    ob_end_clean();
    $__done = true;
    echo json_encode(['success' => false, 'error' => 'No form ID']);
    exit;
}

$file = $_FILES['image'] ?? null;
if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
    ob_end_clean();
    $__done = true;
    $code = $file['error'] ?? -1;
    $labels = [
        UPLOAD_ERR_INI_SIZE   => 'File exceeds php.ini upload_max_filesize',
        UPLOAD_ERR_FORM_SIZE  => 'File exceeds MAX_FILE_SIZE',
        UPLOAD_ERR_PARTIAL    => 'File only partially uploaded',
        UPLOAD_ERR_NO_FILE    => 'No file was uploaded',
        UPLOAD_ERR_NO_TMP_DIR => 'Missing temp folder',
        UPLOAD_ERR_CANT_WRITE => 'Failed to write to disk',
        UPLOAD_ERR_EXTENSION  => 'Upload blocked by PHP extension',
    ];
    echo json_encode(['success' => false, 'error' => 'Upload failed',
        'detail' => $labels[$code] ?? "PHP upload error $code"]);
    exit;
}

$allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
$finfo   = new finfo(FILEINFO_MIME_TYPE);
$mime    = $finfo->file($file['tmp_name']);
if (!in_array($mime, $allowed)) {
    ob_end_clean();
    $__done = true;
    echo json_encode(['success' => false, 'error' => 'Invalid file type', 'mime' => $mime]);
    exit;
}

if ($file['size'] > 5 * 1024 * 1024) {
    ob_end_clean();
    $__done = true;
    echo json_encode(['success' => false, 'error' => 'File too large (max 5 MB)']);
    exit;
}

$upload_dir = GLPI_UPLOAD_DIR . '/plugin_swat/';
if (!is_dir($upload_dir) && !mkdir($upload_dir, 0755, true)) {
    ob_end_clean();
    $__done = true;
    echo json_encode(['success' => false, 'error' => 'Cannot create upload directory']);
    exit;
}

$ext     = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
$newname = 'swat_' . $form_id . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
$dest    = $upload_dir . $newname;

if (!move_uploaded_file($file['tmp_name'], $dest)) {
    ob_end_clean();
    $__done = true;
    echo json_encode(['success' => false, 'error' => 'Could not save file',
        'dir_writable' => is_writable($upload_dir)]);
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

ob_end_clean();
$__done = true;
echo json_encode(['success' => true, 'attachment_id' => $att_id,
    'filename' => $file['name']]);
