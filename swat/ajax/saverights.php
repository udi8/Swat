<?php
/**
 * SWAT Plugin - AJAX: Save profile rights
 *
 * csrf_compliant = true means the PLUGIN handles CSRF (not GLPI bootstrap).
 * We validate the token explicitly, but we also protect against any early
 * exit from the GLPI bootstrap (CSRF fail, session expired, etc.) by using
 * output buffering + a shutdown function that always sends valid JSON back.
 */

// ── Guarantee JSON response even if GLPI bootstrap exits early ──────────────
$__saverights_done = false;

ob_start();

register_shutdown_function(function() use (&$__saverights_done) {
    if ($__saverights_done) return;
    while (ob_get_level() > 0) { ob_end_clean(); }
    header_remove();
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'error'   => 'session_expired',
        'message' => 'Session expired or access denied – please reload the page.',
    ]);
});

include('../../../inc/includes.php');

while (ob_get_level() > 0) { ob_end_clean(); }

header('Content-Type: application/json');

// Validate CSRF token (plugin is responsible for this)
Session::checkCSRF($_POST);

if (!Session::haveRight('profile', UPDATE)) {
    $__saverights_done = true;
    echo json_encode(['success' => false, 'error' => 'Forbidden']);
    exit;
}

$profiles_id = (int) ($_POST['profiles_id'] ?? 0);
$field       = $_POST['field'] ?? '';
$rights      = (int) ($_POST['rights'] ?? 0);

$allowed_fields = ['plugin_swat_form', 'plugin_swat_admin'];

if (!$profiles_id || !in_array($field, $allowed_fields, true)) {
    $__saverights_done = true;
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
    $DB->update('glpi_profilerights', ['rights' => $rights], ['profiles_id' => $profiles_id, 'name' => $field]);
} else {
    $DB->insert('glpi_profilerights', ['profiles_id' => $profiles_id, 'name' => $field, 'rights' => $rights]);
}

$__saverights_done = true;
echo json_encode(['success' => true]);
