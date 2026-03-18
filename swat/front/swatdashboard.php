<?php
/**
 * SWAT Plugin – Dashboard
 */
include('../../../inc/includes.php');

if (!Session::haveRight('plugin_swat_form', READ) && !Session::haveRight('config', UPDATE)) {
    Html::displayRightError(); exit;
}

$current_user_id   = (int) Session::getLoginUserID();
$current_user_name = getUserName($current_user_id);
$is_admin          = Session::haveRight('plugin_swat_admin', READ);

// ── Handle status change / archive ────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['swat_action'])) {
    global $DB;
    $fid = (int)($_POST['form_id'] ?? 0);
    if ($fid && Session::haveRight('plugin_swat_form', UPDATE)) {
        switch ($_POST['swat_action']) {
            case 'set_status':
                $new_status = in_array($_POST['status'] ?? '', ['submitted','closed','archived'])
                    ? $_POST['status'] : 'submitted';
                $DB->update('glpi_plugin_swat_forms', ['status' => $new_status], ['id' => $fid]);
                break;
        }
    }
    $tab = $_POST['tab'] ?? 'mine';
    Html::redirect('swatdashboard.php?tab=' . $tab);
}

// ── Tab: my / team ────────────────────────────────────────────────────────────
$tab = $_GET['tab'] ?? 'mine';  // 'mine' or 'team'

$my_forms   = PluginSwatForm::getDashboardForms($current_user_id, false);
$team_forms = PluginSwatForm::getDashboardForms($current_user_id, true);

// Active filter
$filter_status = $_GET['status'] ?? '';
$filter_permit = $_GET['permit'] ?? '';

function apply_filters(array $forms, string $status, string $permit): array {
    return array_filter($forms, function($f) use ($status, $permit) {
        if ($status && $f['status'] !== $status) return false;
        if ($permit && ($f['work_permit_ref'] ?? '') !== $permit) return false;
        return true;
    });
}

$show_forms = $tab === 'team' ? $team_forms : $my_forms;
$filtered   = array_values(apply_filters($show_forms, $filter_status, $filter_permit));

// ── Stats ─────────────────────────────────────────────────────────────────────
$my_open   = count(array_filter($my_forms,   fn($f) => $f['status'] === 'submitted'));
$my_closed = count(array_filter($my_forms,   fn($f) => $f['status'] === 'closed'));
$tm_open   = count(array_filter($team_forms, fn($f) => $f['status'] === 'submitted'));

// ── Permits grouped ───────────────────────────────────────────────────────────
$permit_map = [];
foreach ($show_forms as $f) {
    $p = $f['work_permit_ref'] ?? '—';
    $permit_map[$p][] = $f;
}

Html::header('SWAT Dashboard', '', 'management', 'PluginSwatDashboard');
$root = Plugin::getWebDir('swat');
?>

<div class="swat-wrap">

<!-- ── Header ─────────────────────────────────────────────────────────── -->
<div class="swat-header">
    <h1>
        <span class="swat-logo-text">SWAT</span>
        <span style="font-size:1.1rem;font-weight:400;">
            My Dashboard &nbsp;|&nbsp; <?= htmlspecialchars($current_user_name) ?>
        </span>
    </h1>
    <div class="swat-header-actions">
        <a href="swatform.php?action=new" class="swat-btn swat-btn-lime">
            <i class="fas fa-plus"></i> New Form
        </a>
        <?php if ($is_admin): ?>
        <a href="swatlogs.php" class="swat-btn swat-btn-secondary">
            <i class="fas fa-bug"></i> Logs
        </a>
        <?php endif; ?>
    </div>
</div>

<!-- ── Stats ──────────────────────────────────────────────────────────── -->
<div class="swat-stats-row" style="margin:16px 0;">
    <div class="swat-stat-box <?= !$filter_status ? 'active' : '' ?>"
         onclick="setFilter('status','')" style="cursor:pointer;">
        <div class="stat-num"><?= count($my_forms) ?></div>
        <div class="stat-label">My Forms / הטפסים שלי</div>
    </div>
    <div class="swat-stat-box <?= $filter_status==='submitted'?'active':'' ?>"
         onclick="setFilter('status','submitted')" style="cursor:pointer;border-color:#f59e0b;">
        <div class="stat-num" style="color:#f59e0b;"><?= $my_open ?></div>
        <div class="stat-label">Active / פעילים</div>
    </div>
    <div class="swat-stat-box <?= $filter_status==='closed'?'active':'' ?>"
         onclick="setFilter('status','closed')" style="cursor:pointer;border-color:#10b981;">
        <div class="stat-num" style="color:#10b981;"><?= $my_closed ?></div>
        <div class="stat-label">Closed / סגורים</div>
    </div>
    <div class="swat-stat-box" style="border-color:var(--swat-teal);">
        <div class="stat-num"><?= count($permit_map) ?></div>
        <div class="stat-label">Active Permits / פרמיטים</div>
    </div>
</div>

<!-- ── Tabs: Mine / Team ─────────────────────────────────────────────── -->
<div class="swat-tabs" style="display:flex;gap:0;margin-bottom:16px;border-bottom:2px solid var(--swat-teal);">
    <a href="?tab=mine<?= $filter_status?"&status=$filter_status":'' ?>"
       class="swat-tab <?= $tab==='mine'?'active':'' ?>">
        <i class="fas fa-user"></i> My Forms (<?= count($my_forms) ?>)
    </a>
    <a href="?tab=team<?= $filter_status?"&status=$filter_status":'' ?>"
       class="swat-tab <?= $tab==='team'?'active':'' ?>">
        <i class="fas fa-users"></i> Team Forms (<?= count($team_forms) ?>)
    </a>
</div>

<!-- ── Active filters strip ──────────────────────────────────────────── -->
<?php if ($filter_status || $filter_permit): ?>
<div style="margin-bottom:10px;display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
    <span style="font-size:0.82rem;color:#666;">Filters:</span>
    <?php if ($filter_status): ?>
    <span class="swat-status-badge swat-status-<?= $filter_status ?>">
        <?= $filter_status ?>
        <a href="?tab=<?= $tab ?>" style="color:inherit;margin-left:4px;">✕</a>
    </span>
    <?php endif; ?>
    <?php if ($filter_permit): ?>
    <span class="swat-status-badge swat-status-submitted">
        Permit: <?= htmlspecialchars($filter_permit) ?>
        <a href="?tab=<?= $tab ?><?= $filter_status?"&status=$filter_status":'' ?>" style="color:inherit;margin-left:4px;">✕</a>
    </span>
    <?php endif; ?>
</div>
<?php endif; ?>

<!-- ── Permits grouped view ──────────────────────────────────────────── -->
<?php if (!$filter_status && !$filter_permit): ?>
<div class="swat-card" style="margin-bottom:16px;">
    <div class="swat-card-header">
        <i class="fas fa-id-card"></i> Permits Overview
        <span class="he">סקירת פרמיטים</span>
    </div>
    <div class="swat-card-body" style="padding:8px;">
        <?php foreach ($permit_map as $permit => $pforms): ?>
        <?php
            $p_open   = count(array_filter($pforms, fn($f)=>$f['status']==='submitted'));
            $p_closed = count(array_filter($pforms, fn($f)=>$f['status']==='closed'));
        ?>
        <div class="swat-permit-row" style="
            display:flex;align-items:center;gap:12px;
            padding:10px 14px;margin-bottom:6px;
            background:var(--swat-bg);border-radius:8px;
            border-left:4px solid var(--swat-teal);cursor:pointer;"
             onclick="setFilter('permit',<?= json_encode($permit === '—' ? '' : $permit) ?>)">
            <div style="font-weight:700;color:var(--swat-teal);min-width:100px;">
                <i class="fas fa-hashtag"></i> <?= htmlspecialchars($permit) ?>
            </div>
            <div style="flex:1;font-size:0.85rem;color:#555;">
                <?= count($pforms) ?> form<?= count($pforms)!==1?'s':'' ?>
            </div>
            <span class="swat-status-badge swat-status-submitted" style="font-size:0.75rem;">
                <?= $p_open ?> active
            </span>
            <span class="swat-status-badge swat-status-closed" style="font-size:0.75rem;">
                <?= $p_closed ?> closed
            </span>
            <i class="fas fa-chevron-right" style="color:#ccc;"></i>
        </div>
        <?php endforeach; ?>
        <?php if (empty($permit_map)): ?>
        <div style="padding:20px;text-align:center;color:#aaa;">No permits yet</div>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<!-- ── Forms table ────────────────────────────────────────────────────── -->
<div class="swat-card">
    <div class="swat-card-header">
        <i class="fas fa-list"></i>
        <?php if ($filter_permit): ?>
            Forms for Permit <strong><?= htmlspecialchars($filter_permit) ?></strong>
        <?php elseif ($filter_status): ?>
            <?= ucfirst($filter_status) ?> Forms
        <?php else: ?>
            All Forms / כל הטפסים
        <?php endif; ?>
        <span style="margin-left:auto;font-size:0.82rem;font-weight:400;color:#aaa;">
            <?= count($filtered) ?> form<?= count($filtered)!==1?'s':'' ?>
        </span>
    </div>
    <div class="swat-card-body" style="padding:0;">
    <?php if (empty($filtered)): ?>
        <div style="padding:32px;text-align:center;color:#888;">
            <i class="fas fa-file-alt" style="font-size:2rem;opacity:0.3;"></i>
            <p>No forms found. <a href="swatform.php?action=new">Create a new SWAT form</a>.</p>
        </div>
    <?php else: ?>
    <div style="overflow-x:auto;">
    <table class="swat-forms-table">
        <thead>
            <tr>
                <th>#</th>
                <th>Date</th>
                <th>Permit #</th>
                <th>Location</th>
                <th>CP</th>
                <th>Creator</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($filtered as $f): ?>
        <tr>
            <td data-label="#"><strong>#<?= (int)$f['id'] ?></strong></td>
            <td data-label="Date"><?= htmlspecialchars($f['form_date'] ?? '') ?></td>
            <td data-label="Permit">
                <?php if ($f['work_permit_ref']): ?>
                <a href="?tab=<?= $tab ?>&permit=<?= urlencode($f['work_permit_ref']) ?>"
                   style="font-weight:600;color:var(--swat-teal);">
                    <?= htmlspecialchars($f['work_permit_ref']) ?>
                </a>
                <?php else: ?>—<?php endif; ?>
            </td>
            <td data-label="Location"><?= htmlspecialchars($f['site_location'] ?? '—') ?></td>
            <td data-label="CP"><?= htmlspecialchars($f['cp_name'] ?? '—') ?></td>
            <td data-label="Creator"><?= htmlspecialchars($f['creator_name'] ?? '—') ?></td>
            <td data-label="Status">
                <!-- Status dropdown -->
                <form method="POST" action="swatdashboard.php" style="display:inline;">
                    <?php echo Html::hidden('_glpi_csrf_token', ['value' => Session::getNewCSRFToken()]); ?>
                    <input type="hidden" name="form_id" value="<?= $f['id'] ?>">
                    <input type="hidden" name="swat_action" value="set_status">
                    <input type="hidden" name="tab" value="<?= htmlspecialchars($tab) ?>">
                    <select name="status" onchange="this.form.submit()"
                            class="swat-status-select swat-status-<?= $f['status'] ?>"
                            style="border:none;border-radius:4px;padding:3px 6px;font-size:0.78rem;font-weight:600;cursor:pointer;">
                        <option value="submitted" <?= $f['status']==='submitted'?'selected':'' ?>>🟡 Active</option>
                        <option value="closed"    <?= $f['status']==='closed'   ?'selected':'' ?>>🟢 Closed</option>
                        <option value="archived"  <?= $f['status']==='archived' ?'selected':'' ?>>📦 Archived</option>
                    </select>
                </form>
            </td>
            <td style="white-space:nowrap;">
                <a href="swatform.php?action=view&id=<?= $f['id'] ?>"
                   class="swat-btn swat-btn-secondary" style="padding:4px 8px;font-size:0.78rem;"
                   title="View"><i class="fas fa-eye"></i></a>
                <a href="swatform.php?action=edit&id=<?= $f['id'] ?>"
                   class="swat-btn swat-btn-primary" style="padding:4px 8px;font-size:0.78rem;"
                   title="Edit"><i class="fas fa-edit"></i></a>
                <a href="swatpdf.php?id=<?= $f['id'] ?>&lang=en"
                   class="swat-btn swat-btn-pdf" style="padding:4px 8px;font-size:0.78rem;"
                   title="Download RTF"><i class="fas fa-file-word"></i></a>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <?php endif; ?>
    </div>
</div>

<div class="swat-revision" style="margin-top:16px;">SWAT Plugin v1.0.0 | GLPI 10.0.6 | GE Vernova</div>
</div>

<style>
.swat-tab {
    padding: 8px 20px;
    font-size: 0.88rem;
    font-weight: 600;
    color: #666;
    text-decoration: none;
    border-bottom: 3px solid transparent;
    transition: all 0.2s;
}
.swat-tab:hover { color: var(--swat-teal); }
.swat-tab.active {
    color: var(--swat-teal);
    border-bottom-color: var(--swat-teal);
}
.swat-stat-box { transition: all 0.2s; }
.swat-stat-box:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,107,107,0.15); }
.swat-stat-box.active { border-color: var(--swat-teal) !important; background: var(--swat-teal); }
.swat-stat-box.active .stat-num,
.swat-stat-box.active .stat-label { color: #fff !important; }
.swat-status-select { background: transparent; }
.swat-status-select.swat-status-submitted { background:#fef3c7;color:#92400e; }
.swat-status-select.swat-status-closed    { background:#d1fae5;color:#065f46; }
.swat-status-select.swat-status-archived  { background:#e5e7eb;color:#374151; }
</style>

<script>
function setFilter(key, val) {
    const url = new URL(window.location);
    if (val) url.searchParams.set(key, val);
    else url.searchParams.delete(key);
    window.location = url.toString();
}
</script>

<?php Html::footer(); ?>
