<?php
/**
 * SWAT Plugin – Debug Logs (Admin only)
 */
include('../../../inc/includes.php');

if (!Session::haveRight('plugin_swat_admin', READ) && !Session::haveRight('config', UPDATE)) {
    Html::displayRightError();
    exit;
}

// Handle clear action
if (isset($_GET['clear']) && (int)$_GET['clear'] === 1) {
    PluginSwatLog::clearLogs(0);
    PluginSwatLog::info('logs_cleared', 'All SWAT logs cleared by admin');
    Html::redirect(Plugin::getWebDir('swat') . '/front/swatlogs.php');
}

Html::header('SWAT Logs', '', 'management', 'PluginSwatDashboard');
PluginSwatLog::showLogViewer();
Html::footer();
