<?php
/**
 * SWAT Plugin – Form page (create / edit / view)
 */
include('../../../inc/includes.php');

if (!Session::haveRight('plugin_swat_form', READ) && !Session::haveRight('config', UPDATE)) {
    Html::displayRightError();
    exit;
}

$action  = $_GET['action'] ?? 'new';
$form_id = (int)($_GET['id'] ?? 0);
$form    = null;

if ($form_id > 0) {
    $form = PluginSwatForm::getFormData($form_id);
    if (!$form) {
        Html::displayErrorAndDie('Form not found');
    }
}
$save_error = '';

// Handle POST save - must be before ANY output
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    while (ob_get_level()) ob_end_clean();

    try {
        $result = PluginSwatForm::saveForm($_POST);

        if ($result['success']) {
            $fid  = (int) $result['form_id'];
            $base = rtrim(dirname($_SERVER['PHP_SELF']), '/\\');
            header('Location: ' . $base . '/swatform.php?action=view&id=' . $fid . '&saved=1');
            exit;
        } else {
            $save_error = $result['error'] ?? 'Unknown error';
        }
    } catch (Throwable $e) {
        $save_error = $e->getMessage() . ' [' . basename($e->getFile()) . ':' . $e->getLine() . ']';
    }
}

$current_user_id   = (int) Session::getLoginUserID();
$current_user_name = getUserName($current_user_id);

Html::header('SWAT Form', '', 'management', 'PluginSwatDashboard');

$root = Plugin::getWebDir('swat');
$pics = $root . '/pics';

// Show success/error banners
if (isset($_GET['saved'])) {
    echo '<div class="alert alert-success m-3"><i class="fas fa-check-circle me-2"></i>
          Form saved successfully! / הטופס נשמר בהצלחה!</div>';
}
if (!empty($save_error)) {
    echo '<div class="alert alert-danger m-3" style="font-family:monospace;white-space:pre-wrap;">'
       . '<strong><i class="fas fa-exclamation-circle me-2"></i>Save Error:</strong><br>'
       . htmlspecialchars($save_error)
       . '</div>';
}

// Icon mapping for Life Saving Rules (matched from PPTX)
$lsr_rules = [
    ['key' => 'mechanical_lifting', 'icon' => 'image2.png', 'en' => 'Mechanical Lifting',  'he' => 'הרמה מכנית'],
    ['key' => 'work_at_height',     'icon' => 'image3.png', 'en' => 'Work at Height',       'he' => 'עבודה בגובה'],
    ['key' => 'driving_safety',     'icon' => 'image4.png', 'en' => 'Driving Safety',        'he' => 'בטיחות בנהיגה'],
    ['key' => 'line_of_fire',       'icon' => 'image5.png', 'en' => 'Line of Fire',          'he' => 'קו האש'],
    ['key' => 'work_authorization', 'icon' => 'image6.png', 'en' => 'Work Authorization',   'he' => 'אישור עבודה'],
    ['key' => 'confined_space',     'icon' => 'image7.png', 'en' => 'Confined Space',        'he' => 'חלל מוקף'],
    ['key' => 'energy_isolation',   'icon' => 'image8.png', 'en' => 'Energy Isolation',      'he' => 'בידוד אנרגיה'],
    ['key' => 'live_electrical',    'icon' => 'image9.png', 'en' => 'Live Electrical',       'he' => 'חשמל חי'],
    ['key' => 'fire_explosion',     'icon' => 'image10.png','en' => 'Fire and Explosion',    'he' => 'אש ופיצוץ'],
];

$hazards = [
    ['key' => 'adjacent_work',    'en' => 'Adjacent Work',              'he' => 'עבודה סמוכה'],
    ['key' => 'ergonomics',       'en' => 'Body Positioning/Ergonomics','he' => 'ארגונומיה / תנוחת גוף'],
    ['key' => 'heavy_lift',       'en' => 'Heavy Manual Lift',          'he' => 'הרמה ידנית כבדה'],
    ['key' => 'burns',            'en' => 'Burns/Fire/Hot Work',        'he' => 'כוויות / אש / עבודות חמות'],
    ['key' => 'comm_failure',     'en' => 'Communication Failure',      'he' => 'כשל בתקשורת'],
    ['key' => 'dust_fume',        'en' => 'Dust/Fume/Grit',            'he' => 'אבק / עשן / גרגרים'],
    ['key' => 'electrical_shock', 'en' => 'Electrical Shock',           'he' => 'התחשמלות'],
    ['key' => 'env_weather',      'en' => 'Environmental/Weather',      'he' => 'סביבה / מזג אוויר'],
    ['key' => 'fall_height',      'en' => 'Fall from Height',           'he' => 'נפילה מגובה'],
    ['key' => 'dropped_objects',  'en' => 'Dropped Objects',            'he' => 'חפצים נופלים'],
    ['key' => 'chemicals',        'en' => 'Chemicals',                  'he' => 'כימיקלים'],
    ['key' => 'noise',            'en' => 'Noise',                      'he' => 'רעש'],
    ['key' => 'pinch_points',     'en' => 'Pinch Points/Sharp Objects', 'he' => 'נקודות צביטה / חפצים חדים'],
    ['key' => 'restricted_ws',    'en' => 'Restricted Workplace',       'he' => 'מקום עבודה מוגבל'],
    ['key' => 'slips_trips',      'en' => 'Slips, Trips & Falls',       'he' => 'החלקה, מעידה ונפילות'],
    ['key' => 'wildlife',         'en' => 'Wildlife',                   'he' => 'חיות בר'],
    ['key' => 'lighting',         'en' => 'Lighting',                   'he' => 'תאורה'],
    ['key' => 'housekeeping',     'en' => 'Housekeeping',               'he' => 'סדר וניקיון'],
    ['key' => 'rotating_equip',   'en' => 'Rotating Equipment',         'he' => 'ציוד מסתובב'],
    ['key' => 'tool_failure',     'en' => 'Tool Failure',               'he' => 'כשל בכלי עבודה'],
    ['key' => 'radiation',        'en' => 'Radiation',                  'he' => 'קרינה'],
];

$is_view   = ($action === 'view');
$form_data = $form ?? [];
$hc_rows   = $form_data['hazards_controls'] ?? [];
$parts     = $form_data['participants']     ?? [];

// CP data
$cp_id        = $form_data['users_id_cp']   ?? $current_user_id;
$cp_contact   = (int)($form_data['cp_is_contact'] ?? 0);
$cp_name      = ($cp_id == $current_user_id && !$cp_contact) ? $current_user_name : getUserName($cp_id);
$is_custom_cp = ($cp_id != $current_user_id || $cp_contact);
?>

<div class="swat-wrap">
    <!-- Header -->
    <div class="swat-header">
        <h1>
            <span class="swat-logo-text">SWAT</span>
            Start Work Assessment Tool &nbsp;|&nbsp; <span style="font-weight:400;font-size:1.1rem;">כלי הערכת התחלת עבודה</span>
        </h1>
        <div class="swat-header-actions">
            <?php if ($form_id && !$is_view): ?>
                <a href="?action=view&id=<?= $form_id ?>" class="swat-btn swat-btn-secondary">
                    <i class="fas fa-eye"></i> View
                </a>
            <?php endif; ?>
            <?php if ($form_id): ?>
                <a href="swatpdf.php?id=<?= $form_id ?>&lang=en" class="swat-btn swat-btn-pdf"><i class="fas fa-file-pdf"></i> PDF EN</a>
                <a href="swatpdf.php?id=<?= $form_id ?>&lang=he" class="swat-btn swat-btn-pdf" style="background:#006B6B;"><i class="fas fa-file-pdf"></i> PDF עב</a>
            <?php endif; ?>
            <a href="swatdashboard.php" class="swat-btn swat-btn-secondary">
                <i class="fas fa-arrow-left"></i> Dashboard
            </a>
        </div>
    </div>

    <form id="swat-main-form" method="POST" action="swatform.php">
    <?php echo Html::hidden('_glpi_csrf_token', ['value' => Session::getNewCSRFToken()]); ?>
    <input type="hidden" name="form_id" value="<?= $form_id ?>">
    <input type="hidden" id="swat-cp-userid"    name="users_id_cp"   value="<?= $cp_id ?>">
    <input type="hidden" id="swat-cp-iscontact" name="cp_is_contact" value="<?= $cp_contact ?>">

    <!-- ══════════════════════ PAGE 1 ══════════════════════ -->

    <!-- Section 1: Site Info -->
    <div class="swat-card">
        <div class="swat-card-header">
            <i class="fas fa-map-marker-alt"></i> Site Information
            <span class="he">פרטי אתר</span>
        </div>
        <div class="swat-card-body">
            <div class="swat-grid-2">
                <div class="swat-field">
                    <div class="swat-label">
                        <span class="en">SITE / Work Location</span>
                        <span class="he">אתר / מיקום עבודה</span>
                    </div>
                    <input type="text" name="site_location" class="swat-input"
                           value="<?= htmlspecialchars($form_data['site_location'] ?? 'Tzafit') ?>"
                           <?= $is_view ? 'readonly' : '' ?>>
                </div>

                <div class="swat-field">
                    <div class="swat-label">
                        <span class="en">Work Permit #</span>
                        <span class="he">מספר אישור עבודה</span>
                    </div>
                    <input type="text" name="work_permit_ref" class="swat-input" required
                           value="<?= htmlspecialchars($form_data['work_permit_ref'] ?? '') ?>"
                           <?= $is_view ? 'readonly' : '' ?>>
                </div>

                <div class="swat-field">
                    <div class="swat-label">
                        <span class="en">Date / תאריך</span>
                    </div>
                    <input type="date" id="swat-form-date" name="form_date" class="swat-input"
                           value="<?= htmlspecialchars($form_data['form_date'] ?? '') ?>"
                           <?= $is_view ? 'readonly' : '' ?>>
                </div>

                <div class="swat-field">
                    <div class="swat-label">
                        <span class="en">Time / שעה</span>
                    </div>
                    <input type="time" id="swat-form-time" name="form_time" class="swat-input"
                           value="<?= htmlspecialchars($form_data['form_time'] ?? '') ?>"
                           <?= $is_view ? 'readonly' : '' ?>>
                </div>

                <div class="swat-field">
                    <div class="swat-label">
                        <span class="en">Shift / משמרת</span>
                    </div>
                    <select name="shift" class="swat-select" <?= $is_view ? 'disabled' : '' ?>>
                        <option value="day"   <?= ($form_data['shift'] ?? 'day') === 'day'   ? 'selected' : '' ?>>☀ Day / יום</option>
                        <option value="night" <?= ($form_data['shift'] ?? '')    === 'night' ? 'selected' : '' ?>>🌙 Night / לילה</option>
                    </select>
                </div>

                <div class="swat-field">
                    <div class="swat-label">
                        <span class="en">Correct unit & equipment?</span>
                        <span class="he">נמצא ביחידה ובציוד הנכון?</span>
                    </div>
                    <div class="swat-yesno">
                        <input type="radio" name="correct_unit" id="cu_yes" value="1"
                               <?= ($form_data['correct_unit'] ?? 0) ? 'checked' : '' ?>>
                        <label for="cu_yes" class="yes">YES / כן</label>
                        <input type="radio" name="correct_unit" id="cu_no" value="0"
                               <?= !($form_data['correct_unit'] ?? 1) ? 'checked' : '' ?>>
                        <label for="cu_no" class="no">NO / לא</label>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Section 2: Task -->
    <div class="swat-card">
        <div class="swat-card-header">
            <i class="fas fa-tasks"></i> Task Assessment
            <span class="he">הערכת המשימה</span>
        </div>
        <div class="swat-card-body swat-grid-2">
            <div class="swat-field">
                <div class="swat-label">
                    <span class="en">WHAT is the task?</span>
                    <span class="he">מהי המשימה?</span>
                </div>
                <textarea name="task_what" class="swat-textarea"
                          placeholder="Brief description of the task…"
                          <?= $is_view ? 'readonly' : '' ?>><?= htmlspecialchars($form_data['task_what'] ?? '') ?></textarea>
            </div>
            <div class="swat-field">
                <div class="swat-label">
                    <span class="en">HOW will I do it?</span>
                    <span class="he">איך תבוצע?</span>
                </div>
                <textarea name="task_how" class="swat-textarea"
                          placeholder="How will the task be performed…"
                          <?= $is_view ? 'readonly' : '' ?>><?= htmlspecialchars($form_data['task_how'] ?? '') ?></textarea>
            </div>
            <div class="swat-field">
                <div class="swat-label">
                    <span class="en">WHAT are the hazards?</span>
                    <span class="he">מהם הסיכונים?</span>
                </div>
                <textarea name="task_description" class="swat-textarea"
                          placeholder="List the main hazards…"
                          <?= $is_view ? 'readonly' : '' ?>><?= htmlspecialchars($form_data['task_description'] ?? '') ?></textarea>
            </div>
            <div class="swat-field">
                <div class="swat-label">
                    <span class="en">HOW can I prevent injury?</span>
                    <span class="he">כיצד ניתן למנוע פציעות?</span>
                </div>
                <textarea name="task_detail" class="swat-textarea"
                          placeholder="Control measures / mitigations…"
                          <?= $is_view ? 'readonly' : '' ?>><?= htmlspecialchars($form_data['task_detail'] ?? '') ?></textarea>
            </div>
        </div>
    </div>

    <!-- Section 3: Life Saving Rules -->
    <div class="swat-card">
        <div class="swat-card-header">
            <i class="fas fa-shield-alt"></i> The Life Saving Rules
            <span class="he">חוקים מצילי חיים — סמן את הרלוונטיים</span>
        </div>
        <div class="swat-card-body">
            <p style="font-size:0.82rem;color:#666;margin:0 0 12px;">
                Check all rules relevant to this task / סמן את כל הכללים הרלוונטיים לעבודה זו
            </p>
            <div class="swat-lsr-grid">
                <?php foreach ($lsr_rules as $rule): ?>
                <?php $checked = (int)($form_data['lsr_' . $rule['key']] ?? 0); ?>
                <label class="swat-lsr-item <?= $checked ? 'checked' : '' ?>">
                    <input type="checkbox" name="lsr_<?= $rule['key'] ?>" value="1" <?= $checked ? 'checked' : '' ?> <?= $is_view ? 'disabled' : '' ?>>
                    <img src="<?= $pics ?>/<?= $rule['icon'] ?>" class="swat-lsr-icon" alt="<?= htmlspecialchars($rule['en']) ?>">
                    <div class="swat-lsr-name-en"><?= htmlspecialchars($rule['en']) ?></div>
                    <div class="swat-lsr-name-he"><?= htmlspecialchars($rule['he']) ?></div>
                </label>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Section 4: Other Hazards -->
    <div class="swat-card">
        <div class="swat-card-header">
            <i class="fas fa-exclamation-triangle"></i> Other Hazards
            <span class="he">סיכונים אחרים</span>
        </div>
        <div class="swat-card-body">
            <div class="swat-hazards-grid">
                <?php foreach ($hazards as $h): ?>
                <?php $checked = (int)($form_data['hazard_' . $h['key']] ?? 0); ?>
                <label class="swat-hazard-item">
                    <input type="checkbox" name="hazard_<?= $h['key'] ?>" value="1" <?= $checked ? 'checked' : '' ?> <?= $is_view ? 'disabled' : '' ?>>
                    <div class="swat-hazard-label">
                        <span class="en"><?= htmlspecialchars($h['en']) ?></span>
                        <span class="he"><?= htmlspecialchars($h['he']) ?></span>
                    </div>
                </label>
                <?php endforeach; ?>
            </div>
            <!-- Other (free text) -->
            <div style="margin-top:14px;">
                <div class="swat-label"><span class="en">Other (Specify) / אחר (ציין):</span></div>
                <div class="swat-grid-2">
                    <?php for ($i = 1; $i <= 4; $i++): ?>
                    <input type="text" name="hazard_other<?= $i ?>" class="swat-input"
                           value="<?= htmlspecialchars($form_data['hazard_other' . $i] ?? '') ?>"
                           placeholder="Other hazard <?= $i ?>…" <?= $is_view ? 'readonly' : '' ?>>
                    <?php endfor; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Section 5: CP + Participants -->
    <div class="swat-card">
        <div class="swat-card-header">
            <i class="fas fa-users"></i> Competent Person &amp; Participants
            <span class="he">אדם מוסמך ומשתתפים</span>
        </div>
        <div class="swat-card-body">

            <!-- CP Selector -->
            <div class="swat-field">
                <div class="swat-label">
                    <span class="en">Competent Person (CP) / Task Leader</span>
                    <span class="he">אדם מוסמך / מוביל משימה</span>
                </div>
                <div class="swat-cp-selector">
                    <div class="cp-current">
                        <i class="fas fa-user-tie"></i>
                        <span id="swat-cp-display"><?= htmlspecialchars($cp_name) ?></span>
                        <?php if ($cp_contact): ?>
                            <small style="color:#888;">(Contact)</small>
                        <?php endif; ?>
                    </div>
                    <?php if (!$is_view): ?>
                    <label style="font-size:0.82rem;display:flex;align-items:center;gap:5px;cursor:pointer;">
                        <input type="checkbox" id="swat-cp-toggle" <?= $is_custom_cp ? 'checked' : '' ?>>
                        Override CP / שנה
                    </label>
                    <div id="swat-cp-override-wrap" class="swat-cp-override" style="<?= $is_custom_cp ? '' : 'display:none' ?>">
                        <input type="text" id="swat-cp-input" class="swat-input"
                               placeholder="Search user or contact…"
                               value="<?= $is_custom_cp ? htmlspecialchars($cp_name) : '' ?>">
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Participants -->
            <div class="swat-field" style="margin-top:16px;">
                <div class="swat-label">
                    <span class="en">Participants (up to 10)</span>
                    <span class="he">משתתפים (עד 10)</span>
                </div>
                <div class="swat-participants-list">
                    <?php
                    $max_default = max(4, count($parts)); // show at least 4
                    for ($i = 0; $i < $max_default; $i++):
                        $p = $parts[$i] ?? null;
                        $pName = $p ? htmlspecialchars($p['display_name']) : '';
                        $pId   = $p ? (int)$p['items_id'] : 0;
                        $pType = $p ? htmlspecialchars($p['item_type'])   : 'user';
                    ?>
                    <div class="swat-participant-row" data-items-id="<?= $pId ?>" data-item-type="<?= $pType ?>">
                        <span class="swat-participant-num"><?= $i + 1 ?>.</span>
                        <div class="swat-participant-input">
                            <input type="text"
                                   name="participants[<?= $i ?>][display_name_text]"
                                   value="<?= $pName ?>"
                                   placeholder="Search or type name… / חפש או הקלד שם…"
                                   <?= $is_view ? 'readonly' : '' ?>>
                            <input type="hidden" class="hidden-items-id"     name="participants[<?= $i ?>][items_id]"     value="<?= $pId ?>">
                            <input type="hidden" class="hidden-item-type"    name="participants[<?= $i ?>][type]"         value="<?= $pType ?>">
                            <input type="hidden" class="hidden-display-name" name="participants[<?= $i ?>][display_name]" value="<?= $pName ?>">
                        </div>
                        <?php if (!$is_view): ?>
                        <button type="button" onclick="
                            const row = this.closest('.swat-participant-row');
                            row.querySelectorAll('input').forEach(i => i.value = '');
                        " style="background:none;border:none;color:#aaa;cursor:pointer;font-size:1rem;padding:0 4px;">✕</button>
                        <?php endif; ?>
                    </div>
                    <?php endfor; ?>
                    <?php if (!$is_view): ?>
                    <button type="button" id="swat-add-participant"
                            style="margin-top:8px;background:none;border:1px dashed var(--swat-teal);color:var(--swat-teal);border-radius:6px;padding:6px 14px;cursor:pointer;font-size:0.85rem;width:100%;">
                        <i class="fas fa-plus"></i> Add Participant / הוסף משתתף
                    </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- ══════════════════════ PAGE 2 ══════════════════════ -->

    <!-- Section 6: Guiding Documents -->
    <div class="swat-card">
        <div class="swat-card-header">
            <i class="fas fa-folder-open"></i> Guiding Documents Used
            <span class="he">מסמכי הנחיה בשימוש</span>
        </div>
        <div class="swat-card-body">
            <div class="swat-hazards-grid">
                <?php
                $docs = [
                    ['key' => 'work_procedures', 'en' => 'Work Procedures',        'he' => 'נהלי עבודה'],
                    ['key' => 'drawing',          'en' => 'Drawing',                'he' => 'שרטוט'],
                    ['key' => 'lifting_plan',     'en' => 'Lifting Plan',           'he' => 'תוכנית הרמה'],
                    ['key' => 'tra',              'en' => 'Task Risk Assessment (TRA)', 'he' => 'הערכת סיכוני משימה'],
                    ['key' => 'equip_manual',     'en' => 'Equipment Manual',       'he' => 'מדריך ציוד'],
                ];
                foreach ($docs as $d):
                    $ch = (int)($form_data['doc_' . $d['key']] ?? 0);
                ?>
                <label class="swat-hazard-item">
                    <input type="checkbox" name="doc_<?= $d['key'] ?>" value="1" <?= $ch ? 'checked' : '' ?> <?= $is_view ? 'disabled' : '' ?>>
                    <div class="swat-hazard-label">
                        <span class="en"><?= htmlspecialchars($d['en']) ?></span>
                        <span class="he"><?= htmlspecialchars($d['he']) ?></span>
                    </div>
                </label>
                <?php endforeach; ?>
            </div>
            <div class="swat-field" style="margin-top:10px;">
                <div class="swat-label"><span class="en">Other / אחר:</span></div>
                <input type="text" name="doc_other" class="swat-input"
                       value="<?= htmlspecialchars($form_data['doc_other'] ?? '') ?>"
                       placeholder="Specify other document…" <?= $is_view ? 'readonly' : '' ?>>
            </div>
        </div>
    </div>

    <!-- Section 7: Detailed Hazard & Controls table -->
    <div class="swat-card">
        <div class="swat-card-header">
            <i class="fas fa-table"></i> Hazard &amp; Control Measures
            <span class="he">סיכונים ואמצעי בקרה מפורטים</span>
        </div>
        <div class="swat-card-body">
            <div style="margin-bottom:6px;font-size:0.8rem;color:#888;">
                PPE IS THE LAST LINE OF DEFENSE &nbsp;|&nbsp; ציוד מגן אישי הינו המגן האחרון
            </div>
            <table class="swat-hc-table" id="swat-hc-table">
                <thead>
                    <tr>
                        <th style="width:45%">WHAT can hurt me? (Hazard)<br><span class="he">מה עלול לפגוע בי (סיכון)</span></th>
                        <th style="width:45%">HOW can I prevent Injury? (Controls)<br><span class="he">כיצד ניתן למנוע פציעות (אמצעי בקרה)</span></th>
                        <?php if (!$is_view): ?><th style="width:10%"></th><?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $rows_to_show = max(3, count($hc_rows));
                    for ($i = 0; $i < $rows_to_show; $i++):
                        $hazard  = htmlspecialchars($hc_rows[$i]['hazard']  ?? '');
                        $control = htmlspecialchars($hc_rows[$i]['control'] ?? '');
                    ?>
                    <tr>
                        <td><textarea name="hazards_controls[<?= $i ?>][hazard]"  <?= $is_view ? 'readonly' : '' ?>><?= $hazard ?></textarea></td>
                        <td><textarea name="hazards_controls[<?= $i ?>][control]" <?= $is_view ? 'readonly' : '' ?>><?= $control ?></textarea></td>
                        <?php if (!$is_view): ?>
                        <td style="text-align:center;vertical-align:middle;">
                            <button type="button" class="swat-btn swat-btn-danger swat-hc-remove" style="padding:4px 10px;font-size:0.8rem;">✕</button>
                        </td>
                        <?php endif; ?>
                    </tr>
                    <?php endfor; ?>
                </tbody>
            </table>
            <?php if (!$is_view): ?>
            <div style="margin-top:10px;">
                <button type="button" id="swat-hc-add" class="swat-btn swat-btn-secondary">
                    <i class="fas fa-plus"></i> Add Row / הוסף שורה
                </button>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Section 8: Task Refocus -->
    <div class="swat-card">
        <div class="swat-card-header">
            <i class="fas fa-redo"></i> Task Refocus
            <span class="he">מיקוד מחדש של המשימה</span>
        </div>
        <div class="swat-card-body">
            <div class="swat-refocus-row">
                <?php for ($i = 1; $i <= 3; $i++): ?>
                <div class="swat-refocus-field">
                    <label>Task Refocus #<?= $i ?> — Time / זמן:</label>
                    <input type="time" name="refocus<?= $i ?>_time" class="swat-input"
                           value="<?= htmlspecialchars($form_data['refocus' . $i . '_time'] ?? '') ?>"
                           style="width:130px;" <?= $is_view ? 'readonly' : '' ?>>
                </div>
                <?php endfor; ?>
            </div>
        </div>
    </div>

    <!-- Section 9: Close Out -->
    <div class="swat-card">
        <div class="swat-card-header">
            <i class="fas fa-check-circle"></i> Close Out
            <span class="he">סגירה</span>
        </div>
        <div class="swat-card-body">
            <div class="swat-closeout">
                <h3><i class="fas fa-clipboard-check"></i> Close Out / סגירה</h3>

                <div class="swat-grid-2">
                    <div class="swat-field">
                        <div class="swat-label">
                            <span class="en">Is the task complete?</span>
                            <span class="he">האם המשימה הסתיימה?</span>
                        </div>
                        <div class="swat-yesno">
                            <input type="radio" name="closeout_task_complete" id="tc_yes" value="1"
                                   <?= ($form_data['closeout_task_complete'] ?? 0) ? 'checked' : '' ?>>
                            <label for="tc_yes" class="yes">YES / כן</label>
                            <input type="radio" name="closeout_task_complete" id="tc_no" value="0"
                                   <?= !($form_data['closeout_task_complete'] ?? 1) ? 'checked' : '' ?>>
                            <label for="tc_no" class="no">NO / לא</label>
                        </div>
                    </div>
                    <div class="swat-field">
                        <div class="swat-label">
                            <span class="en">Were there any EHS Events?</span>
                            <span class="he">האם אירעו אירועי בטיחות?</span>
                        </div>
                        <div class="swat-yesno">
                            <input type="radio" name="closeout_ehs_events" id="ehs_yes" value="1"
                                   <?= ($form_data['closeout_ehs_events'] ?? 0) ? 'checked' : '' ?>>
                            <label for="ehs_yes" class="yes">YES / כן</label>
                            <input type="radio" name="closeout_ehs_events" id="ehs_no" value="0"
                                   <?= !($form_data['closeout_ehs_events'] ?? 1) ? 'checked' : '' ?>>
                            <label for="ehs_no" class="no">NO / לא</label>
                        </div>
                    </div>
                </div>

                <div class="swat-field">
                    <div class="swat-label">
                        <span class="en">If yes, who was notified &amp; what time?</span>
                        <span class="he">אם כן, מי עודכן ובאיזו שעה?</span>
                    </div>
                    <input type="text" name="closeout_notified" class="swat-input"
                           value="<?= htmlspecialchars($form_data['closeout_notified'] ?? '') ?>"
                           <?= $is_view ? 'readonly' : '' ?>>
                </div>

                <!-- CP Sign-off -->
                <div class="swat-cp-signoff">
                    <i class="fas fa-signature" style="color:var(--swat-teal);font-size:1.2rem;"></i>
                    <div class="cp-name" style="flex:1;">
                        <?php if ($is_view && $form_data['closeout_cp_name']): ?>
                            <strong><?= htmlspecialchars($form_data['closeout_cp_name']) ?></strong>
                            <div style="font-size:0.75rem;color:#777;">CP / Task Leader Sign-off &nbsp;|&nbsp; חתימת אדם מוסמך</div>
                        <?php else: ?>
                            <div style="font-size:0.75rem;color:#777;margin-bottom:4px;">CP / Task Leader Sign-off &nbsp;|&nbsp; חתימת אדם מוסמך</div>
                            <div class="swat-participant-input" style="max-width:320px;">
                                <input type="text"
                                       id="swat-signoff-input"
                                       name="closeout_cp_name"
                                       value="<?= htmlspecialchars($form_data['closeout_cp_name'] ?? $cp_name) ?>"
                                       placeholder="CP name / שם אדם מוסמך…"
                                       class="swat-input"
                                       style="font-weight:700;font-size:1rem;">
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="cp-timestamp">
                        <?php if ($is_view && $form_data['closeout_timestamp']): ?>
                            <?= htmlspecialchars($form_data['closeout_timestamp']) ?>
                        <?php else: ?>
                            <span id="swat-signoff-time"></span>
                            <script>document.getElementById('swat-signoff-time').textContent = new Date().toLocaleString('he-IL');</script>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Motto + Footer -->
    <div class="swat-motto-bar">
        <span class="he">אנחנו מתחילים עבודה רק אם זה בטוח ועוצרים באם לא</span>
        &nbsp;|&nbsp;
        WE START WORK ONLY WHEN IT'S SAFE, AND STOP WHEN IT'S NOT
    </div>

    <?php if (!$is_view): ?>
    <div class="swat-form-footer">
        <div class="swat-footer-motto">
            <i class="fas fa-hard-hat"></i> Revision 2025 01
        </div>
        <div style="display:flex;gap:10px;flex-wrap:wrap;">
            <a href="swatdashboard.php" class="swat-btn swat-btn-secondary">
                <i class="fas fa-times"></i> Cancel
            </a>
            <input type="submit"
                   value="Save / שמור"
                   style="background:#C8E000;color:#006B6B;border:none;border-radius:5px;padding:9px 20px;font-size:0.9rem;font-weight:700;cursor:pointer;">
        </div>
    </div>
    <?php else: ?>
    <div class="swat-form-footer">
        <div class="swat-revision">Revision 2025 01 &nbsp;|&nbsp; Form #<?= $form_id ?></div>
        <div style="display:flex;gap:10px;">
            <a href="swatform.php?action=edit&id=<?= $form_id ?>" class="swat-btn swat-btn-primary">
                <i class="fas fa-edit"></i> Edit
            </a>
            <a href="swatpdf.php?id=<?= $form_id ?>&lang=en" class="swat-btn swat-btn-pdf">
                <i class="fas fa-file-word"></i> Download RTF
            </a>
        </div>
    </div>
    <?php endif; ?>

    </form>

</div>

<script>
window.SWAT_CURRENT_USER_ID = <?= $current_user_id ?>;

// CRITICAL: Kill any submit event listeners from cached swat.js
// We clone the form element to remove all attached event listeners
document.addEventListener('DOMContentLoaded', function () {
    var form = document.getElementById('swat-main-form');
    if (form) {
        var clone = form.cloneNode(true);
        form.parentNode.replaceChild(clone, form);
    }

    // Re-attach our own listeners on the fresh clone
    var freshForm = document.getElementById('swat-main-form');

    // Life Saving Rules - click to toggle
    document.querySelectorAll('.swat-lsr-item').forEach(function(item) {
        var cb = item.querySelector('input[type=checkbox]');
        if (cb && cb.checked) item.classList.add('checked');
        item.addEventListener('click', function() {
            var cb = this.querySelector('input[type=checkbox]');
            if (!cb) return;
            cb.checked = !cb.checked;
            this.classList.toggle('checked', cb.checked);
        });
    });

    // Auto date/time
    var dateField = document.getElementById('swat-form-date');
    var timeField = document.getElementById('swat-form-time');
    var now = new Date();
    if (dateField && !dateField.value) {
        dateField.value = now.toISOString().split('T')[0];
    }
    if (timeField && !timeField.value) {
        timeField.value = now.toTimeString().slice(0, 5);
    }

    // Add participant button
    var addPartBtn = document.getElementById('swat-add-participant');
    if (addPartBtn) {
        addPartBtn.addEventListener('click', function() {
            var list = document.querySelector('.swat-participants-list');
            var rows = list.querySelectorAll('.swat-participant-row');
            var idx = rows.length;
            if (idx >= 10) { alert('Maximum 10 participants'); return; }
            var row = document.createElement('div');
            row.className = 'swat-participant-row';
            row.innerHTML =
                '<span class="swat-participant-num">' + (idx+1) + '.</span>'
                + '<div class="swat-participant-input">'
                + '<input type="text" name="participants['+idx+'][display_name_text]" placeholder="Search or type name… / חפש או הקלד שם…">'
                + '<input type="hidden" class="hidden-items-id" name="participants['+idx+'][items_id]" value="0">'
                + '<input type="hidden" class="hidden-item-type" name="participants['+idx+'][type]" value="manual">'
                + '<input type="hidden" class="hidden-display-name" name="participants['+idx+'][display_name]" value="">'
                + '</div>'
                + '<button type="button" class="swat-part-clear" style="background:none;border:none;color:#aaa;cursor:pointer;font-size:1rem;padding:0 4px;">✕</button>';
            // Insert before the Add button
            list.insertBefore(row, addPartBtn);
            // Wire up X button
            row.querySelector('.swat-part-clear').addEventListener('click', function() {
                row.remove();
                // Re-number remaining rows
                list.querySelectorAll('.swat-participant-row').forEach(function(r, i) {
                    var num = r.querySelector('.swat-participant-num');
                    if (num) num.textContent = (i+1) + '.';
                });
            });
            // re-init autocomplete on new input
            var newInput = row.querySelector('input[type=text]');
            newInput.addEventListener('input', function() {
                clearTimeout(window._swatPTimer);
                var q = this.value.trim();
                if (q.length < 2) return;
                window._swatPTimer = setTimeout(function() {
                    if (window.SWAT) SWAT._searchPeople(newInput, q, row);
                }, 280);
            });
            newInput.addEventListener('blur', function() {
                var dn = row.querySelector('.hidden-display-name');
                if (dn && !dn.value) dn.value = newInput.value;
                setTimeout(function() { if(window.SWAT) SWAT._closeDropdown(newInput); }, 200);
            });
        });
    }
    if (addBtn) {
        addBtn.addEventListener('click', function() {
            var tbody = document.querySelector('#swat-hc-table tbody');
            if (!tbody) return;
            var idx = tbody.querySelectorAll('tr').length;
            var tr = document.createElement('tr');
            tr.innerHTML = '<td><textarea name="hazards_controls['+idx+'][hazard]" placeholder="Hazard / סיכון" style="width:100%;min-height:38px;"></textarea></td>'
                         + '<td><textarea name="hazards_controls['+idx+'][control]" placeholder="Control / אמצעי בקרה" style="width:100%;min-height:38px;"></textarea></td>'
                         + '<td style="text-align:center;vertical-align:middle;"><button type="button" style="background:#d32f2f;color:#fff;border:none;border-radius:4px;padding:4px 10px;cursor:pointer;">✕</button></td>';
            tr.querySelector('button').addEventListener('click', function() { tr.remove(); });
            tbody.appendChild(tr);
        });
        document.querySelectorAll('.swat-hc-remove').forEach(function(btn) {
            btn.addEventListener('click', function() { btn.closest('tr').remove(); });
        });
    }

});
</script>

<?php Html::footer(); ?>
