<?php
/**
 * SWAT Plugin – Admin: Team Group Configuration
 * Accessible via Administration > Plugins > SWAT > Configure
 */
include('../../../inc/includes.php');

if (!Session::haveRight('plugin_swat_admin', READ)) {
    Html::displayRightError();
    exit;
}

$is_edit = Session::haveRight('plugin_swat_admin', UPDATE);

// Handle POST save (CSRF auto-validated by GLPI bootstrap for csrf_compliant plugins)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $is_edit) {
    global $DB;

    if (!$DB->tableExists('glpi_plugin_swat_teams')) {
        $DB->query("CREATE TABLE IF NOT EXISTS `glpi_plugin_swat_teams` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `groups_id` INT UNSIGNED NOT NULL DEFAULT 0,
            PRIMARY KEY (`id`), UNIQUE KEY `groups_id` (`groups_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    $DB->query("DELETE FROM `glpi_plugin_swat_teams`");
    foreach (array_map('intval', $_POST['team_groups'] ?? []) as $gid) {
        if ($gid > 0) {
            $DB->query("INSERT IGNORE INTO `glpi_plugin_swat_teams` (`groups_id`) VALUES ({$gid})");
        }
    }
    Html::redirect(Plugin::getWebDir('swat') . '/front/config.form.php?saved=1');
}

global $DB;

// Load currently configured SWAT team groups
$configured = [];
if ($DB->tableExists('glpi_plugin_swat_teams')) {
    foreach ($DB->request(['SELECT' => ['groups_id'], 'FROM' => 'glpi_plugin_swat_teams']) as $r) {
        $configured[] = (int)$r['groups_id'];
    }
}

// Load all GLPI groups (no is_deleted filter — GLPI ORM handles it)
$all_groups = [];
foreach ($DB->request(['SELECT' => ['id', 'name'], 'FROM' => 'glpi_groups', 'ORDER' => 'name ASC']) as $g) {
    $all_groups[] = ['id' => (int)$g['id'], 'name' => $g['name']];
}

Html::header('SWAT – Team Configuration', '', 'management', 'PluginSwatDashboard');
$root = Plugin::getWebDir('swat');
?>

<div class="swat-wrap">

<div class="swat-header">
    <h1>
        <span class="swat-logo-text">SWAT</span>
        <span style="font-size:1.1rem;font-weight:400;">
            Team Configuration &nbsp;|&nbsp; <span style="font-weight:400;">הגדרות צוות</span>
        </span>
    </h1>
    <div class="swat-header-actions">
        <a href="swatdashboard.php" class="swat-btn swat-btn-secondary">
            <i class="fas fa-arrow-left"></i> Dashboard
        </a>
    </div>
</div>

<?php if (isset($_GET['saved'])): ?>
<div class="alert alert-success m-3 mt-2">
    <i class="fas fa-check-circle me-2"></i>
    Configuration saved / ההגדרות נשמרו בהצלחה
</div>
<?php endif; ?>

<div class="swat-card">
    <div class="swat-card-header">
        <i class="fas fa-users-cog"></i> SWAT Team Groups / קבוצות צוות SWAT
    </div>
    <div class="swat-card-body">
        <p style="font-size:0.9rem;color:#555;margin-bottom:20px;line-height:1.6;">
            Select which GLPI groups are considered <strong>SWAT Teams</strong>.
            Members of selected groups will see all team members' forms in the
            <strong>Team</strong> tab on the dashboard.
            If no groups are selected, users see only forms from their own GLPI groups (auto-detected).<br>
            <small style="color:#888;">
                בחר אילו קבוצות GLPI הן צוותי SWAT. חברי קבוצות נבחרות יראו את טפסי חברי הצוות בדשבורד.
                אם לא נבחרות קבוצות, כל משתמש יראה את הטפסים של חברי הקבוצות שלו (גילוי אוטומטי).
            </small>
        </p>

        <?php if ($is_edit): ?>
        <form method="POST" action="config.form.php">
            <?php echo Html::hidden('_glpi_csrf_token', ['value' => Session::getNewCSRFToken()]); ?>

            <?php if (empty($all_groups)): ?>
            <div style="padding:16px;background:#fff8e1;border-radius:6px;border-left:4px solid #f59e0b;margin-bottom:16px;">
                <i class="fas fa-exclamation-triangle" style="color:#f59e0b;"></i>
                No groups found in GLPI. Create groups first in
                <a href="../../../front/group.php">Administration &gt; Groups</a>.<br>
                <small>לא נמצאו קבוצות בGLPI. צור קבוצות תחילה תחת ניהול &gt; קבוצות.</small>
            </div>
            <?php else: ?>
            <div style="display:flex;flex-wrap:wrap;gap:10px;margin-bottom:22px;">
                <?php foreach ($all_groups as $g): ?>
                <?php $chk = in_array($g['id'], $configured); ?>
                <label style="display:inline-flex;align-items:center;gap:8px;
                              background:<?= $chk ? '#e0f7f7' : '#f5f5f5' ?>;
                              padding:9px 16px;border-radius:6px;cursor:pointer;
                              border:2px solid <?= $chk ? '#006B6B' : '#e0e0e0' ?>;
                              font-size:0.9rem;">
                    <input type="checkbox" name="team_groups[]" value="<?= $g['id'] ?>"
                           <?= $chk ? 'checked' : '' ?> style="width:16px;height:16px;">
                    <span style="font-weight:<?= $chk ? '700' : '400' ?>;color:<?= $chk ? '#006B6B' : '#333' ?>;">
                        <?= htmlspecialchars($g['name']) ?>
                    </span>
                </label>
                <?php endforeach; ?>
            </div>
            <button type="submit"
                    style="background:#006B6B;color:#C8E000;border:none;border-radius:6px;
                           padding:9px 26px;font-size:0.95rem;font-weight:700;cursor:pointer;">
                <i class="fas fa-save me-1"></i> Save Configuration / שמור הגדרות
            </button>
            <?php endif; ?>
        </form>
        <?php else: ?>
        <div style="color:#888;padding:10px;">
            Read-only. You need SWAT Administration rights to modify team configuration.
        </div>
        <?php endif; ?>
    </div>
</div>

</div>

<?php Html::footer(); ?>
