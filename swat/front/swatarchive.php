<?php
/**
 * SWAT Plugin – Archive: permits with linked forms + delete
 */
include('../../../inc/includes.php');

if (!Session::haveRight('plugin_swat_form', READ) && !Session::haveRight('config', UPDATE)) {
    Html::displayRightError(); exit;
}

$current_user_id = (int) Session::getLoginUserID();
$is_admin        = Session::haveRight('plugin_swat_admin', READ);

// ── Handle delete from archive ─────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['swat_action'] ?? '') === 'delete') {
    global $DB;
    $fid = (int)($_POST['form_id'] ?? 0);
    if ($fid) {
        // Only allow delete if form is archived
        $rows = iterator_to_array($DB->request([
            'FROM' => 'glpi_plugin_swat_forms',
            'WHERE' => ['id' => $fid, 'status' => 'archived'],
        ]));
        if (!empty($rows)) {
            $DB->update('glpi_plugin_swat_forms', ['is_deleted' => 1], ['id' => $fid]);
        }
    }
    Html::redirect('swatarchive.php');
}

$filter_permit = trim($_GET['permit'] ?? '');
$permits = PluginSwatPermit::getPermitsForUser($current_user_id, $is_admin);

Html::header('SWAT Archive', '', 'management', 'PluginSwatDashboard');
$root = Plugin::getWebDir('swat');
?>

<div class="swat-wrap">

    <div class="swat-header">
        <h1>
            <span class="swat-logo-text">SWAT</span>
            Archive &nbsp;|&nbsp; <span style="font-weight:400;font-size:1.1rem;">ארכיון</span>
        </h1>
        <div class="swat-header-actions">
            <a href="swatform.php?action=new" class="swat-btn swat-btn-lime">
                <i class="fas fa-plus"></i> New Form
            </a>
            <a href="swatdashboard.php" class="swat-btn swat-btn-secondary">
                <i class="fas fa-arrow-left"></i> Dashboard
            </a>
        </div>
    </div>

    <div style="background:#fef3c7;border:1px solid #fcd34d;border-radius:8px;padding:10px 16px;margin-bottom:16px;font-size:0.85rem;color:#92400e;">
        <i class="fas fa-info-circle"></i>
        <strong>Archive:</strong> Only <strong>Archived</strong> forms can be permanently deleted here.
        To archive a form, change its status to "Archived" on the Dashboard.
    </div>

    <!-- Filter bar -->
    <div style="margin:16px 0;display:flex;gap:10px;align-items:center;">
        <form method="GET" style="display:flex;gap:8px;flex:1;">
            <input type="text" name="permit" class="swat-input" style="max-width:260px;"
                   value="<?= htmlspecialchars($filter_permit) ?>" placeholder="Filter by Permit # / סנן לפי פרמיט">
            <button type="submit" class="swat-btn swat-btn-primary">
                <i class="fas fa-search"></i> Filter
            </button>
            <?php if ($filter_permit): ?>
            <a href="swatarchive.php" class="swat-btn swat-btn-secondary">Clear</a>
            <?php endif; ?>
        </form>
        <div style="font-size:0.82rem;color:#888;"><?= count($permits) ?> permit(s)</div>
    </div>

    <?php if (empty($permits)): ?>
        <div class="swat-card">
            <div class="swat-card-body" style="text-align:center;padding:40px;color:#888;">
                <i class="fas fa-archive" style="font-size:2.5rem;opacity:0.25;"></i>
                <p>No permits yet. Forms with a Work Permit # will appear here.</p>
            </div>
        </div>
    <?php else: ?>

    <?php foreach ($permits as $permit):
        if ($filter_permit && stripos($permit['permit_number'], $filter_permit) === false) continue;
        $pForms = PluginSwatPermit::getFormsForPermit((int)$permit['id']);
        $auto_open = (bool)$filter_permit;
    ?>
    <div class="swat-permit-card">
        <div class="swat-permit-header">
            <div>
                <span class="permit-num"><i class="fas fa-tag"></i> <?= htmlspecialchars($permit['permit_number']) ?></span>
                <div class="permit-meta" style="margin-top:2px;">
                    Created: <?= htmlspecialchars($permit['date_creation'] ?? '') ?>
                </div>
            </div>
            <div style="display:flex;align-items:center;gap:12px;">
                <span class="permit-count"><?= (int)$permit['form_count'] ?> form(s)</span>
                <span class="toggle-icon"><?= $auto_open ? '▲' : '▼' ?></span>
            </div>
        </div>

        <div class="swat-permit-forms <?= $auto_open ? 'open' : '' ?>">
            <?php if (empty($pForms)): ?>
                <p style="color:#aaa;font-size:0.85rem;margin:0;">No forms linked to this permit.</p>
            <?php else: ?>
            <div style="overflow-x:auto;">
            <table class="swat-forms-table">
                <thead>
                    <tr>
                        <th>Form #</th>
                        <th>Date</th>
                        <th>Location</th>
                        <th>CP</th>
                        <th>Status</th>
                        <th>Creator</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($pForms as $f): ?>
                <tr style="<?= $f['status']==='archived'?'background:#f9fafb;':''; ?>">
                    <td><strong>#<?= (int)$f['id'] ?></strong></td>
                    <td><?= htmlspecialchars($f['form_date'] ?? '') ?></td>
                    <td><?= htmlspecialchars($f['site_location'] ?? '—') ?></td>
                    <td><?= htmlspecialchars($f['cp_name'] ?? '—') ?></td>
                    <td><span class="swat-status-badge swat-status-<?= $f['status'] ?>"><?= $f['status'] ?></span></td>
                    <td><?= htmlspecialchars($f['creator_name'] ?? '—') ?></td>
                    <td style="white-space:nowrap;">
                        <a href="swatform.php?action=view&id=<?= $f['id'] ?>"
                           class="swat-btn swat-btn-secondary" style="padding:4px 8px;font-size:0.78rem;">
                            <i class="fas fa-eye"></i>
                        </a>
                        <a href="swatpdf.php?id=<?= $f['id'] ?>&lang=en"
                           class="swat-btn swat-btn-pdf" style="padding:4px 8px;font-size:0.78rem;">
                            <i class="fas fa-file-word"></i>
                        </a>
                        <?php if ($f['status'] === 'archived'): ?>
                        <form method="POST" action="swatarchive.php" style="display:inline;"
                              onsubmit="return confirm('Permanently delete Form #<?= $f['id'] ?>? This cannot be undone.');">
                            <?php echo Html::hidden('_glpi_csrf_token', ['value' => Session::getNewCSRFToken()]); ?>
                            <input type="hidden" name="form_id" value="<?= $f['id'] ?>">
                            <input type="hidden" name="swat_action" value="delete">
                            <button type="submit" class="swat-btn"
                                    style="padding:4px 8px;font-size:0.78rem;background:#fee2e2;color:#dc2626;border:1px solid #fca5a5;"
                                    title="Delete permanently">
                                <i class="fas fa-trash"></i>
                            </button>
                        </form>
                        <?php else: ?>
                        <span style="padding:4px 8px;font-size:0.72rem;color:#aaa;"
                              title="Archive first to enable delete">
                            <i class="fas fa-lock"></i>
                        </span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            </div>
            <?php endif; ?>
            <div style="margin-top:10px;">
                <a href="swatform.php?action=new&permit=<?= urlencode($permit['permit_number']) ?>"
                   class="swat-btn swat-btn-lime" style="font-size:0.83rem;padding:6px 14px;">
                    <i class="fas fa-plus"></i> Add Form to This Permit
                </a>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>

    <div class="swat-revision">SWAT Plugin v1.0.0 | Archive</div>
</div>

<?php Html::footer(); ?>


if (!Session::haveRight('plugin_swat_form', READ) && !Session::haveRight('config', UPDATE)) {
    Html::displayRightError();
    exit;
}

$current_user_id = (int) Session::getLoginUserID();
$is_admin        = Session::haveRight('plugin_swat_admin', READ);
$filter_permit   = trim($_GET['permit'] ?? '');

$permits = PluginSwatPermit::getPermitsForUser($current_user_id, $is_admin);

Html::header('SWAT Archive', '', 'management', 'PluginSwatDashboard');
$root = Plugin::getWebDir('swat');
?>

<div class="swat-wrap">

    <div class="swat-header">
        <h1>
            <span class="swat-logo-text">SWAT</span>
            Permit Archive &nbsp;|&nbsp; <span style="font-weight:400;font-size:1.1rem;">ארכיון פרמיטים</span>
        </h1>
        <div class="swat-header-actions">
            <a href="swatform.php?action=new" class="swat-btn swat-btn-lime">
                <i class="fas fa-plus"></i> New Form
            </a>
            <a href="swatdashboard.php" class="swat-btn swat-btn-secondary">
                <i class="fas fa-arrow-left"></i> Dashboard
            </a>
        </div>
    </div>

    <!-- Filter bar -->
    <div style="margin:16px 0;display:flex;gap:10px;align-items:center;">
        <form method="GET" style="display:flex;gap:8px;flex:1;">
            <input type="text" name="permit" class="swat-input" style="max-width:260px;"
                   value="<?= htmlspecialchars($filter_permit) ?>" placeholder="Filter by Permit # / סנן לפי פרמיט">
            <button type="submit" class="swat-btn swat-btn-primary">
                <i class="fas fa-search"></i> Filter
            </button>
            <?php if ($filter_permit): ?>
            <a href="swatarchive.php" class="swat-btn swat-btn-secondary">Clear</a>
            <?php endif; ?>
        </form>
        <div style="font-size:0.82rem;color:#888;"><?= count($permits) ?> permit(s)</div>
    </div>

    <?php if (empty($permits)): ?>
        <div class="swat-card">
            <div class="swat-card-body" style="text-align:center;padding:40px;color:#888;">
                <i class="fas fa-archive" style="font-size:2.5rem;opacity:0.25;"></i>
                <p>No permits yet. Forms with a Work Permit # will appear here.</p>
                <p style="direction:rtl;font-size:0.85rem;">אין פרמיטים. טפסים עם מספר אישור עבודה יוצגו כאן.</p>
            </div>
        </div>
    <?php else: ?>

    <?php foreach ($permits as $permit):
        if ($filter_permit && stripos($permit['permit_number'], $filter_permit) === false) continue;
        $pForms = PluginSwatPermit::getFormsForPermit((int)$permit['id']);
        $auto_open = ($filter_permit && stripos($permit['permit_number'], $filter_permit) !== false);
    ?>
    <div class="swat-permit-card">
        <div class="swat-permit-header">
            <div>
                <span class="permit-num"><i class="fas fa-tag"></i> <?= htmlspecialchars($permit['permit_number']) ?></span>
                <?php if ($permit['description']): ?>
                    <span class="permit-meta"> — <?= htmlspecialchars($permit['description']) ?></span>
                <?php endif; ?>
                <div class="permit-meta" style="margin-top:2px;">
                    Created: <?= htmlspecialchars($permit['date_creation'] ?? '') ?>
                </div>
            </div>
            <div style="display:flex;align-items:center;gap:12px;">
                <span class="permit-count"><?= (int)$permit['form_count'] ?> form(s)</span>
                <span class="toggle-icon"><?= $auto_open ? '▲' : '▼' ?></span>
            </div>
        </div>

        <div class="swat-permit-forms <?= $auto_open ? 'open' : '' ?>">
            <?php if (empty($pForms)): ?>
                <p style="color:#aaa;font-size:0.85rem;margin:0;">No forms linked to this permit yet.</p>
            <?php else: ?>
            <div style="overflow-x:auto;">
            <table class="swat-forms-table">
                <thead>
                    <tr>
                        <th>Form #</th>
                        <th>Date</th>
                        <th>Location</th>
                        <th>CP</th>
                        <th>Status</th>
                        <th>Creator</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($pForms as $f): ?>
                <tr>
                    <td><strong>#<?= (int)$f['id'] ?></strong></td>
                    <td><?= htmlspecialchars($f['form_date'] ?? '') ?></td>
                    <td><?= htmlspecialchars($f['site_location']) ?></td>
                    <td><?= htmlspecialchars($f['cp_name'] ?? '—') ?></td>
                    <td><span class="swat-status-badge swat-status-<?= $f['status'] ?>"><?= $f['status'] ?></span></td>
                    <td><?= htmlspecialchars($f['creator_name'] ?? '—') ?></td>
                    <td style="white-space:nowrap;">
                        <a href="swatform.php?action=view&id=<?= $f['id'] ?>" class="swat-btn swat-btn-secondary" style="padding:4px 9px;font-size:0.78rem;"><i class="fas fa-eye"></i></a>
                        <a href="swatform.php?action=edit&id=<?= $f['id'] ?>" class="swat-btn swat-btn-primary"   style="padding:4px 9px;font-size:0.78rem;"><i class="fas fa-edit"></i></a>
                        <a href="swatpdf.php?id=<?= $f['id'] ?>&lang=en" target="_blank" class="swat-btn swat-btn-pdf"    style="padding:4px 9px;font-size:0.78rem;"><i class="fas fa-file-pdf"></i></a>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            </div>
            <?php endif; ?>

            <div style="margin-top:10px;">
                <a href="swatform.php?action=new&permit=<?= urlencode($permit['permit_number']) ?>" class="swat-btn swat-btn-lime" style="font-size:0.83rem;padding:6px 14px;">
                    <i class="fas fa-plus"></i> Add Form to This Permit / הוסף טופס לפרמיט זה
                </a>
            </div>
        </div>
    </div>
    <?php endforeach; ?>

    <?php endif; ?>

    <div class="swat-revision">SWAT Plugin v1.0.0 | Archive</div>
</div>

<?php Html::footer(); ?>
