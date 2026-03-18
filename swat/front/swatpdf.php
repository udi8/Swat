<?php
/**
 * SWAT Plugin – PDF Export
 * TCPDF 6.6.2 — EN + HE versions, icons embedded, white logo
 */
include('../../../inc/includes.php');

if (!Session::haveRight('plugin_swat_form', READ) && !Session::haveRight('config', UPDATE)) {
    Html::displayRightError(); exit;
}

$form_id = (int)($_GET['id'] ?? 0);
$lang    = $_GET['lang'] ?? 'en'; // 'en' or 'he'
if (!$form_id) Html::displayErrorAndDie('No form ID');
$form = PluginSwatForm::getFormData($form_id);
if (!$form) Html::displayErrorAndDie('Form not found');

PluginSwatLog::info('pdf_generate', "PDF [{$lang}] form #{$form_id}", $form_id);

// Load TCPDF from GLPI core
$tcpdf = GLPI_ROOT . '/vendor/tecnickcom/tcpdf/tcpdf.php';
if (!file_exists($tcpdf)) $tcpdf = Plugin::getPhpDir('pdf') . '/vendor/tecnickcom/tcpdf/tcpdf.php';
if (!file_exists($tcpdf)) Html::displayErrorAndDie('TCPDF not found');
require_once $tcpdf;

// ── Data prep ──────────────────────────────────────────────────────────────
$form['cp_name'] = getUserName((int)($form['users_id_cp'] ?? 0));
if (!isset($form['participants']))     $form['participants']     = [];
if (!isset($form['hazards_controls'])) $form['hazards_controls'] = [];
if (is_string($form['hazards_controls']))
    $form['hazards_controls'] = json_decode($form['hazards_controls'], true) ?? [];
$form['participants'] = array_values($form['participants'] ?? []);

$fid  = (int)$form['id'];
$cp   = $form['closeout_cp_name'] ?: ($form['cp_name'] ?? '');
$gdt  = substr((string)($form['date_creation'] ?? ''), 0, 19);
$pics = Plugin::getPhpDir('swat') . DIRECTORY_SEPARATOR . 'pics' . DIRECTORY_SEPARATOR;

// ── Hebrew: use HTML with dir=rtl for proper rendering ─────────────────────
$IS_HE = ($lang === 'he');

// Labels: [EN, HE]
$L = [
    'title'        => $IS_HE ? 'כלי הערכת התחלת עבודה' : 'Start Work Assessment Tool',
    'site_info'    => $IS_HE ? 'פרטי אתר' : 'Site Information',
    'site_loc'     => $IS_HE ? 'אתר / מיקום עבודה' : 'SITE / Work Location',
    'permit'       => $IS_HE ? 'מספר אישור עבודה' : 'Work Permit #',
    'date'         => $IS_HE ? 'תאריך' : 'Date',
    'time'         => $IS_HE ? 'שעה' : 'Time',
    'shift'        => $IS_HE ? 'משמרת' : 'Shift',
    'shift_day'    => $IS_HE ? 'יום' : 'Day',
    'shift_night'  => $IS_HE ? 'לילה' : 'Night',
    'correct_unit' => $IS_HE ? 'ביחידה הנכונה?' : 'Correct unit & equipment?',
    'yes'          => $IS_HE ? 'כן' : 'YES',
    'no'           => $IS_HE ? 'לא' : 'NO',
    'task_assess'  => $IS_HE ? 'הערכת המשימה' : 'Task Assessment',
    'what_task'    => $IS_HE ? 'מהי המשימה?' : 'WHAT is the task?',
    'how_do'       => $IS_HE ? 'כיצד תבוצע?' : 'HOW will I do it?',
    'what_haz'     => $IS_HE ? 'מהם הסיכונים?' : 'WHAT are the hazards?',
    'how_prev'     => $IS_HE ? 'כיצד למנוע פציעות?' : 'HOW can I prevent injury?',
    'lsr'          => $IS_HE ? 'חוקים מצילי חיים' : 'The Life Saving Rules',
    'other_haz'    => $IS_HE ? 'סיכונים אחרים' : 'Other Hazards',
    'other'        => $IS_HE ? 'אחר' : 'Other',
    'cp_part'      => $IS_HE ? 'אדם מוסמך ומשתתפים' : 'Competent Person & Participants',
    'cp_leader'    => $IS_HE ? 'אדם מוסמך / מוביל משימה' : 'CP / Task Leader',
    'participants' => $IS_HE ? 'משתתפים' : 'Participants',
    'guiding_docs' => $IS_HE ? 'מסמכי הנחיה בשימוש' : 'Guiding Documents Used',
    'hc_title'     => $IS_HE ? 'סיכונים ואמצעי בקרה' : 'Hazard & Control Measures',
    'ppe'          => $IS_HE ? 'ציוד מגן אישי הינו המגן האחרון' : 'PPE IS THE LAST LINE OF DEFENSE',
    'what_hurt'    => $IS_HE ? 'מה עלול לפגוע בי?' : 'WHAT can hurt me?',
    'how_prevent'  => $IS_HE ? 'כיצד ניתן למנוע?' : 'HOW can I prevent injury?',
    'refocus'      => $IS_HE ? 'מיקוד מחדש' : 'Task Refocus',
    'refocus_n'    => $IS_HE ? 'מיקוד מחדש #' : 'Refocus #',
    'time_lbl'     => $IS_HE ? 'שעה' : 'Time',
    'closeout'     => $IS_HE ? 'סגירה' : 'Close Out',
    'task_done'    => $IS_HE ? 'האם המשימה הסתיימה?' : 'Is the task complete?',
    'ehs'          => $IS_HE ? 'אירועי בטיחות?' : 'Were there any EHS Events?',
    'notified'     => $IS_HE ? 'אם כן, מי עודכן ובאיזו שעה?' : 'If yes, who was notified & what time?',
    'signoff'      => $IS_HE ? 'חתימת אדם מוסמך' : 'CP / Task Leader Sign-off',
    'datetime'     => $IS_HE ? 'תאריך ושעה' : 'Date & Time',
    'motto'        => $IS_HE
        ? 'אנחנו מתחילים עבודה רק אם זה בטוח ועוצרים באם לא'
        : "WE START WORK ONLY WHEN IT'S SAFE, AND STOP WHEN IT'S NOT",
    'motto2'       => $IS_HE
        ? "WE START WORK ONLY WHEN IT'S SAFE, AND STOP WHEN IT'S NOT"
        : 'אנחנו מתחילים עבודה רק אם זה בטוח ועוצרים באם לא',
    'detail_task'  => $IS_HE ? 'פירוט המשימה' : 'Detailed Task',
    'brief_desc'   => $IS_HE ? 'תיאור קצר' : 'Brief Description',
    'details'      => $IS_HE ? 'פרטים' : 'Details',
];

$LSR = [
    ['lsr_mechanical_lifting','image2.png','Mechanical Lifting',  'הרמה מכנית'],
    ['lsr_work_at_height',    'image3.png','Work at Height',      'עבודה בגובה'],
    ['lsr_driving_safety',    'image4.png','Driving Safety',      'בטיחות בנהיגה'],
    ['lsr_line_of_fire',      'image5.png','Line of Fire',        'קו האש'],
    ['lsr_work_authorization','image6.png','Work Authorization',  'אישור עבודה'],
    ['lsr_confined_space',    'image7.png','Confined Space',      'חלל מוקף'],
    ['lsr_energy_isolation',  'image8.png','Energy Isolation',    'בידוד אנרגיה'],
    ['lsr_live_electrical',   'image9.png','Live Electrical',     'חשמל חי'],
    ['lsr_fire_explosion',    'image10.png','Fire & Explosion',   'אש ופיצוץ'],
];
$HAZARDS = [
    ['hazard_adjacent_work',   'Adjacent Work',              'עבודה סמוכה'],
    ['hazard_ergonomics',      'Body Positioning/Ergonomics','ארגונומיה'],
    ['hazard_heavy_lift',      'Heavy Manual Lift',          'הרמה ידנית כבדה'],
    ['hazard_burns',           'Burns/Fire/Hot Work',        'כוויות/אש'],
    ['hazard_comm_failure',    'Communication Failure',      'כשל בתקשורת'],
    ['hazard_dust_fume',       'Dust/Fume/Grit',            'אבק/עשן'],
    ['hazard_electrical_shock','Electrical Shock',           'התחשמלות'],
    ['hazard_env_weather',     'Environmental/Weather',      'סביבה/מזג אוויר'],
    ['hazard_fall_height',     'Fall from Height',           'נפילה מגובה'],
    ['hazard_dropped_objects', 'Dropped Objects',            'חפצים נופלים'],
    ['hazard_chemicals',       'Chemicals',                  'כימיקלים'],
    ['hazard_noise',           'Noise',                      'רעש'],
    ['hazard_pinch_points',    'Pinch Points/Sharp Objects', 'נקודות צביטה'],
    ['hazard_restricted_ws',   'Restricted Workplace',       'מקום עבודה מוגבל'],
    ['hazard_slips_trips',     'Slips, Trips & Falls',      'החלקה/מעידה'],
    ['hazard_wildlife',        'Wildlife',                   'חיות בר'],
    ['hazard_lighting',        'Lighting',                   'תאורה'],
    ['hazard_housekeeping',    'Housekeeping',               'סדר וניקיון'],
    ['hazard_rotating_equip',  'Rotating Equipment',         'ציוד מסתובב'],
    ['hazard_tool_failure',    'Tool Failure',               'כשל בכלי עבודה'],
    ['hazard_radiation',       'Radiation',                  'קרינה'],
];
$DOCS = [
    ['doc_work_procedures','Work Procedures',            'נהלי עבודה'],
    ['doc_drawing',        'Drawing',                    'שרטוט'],
    ['doc_lifting_plan',   'Lifting Plan',               'תוכנית הרמה'],
    ['doc_tra',            'Task Risk Assessment (TRA)', 'הערכת סיכוני משימה'],
    ['doc_equip_manual',   'Equipment Manual',           'מדריך ציוד'],
];

// ── TCPDF Setup ────────────────────────────────────────────────────────────
class SWAT_PDF extends TCPDF {
    public function Footer() {
        $this->SetY(-12);
        $this->SetFillColor(0, 107, 107);
        $this->Rect(10, $this->GetY(), 190, 8, 'F');
        $this->SetFont('dejavusans', 'I', 5.5);
        $this->SetTextColor(200, 224, 0);
        $this->SetXY(12, $this->GetY() + 1.5);
        $this->Cell(130, 4, 'Revision 2025 01  |  GE Vernova SWAT Plugin', 0, 0, 'L');
        $this->SetXY(142, $this->GetY());
        $this->Cell(58, 4, 'Form #' . $this->form_id . '  |  Page ' . $this->getAliasNumPage() . ' of ' . $this->getAliasNbPages(), 0, 0, 'R');
    }
    public int $form_id = 0;
}

$pdf = new SWAT_PDF('P', 'mm', 'A4', true, 'UTF-8', false);
$pdf->form_id = $fid;
$pdf->SetCreator('SWAT Plugin');
$pdf->SetTitle("SWAT Form #{$fid}");
$pdf->SetMargins(10, 10, 10);
$pdf->SetAutoPageBreak(true, 16);
$pdf->setPrintHeader(false);
$pdf->SetFont('dejavusans', '', 8);
if ($IS_HE) $pdf->setRTL(false); // We control RTL per cell

// ── Color helpers ──────────────────────────────────────────────────────────
function fc($p, $r, $g, $b) { $p->SetFillColor($r,$g,$b); }
function dc($p, $r, $g, $b) { $p->SetDrawColor($r,$g,$b); }
function tc($p, $r, $g, $b) { $p->SetTextColor($r,$g,$b); }

// ── HTML cell for Hebrew (proper RTL via writeHTMLCell) ────────────────────
function heCell(TCPDF $p, float $x, float $y, float $w, float $h,
                string $text, float $fs=8, string $color='#1a1a1a', bool $bold=false): void {
    if (!$text) return;
    $b   = $bold ? 'font-weight:bold;' : '';
    $html = '<p style="font-size:'.$fs.'pt;'.$b.'color:'.$color.';text-align:right;" dir="rtl">'
           . htmlspecialchars($text, ENT_QUOTES, 'UTF-8')
           . '</p>';
    $p->writeHTMLCell($w, $h, $x, $y, $html, 0, 0, false, true, 'R');
}

// English cell
function enCell(TCPDF $p, float $x, float $y, float $w, float $h,
                string $text, float $fs=8, string $align='L',
                bool $bold=false, array $color=[26,26,26]): void {
    tc($p, $color[0], $color[1], $color[2]);
    $p->SetFont('dejavusans', $bold?'B':'', $fs);
    $p->SetXY($x, $y);
    $p->Cell($w, $h, $text, 0, 0, $align);
}

// Section header
function sectionBar(TCPDF $p, float $x, float $y, float $w, string $label): float {
    $h = 7;
    fc($p,0,107,107); dc($p,0,107,107);
    $p->Rect($x, $y, $w, $h, 'F');
    tc($p,255,255,255);
    $p->SetFont('dejavusans','B',9);
    $p->SetXY($x+3, $y+1.5);
    $p->Cell($w-6, $h-2, $label, 0, 0, 'L');
    return $y + $h;
}

// Field with label on top, value below
function field(TCPDF $p, float $x, float $y, float $w, float $h,
               string $lbl, string $val, bool $isHe=false,
               array $bg=[255,255,255], bool $bold=false): void {
    fc($p,$bg[0],$bg[1],$bg[2]); dc($p,184,206,206);
    $p->SetLineWidth(0.2);
    $p->Rect($x, $y, $w, $h, 'DF');
    // Label
    if ($isHe) {
        heCell($p, $x+1, $y+0.5, $w-2, 4, $lbl, 6, '#006B6B', true);
    } else {
        tc($p,0,107,107); $p->SetFont('dejavusans','B',6);
        $p->SetXY($x+2, $y+1); $p->Cell($w-4, 3.5, $lbl, 0, 0, 'L');
    }
    // Value
    if ($val) {
        if ($isHe) {
            heCell($p, $x+1, $y+$h-6, $w-2, 5.5, $val, 9, '#1a1a1a', $bold);
        } else {
            tc($p,26,26,26); $p->SetFont('dejavusans',$bold?'B':'',9);
            $p->SetXY($x+2, $y+$h-5.5); $p->Cell($w-4, 5, $val, 0, 0, 'L');
        }
    }
}

// Sub-header row
function subhead(TCPDF $p, float $x, float $y, float $w, float $h,
                 string $lbl, bool $isHe=false): void {
    fc($p,224,240,240); dc($p,184,206,206);
    $p->SetLineWidth(0.2);
    $p->Rect($x, $y, $w, $h, 'DF');
    if ($isHe) {
        heCell($p, $x+1, $y+0.5, $w-2, $h-1, $lbl, 7, '#006B6B', true);
    } else {
        tc($p,0,107,107); $p->SetFont('dejavusans','B',7);
        $p->SetXY($x+3, $y+($h-4.5)/2);
        $p->Cell($w-6, 4.5, $lbl, 0, 0, 'L');
    }
}

// Text content box
function textbox(TCPDF $p, float $x, float $y, float $w, float $h,
                 string $txt, bool $isHe=false): void {
    fc($p,255,255,255); dc($p,184,206,206);
    $p->SetLineWidth(0.2);
    $p->Rect($x, $y, $w, $h, 'DF');
    if (!$txt) return;
    if ($isHe) {
        heCell($p, $x+1, $y+2, $w-2, $h-3, $txt, 8, '#1a1a1a');
    } else {
        tc($p,26,26,26); $p->SetFont('dejavusans','',8);
        $p->SetXY($x+3, $y+2);
        $p->MultiCell($w-6, 4.2, $txt, 0, 'L', false, 1, '', '', true, 0, false, true, $h-3, 'T');
    }
}

// Checkbox
function cbx(TCPDF $p, float $x, float $y, float $sz, bool $checked): void {
    dc($p,0,107,107); $p->SetLineWidth(0.5);
    if ($checked) fc($p,200,224,0); else fc($p,255,255,255);
    $p->Rect($x,$y,$sz,$sz,'DF'); $p->SetLineWidth(0.2);
    if ($checked) {
        tc($p,0,107,107); $p->SetFont('dejavusans','B',$sz+1);
        $p->SetXY($x,$y-0.3); $p->Cell($sz,$sz+0.3,chr(10003),0,0,'C');
    }
}

// Motto bar
function mottoBar(TCPDF $p, float $x, float $y, float $w, bool $isHe): float {
    $h = 12;
    fc($p,0,107,107); dc($p,0,107,107);
    $p->Rect($x,$y,$w,$h,'F');
    $line1 = $isHe
        ? 'אנחנו מתחילים עבודה רק אם זה בטוח ועוצרים באם לא'
        : "WE START WORK ONLY WHEN IT'S SAFE, AND STOP WHEN IT'S NOT";
    $line2 = $isHe
        ? "WE START WORK ONLY WHEN IT'S SAFE, AND STOP WHEN IT'S NOT"
        : 'אנחנו מתחילים עבודה רק אם זה בטוח ועוצרים באם לא';
    if ($isHe) {
        $html = '<p style="font-size:8pt;font-weight:bold;color:#C8E000;text-align:center;" dir="rtl">'
               . htmlspecialchars($line1) . '</p>';
        $p->writeHTMLCell($w, 6, $x, $y+0.5, $html, 0, 0, false, true, 'C');
        tc($p,200,224,0); $p->SetFont('dejavusans','',7);
        $p->SetXY($x, $y+6.5);
        $p->Cell($w, 4.5, $line2, 0, 0, 'C');
    } else {
        tc($p,200,224,0); $p->SetFont('dejavusans','B',7.5);
        $p->SetXY($x, $y+1.5);
        $p->Cell($w, 4.5, $line1, 0, 0, 'C');
        $html = '<p style="font-size:7pt;color:#C8E000;text-align:center;" dir="rtl">'
               . htmlspecialchars($line2) . '</p>';
        $p->writeHTMLCell($w, 5, $x, $y+6, $html, 0, 0, false, true, 'C');
    }
    return $y + $h;
}

// ── PAGE 1 ─────────────────────────────────────────────────────────────────
$pdf->AddPage();
$W  = 190;
$x0 = 10;
$y  = 10;

// ── Header ─────────────────────────────────────────────────────────────────
$HDR = 18;
fc($pdf,0,107,107); $pdf->Rect($x0,$y,$W,$HDR,'F');

// White logo
$logo = $pics.'logo_white.png';
if (file_exists($logo)) {
    try { $pdf->Image($logo, $x0+2, $y+2, 45, 0, 'PNG'); } catch(Exception $e){}
}

// SWAT title
tc($pdf,200,224,0); $pdf->SetFont('dejavusans','B',20);
$pdf->SetXY($x0+55, $y+2); $pdf->Cell(80, 9, 'SWAT', 0, 0, 'C');
tc($pdf,255,255,255); $pdf->SetFont('dejavusans','',7);
$pdf->SetXY($x0+55, $y+11); $pdf->Cell(80, 5, $L['title'], 0, 0, 'C');

// Form badge
fc($pdf,200,224,0); $pdf->RoundedRect($x0+$W-26,$y+4,24,10,3,'1111','F');
tc($pdf,0,107,107); $pdf->SetFont('dejavusans','B',8);
$pdf->SetXY($x0+$W-26,$y+6.5); $pdf->Cell(24,5,"Form #{$fid}",0,0,'C');
$y += $HDR + 2;

// ── Section 1: Site Information ────────────────────────────────────────────
$y = sectionBar($pdf,$x0,$y,$W,$L['site_info']);
$RH = 13;
field($pdf,$x0,$y,$W*0.56,$RH,$L['site_loc'],$form['site_location']??'',$IS_HE);
field($pdf,$x0+$W*0.56,$y,$W*0.44,$RH,$L['permit'],$form['work_permit_ref']??'',$IS_HE,[255,255,255],true);
$y+=$RH;
$fw=$W/4;
$shift_val = ($form['shift']??'day')==='day' ? $L['shift_day'] : $L['shift_night'];
$cu = (bool)($form['correct_unit']??0);
field($pdf,$x0,$y,$fw,$RH,$L['date'],$form['form_date']??'',$IS_HE);
field($pdf,$x0+$fw,$y,$fw,$RH,$L['time'],substr($form['form_time']??'',0,5),$IS_HE);
field($pdf,$x0+$fw*2,$y,$fw,$RH,$L['shift'],$shift_val,$IS_HE);
field($pdf,$x0+$fw*3,$y,$fw,$RH,$L['correct_unit'],$cu?$L['yes']:$L['no'],$IS_HE,
      $cu?[232,245,233]:[255,235,238]);
$y+=$RH+2;

// ── Section 2: Task Assessment ─────────────────────────────────────────────
$y = sectionBar($pdf,$x0,$y,$W,$L['task_assess']);
$hw=$W/2; $SH=7; $TH=18;
subhead($pdf,$x0,$y,$hw,$SH,$L['what_task'],$IS_HE);
subhead($pdf,$x0+$hw,$y,$hw,$SH,$L['how_do'],$IS_HE);
$y+=$SH;
textbox($pdf,$x0,$y,$hw,$TH,$form['task_what']??'',$IS_HE);
textbox($pdf,$x0+$hw,$y,$hw,$TH,$form['task_how']??'',$IS_HE);
$y+=$TH;
subhead($pdf,$x0,$y,$hw,$SH,$L['what_haz'],$IS_HE);
subhead($pdf,$x0+$hw,$y,$hw,$SH,$L['how_prev'],$IS_HE);
$y+=$SH;
textbox($pdf,$x0,$y,$hw,$TH,$form['task_description']??'',$IS_HE);
textbox($pdf,$x0+$hw,$y,$hw,$TH,$form['task_detail']??'',$IS_HE);
$y+=$TH+2;

// ── Section 3: Life Saving Rules ───────────────────────────────────────────
$y = sectionBar($pdf,$x0,$y,$W,$L['lsr']);
$n=count($LSR); $cw=$W/$n; $BXH=30; $ICH=16;
foreach($LSR as $i=>[$key,$icon,$en_t,$he_t]){
    $chk=(bool)($form[$key]??false);
    $bx=$x0+$i*$cw;
    fc($pdf,$chk?200:244,$chk?224:248,$chk?0:248);
    dc($pdf,$chk?200:184,$chk?224:206,$chk?0:206);
    $pdf->SetLineWidth($chk?0.6:0.2);
    $pdf->Rect($bx,$y,$cw,$BXH,'DF'); $pdf->SetLineWidth(0.2);
    if($chk){
        fc($pdf,0,107,107); $pdf->Circle($bx+$cw-4,$y+4,3.5,0,360,'F');
        tc($pdf,255,255,255); $pdf->SetFont('dejavusans','B',6);
        $pdf->SetXY($bx+$cw-8,$y+1.5); $pdf->Cell(7.5,5,chr(10003),0,0,'C');
    }
    $ip=$pics.$icon;
    if(file_exists($ip)) try{
        $isz=min($cw-3,$ICH); $pdf->Image($ip,$bx+($cw-$isz)/2,$y+1,$isz,$isz,'PNG');
    }catch(Exception $e){}
    // Icon label - show primary language on top
    $primary = $IS_HE ? $he_t : $en_t;
    $secondary = $IS_HE ? $en_t : $he_t;
    if($IS_HE){
        $html='<p style="font-size:4.8pt;font-weight:bold;color:#1a1a1a;text-align:center;" dir="rtl">'
             .htmlspecialchars($primary).'</p>';
        $pdf->writeHTMLCell($cw,3.5,$bx,$y+$ICH+2,$html,0,0,false,true,'C');
    } else {
        tc($pdf,26,26,26); $pdf->SetFont('dejavusans','B',4.8);
        $pdf->SetXY($bx,$y+$ICH+2); $pdf->Cell($cw,3.5,$primary,0,0,'C');
    }
    tc($pdf,85,85,85); $pdf->SetFont('dejavusans','',4.2);
    $pdf->SetXY($bx,$y+$ICH+6); $pdf->Cell($cw,3,$secondary,0,0,'C');
}
$y+=$BXH+2;

// ── Section 4: Other Hazards ───────────────────────────────────────────────
$y = sectionBar($pdf,$x0,$y,$W,$L['other_haz']);
$COLS=4; $hcw=$W/$COLS; $HH=10; $CBS=3.5;
foreach(array_chunk($HAZARDS,$COLS) as $ri=>$row_items){
    $fill=$ri%2===0?[244,248,248]:[255,255,255];
    foreach($row_items as $ci=>[$key,$en_t,$he_t]){
        $chk=(bool)($form[$key]??false);
        $hx=$x0+$ci*$hcw; $hy=$y;
        fc($pdf,$fill[0],$fill[1],$fill[2]); dc($pdf,$fill[0],$fill[1],$fill[2]);
        $pdf->Rect($hx,$hy,$hcw,$HH,'F');
        cbx($pdf,$hx+1.5,$hy+1.8,$CBS,$chk);
        $primary = $IS_HE ? $he_t : $en_t;
        $secondary = $IS_HE ? $en_t : $he_t;
        if($IS_HE){
            $html='<p style="font-size:6pt;font-weight:bold;color:'.($chk?'#006B6B':'#1a1a1a').';text-align:right;" dir="rtl">'
                 .htmlspecialchars($primary).'</p>';
            $pdf->writeHTMLCell($hcw-$CBS-5,4.5,$hx+$CBS+3,$hy+1,$html,0,0,false,true,'R');
        } else {
            tc($pdf,$chk?0:26,$chk?107:26,$chk?107:26);
            $pdf->SetFont('dejavusans','B',6);
            $pdf->SetXY($hx+$CBS+3,$hy+1.5); $pdf->Cell($hcw-$CBS-4,3.5,$primary,0,0,'L');
        }
        tc($pdf,85,85,85); $pdf->SetFont('dejavusans','',5.2);
        $pdf->SetXY($hx+$CBS+3,$hy+$HH-4); $pdf->Cell($hcw-$CBS-4,3,$secondary,0,0,'L');
    }
    $y+=$HH+0.5;
}
// Others free text
$others=array_filter(array_map(fn($i)=>$form["hazard_other{$i}"]??'',[1,2,3,4]));
if($others){
    tc($pdf,26,26,26); $pdf->SetFont('dejavusans','',7);
    $pdf->SetXY($x0+2,$y+1);
    $pdf->Cell($W-4,5,$L['other'].': '.implode('  |  ',$others),0,0,'L');
    $y+=7;
}
$y+=2;

// ── Section 5: CP & Participants ───────────────────────────────────────────
$y = sectionBar($pdf,$x0,$y,$W,$L['cp_part']);
$CPH=14;
fc($pdf,224,240,240); dc($pdf,0,107,107); $pdf->SetLineWidth(0.6);
$pdf->Rect($x0,$y,$W,$CPH,'DF'); $pdf->SetLineWidth(0.2);
tc($pdf,0,107,107); $pdf->SetFont('dejavusans','B',7);
$pdf->SetXY($x0+3,$y+2); $pdf->Cell(60,4,$L['cp_leader'].':',0,0,'L');
if($IS_HE){
    heCell($pdf,$x0+3,$y+7,$W-6,5.5,$cp,11,'#1a1a1a',true);
} else {
    tc($pdf,26,26,26); $pdf->SetFont('dejavusans','B',11);
    $pdf->SetXY($x0+3,$y+7); $pdf->Cell($W-6,5.5,$cp,0,0,'L');
}
$y+=$CPH;

$parts=$form['participants']??[]; $PH=8; $PC=2;
for($i=0;$i<10;$i++){
    $row=(int)floor($i/$PC); $col=$i%$PC;
    $px=$x0+$col*($W/$PC); $py=$y+$row*$PH;
    $bg=$row%2===0?[244,248,248]:[255,255,255];
    fc($pdf,$bg[0],$bg[1],$bg[2]); dc($pdf,184,206,206);
    $pdf->Rect($px,$py,$W/$PC,$PH,'DF');
    tc($pdf,0,107,107); $pdf->SetFont('dejavusans','B',7);
    $pdf->SetXY($px+2,$py+2); $pdf->Cell(8,4.5,($i+1).')',0,0,'L');
    $pname=$parts[$i]['display_name']??'';
    tc($pdf,26,26,26); $pdf->SetFont('dejavusans','',9.5);
    $pdf->SetXY($px+11,$py+1.8); $pdf->Cell($W/$PC-13,4.5,$pname,0,0,'L');
}
$y+=(int)ceil(10/$PC)*$PH+2;

// Motto page 1
$y = mottoBar($pdf,$x0,$y,$W,$IS_HE);

// ── PAGE 2 ─────────────────────────────────────────────────────────────────
$pdf->AddPage();
$y=10;

// Page 2 compact header
fc($pdf,0,107,107); $pdf->Rect($x0,$y,$W,12,'F');
tc($pdf,200,224,0); $pdf->SetFont('dejavusans','B',13);
$pdf->SetXY($x0+2,$y+2); $pdf->Cell(20,8,'SWAT',0,0,'L');
tc($pdf,255,255,255); $pdf->SetFont('dejavusans','',8.5);
$pdf->SetXY($x0+24,$y+3);
$pdf->Cell($W-26,7,"Form #{$fid}  |  Permit: ".($form['work_permit_ref']??'')
    ."  |  Date: ".($form['form_date']??''),0,0,'L');
$y+=14;

// ── Section 6: Guiding Documents ──────────────────────────────────────────
$y = sectionBar($pdf,$x0,$y,$W,$L['guiding_docs']);
$DC=3; $dcw=$W/$DC; $DH=12;
foreach(array_chunk($DOCS,$DC) as $ri=>$row_items){
    $fill=$ri%2===0?[244,248,248]:[255,255,255];
    foreach($row_items as $ci=>[$key,$en_t,$he_t]){
        $chk=(bool)($form[$key]??false);
        $dx=$x0+$ci*$dcw; $dy=$y;
        fc($pdf,$fill[0],$fill[1],$fill[2]); dc($pdf,$fill[0],$fill[1],$fill[2]);
        $pdf->Rect($dx,$dy,$dcw,$DH,'F');
        cbx($pdf,$dx+2,$dy+2.5,5,$chk);
        $primary = $IS_HE ? $he_t : $en_t;
        if($IS_HE){
            $html='<p style="font-size:7.5pt;font-weight:bold;color:'.($chk?'#006B6B':'#1a1a1a').';text-align:right;" dir="rtl">'
                 .htmlspecialchars($primary).'</p>';
            $pdf->writeHTMLCell($dcw-10,5,$dx+9,$dy+1,$html,0,0,false,true,'R');
            tc($pdf,85,85,85); $pdf->SetFont('dejavusans','',6);
            $pdf->SetXY($dx+9,$dy+6); $pdf->Cell($dcw-11,4,$en_t,0,0,'L');
        } else {
            tc($pdf,$chk?0:26,$chk?107:26,$chk?107:26);
            $pdf->SetFont('dejavusans','B',7.5);
            $pdf->SetXY($dx+9,$dy+1.5); $pdf->Cell($dcw-11,4.5,$en_t,0,0,'L');
            tc($pdf,85,85,85); $pdf->SetFont('dejavusans','',6.5);
            $pdf->SetXY($dx+9,$dy+6); $pdf->Cell($dcw-11,4,$he_t,0,0,'L');
        }
    }
    $y+=$DH;
}
if($form['doc_other']??''){
    tc($pdf,26,26,26); $pdf->SetFont('dejavusans','',8);
    $pdf->SetXY($x0+2,$y+1);
    $pdf->Cell($W-4,5,$L['other'].': '.($form['doc_other']),0,0,'L'); $y+=7;
}
$y+=2;

// ── Section 7: Detailed Task ───────────────────────────────────────────────
$y = sectionBar($pdf,$x0,$y,$W,$L['detail_task']);
subhead($pdf,$x0,$y,$hw,7,$L['brief_desc'],$IS_HE);
subhead($pdf,$x0+$hw,$y,$hw,7,$L['details'],$IS_HE);
$y+=7;
textbox($pdf,$x0,$y,$hw,24,$form['task_description']??'',$IS_HE);
textbox($pdf,$x0+$hw,$y,$hw,24,$form['task_detail']??'',$IS_HE);
$y+=26;

// ── Section 8: Hazard & Controls ──────────────────────────────────────────
$y = sectionBar($pdf,$x0,$y,$W,$L['hc_title']);
// PPE note
fc($pdf,244,248,248); dc($pdf,184,206,206);
$pdf->Rect($x0,$y,$W,7,'DF');
if($IS_HE){
    $html='<p style="font-size:7pt;font-style:italic;color:#555;text-align:center;" dir="rtl">'
         .htmlspecialchars($L['ppe']).'</p>';
    $pdf->writeHTMLCell($W,7,$x0,$y,$html,0,0,false,true,'C');
} else {
    tc($pdf,85,85,85); $pdf->SetFont('dejavusans','I',7);
    $pdf->SetXY($x0,$y+1.5); $pdf->Cell($W,4,$L['ppe'],0,0,'C');
}
$y+=7;
subhead($pdf,$x0,$y,$hw,7,$L['what_hurt'],$IS_HE);
subhead($pdf,$x0+$hw,$y,$hw,7,$L['how_prevent'],$IS_HE);
$y+=7;

$hc=$form['hazards_controls']??[];
$filled=array_values(array_filter($hc,fn($r)=>($r['hazard']??'')||($r['control']??'')));
$nrows=max(5,count($filled)); $RH3=8;
for($i=0;$i<$nrows;$i++){
    $hz=$filled[$i]['hazard']??''; $ct=$filled[$i]['control']??'';
    $bg=$i%2===0?[244,248,248]:[255,255,255];
    fc($pdf,$bg[0],$bg[1],$bg[2]); dc($pdf,184,206,206);
    $pdf->Rect($x0,$y,$hw,$RH3,'DF'); $pdf->Rect($x0+$hw,$y,$hw,$RH3,'DF');
    tc($pdf,26,26,26); $pdf->SetFont('dejavusans','',7.5);
    if($hz){ $pdf->SetXY($x0+2,$y+2); $pdf->Cell($hw-4,$RH3-2,substr($hz,0,60),0,0,'L'); }
    if($ct){ $pdf->SetXY($x0+$hw+2,$y+2); $pdf->Cell($hw-4,$RH3-2,substr($ct,0,60),0,0,'L'); }
    $y+=$RH3;
}
$y+=2;

// ── Section 9: Task Refocus ────────────────────────────────────────────────
$y = sectionBar($pdf,$x0,$y,$W,$L['refocus']);
$rfw=$W/3; $RFH=14;
fc($pdf,255,253,231); $pdf->Rect($x0,$y,$W,$RFH,'F');
foreach([['refocus1_time',1],['refocus2_time',2],['refocus3_time',3]] as [$key,$n]){
    $val=$form[$key]??''; $rx=$x0+($n-1)*$rfw;
    dc($pdf,184,206,206); $pdf->Rect($rx,$y,$rfw,$RFH,'D');
    tc($pdf,0,107,107); $pdf->SetFont('dejavusans','B',7.5);
    $pdf->SetXY($rx+3,$y+2); $pdf->Cell($rfw-6,4,$L['refocus_n'].$n,0,0,'L');
    tc($pdf,26,26,26); $pdf->SetFont('dejavusans',$val?'B':'',10);
    $pdf->SetXY($rx+3,$y+8); $pdf->Cell($rfw-6,5,$L['time_lbl'].': '.($val?:'—'),0,0,'L');
}
$y+=$RFH+2;

// ── Section 10: Close Out ──────────────────────────────────────────────────
$y = sectionBar($pdf,$x0,$y,$W,$L['closeout']);
$tc=(bool)($form['closeout_task_complete']??0);
$eh=(bool)($form['closeout_ehs_events']??0);
$COH=13;
field($pdf,$x0,$y,$hw,$COH,$L['task_done'],$tc?$L['yes']:$L['no'],$IS_HE,$tc?[232,245,233]:[255,235,238]);
field($pdf,$x0+$hw,$y,$hw,$COH,$L['ehs'],$eh?$L['yes']:$L['no'],$IS_HE,$eh?[255,235,238]:[232,245,233]);
$y+=$COH;
field($pdf,$x0,$y,$W,$COH,$L['notified'],$form['closeout_notified']??'',$IS_HE);
$y+=$COH;

// Sign-off
$SOH=16;
fc($pdf,224,240,240); dc($pdf,0,107,107); $pdf->SetLineWidth(0.6);
$pdf->Rect($x0,$y,$W,$SOH,'DF'); $pdf->SetLineWidth(0.2);
// Left
tc($pdf,0,107,107); $pdf->SetFont('dejavusans','B',7);
$pdf->SetXY($x0+3,$y+2); $pdf->Cell($hw-6,4,$L['signoff'].':',0,0,'L');
if($IS_HE){
    heCell($pdf,$x0+1,$y+7,$hw-2,7,$cp,11,'#1a1a1a',true);
} else {
    tc($pdf,26,26,26); $pdf->SetFont('dejavusans','B',11);
    $pdf->SetXY($x0+3,$y+8); $pdf->Cell($hw-6,6,$cp,0,0,'L');
}
// Divider
dc($pdf,184,206,206); $pdf->SetLineWidth(0.3);
$pdf->Line($x0+$hw,$y,$x0+$hw,$y+$SOH); $pdf->SetLineWidth(0.2);
// Right
tc($pdf,0,107,107); $pdf->SetFont('dejavusans','B',7);
$pdf->SetXY($x0+$hw+3,$y+2); $pdf->Cell($hw-6,4,$L['datetime'].':',0,0,'L');
tc($pdf,26,26,26); $pdf->SetFont('dejavusans','',10);
$pdf->SetXY($x0+$hw+3,$y+8);
$pdf->Cell($hw-6,6,substr($form['closeout_timestamp']??'',0,19),0,0,'L');
$y+=$SOH+2;

mottoBar($pdf,$x0,$y,$W,$IS_HE);

// ── Output ─────────────────────────────────────────────────────────────────
$lang_suffix = $IS_HE ? '_HE' : '_EN';
$pdf->Output("SWAT_Form_{$fid}{$lang_suffix}_".date('Ymd_His').'.pdf','D');
exit;
