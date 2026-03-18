<?php
/**
 * SWAT Plugin - AJAX: Save profile rights
 * Note: CSRF is auto-validated by GLPI bootstrap for csrf_compliant plugins.
 */
include('../../../inc/includes.php');

Session::checkRight('profile', UPDATE);

header('Content-Type: application/json');

$profiles_id = (int) ($_POST['profiles_id'] ?? 0);
$field       = $_POST['field'] ?? '';
$rights      = (int) ($_POST['rights'] ?? 0);

$allowed_fields = ['plugin_swat_form', 'plugin_swat_admin'];

if (!$profiles_id || !in_array($field, $allowed_fields, true)) {
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

echo json_encode(['success' => true]);
