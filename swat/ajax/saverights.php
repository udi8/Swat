<?php
/**
 * SWAT Plugin - AJAX: Save profile rights
 *
 * csrf_compliant = true means GLPI's bootstrap auto-validates and consumes
 * the CSRF token. Do NOT call Session::checkCSRF again — that would try to
 * validate an already-consumed token and return an HTML error page.
 *
 * Pattern: Buffer 1 catches bootstrap, Buffer 2 catches our own code.
 * The shutdown function guarantees JSON even on unexpected exit().
 */

$__done = false;

// ── Buffer 1: catch any HTML that bootstrap might emit on exit ───────────────
ob_start();

register_shutdown_function(function () use (&$__done) {
    if ($__done) return;
    while (ob_get_level() > 0) { ob_end_clean(); }
    if (!headers_sent()) {
        header_remove();
        header('Content-Type: application/json');
    }
    echo json_encode([
        'success' => false,
        'error'   => 'auth_failed',
        'message' => 'Session expired or access denied. Please reload the page.',
    ]);
});

include('../../../inc/includes.php');
// GLPI bootstrap already validated the CSRF token (csrf_compliant plugin).
// Do NOT call Session::checkCSRF – the token is already consumed.

ob_end_clean();       // ← End Buffer 1, discard bootstrap output
ob_start();           // ← Buffer 2: catch any output from our validation code

header('Content-Type: application/json');

if (!Session::haveRight('profile', UPDATE)) {
    ob_end_clean();
    $__done = true;
    echo json_encode(['success' => false, 'error' => 'Forbidden']);
    exit;
}

$profiles_id    = (int)($_POST['profiles_id'] ?? 0);
$field          = $_POST['field'] ?? '';
$rights         = (int)($_POST['rights'] ?? 0);
$allowed_fields = ['plugin_swat_form', 'plugin_swat_admin'];

if (!$profiles_id || !in_array($field, $allowed_fields, true)) {
    ob_end_clean();
    $__done = true;
    echo json_encode(['success' => false, 'error' => 'Invalid parameters']);
    exit;
}

global $DB;
$existing = $DB->request([
    'SELECT' => ['id'],
    'FROM'   => 'glpi_profilerights',
    'WHERE'  => ['profiles_id' => $profiles_id, 'name' => $field],
    'LIMIT'  => 1,
]);
if (count($existing)) {
    $DB->update('glpi_profilerights', ['rights' => $rights],
                ['profiles_id' => $profiles_id, 'name' => $field]);
} else {
    $DB->insert('glpi_profilerights',
                ['profiles_id' => $profiles_id, 'name' => $field, 'rights' => $rights]);
}

ob_end_clean();
$__done = true;
echo json_encode(['success' => true]);
