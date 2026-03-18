<?php
/**
 * SWAT Plugin - Dashboard class (used for menu registration)
 */
class PluginSwatDashboard extends CommonGLPI {

    static $rightname = 'plugin_swat_form';

    public static function getMenuName() {
        return 'SWAT';
    }

    public static function getMenuContent() {
        // Build menu for anyone who can view - GLPI checks rights separately
        $menu = [];
        $menu['title']   = 'SWAT';
        $menu['page']    = Plugin::getWebDir('swat', false) . '/front/swatdashboard.php';
        $menu['icon']    = 'fas fa-hard-hat';
        $menu['options'] = [
            'swat_form' => [
                'title' => 'New Form',
                'page'  => Plugin::getWebDir('swat', false) . '/front/swatform.php',
                'icon'  => 'fas fa-plus',
            ],
            'swat_archive' => [
                'title' => 'Archive',
                'page'  => Plugin::getWebDir('swat', false) . '/front/swatarchive.php',
                'icon'  => 'fas fa-archive',
            ],
            'swat_logs' => [
                'title' => 'Debug Logs',
                'page'  => Plugin::getWebDir('swat', false) . '/front/swatlogs.php',
                'icon'  => 'fas fa-bug',
            ],
        ];
        return $menu;
    }

    public static function getTypeName($nb = 0) {
        return 'SWAT Dashboard';
    }

    public function getName($with_comment = false) {
        return 'SWAT Dashboard';
    }

    public static function getMenuFrontend() {
        return Plugin::getWebDir('swat', false) . '/front/swatdashboard.php';
    }

    public static function canView() {
        return Session::haveRight(static::$rightname, READ)
            || Session::isCron();
    }
}
