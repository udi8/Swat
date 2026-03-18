<?php
/**
 * SWAT Plugin - Permit Management
 */
class PluginSwatPermit extends CommonDBTM {

    static $rightname = 'plugin_swat_form';

    public function getName($with_comment = false) { return 'SWAT Permit'; }
    public static function getMenuName()           { return 'SWAT Permits'; }
    public static function getTypeName($nb = 0)    { return 'Permit'; }

    /**
     * Get all permits for a user (or all if admin)
     */
    public static function getPermitsForUser(int $users_id, bool $is_admin = false): array {
        global $DB;

        $where = ['is_deleted' => 0];
        if (!$is_admin) {
            $where['users_id'] = $users_id;
        }

        $permits = [];
        foreach ($DB->request(['FROM' => 'glpi_plugin_swat_permits', 'WHERE' => $where, 'ORDER' => 'date_creation DESC']) as $row) {
            // Count forms under this permit
            $count = countElementsInTable('glpi_plugin_swat_forms', [
                'permits_id' => $row['id'],
                'is_deleted' => 0,
            ]);
            $row['form_count'] = $count;
            $permits[] = $row;
        }
        return $permits;
    }

    /**
     * Get forms for a specific permit
     */
    public static function getFormsForPermit(int $permit_id): array {
        global $DB;

        $forms = [];
        foreach ($DB->request([
            'FROM'  => 'glpi_plugin_swat_forms',
            'WHERE' => ['permits_id' => $permit_id, 'is_deleted' => 0],
            'ORDER' => 'date_creation DESC',
        ]) as $row) {
            $row['cp_name']      = getUserName($row['users_id_cp']);
            $row['creator_name'] = getUserName($row['users_id_creator']);
            $forms[] = $row;
        }
        return $forms;
    }

    /**
     * Create or get permit by number
     */
    public static function getOrCreate(string $permit_number, int $users_id): int {
        global $DB;

        $existing = $DB->request([
            'FROM'  => 'glpi_plugin_swat_permits',
            'WHERE' => ['permit_number' => $permit_number, 'is_deleted' => 0],
            'LIMIT' => 1,
        ]);

        foreach ($existing as $row) {
            return (int) $row['id'];
        }

        // Create new
        $DB->insert('glpi_plugin_swat_permits', [
            'permit_number' => $permit_number,
            'users_id'      => $users_id,
            'date_creation' => date('Y-m-d H:i:s'),
            'date_mod'      => date('Y-m-d H:i:s'),
            'is_deleted'    => 0,
        ]);

        PluginSwatLog::info('permit_create', "Created permit #{$permit_number}", 0, ['permit_number' => $permit_number]);
        return (int) $DB->insertId();
    }
}
