<?php
/**
 * SWAT Plugin for GLPI 10.0.6
 */

define('PLUGIN_SWAT_VERSION', '1.0.0');
define('PLUGIN_SWAT_MIN_GLPI', '10.0.0');
define('PLUGIN_SWAT_MAX_GLPI', '10.0.99');

function plugin_init_swat() {
    global $PLUGIN_HOOKS;

    $PLUGIN_HOOKS['csrf_compliant']['swat'] = true;

    // Add SWAT tab to Administration > Profiles (correct GLPI 10 method)
    Plugin::registerClass('PluginSwatProfile', ['addtabon' => ['Profile']]);

    // Refresh session rights when user switches profile
    $PLUGIN_HOOKS['change_profile']['swat'] = ['PluginSwatProfile', 'initProfile'];

    // Menu under Management
    $PLUGIN_HOOKS['menu_toadd']['swat'] = ['management' => 'PluginSwatDashboard'];

    if (isset($_SESSION['glpiID'])) {
        $PLUGIN_HOOKS['add_css']['swat']        = ['css/swat.css'];
        $PLUGIN_HOOKS['add_javascript']['swat'] = ['js/swat.js'];
    }
}

function plugin_version_swat() {
    return [
        'name'         => 'SWAT - Start Work Assessment Tool',
        'version'      => PLUGIN_SWAT_VERSION,
        'author'       => 'GE Vernova',
        'license'      => 'GPLv2+',
        'homepage'     => '',
        'requirements' => [
            'glpi' => [
                'min' => PLUGIN_SWAT_MIN_GLPI,
                'max' => PLUGIN_SWAT_MAX_GLPI,
            ],
            'php' => ['min' => '7.4'],
        ],
    ];
}

function plugin_swat_check_prerequisites() {
    if (version_compare(GLPI_VERSION, PLUGIN_SWAT_MIN_GLPI, 'lt')
        || version_compare(GLPI_VERSION, PLUGIN_SWAT_MAX_GLPI, 'gt')) {
        echo 'This plugin requires GLPI >= ' . PLUGIN_SWAT_MIN_GLPI;
        return false;
    }
    return true;
}

function plugin_swat_check_config($verbose = false) {
    return true;
}
