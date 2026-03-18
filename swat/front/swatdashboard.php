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

// Get user's primary GLPI group name (for team display in header)
$team_name = '';
$gr = $DB->query("SELECT g.name FROM glpi_groups_users gu
    INNER JOIN glpi_groups g ON g.id = gu.groups_id
    WHERE gu.users_id = {$current_user_id} LIMIT 1");
if ($gr && $row = $DB->fetchAssoc($gr)) { $team_name = $row['name']; }

// ── Handle status change / archive ────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['swat_action'])) {
    global $DB;
    $fid = (int)($_POST['form_id'] ?? 0);
    if (Session::haveRight('plugin_swat_form', UPDATE)) {
        switch ($_POST['swat_action']) {
            case 'set_status':
                if ($fid) {
                    $new_status = in_array($_POST['status'] ?? '', ['submitted','archived'])
                        ? $_POST['status'] : 'submitted';
                    $DB->update('glpi_plugin_swat_forms', ['status' => $new_status], ['id' => $fid]);
                }
                break;
            case 'archive_permit':
                $permit_num = $_POST['permit_number'] ?? '';
                if ($permit_num) {
                    $DB->update(
                        'glpi_plugin_swat_forms',
                        ['status' => 'archived'],
                        ['work_permit_ref' => $permit_num, 'is_deleted' => 0]
                    );
                }
                break;
        }
    }
    $tab    = $_POST['tab']    ?? 'mine';
    $subtab = $_POST['subtab'] ?? 'active';
    Html::redirect('swatdashboard.php?tab=' . $tab . '&subtab=' . $subtab);
}

// ── Tab: my / team ────────────────────────────────────────────────────────────
$tab    = $_GET['tab']    ?? 'mine';   // 'mine' or 'team'
$subtab = $_GET['subtab'] ?? 'active'; // 'active' or 'archive'

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

// Sub-tab filtering (active / archive) - only when no explicit status filter
function apply_subtab_filter(array $forms, string $subtab): array {
    if ($subtab === 'archive') {
        return array_values(array_filter($forms, fn($f) => in_array($f['status'], ['archived', 'closed'])));
    }
    // active = submitted
    return array_values(array_filter($forms, fn($f) => $f['status'] === 'submitted'));
}

// Counts for subtab badges
$show_active_forms  = apply_subtab_filter($show_forms, 'active');
$show_archive_forms = apply_subtab_filter($show_forms, 'archive');
$active_count  = count($show_active_forms);
$archive_count = count($show_archive_forms);

// Apply subtab filter to shown forms (unless status filter is set manually)
if (!$filter_status) {
    $subtab_filtered = apply_subtab_filter($show_forms, $subtab);
} else {
    $subtab_filtered = $show_forms;
}

$filtered = array_values(apply_filters($subtab_filtered, $filter_status, $filter_permit));

// ── Stats ─────────────────────────────────────────────────────────────────────
$my_active  = count(array_filter($my_forms, fn($f) => $f['status'] === 'submitted'));
$my_archive = count(array_filter($my_forms, fn($f) => in_array($f['status'], ['archived', 'closed'])));
$tm_active  = count(array_filter($team_forms, fn($f) => $f['status'] === 'submitted'));

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
            Start Work Assessment Tool
            <?php if ($team_name): ?>
            &nbsp;|&nbsp; <span style="color:#C8E000;"><?= htmlspecialchars($team_name) ?></span>
            <?php endif; ?>
            &nbsp;|&nbsp; <?= htmlspecialchars($current_user_name) ?>
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
        <div class="stat-num" style="color:#f59e0b;"><?= $my_active ?></div>
        <div class="stat-label">Active / פעילים</div>
    </div>
    <div class="swat-stat-box <?= $filter_status==='archived'?'active':'' ?>"
         onclick="setFilter('status','archived')" style="cursor:pointer;border-color:#6b7280;">
        <div class="stat-num" style="color:#6b7280;"><?= $my_archive ?></div>
        <div class="stat-label">Archive / ארכיון</div>
    </div>
    <div class="swat-stat-box" style="border-color:var(--swat-teal);">
        <div class="stat-num"><?= count($permit_map) ?></div>
        <div class="stat-label">Active Permits / פרמיטים</div>
    </div>
</div>

<!-- ── Tabs: Mine / Team ─────────────────────────────────────────────── -->
<div class="swat-tabs" style="display:flex;gap:0;margin-bottom:0;border-bottom:2px solid var(--swat-teal);">
    <a href="?tab=mine&subtab=<?= $subtab ?><?= $filter_permit?"&permit=".urlencode($filter_permit):'' ?>"
       class="swat-tab <?= $tab==='mine'?'active':'' ?>">
        <i class="fas fa-user"></i> My Forms (<?= count($my_forms) ?>)
    </a>
    <a href="?tab=team&subtab=<?= $subtab ?><?= $filter_permit?"&permit=".urlencode($filter_permit):'' ?>"
       class="swat-tab <?= $tab==='team'?'active':'' ?>">
        <i class="fas fa-users"></i> Team Forms (<?= count($team_forms) ?>)
    </a>
</div>

<!-- ── Sub-tabs: Active / Archive ────────────────────────────────────── -->
<?php if (!$filter_status): ?>
<div class="swat-subtabs" style="display:flex;gap:0;margin-bottom:16px;border-bottom:2px solid #e5e7eb;">
    <a href="?tab=<?= $tab ?>&subtab=active<?= $filter_permit?"&permit=".urlencode($filter_permit):'' ?>"
       class="swat-subtab <?= $subtab==='active'?'active':'' ?>">
        <i class="fas fa-bolt"></i> Active / פעילים (<?= $active_count ?>)
    </a>
    <a href="?tab=<?= $tab ?>&subtab=archive<?= $filter_permit?"&permit=".urlencode($filter_permit):'' ?>"
       class="swat-subtab <?= $subtab==='archive'?'active':'' ?>">
        <i class="fas fa-archive"></i> Archive / ארכיון (<?= $archive_count ?>)
    </a>
</div>
<?php else: ?>
<div style="margin-bottom:16px;"></div>
<?php endif; ?>

<!-- ── Active filters strip ──────────────────────────────────────────── -->
<?php if ($filter_status || $filter_permit): ?>
<div style="margin-bottom:10px;display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
    <span style="font-size:0.82rem;color:#666;">Filters:</span>
    <?php if ($filter_status): ?>
    <span class="swat-status-badge swat-status-<?= $filter_status ?>">
        <?= $filter_status ?>
        <a href="?tab=<?= $tab ?>&subtab=<?= $subtab ?>" style="color:inherit;margin-left:4px;">✕</a>
    </span>
    <?php endif; ?>
    <?php if ($filter_permit): ?>
    <span class="swat-status-badge swat-status-submitted">
        Permit: <?= htmlspecialchars($filter_permit) ?>
        <a href="?tab=<?= $tab ?>&subtab=<?= $subtab ?><?= $filter_status?"&status=$filter_status":'' ?>" style="color:inherit;margin-left:4px;">✕</a>
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
        <div style="padding:8px 12px;">
            <input type="text" id="swat-permit-search"
                   placeholder="🔍 Search permit / חיפוש פרמיט..."
                   class="swat-input" style="max-width:320px;"
                   oninput="filterPermitRows(this.value)">
        </div>
        <?php foreach ($permit_map as $permit => $pforms): ?>
        <?php
            $p_active  = count(array_filter($pforms, fn($f)=>$f['status']==='submitted'));
            $p_archive = count(array_filter($pforms, fn($f)=>in_array($f['status'],['archived','closed'])));
        ?>
        <div class="swat-permit-row" data-permit="<?= htmlspecialchars($permit) ?>" style="
            display:flex;align-items:center;gap:12px;
            padding:10px 14px;margin-bottom:6px;
            background:var(--swat-bg);border-radius:8px;
            border-left:4px solid var(--swat-teal);">
            <div style="font-weight:700;color:var(--swat-teal);min-width:100px;cursor:pointer;"
                 onclick="setFilter('permit',<?= json_encode($permit === '—' ? '' : $permit) ?>)">
                <i class="fas fa-hashtag"></i> <?= htmlspecialchars($permit) ?>
            </div>
            <div style="flex:1;font-size:0.85rem;color:#555;cursor:pointer;"
                 onclick="setFilter('permit',<?= json_encode($permit === '—' ? '' : $permit) ?>)">
                <?= count($pforms) ?> form<?= count($pforms)!==1?'s':'' ?>
            </div>
            <span class="swat-status-badge swat-status-submitted" style="font-size:0.75rem;">
                <?= $p_active ?> active
            </span>
            <span class="swat-status-badge swat-status-archived" style="font-size:0.75rem;">
                <?= $p_archive ?> archived
            </span>
            <?php if ($permit !== '—'): ?>
            <?php
                // Get latest form ID for this permit to allow copying
                $latest_form_id = 0;
                foreach ($pforms as $pf) {
                    if ((int)$pf['id'] > $latest_form_id) $latest_form_id = (int)$pf['id'];
                }
                $new_form_url = 'swatform.php?action=new&permit=' . urlencode($permit)
                    . ($latest_form_id ? '&copy_from=' . $latest_form_id : '');
            ?>
            <a href="<?= $new_form_url ?>"
               class="swat-btn swat-btn-secondary" style="padding:3px 8px;font-size:0.75rem;"
               title="New form for this permit (pre-filled from last form)">
                <i class="fas fa-plus"></i>
            </a>
            <?php if ($p_active > 0): ?>
            <form method="POST" action="swatdashboard.php" style="display:inline;"
                  onsubmit="return confirm('Archive all <?= $p_active ?> active form(s) under permit <?= htmlspecialchars($permit) ?>?\nכל הטפסים הפעילים יועברו לארכיון.');">
                <?php echo Html::hidden('_glpi_csrf_token', ['value' => Session::getNewCSRFToken()]); ?>
                <input type="hidden" name="swat_action"    value="archive_permit">
                <input type="hidden" name="permit_number"  value="<?= htmlspecialchars($permit) ?>">
                <input type="hidden" name="tab"            value="<?= htmlspecialchars($tab) ?>">
                <input type="hidden" name="subtab"         value="<?= htmlspecialchars($subtab) ?>">
                <button type="submit" class="swat-btn swat-btn-secondary"
                        style="padding:3px 8px;font-size:0.75rem;background:#f59e0b;border:none;border-radius:4px;color:#fff;cursor:pointer;"
                        title="Archive all forms under this permit / ארכב את כל הטפסים">
                    <i class="fas fa-archive"></i>
                </button>
            </form>
            <?php endif; ?>
            <?php endif; ?>
            <i class="fas fa-chevron-right" style="color:#ccc;cursor:pointer;"
               onclick="setFilter('permit',<?= json_encode($permit === '—' ? '' : $permit) ?>)"></i>
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
        <?php elseif ($subtab === 'archive'): ?>
            Archive / ארכיון
        <?php else: ?>
            Active Forms / טפסים פעילים
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
                <a href="?tab=<?= $tab ?>&subtab=<?= $subtab ?>&permit=<?= urlencode($f['work_permit_ref']) ?>"
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
                    <input type="hidden" name="subtab" value="<?= htmlspecialchars($subtab) ?>">
                    <select name="status" onchange="this.form.submit()"
                            class="swat-status-select swat-status-<?= $f['status'] ?>"
                            style="border:none;border-radius:4px;padding:3px 6px;font-size:0.78rem;font-weight:600;cursor:pointer;">
                        <option value="submitted" <?= $f['status']==='submitted'?'selected':'' ?>>🟡 Active / פעיל</option>
                        <option value="archived"  <?= in_array($f['status'],['archived','closed'])?'selected':'' ?>>📦 Archive / ארכיון</option>
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
                <a href="swatpdf.php?id=<?= $f['id'] ?>&lang=both"
                   class="swat-btn swat-btn-pdf" style="padding:4px 8px;font-size:0.78rem;"
                   title="PDF (EN + עב)"><i class="fas fa-file-pdf"></i></a>
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
.swat-subtab {
    padding: 6px 16px;
    font-size: 0.82rem;
    font-weight: 500;
    color: #888;
    text-decoration: none;
    border-bottom: 2px solid transparent;
    transition: all 0.2s;
    display: inline-flex;
    align-items: center;
    gap: 5px;
}
.swat-subtab:hover { color: var(--swat-teal); }
.swat-subtab.active {
    color: var(--swat-teal);
    border-bottom-color: var(--swat-teal);
    font-weight: 600;
}
.swat-stat-box { transition: all 0.2s; }
.swat-stat-box:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,107,107,0.15); }
.swat-stat-box.active { border-color: var(--swat-teal) !important; background: var(--swat-teal); }
.swat-stat-box.active .stat-num,
.swat-stat-box.active .stat-label { color: #fff !important; }
.swat-status-select { background: transparent; }
.swat-status-select.swat-status-submitted { background:#fef3c7;color:#92400e; }
.swat-status-select.swat-status-archived  { background:#e5e7eb;color:#374151; }
.swat-status-select.swat-status-closed    { background:#e5e7eb;color:#374151; }
.swat-status-badge.swat-status-archived   { background:#e5e7eb;color:#374151; }
</style>

<script>
function setFilter(key, val) {
    const url = new URL(window.location);
    if (val) url.searchParams.set(key, val);
    else url.searchParams.delete(key);
    window.location = url.toString();
}

function filterPermitRows(q) {
    q = q.toLowerCase().trim();
    document.querySelectorAll('.swat-permit-row[data-permit]').forEach(function(row) {
        var perm = row.dataset.permit.toLowerCase();
        row.style.display = (q === '' || perm.includes(q)) ? '' : 'none';
    });
}
</script>

<?php Html::footer(); ?>
