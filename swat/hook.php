<?php
/**
 * SWAT Plugin - Install / Uninstall hooks
 * Compatible with GLPI 10.0.6 on Windows Server / MySQL 8.0.28
 */

function plugin_swat_install() {
    global $DB;

    $charset   = 'utf8mb4';
    $collation = 'utf8mb4_unicode_ci';

    // ── Table: permits ────────────────────────────────────────────────────────
    if (!$DB->tableExists('glpi_plugin_swat_permits')) {
        $DB->query("
            CREATE TABLE `glpi_plugin_swat_permits` (
                `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `permit_number` VARCHAR(100) NOT NULL DEFAULT '',
                `description`   TEXT,
                `users_id`      INT UNSIGNED NOT NULL DEFAULT 0,
                `date_creation` DATETIME DEFAULT NULL,
                `date_mod`      DATETIME DEFAULT NULL,
                `is_deleted`    TINYINT NOT NULL DEFAULT 0,
                PRIMARY KEY (`id`),
                KEY `users_id`      (`users_id`),
                KEY `permit_number` (`permit_number`)
            ) ENGINE=InnoDB DEFAULT CHARSET={$charset} COLLATE={$collation}
        ") or die('SWAT: Error creating glpi_plugin_swat_permits: ' . $DB->error());
    }

    // ── Table: forms ──────────────────────────────────────────────────────────
    if (!$DB->tableExists('glpi_plugin_swat_forms')) {
        $DB->query("
            CREATE TABLE `glpi_plugin_swat_forms` (
                `id`                      INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `permits_id`              INT UNSIGNED NOT NULL DEFAULT 0,
                `users_id_cp`             INT UNSIGNED NOT NULL DEFAULT 0,
                `cp_is_contact`           TINYINT NOT NULL DEFAULT 0,
                `site_location`           VARCHAR(255) NOT NULL DEFAULT '',
                `correct_unit`            TINYINT NOT NULL DEFAULT 0,
                `work_permit_ref`         VARCHAR(100) NOT NULL DEFAULT '',
                `form_date`               DATE DEFAULT NULL,
                `form_time`               TIME DEFAULT NULL,
                `shift`                   VARCHAR(10) NOT NULL DEFAULT 'day',
                `task_what`               TEXT,
                `task_how`                TEXT,
                `lsr_mechanical_lifting`  TINYINT NOT NULL DEFAULT 0,
                `lsr_work_at_height`      TINYINT NOT NULL DEFAULT 0,
                `lsr_driving_safety`      TINYINT NOT NULL DEFAULT 0,
                `lsr_line_of_fire`        TINYINT NOT NULL DEFAULT 0,
                `lsr_work_authorization`  TINYINT NOT NULL DEFAULT 0,
                `lsr_confined_space`      TINYINT NOT NULL DEFAULT 0,
                `lsr_energy_isolation`    TINYINT NOT NULL DEFAULT 0,
                `lsr_live_electrical`     TINYINT NOT NULL DEFAULT 0,
                `lsr_fire_explosion`      TINYINT NOT NULL DEFAULT 0,
                `hazard_adjacent_work`    TINYINT NOT NULL DEFAULT 0,
                `hazard_ergonomics`       TINYINT NOT NULL DEFAULT 0,
                `hazard_heavy_lift`       TINYINT NOT NULL DEFAULT 0,
                `hazard_burns`            TINYINT NOT NULL DEFAULT 0,
                `hazard_comm_failure`     TINYINT NOT NULL DEFAULT 0,
                `hazard_dust_fume`        TINYINT NOT NULL DEFAULT 0,
                `hazard_electrical_shock` TINYINT NOT NULL DEFAULT 0,
                `hazard_env_weather`      TINYINT NOT NULL DEFAULT 0,
                `hazard_fall_height`      TINYINT NOT NULL DEFAULT 0,
                `hazard_dropped_objects`  TINYINT NOT NULL DEFAULT 0,
                `hazard_chemicals`        TINYINT NOT NULL DEFAULT 0,
                `hazard_noise`            TINYINT NOT NULL DEFAULT 0,
                `hazard_pinch_points`     TINYINT NOT NULL DEFAULT 0,
                `hazard_restricted_ws`    TINYINT NOT NULL DEFAULT 0,
                `hazard_slips_trips`      TINYINT NOT NULL DEFAULT 0,
                `hazard_wildlife`         TINYINT NOT NULL DEFAULT 0,
                `hazard_lighting`         TINYINT NOT NULL DEFAULT 0,
                `hazard_housekeeping`     TINYINT NOT NULL DEFAULT 0,
                `hazard_rotating_equip`   TINYINT NOT NULL DEFAULT 0,
                `hazard_tool_failure`     TINYINT NOT NULL DEFAULT 0,
                `hazard_radiation`        TINYINT NOT NULL DEFAULT 0,
                `hazard_other1`           VARCHAR(255) NOT NULL DEFAULT '',
                `hazard_other2`           VARCHAR(255) NOT NULL DEFAULT '',
                `hazard_other3`           VARCHAR(255) NOT NULL DEFAULT '',
                `hazard_other4`           VARCHAR(255) NOT NULL DEFAULT '',
                `task_description`        TEXT,
                `task_detail`             TEXT,
                `doc_work_procedures`     TINYINT NOT NULL DEFAULT 0,
                `doc_drawing`             TINYINT NOT NULL DEFAULT 0,
                `doc_lifting_plan`        TINYINT NOT NULL DEFAULT 0,
                `doc_tra`                 TINYINT NOT NULL DEFAULT 0,
                `doc_equip_manual`        TINYINT NOT NULL DEFAULT 0,
                `doc_other`               VARCHAR(255) NOT NULL DEFAULT '',
                `hazards_controls`        TEXT,
                `refocus1_time`           VARCHAR(10) NOT NULL DEFAULT '',
                `refocus2_time`           VARCHAR(10) NOT NULL DEFAULT '',
                `refocus3_time`           VARCHAR(10) NOT NULL DEFAULT '',
                `closeout_task_complete`  TINYINT NOT NULL DEFAULT 0,
                `closeout_ehs_events`     TINYINT NOT NULL DEFAULT 0,
                `closeout_notified`       VARCHAR(255) NOT NULL DEFAULT '',
                `closeout_cp_name`        VARCHAR(255) NOT NULL DEFAULT '',
                `closeout_timestamp`      DATETIME DEFAULT NULL,
                `status`                  VARCHAR(20) NOT NULL DEFAULT 'draft',
                `users_id_creator`        INT UNSIGNED NOT NULL DEFAULT 0,
                `date_creation`           DATETIME DEFAULT NULL,
                `date_mod`                DATETIME DEFAULT NULL,
                `is_deleted`              TINYINT NOT NULL DEFAULT 0,
                PRIMARY KEY (`id`),
                KEY `permits_id`       (`permits_id`),
                KEY `users_id_cp`      (`users_id_cp`),
                KEY `users_id_creator` (`users_id_creator`)
            ) ENGINE=InnoDB DEFAULT CHARSET={$charset} COLLATE={$collation}
        ") or die('SWAT: Error creating glpi_plugin_swat_forms: ' . $DB->error());
    }

    // ── Table: participants ───────────────────────────────────────────────────
    if (!$DB->tableExists('glpi_plugin_swat_participants')) {
        $DB->query("
            CREATE TABLE `glpi_plugin_swat_participants` (
                `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `forms_id`     INT UNSIGNED NOT NULL DEFAULT 0,
                `item_type`    VARCHAR(10)  NOT NULL DEFAULT 'user',
                `items_id`     INT UNSIGNED NOT NULL DEFAULT 0,
                `display_name` VARCHAR(255) NOT NULL DEFAULT '',
                `sort_order`   INT NOT NULL DEFAULT 0,
                PRIMARY KEY (`id`),
                KEY `forms_id` (`forms_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET={$charset} COLLATE={$collation}
        ") or die('SWAT: Error creating glpi_plugin_swat_participants: ' . $DB->error());
    }

    // ── Table: logs ───────────────────────────────────────────────────────────
    if (!$DB->tableExists('glpi_plugin_swat_logs')) {
        $DB->query("
            CREATE TABLE `glpi_plugin_swat_logs` (
                `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `log_time`   DATETIME NOT NULL,
                `level`      VARCHAR(10) NOT NULL DEFAULT 'INFO',
                `users_id`   INT UNSIGNED NOT NULL DEFAULT 0,
                `forms_id`   INT UNSIGNED NOT NULL DEFAULT 0,
                `action`     VARCHAR(100) NOT NULL DEFAULT '',
                `message`    TEXT,
                `context`    TEXT,
                `ip_address` VARCHAR(45) NOT NULL DEFAULT '',
                PRIMARY KEY (`id`),
                KEY `log_time` (`log_time`),
                KEY `level`    (`level`)
            ) ENGINE=InnoDB DEFAULT CHARSET={$charset} COLLATE={$collation}
        ") or die('SWAT: Error creating glpi_plugin_swat_logs: ' . $DB->error());
    }

    // ── Table: attachments ────────────────────────────────────────────────────
    if (!$DB->tableExists('glpi_plugin_swat_attachments')) {
        $DB->query("
            CREATE TABLE `glpi_plugin_swat_attachments` (
                `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `forms_id`      INT UNSIGNED NOT NULL DEFAULT 0,
                `users_id`      INT UNSIGNED NOT NULL DEFAULT 0,
                `filename`      VARCHAR(255) NOT NULL DEFAULT '',
                `filepath`      VARCHAR(500) NOT NULL DEFAULT '',
                `mime_type`     VARCHAR(100) NOT NULL DEFAULT '',
                `filesize`      INT UNSIGNED NOT NULL DEFAULT 0,
                `date_creation` DATETIME DEFAULT NULL,
                PRIMARY KEY (`id`),
                KEY `forms_id` (`forms_id`),
                KEY `users_id` (`users_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET={$charset} COLLATE={$collation}
        ") or die('SWAT: Error creating glpi_plugin_swat_attachments: ' . $DB->error());
    }

    // ── Rights ────────────────────────────────────────────────────────────────
    $right_names = ['plugin_swat_form', 'plugin_swat_admin'];

    // All profile IDs
    $all_pids = [];
    $res = $DB->query("SELECT `id` FROM `glpi_profiles`");
    while ($row = $DB->fetchAssoc($res)) {
        $all_pids[] = (int) $row['id'];
    }

    // Super-admin profile IDs (have config right)
    $admin_pids = [];
    $res2 = $DB->query("SELECT `profiles_id` FROM `glpi_profilerights` WHERE `name` = 'config' AND `rights` > 0");
    while ($row = $DB->fetchAssoc($res2)) {
        $admin_pids[] = (int) $row['profiles_id'];
    }

    foreach ($right_names as $rname) {
        $res3 = $DB->query("SELECT COUNT(*) as cnt FROM `glpi_profilerights` WHERE `name` = '{$rname}'");
        $cnt  = (int) $DB->fetchAssoc($res3)['cnt'];
        if ($cnt > 0) continue;

        foreach ($all_pids as $pid) {
            $rval = in_array($pid, $admin_pids) ? 31 : 0;
            $DB->query("INSERT IGNORE INTO `glpi_profilerights` (`profiles_id`, `name`, `rights`) VALUES ({$pid}, '{$rname}', {$rval})");
        }
    }

    plugin_swat_grant_admin_rights();

    return true;
}

function plugin_swat_uninstall() {
    global $DB;

    foreach (['glpi_plugin_swat_logs','glpi_plugin_swat_attachments','glpi_plugin_swat_participants','glpi_plugin_swat_forms','glpi_plugin_swat_permits'] as $t) {
        if ($DB->tableExists($t)) {
            $DB->query("DROP TABLE `{$t}`");
        }
    }
    $DB->query("DELETE FROM `glpi_profilerights` WHERE `name` IN ('plugin_swat_form','plugin_swat_admin')");

    return true;
}

/**
 * Helper: grant SWAT rights to all super-admin profiles.
 * Called separately so it can be triggered manually if needed.
 */
function plugin_swat_grant_admin_rights() {
    global $DB;

    $right_names = ['plugin_swat_form', 'plugin_swat_admin'];

    // Super-admin profile IDs
    $admin_pids = [];
    $res = $DB->query("SELECT `profiles_id` FROM `glpi_profilerights` WHERE `name` = 'config' AND `rights` > 0");
    while ($row = $DB->fetchAssoc($res)) {
        $admin_pids[] = (int) $row['profiles_id'];
    }

    foreach ($right_names as $rname) {
        foreach ($admin_pids as $pid) {
            // UPDATE existing row OR insert - handles both cases
            $DB->query("
                INSERT INTO `glpi_profilerights` (`profiles_id`, `name`, `rights`)
                VALUES ({$pid}, '{$rname}', 31)
                ON DUPLICATE KEY UPDATE `rights` = 31
            ");
        }
    }
}
