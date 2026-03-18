<?php
/**
 * SWAT Plugin – Profile rights display
 * GLPI includes this file when rendering the Profile edit form.
 */
if (!defined('GLPI_ROOT')) {
    die('Direct access forbidden.');
}

// Get the profile ID being edited
$profiles_id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);

// Handle POST save of rights
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['_swat_rights'])) {
    Session::checkCSRF($_POST);
    PluginSwatProfile::saveRightsForProfile($profiles_id, $_POST['_swat_rights']);
}

// Display the rights table
if ($profiles_id > 0) {
    PluginSwatProfile::showForProfile($profiles_id);
}
