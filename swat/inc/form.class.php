<?php
/**
 * SWAT Plugin - Form management
 */
class PluginSwatForm extends CommonDBTM {

    static $rightname = 'plugin_swat_form';

    public function getName($with_comment = false) { return 'SWAT Form'; }
    public static function getMenuName()           { return 'SWAT'; }
    public static function getTypeName($nb = 0)    { return 'SWAT Form'; }

    public static function getMenuContent() {
        $menu = [];
        if (Session::haveRight(static::$rightname, READ)) {
            $menu['title']   = 'SWAT';
            $menu['page']    = Plugin::getWebDir('swat') . '/front/swatdashboard.php';
            $menu['icon']    = 'fas fa-hard-hat';
            $menu['options'] = [
                'swat_form' => [
                    'title' => 'New Form',
                    'page'  => Plugin::getWebDir('swat') . '/front/swatform.php',
                    'icon'  => 'fas fa-plus',
                ],
                'swat_archive' => [
                    'title' => 'Archive',
                    'page'  => Plugin::getWebDir('swat') . '/front/swatarchive.php',
                    'icon'  => 'fas fa-archive',
                ],
            ];
            if (Session::haveRight('plugin_swat_admin', READ)) {
                $menu['options']['swat_logs'] = [
                    'title' => 'Debug Logs',
                    'page'  => Plugin::getWebDir('swat') . '/front/swatlogs.php',
                    'icon'  => 'fas fa-bug',
                ];
            }
        }
        return $menu;
    }

    /**
     * Save a SWAT form submission
     */
    public static function saveForm(array $post): array {
        global $DB;

        try {
            $users_id = (int) Session::getLoginUserID();

            // Resolve permit
            $permit_number = trim($post['work_permit_ref'] ?? '');
            $permits_id    = 0;
            if ($permit_number) {
                $permits_id = PluginSwatPermit::getOrCreate($permit_number, $users_id);
            }

            // Resolve CP
            $cp_users_id    = (int)($post['users_id_cp']    ?? $users_id);
            $cp_is_contact  = (int)($post['cp_is_contact']  ?? 0);

            // Build participants JSON is handled separately
            $hazards_controls = json_encode($post['hazards_controls'] ?? [], JSON_UNESCAPED_UNICODE);

            // Validate date and time formats
            $raw_date = $post['form_date'] ?? date('Y-m-d');
            $raw_time = $post['form_time'] ?? date('H:i:s');
            if (!empty($raw_date) && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw_date)) {
                $raw_date = date('Y-m-d');
            }
            if (!empty($raw_time) && !preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $raw_time)) {
                $raw_time = date('H:i:s');
            }

            $data = [
                'permits_id'             => $permits_id,
                'users_id_cp'            => $cp_users_id,
                'cp_is_contact'          => $cp_is_contact,
                'site_location'          => $post['site_location']       ?? '',
                'correct_unit'           => (int)($post['correct_unit']  ?? 0),
                'work_permit_ref'        => $permit_number,
                'form_date'              => $raw_date,
                'form_time'              => $raw_time,
                'shift'                  => in_array($post['shift'] ?? '', ['day','night']) ? $post['shift'] : 'day',
                'task_what'              => $post['task_what']            ?? '',
                'task_how'               => $post['task_how']             ?? '',
                // LSR
                'lsr_mechanical_lifting' => (int)($post['lsr_mechanical_lifting'] ?? 0),
                'lsr_work_at_height'     => (int)($post['lsr_work_at_height']     ?? 0),
                'lsr_driving_safety'     => (int)($post['lsr_driving_safety']     ?? 0),
                'lsr_line_of_fire'       => (int)($post['lsr_line_of_fire']       ?? 0),
                'lsr_work_authorization' => (int)($post['lsr_work_authorization'] ?? 0),
                'lsr_confined_space'     => (int)($post['lsr_confined_space']     ?? 0),
                'lsr_energy_isolation'   => (int)($post['lsr_energy_isolation']   ?? 0),
                'lsr_live_electrical'    => (int)($post['lsr_live_electrical']     ?? 0),
                'lsr_fire_explosion'     => (int)($post['lsr_fire_explosion']      ?? 0),
                // Hazards
                'hazard_adjacent_work'   => (int)($post['hazard_adjacent_work']   ?? 0),
                'hazard_ergonomics'      => (int)($post['hazard_ergonomics']       ?? 0),
                'hazard_heavy_lift'      => (int)($post['hazard_heavy_lift']       ?? 0),
                'hazard_burns'           => (int)($post['hazard_burns']            ?? 0),
                'hazard_comm_failure'    => (int)($post['hazard_comm_failure']     ?? 0),
                'hazard_dust_fume'       => (int)($post['hazard_dust_fume']        ?? 0),
                'hazard_electrical_shock'=> (int)($post['hazard_electrical_shock'] ?? 0),
                'hazard_env_weather'     => (int)($post['hazard_env_weather']      ?? 0),
                'hazard_fall_height'     => (int)($post['hazard_fall_height']      ?? 0),
                'hazard_dropped_objects' => (int)($post['hazard_dropped_objects']  ?? 0),
                'hazard_chemicals'       => (int)($post['hazard_chemicals']        ?? 0),
                'hazard_noise'           => (int)($post['hazard_noise']            ?? 0),
                'hazard_pinch_points'    => (int)($post['hazard_pinch_points']     ?? 0),
                'hazard_restricted_ws'   => (int)($post['hazard_restricted_ws']    ?? 0),
                'hazard_slips_trips'     => (int)($post['hazard_slips_trips']      ?? 0),
                'hazard_wildlife'        => (int)($post['hazard_wildlife']         ?? 0),
                'hazard_lighting'        => (int)($post['hazard_lighting']         ?? 0),
                'hazard_housekeeping'    => (int)($post['hazard_housekeeping']     ?? 0),
                'hazard_rotating_equip'  => (int)($post['hazard_rotating_equip']  ?? 0),
                'hazard_tool_failure'    => (int)($post['hazard_tool_failure']     ?? 0),
                'hazard_radiation'       => (int)($post['hazard_radiation']        ?? 0),
                'hazard_other1'          => $post['hazard_other1']                 ?? '',
                'hazard_other2'          => $post['hazard_other2']                 ?? '',
                'hazard_other3'          => $post['hazard_other3']                 ?? '',
                'hazard_other4'          => $post['hazard_other4']                 ?? '',
                // Page 2
                'task_description'       => $post['task_description']              ?? '',
                'task_detail'            => $post['task_detail']                   ?? '',
                'doc_work_procedures'    => (int)($post['doc_work_procedures']     ?? 0),
                'doc_drawing'            => (int)($post['doc_drawing']             ?? 0),
                'doc_lifting_plan'       => (int)($post['doc_lifting_plan']        ?? 0),
                'doc_tra'                => (int)($post['doc_tra']                 ?? 0),
                'doc_equip_manual'       => (int)($post['doc_equip_manual']        ?? 0),
                'doc_other'              => $post['doc_other']                     ?? '',
                'hazards_controls'       => $hazards_controls,
                'refocus1_time'          => $post['refocus1_time']                 ?? '',
                'refocus2_time'          => $post['refocus2_time']                 ?? '',
                'refocus3_time'          => $post['refocus3_time']                 ?? '',
                // Close out
                'closeout_task_complete' => (int)($post['closeout_task_complete']  ?? 0),
                'closeout_ehs_events'    => (int)($post['closeout_ehs_events']     ?? 0),
                'closeout_notified'      => $post['closeout_notified']             ?? '',
                'closeout_cp_name'       => $post['closeout_cp_name']              ?? '',
                'closeout_timestamp'     => (int)($post['closeout_task_complete']  ?? 0) ? date('Y-m-d H:i:s') : null,
                'status'                 => 'submitted',
                'users_id_creator'       => $users_id,
                'date_creation'          => date('Y-m-d H:i:s'),
                'date_mod'               => date('Y-m-d H:i:s'),
            ];

            // Check if editing existing
            $form_id = (int)($post['form_id'] ?? 0);

            $DB->beginTransaction();
            try {
                if ($form_id > 0) {
                    unset($data['date_creation'], $data['users_id_creator']);
                    $DB->update('glpi_plugin_swat_forms', $data, ['id' => $form_id]);
                    PluginSwatLog::info('form_update', "Updated form #{$form_id}", $form_id);
                } else {
                    $DB->insert('glpi_plugin_swat_forms', $data);
                    $form_id = $DB->insertId();
                    PluginSwatLog::info('form_create', "Created new form #{$form_id}", $form_id, ['permit' => $permit_number]);
                }

                // Save participants
                self::saveParticipants($form_id, $post['participants'] ?? []);

                $DB->commit();
            } catch (\Throwable $e) {
                $DB->rollBack();
                throw $e;
            }

            return ['success' => true, 'form_id' => $form_id];

        } catch (Exception $e) {
            PluginSwatLog::error('form_save_error', $e->getMessage(), 0, ['trace' => $e->getTraceAsString()]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Save participants for a form
     */
    private static function saveParticipants(int $form_id, array $participants): void {
        global $DB;

        $DB->delete('glpi_plugin_swat_participants', ['forms_id' => $form_id]);

        foreach ($participants as $i => $p) {
            // Use display_name_text if set (free text), fallback to display_name
            $name = trim($p['display_name_text'] ?? $p['display_name'] ?? '');
            if (empty($name)) continue;

            $DB->insert('glpi_plugin_swat_participants', [
                'forms_id'     => $form_id,
                'item_type'    => in_array($p['type'] ?? '', ['user','contact']) ? $p['type'] : 'manual',
                'items_id'     => (int)($p['items_id'] ?? 0),
                'display_name' => $name,
                'sort_order'   => $i + 1,
            ]);
        }
    }

    /**
     * Get participants for a form
     */
    public static function getParticipants(int $form_id): array {
        global $DB;

        $parts = [];
        foreach ($DB->request([
            'FROM'  => 'glpi_plugin_swat_participants',
            'WHERE' => ['forms_id' => $form_id],
            'ORDER' => 'sort_order ASC',
        ]) as $row) {
            $parts[] = $row;
        }
        return $parts;
    }

    /**
     * Load form data by ID
     */
    public static function getFormData(int $form_id): ?array {
        global $DB;

        foreach ($DB->request(['FROM' => 'glpi_plugin_swat_forms', 'WHERE' => ['id' => $form_id, 'is_deleted' => 0]]) as $row) {
            $row['participants']   = self::getParticipants($form_id);
            $row['hazards_controls'] = json_decode($row['hazards_controls'] ?? '[]', true);
            return $row;
        }
        return null;
    }

    /**
     * Get forms for current user's dashboard.
     * When include_team=true, uses GLPI groups to find team members.
     */
    public static function getDashboardForms(int $users_id, bool $include_team = false): array {
        global $DB;

        $where = ['is_deleted' => 0];

        if ($include_team) {
            // Check for admin-configured SWAT team groups
            $configured_groups = [];
            if ($DB->tableExists('glpi_plugin_swat_teams')) {
                foreach ($DB->request(['SELECT' => ['groups_id'], 'FROM' => 'glpi_plugin_swat_teams']) as $tg) {
                    $configured_groups[] = (int)$tg['groups_id'];
                }
            }

            if (empty($configured_groups)) {
                // No admin config: fall back to user's own GLPI groups
                foreach ($DB->request([
                    'SELECT' => ['groups_id'],
                    'FROM'   => 'glpi_groups_users',
                    'WHERE'  => ['users_id' => $users_id],
                ]) as $gr) {
                    $configured_groups[] = (int)$gr['groups_id'];
                }
            }

            if (!empty($configured_groups)) {
                // Find all users in the configured SWAT team groups
                $team_user_ids = [$users_id];
                foreach ($DB->request([
                    'SELECT' => ['users_id'],
                    'FROM'   => 'glpi_groups_users',
                    'WHERE'  => ['groups_id' => $configured_groups],
                ]) as $tu) {
                    $team_user_ids[] = (int)$tu['users_id'];
                }
                $where['users_id_creator'] = array_unique($team_user_ids);
            } else {
                $where['users_id_creator'] = $users_id;
            }
        } else {
            $where['users_id_creator'] = $users_id;
        }

        $forms = [];
        foreach ($DB->request([
            'FROM'  => 'glpi_plugin_swat_forms',
            'WHERE' => $where,
            'ORDER' => 'date_creation DESC',
            'LIMIT' => 100,
        ]) as $row) {
            $row['cp_name']      = getUserName($row['users_id_cp']);
            $row['creator_name'] = getUserName($row['users_id_creator']);
            $forms[] = $row;
        }
        return $forms;
    }
}
