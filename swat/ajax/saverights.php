<?php
/**
 * SWAT Plugin - AJAX: Save profile rights
 */
include('../../../inc/includes.php');

Session::checkCSRF($_POST);
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
$DB->query("
    INSERT INTO `glpi_profilerights` (`profiles_id`, `name`, `rights`)
    VALUES ({$profiles_id}, '{$field}', {$rights})
    ON DUPLICATE KEY UPDATE `rights` = {$rights}
");

echo json_encode(['success' => true]);
