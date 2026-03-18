<?php
/**
 * SWAT Plugin – Return a fresh CSRF token for JS use.
 * Required for sequential multi-file uploads (GLPI tokens are single-use).
 */
include('../../../inc/includes.php');

if (!Session::haveRight('plugin_swat_form', READ)) {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden']);
    exit;
}

header('Content-Type: application/json');
echo json_encode(['token' => Session::getNewCSRFToken()]);
