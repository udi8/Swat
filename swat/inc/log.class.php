<?php
/**
 * SWAT Plugin - Logging & Debug System
 */
class PluginSwatLog extends CommonDBTM {

    static $rightname = 'plugin_swat_admin';

    const LEVEL_DEBUG   = 'DEBUG';
    const LEVEL_INFO    = 'INFO';
    const LEVEL_WARNING = 'WARNING';
    const LEVEL_ERROR   = 'ERROR';

    // ── Write a log entry ─────────────────────────────────────────────────────
    public static function write(
        string $level,
        string $action,
        string $message,
        int    $forms_id = 0,
        array  $context  = []
    ): void {
        global $DB;

        $users_id = (int) ($_SESSION['glpiID'] ?? 0);
        $ip       = $_SERVER['REMOTE_ADDR'] ?? '';

        $DB->insert('glpi_plugin_swat_logs', [
            'log_time'   => date('Y-m-d H:i:s'),
            'level'      => $level,
            'users_id'   => $users_id,
            'forms_id'   => $forms_id,
            'action'     => $action,
            'message'    => $message,
            'context'    => json_encode($context, JSON_UNESCAPED_UNICODE),
            'ip_address' => $ip,
        ]);

        // Also write to GLPI's standard log file for critical levels
        if (in_array($level, [self::LEVEL_WARNING, self::LEVEL_ERROR])) {
            Toolbox::logError("[SWAT][{$level}][{$action}] {$message}");
        }
    }

    // Convenience wrappers
    public static function debug(string $action, string $msg, int $fid = 0, array $ctx = []): void {
        self::write(self::LEVEL_DEBUG, $action, $msg, $fid, $ctx);
    }
    public static function info(string $action, string $msg, int $fid = 0, array $ctx = []): void {
        self::write(self::LEVEL_INFO, $action, $msg, $fid, $ctx);
    }
    public static function warning(string $action, string $msg, int $fid = 0, array $ctx = []): void {
        self::write(self::LEVEL_WARNING, $action, $msg, $fid, $ctx);
    }
    public static function error(string $action, string $msg, int $fid = 0, array $ctx = []): void {
        self::write(self::LEVEL_ERROR, $action, $msg, $fid, $ctx);
    }

    // ── Display log viewer (admin only) ───────────────────────────────────────
    public static function showLogViewer(): void {
        global $DB;

        echo '<div class="swat-log-viewer">';
        echo '<div class="swat-log-toolbar">';
        echo '<h3><i class="fas fa-bug"></i> SWAT Debug Logs</h3>';

        // Filters
        $level    = $_GET['log_level']  ?? '';
        $form_id  = (int)($_GET['forms_id'] ?? 0);
        $dateFrom = $_GET['date_from']  ?? date('Y-m-d', strtotime('-7 days'));
        $dateTo   = $_GET['date_to']    ?? date('Y-m-d');

        echo '<form method="GET" class="swat-log-filter">';
        echo '<select name="log_level">
            <option value="">All Levels</option>
            <option value="DEBUG"   ' . ($level === 'DEBUG'   ? 'selected' : '') . '>DEBUG</option>
            <option value="INFO"    ' . ($level === 'INFO'    ? 'selected' : '') . '>INFO</option>
            <option value="WARNING" ' . ($level === 'WARNING' ? 'selected' : '') . '>WARNING</option>
            <option value="ERROR"   ' . ($level === 'ERROR'   ? 'selected' : '') . '>ERROR</option>
        </select>';
        echo '<input type="date" name="date_from" value="' . htmlspecialchars($dateFrom) . '">';
        echo '<input type="date" name="date_to"   value="' . htmlspecialchars($dateTo)   . '">';
        echo '<input type="number" name="forms_id" placeholder="Form ID" value="' . $form_id . '">';
        echo '<button type="submit" class="btn btn-sm btn-primary">Filter</button>';
        echo '<a href="' . Plugin::getWebDir('swat') . '/front/swatlogs.php?clear=1" class="btn btn-sm btn-danger" onclick="return confirm(\'Clear all logs?\')">Clear Logs</a>';
        echo '</form>';
        echo '</div>'; // toolbar

        // Query – GLPI 10 DBmysqli uses QueryExpression for raw comparisons
        $where = [
            new \QueryExpression("`log_time` >= '" . $DB->escape($dateFrom . ' 00:00:00') . "'"),
            new \QueryExpression("`log_time` <= '" . $DB->escape($dateTo   . ' 23:59:59') . "'"),
        ];
        if ($level)   $where['level']    = $level;
        if ($form_id) $where['forms_id'] = $form_id;

        $rows = $DB->request([
            'FROM'    => 'glpi_plugin_swat_logs',
            'WHERE'   => $where,
            'ORDER'   => 'log_time DESC',
            'LIMIT'   => 500,
        ]);

        echo '<table class="swat-log-table">';
        echo '<thead><tr>
            <th>Time</th>
            <th>Level</th>
            <th>User</th>
            <th>Form ID</th>
            <th>Action</th>
            <th>Message</th>
            <th>IP</th>
            <th>Context</th>
        </tr></thead><tbody>';

        foreach ($rows as $row) {
            $lvl   = htmlspecialchars($row['level']);
            $user  = getUserName($row['users_id']);
            $ctx   = $row['context'] ? json_decode($row['context'], true) : [];
            $ctxHtml = $ctx ? '<pre class="swat-log-ctx">' . htmlspecialchars(json_encode($ctx, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) . '</pre>' : '-';
            $formLink = $row['forms_id']
                ? '<a href="' . Plugin::getWebDir('swat') . '/front/swatform.php?action=view&id=' . (int)$row['forms_id'] . '">#' . (int)$row['forms_id'] . '</a>'
                : '-';

            echo "<tr class=\"swat-log-{$lvl}\">
                <td>{$row['log_time']}</td>
                <td><span class=\"swat-badge-{$lvl}\">{$lvl}</span></td>
                <td>" . htmlspecialchars($user) . "</td>
                <td>{$formLink}</td>
                <td>" . htmlspecialchars($row['action']) . "</td>
                <td>" . htmlspecialchars($row['message']) . "</td>
                <td>" . htmlspecialchars($row['ip_address']) . "</td>
                <td>{$ctxHtml}</td>
            </tr>";
        }

        echo '</tbody></table>';
        echo '</div>';
    }

    // ── Clear old logs ─────────────────────────────────────────────────────
    public static function clearLogs(int $days_old = 30): void {
        global $DB;
        $cutoff = date('Y-m-d H:i:s', strtotime("-{$days_old} days"));
        $DB->delete('glpi_plugin_swat_logs', ['log_time' => ['<', $cutoff]]);
    }

    public static function getMenuName() { return 'SWAT Logs'; }
    public function getName($with_comment = false) { return 'SWAT Log'; }
}
