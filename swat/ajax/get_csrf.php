<?php
/**
 * SWAT Plugin – Return a fresh CSRF token for JS use.
 * Required for sequential multi-file uploads (GLPI tokens are single-use).
 */
include('../../../inc/includes.php');

// Only require an active GLPI session — this token is consumed by GLPI's
// own csrf_compliant bootstrap check on the subsequent POST request.
if (empty($_SESSION['glpiID'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Not logged in']);
    exit;
}

header('Content-Type: application/json');
echo json_encode(['token' => Session::getNewCSRFToken()]);
