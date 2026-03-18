<?php
/**
 * SWAT RTF Generator v2 - Pure PHP, no Python
 * Fixed: icons, logo, Hebrew names, timestamp
 */

function swat_generate_rtf(array $form): string {

    // Colors (RTF 1-based)
    $C_TEAL=1; $C_LIME=2; $C_DARK=3; $C_WHITE=4; $C_LGRAY=5;
    $C_BGRAY=6; $C_GREEN=7; $C_RED=8; $C_YELL=9; $C_GRAY5=10;

    $COLOR_TBL =
        '\red0\green107\blue107;'
        .'\red200\green224\blue0;'
        .'\red26\green26\blue26;'
        .'\red255\green255\blue255;'
        .'\red244\green248\blue248;'
        .'\red224\green240\blue240;'
        .'\red232\green245\blue233;'
        .'\red255\green235\blue238;'
        .'\red255\green253\blue231;'
        .'\red85\green85\blue85;';

    $pics = Plugin::getPhpDir('swat') . DIRECTORY_SEPARATOR . 'pics' . DIRECTORY_SEPARATOR;

    // ── Escape for RTF ────────────────────────────────────────────────────────
    $esc = function(string $s) use (&$esc): string {
        $out = '';
        $len = mb_strlen($s);
        for ($i = 0; $i < $len; $i++) {
            $ch = mb_substr($s, $i, 1);
            $cp = mb_ord($ch);
            if      ($ch === '\\') $out .= '\\\\';
            elseif  ($ch === '{')  $out .= '\{';
            elseif  ($ch === '}')  $out .= '\}';
            elseif  ($cp > 127) {
                $signed = $cp < 32768 ? $cp : $cp - 65536;
                $out .= "\\u{$signed}?";
            }
            else $out .= $ch;
        }
        return $out;
    };

    // ── Embed PNG ─────────────────────────────────────────────────────────────
    $img = function(string $path, int $w=480, int $h=480) use ($pics): string {
        $full = $pics . $path;
        if (!file_exists($full)) return '';
        $data = @file_get_contents($full);
        if (!$data) return '';
        return '{\\pict\\pngblip\\picwgoal'.$w.'\\pichgoal'.$h.' '.bin2hex($data).'}';
    };

    // ── Borders ───────────────────────────────────────────────────────────────
    $brd = function(int $c=6, int $w=5): string {
        return "\\brdrs\\brdrw{$w}\\brdrcf{$c}";
    };

    $B = function(bool $t=true, bool $b=true, bool $l=true, bool $r=true,
                   int $c=6, int $w=5) use ($brd): string {
        $s = '';
        if ($t) $s .= '\\clbrdrt'.$brd($c,$w);
        if ($b) $s .= '\\clbrdrb'.$brd($c,$w);
        if ($l) $s .= '\\clbrdrl'.$brd($c,$w);
        if ($r) $s .= '\\clbrdrr'.$brd($c,$w);
        return $s;
    };

    $allB = $B();  // all borders thin gray

    $sp = function(int $n=2): string {
        return "\\pard\\sb0\\sa".($n*20)."\\sl240\\slmult1\\fs2 \\par\n";
    };

    // ── Section header ────────────────────────────────────────────────────────
    $sec = function(string $en, string $he) use ($C_TEAL,$C_WHITE,$C_LIME,$esc,&$W): string {
        $hw = $W>>1;
        return
            "\\trowd\\trgaph0\\trleft0"
            ."\\clcbpat{$C_TEAL}\\clcbpatraw{$C_TEAL}\\cellx{$hw}"
            ."\\clcbpat{$C_TEAL}\\clcbpatraw{$C_TEAL}\\cellx{$W}"
            ."\n\\pard\\intbl\\sb90\\sa90\\sl276\\slmult1 "
            ."\\b\\cf{$C_WHITE}\\fs21 ".$esc($en)."\\cell"
            ."\n\\pard\\intbl\\qr\\rtlpar\\sb90\\sa90\\sl276\\slmult1 "
            ."\\rtlch\\cf{$C_WHITE}\\fs19 ".$esc($he)."\\cell"
            ."\\row\n";
    };

    // ── Field: label row + value row ──────────────────────────────────────────
    $field = function(array $cells) use ($C_TEAL,$C_DARK,$C_GRAY5,$esc,$B,$allB,&$W): string {
        // Label row
        $s = "\\trowd\\trgaph0\\trleft0"; $x=0;
        foreach ($cells as [$enL,$heL,$val,$w,$fill,$vfs,$vb]) {
            $x += $w;
            $s .= "\\clcbpat{$fill}\\clcbpatraw{$fill}"
                 .$B(true,false,true,true)."\\cellx{$x}";
        }
        $s .= "\n";
        foreach ($cells as [$enL,$heL,$val,$w,$fill,$vfs,$vb]) {
            $s .= "\\pard\\intbl\\sb40\\sa0\\sl240\\slmult1 "
                 ."\\b\\cf{$C_TEAL}\\fs12 ".$esc($enL)."  "
                 ."\\b0\\rtlch\\cf{$C_GRAY5}\\fs11 ".$esc($heL)."\\cell\n";
        }
        $s .= "\\row\n";
        // Value row
        $s .= "\\trowd\\trgaph0\\trleft0"; $x=0;
        foreach ($cells as [$enL,$heL,$val,$w,$fill,$vfs,$vb]) {
            $x += $w;
            $s .= "\\clcbpat{$fill}\\clcbpatraw{$fill}"
                 .$B(false,true,true,true)."\\cellx{$x}";
        }
        $s .= "\n";
        foreach ($cells as [$enL,$heL,$val,$w,$fill,$vfs,$vb]) {
            $bb = $vb ? '\\b' : '';
            $s .= "\\pard\\intbl\\sb30\\sa80\\sl276\\slmult1 "
                 ."{$bb}\\cf{$C_DARK}\\fs{$vfs} ".$esc((string)($val??''))."\\cell\n";
        }
        $s .= "\\row\n";
        return $s;
    };

    // ── Sub-header ────────────────────────────────────────────────────────────
    $subh = function(array $pairs) use ($C_TEAL,$C_BGRAY,$esc,$B,&$W): string {
        $n = count($pairs); $cw = (int)($W/$n);
        $s = "\\trowd\\trgaph0\\trleft0";
        for ($i=0;$i<$n;$i++)
            $s .= "\\clcbpat{$C_BGRAY}\\clcbpatraw{$C_BGRAY}".$B()."\\cellx".($cw*($i+1));
        $s .= "\n";
        foreach ($pairs as [$en,$he])
            $s .= "\\pard\\intbl\\sb60\\sa60\\sl260\\slmult1 "
                 ."\\b\\cf{$C_TEAL}\\fs16 ".$esc($en)."  "
                 ."\\b0\\rtlch\\cf{$C_TEAL}\\fs15 ".$esc($he)."\\cell\n";
        $s .= "\\row\n";
        return $s;
    };

    // ── 2-col text box ────────────────────────────────────────────────────────
    $txt2 = function(string $t1, string $t2, int $h=800) use ($C_DARK,$esc,$B,&$W): string {
        $hw = $W>>1;
        return
            "\\trowd\\trgaph0\\trleft0\\trrh{$h}"
            ."\\clcbpat4\\clcbpatraw4".$B()."\\cellx{$hw}"
            ."\\clcbpat4\\clcbpatraw4".$B()."\\cellx{$W}"
            ."\n\\pard\\intbl\\sb60\\sa60\\sl276\\slmult1 \\cf{$C_DARK}\\fs18 ".$esc($t1)."\\cell"
            ."\n\\pard\\intbl\\sb60\\sa60\\sl276\\slmult1 \\cf{$C_DARK}\\fs18 ".$esc($t2)."\\cell"
            ."\\row\n";
    };

    // ── Motto ─────────────────────────────────────────────────────────────────
    $motto = function() use ($C_TEAL,$C_LIME,$esc,&$W): string {
        return
            "\\trowd\\trgaph0\\trleft0\\trrh600"
            ."\\clcbpat{$C_TEAL}\\clcbpatraw{$C_TEAL}\\cellx{$W}"
            ."\n\\pard\\intbl\\qc\\sb80\\sa40\\sl276\\slmult1 "
            ."\\b\\cf{$C_LIME}\\fs17 WE START WORK ONLY WHEN IT'S SAFE, AND STOP WHEN IT'S NOT\\par"
            ."\n\\pard\\intbl\\qc\\rtlpar\\sb0\\sa80\\sl276\\slmult1 "
            ."\\rtlch\\cf{$C_LIME}\\fs16 ".$esc('אנחנו מתחילים עבודה רק אם זה בטוח ועוצרים באם לא')."\\cell"
            ."\\row\n";
    };

    // ── Data prep ─────────────────────────────────────────────────────────────
    $fid   = (int)($form['id'] ?? 1);
    $cp    = (string)($form['closeout_cp_name'] ?: ($form['cp_name'] ?? ''));
    $parts = array_values((array)($form['participants'] ?? []));
    $hc    = (array)($form['hazards_controls'] ?? []);
    $cu    = (bool)($form['correct_unit'] ?? false);
    $tc    = (bool)($form['closeout_task_complete'] ?? false);
    $eh    = (bool)($form['closeout_ehs_events'] ?? false);
    $gdt   = substr((string)($form['date_creation'] ?? ''), 0, 19);
    $shift = ($form['shift'] ?? 'day') === 'day'
           ? 'Day / '.$esc('יום') : 'Night / '.$esc('לילה');
    // Timestamp: use date_mod or date_creation if closeout_timestamp empty
    $ts = $form['closeout_timestamp'] ?? $form['date_mod'] ?? $gdt;

    $W = 10466; // A4 content width in twips

    $LSR = [
        ['lsr_mechanical_lifting','image2.png','Mechanical Lifting','הרמה מכנית'],
        ['lsr_work_at_height',    'image3.png','Work at Height',   'עבודה בגובה'],
        ['lsr_driving_safety',    'image4.png','Driving Safety',   'בטיחות בנהיגה'],
        ['lsr_line_of_fire',      'image5.png','Line of Fire',     'קו האש'],
        ['lsr_work_authorization','image6.png','Work Authorization','אישור עבודה'],
        ['lsr_confined_space',    'image7.png','Confined Space',   'חלל מוקף'],
        ['lsr_energy_isolation',  'image8.png','Energy Isolation', 'בידוד אנרגיה'],
        ['lsr_live_electrical',   'image9.png','Live Electrical',  'חשמל חי'],
        ['lsr_fire_explosion',    'image10.png','Fire and Explosion','אש ופיצוץ'],
    ];

    $HAZARDS = [
        ['hazard_adjacent_work',   'Adjacent Work',              'עבודה סמוכה'],
        ['hazard_ergonomics',      'Body Positioning/Ergonomics','ארגונומיה / תנוחת גוף'],
        ['hazard_heavy_lift',      'Heavy Manual Lift',          'הרמה ידנית כבדה'],
        ['hazard_burns',           'Burns/Fire/Hot Work',        'כוויות / אש / עבודות חמות'],
        ['hazard_comm_failure',    'Communication Failure',      'כשל בתקשורת'],
        ['hazard_dust_fume',       'Dust/Fume/Grit',            'אבק / עשן / גרגרים'],
        ['hazard_electrical_shock','Electrical Shock',           'התחשמלות'],
        ['hazard_env_weather',     'Environmental/Weather',      'סביבה / מזג אוויר'],
        ['hazard_fall_height',     'Fall from Height',           'נפילה מגובה'],
        ['hazard_dropped_objects', 'Dropped Objects',            'חפצים נופלים'],
        ['hazard_chemicals',       'Chemicals',                  'כימיקלים'],
        ['hazard_noise',           'Noise',                      'רעש'],
        ['hazard_pinch_points',    'Pinch Points/Sharp Objects', 'נקודות צביטה / חפצים חדים'],
        ['hazard_restricted_ws',   'Restricted Workplace',       'מקום עבודה מוגבל'],
        ['hazard_slips_trips',     'Slips, Trips & Falls',      'החלקה, מעידה ונפילות'],
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

    // ════════════════════════════ BUILD RTF ═══════════════════════════════════
    $b = '';

    // ── Header ────────────────────────────────────────────────────────────────
    $logo_img = $img('logo_white.png', 1200, 0);  // auto height
    $w1=(int)($W*0.38); $w2=(int)($W*0.72); $w3=$W;
    $b .=
        "\\trowd\\trgaph0\\trleft0\\trrh800"
        ."\\clcbpat{$C_TEAL}\\clcbpatraw{$C_TEAL}\\cellx{$w1}"
        ."\\clcbpat{$C_TEAL}\\clcbpatraw{$C_TEAL}\\cellx{$w2}"
        ."\\clcbpat{$C_LIME}\\clcbpatraw{$C_LIME}\\cellx{$w3}"
        ."\n\\pard\\intbl\\sb80\\sa80\\sl276\\slmult1 "
        .$logo_img."\\cell"
        ."\n\\pard\\intbl\\qc\\sb60\\sa20\\sl276\\slmult1 "
        ."\\b\\cf{$C_LIME}\\fs52 SWAT\\par"
        ."\\pard\\intbl\\qc\\sb0\\sa60\\sl240\\slmult1 "
        ."\\b0\\cf{$C_WHITE}\\fs14 Start Work Assessment Tool  |  ".$esc('כלי הערכת התחלת עבודה')."\\cell"
        ."\n\\pard\\intbl\\qc\\sb120\\sa60\\sl276\\slmult1 "
        ."\\b\\cf{$C_TEAL}\\fs22 Form #{$fid}\\par"
        ."\\pard\\intbl\\qc\\sb0\\sa120\\sl240\\slmult1 "
        ."\\b0\\cf{$C_TEAL}\\fs16 ".$esc($form['form_date']??'')."\\cell"
        ."\\row\n";
    $b .= $sp(2);

    // ── S1: Site Information ──────────────────────────────────────────────────
    $b .= $sec('Site Information', 'פרטי אתר');
    $b .= $field([
        ['SITE / Work Location','אתר / מיקום עבודה',$form['site_location']??'',(int)($W*0.56),4,21,false],
        ['Work Permit #','מספר אישור עבודה',$form['work_permit_ref']??'',$W-(int)($W*0.56),4,21,true],
    ]);
    $b .= $field([
        ['Date / תאריך','',$form['form_date']??'',$W>>2,4,20,false],
        ['Time / שעה','',substr($form['form_time']??'',0,5),$W>>2,4,20,false],
        ['Shift / משמרת','',$shift,$W>>2,4,20,false],
        ['Correct unit?',$esc('נמצא ביחידה ובציוד הנכון?'),
         $cu?'YES / '.$esc('כן'):'NO / '.$esc('לא'),
         $W>>2,$cu?$C_GREEN:$C_RED,20,true],
    ]);
    $b .= $sp(2);

    // ── S2: Task Assessment ───────────────────────────────────────────────────
    $b .= $sec('Task Assessment', 'הערכת המשימה');
    $b .= $subh([['WHAT is the task?','מהי המשימה?'],['HOW will I do it?','איך תבוצע?']]);
    $b .= $txt2($form['task_what']??'', $form['task_how']??'', 800);
    $b .= $subh([['WHAT are the hazards?','מהם הסיכונים?'],['HOW can I prevent injury?','כיצד ניתן למנוע פציעות?']]);
    $b .= $txt2($form['task_description']??'', $form['task_detail']??'', 800);
    $b .= $sp(2);

    // ── S3: Life Saving Rules ─────────────────────────────────────────────────
    $b .= $sec('The Life Saving Rules', 'חוקים מצילי חיים — סמן את הרלוונטיים');
    $n=count($LSR); $cw=(int)($W/$n);
    $row = "\\trowd\\trgaph0\\trleft0\\trrh1100";
    foreach ($LSR as $i => [$key,$icon,$en_t,$he_t]) {
        $chk = (bool)($form[$key]??false);
        $fill = $chk ? $C_LIME : $C_LGRAY;
        $row .= "\\clcbpat{$fill}\\clcbpatraw{$fill}".$B()."\\cellx".($cw*($i+1));
    }
    $row .= "\n";
    foreach ($LSR as [$key,$icon,$en_t,$he_t]) {
        $chk = (bool)($form[$key]??false);
        $ck  = $chk ? '\\u10003?' : ' ';
        $ic  = $img($icon, 460, 460);
        $row .=
            "\\pard\\intbl\\qc\\sb30\\sa10\\sl240\\slmult1 "
            ."\\b\\cf{$C_TEAL}\\fs20 {$ck}\\b0\\par"
            ."\\pard\\intbl\\qc\\sb0\\sa15\\sl240\\slmult1 {$ic}\\par"
            ."\\pard\\intbl\\qc\\sb0\\sa15\\sl240\\slmult1 "
            ."\\b\\cf{$C_DARK}\\fs14 ".$esc($en_t)."\\b0\\par"
            ."\\pard\\intbl\\qc\\rtlpar\\sb0\\sa30\\sl240\\slmult1 "
            ."\\rtlch\\cf{$C_GRAY5}\\fs14 ".$esc($he_t)."\\cell\n";
    }
    $row .= "\\row\n";
    $b .= $row;
    $b .= $sp(2);

    // ── S4: Other Hazards ─────────────────────────────────────────────────────
    $b .= $sec('Other Hazards', 'סיכונים אחרים');
    $COLS=4; $hcw=(int)($W/$COLS);
    foreach (array_chunk($HAZARDS,$COLS) as $ri => $row_items) {
        $fill = $ri%2===0 ? $C_LGRAY : 4;
        $s = "\\trowd\\trgaph0\\trleft0\\trrh390";
        for ($ci=0;$ci<$COLS;$ci++)
            $s .= "\\clcbpat{$fill}\\clcbpatraw{$fill}".$B()."\\cellx".($hcw*($ci+1));
        $s .= "\n";
        for ($ci=0;$ci<$COLS;$ci++) {
            if ($ci < count($row_items)) {
                [$key,$en_t,$he_t] = $row_items[$ci];
                $chk = (bool)($form[$key]??false);
                $ck  = $chk ? '\\u9745?' : '\\u9744?';
                $bold = $chk ? '\\b' : '';
                $s .=
                    "\\pard\\intbl\\sb30\\sa10\\sl240\\slmult1 "
                    ."{$bold}\\cf".($chk?$C_TEAL:$C_DARK)."\\fs17 {$ck}  "
                    ."\\cf{$C_DARK}\\fs15 ".$esc($en_t)."\\b0\\par"
                    ."\\pard\\intbl\\qr\\rtlpar\\sb0\\sa30\\sl240\\slmult1 "
                    ."\\rtlch\\cf{$C_GRAY5}\\fs14 ".$esc($he_t)."\\cell\n";
            } else {
                $s .= "\\pard\\intbl\\sb0\\sa0 \\cell\n";
            }
        }
        $s .= "\\row\n";
        $b .= $s;
    }
    $others = array_filter(array_map(
        fn($i) => (string)($form["hazard_other{$i}"]??''), [1,2,3,4]
    ));
    if ($others) {
        $b .= "\\trowd\\trgaph0\\trleft0\\trrh320"
             ."\\clcbpat4\\clcbpatraw4".$B()."\\cellx{$W}"
             ."\n\\pard\\intbl\\sb40\\sa40 "
             ."\\b\\cf{$C_TEAL}\\fs16 Other / ".$esc('אחר').":  "
             ."\\b0\\cf{$C_DARK}\\fs17 ".$esc(implode('  |  ',$others))."\\cell\\row\n";
    }
    $b .= $sp(2);
    $b .= $motto();

    // ── PAGE BREAK ────────────────────────────────────────────────────────────
    $b .= "\\page\n";

    // Page 2 compact header
    $b .=
        "\\trowd\\trgaph0\\trleft0\\trrh420"
        ."\\clcbpat{$C_TEAL}\\clcbpatraw{$C_TEAL}\\cellx".(int)($W*0.18)
        ."\\clcbpat{$C_TEAL}\\clcbpatraw{$C_TEAL}\\cellx{$W}"
        ."\n\\pard\\intbl\\sb70\\sa70 \\b\\cf{$C_LIME}\\fs28 SWAT\\cell"
        ."\n\\pard\\intbl\\sb70\\sa70 \\cf{$C_WHITE}\\fs18 "
        ."Form #{$fid}  |  Permit: ".$esc($form['work_permit_ref']??'')
        ."  |  Date: ".$esc($form['form_date']??'')."\\cell"
        ."\\row\n";
    $b .= $sp(2);

    // ── S5: CP & Participants ─────────────────────────────────────────────────
    $b .= $sec('Competent Person & Participants', 'אדם מוסמך ומשתתפים');
    $b .=
        "\\trowd\\trgaph0\\trleft0\\trrh580"
        ."\\clcbpat{$C_BGRAY}\\clcbpatraw{$C_BGRAY}"
        ."\\clbrdrt\\brdrs\\brdrw10\\brdrcf{$C_TEAL}"
        ."\\clbrdrb\\brdrs\\brdrw10\\brdrcf{$C_TEAL}"
        ."\\clbrdrl\\brdrs\\brdrw10\\brdrcf{$C_TEAL}"
        ."\\clbrdrr\\brdrs\\brdrw10\\brdrcf{$C_TEAL}"
        ."\\cellx{$W}"
        ."\n\\pard\\intbl\\sb80\\sa20\\sl276\\slmult1 "
        ."\\b\\cf{$C_TEAL}\\fs17 CP / Task Leader:  \\par"
        ."\\pard\\intbl\\sb0\\sa80\\sl276\\slmult1 "
        ."\\b\\cf{$C_DARK}\\fs26 ".$esc($cp)."\\cell"
        ."\\row\n";
    $pw = $W>>1;
    for ($r=0; $r<5; $r++) {
        $fill = $r%2===0 ? $C_LGRAY : 4;
        $p1 = (string)($parts[$r*2]['display_name'] ?? '');
        $p2 = (string)($parts[$r*2+1]['display_name'] ?? '');
        $b .=
            "\\trowd\\trgaph0\\trleft0\\trrh380"
            ."\\clcbpat{$fill}\\clcbpatraw{$fill}".$B()."\\cellx{$pw}"
            ."\\clcbpat{$fill}\\clcbpatraw{$fill}".$B()."\\cellx{$W}"
            ."\n\\pard\\intbl\\sb45\\sa45\\sl276\\slmult1 "
            ."\\b\\cf{$C_TEAL}\\fs15 ".($r*2+1).") \\b0\\cf{$C_DARK}\\fs20 ".$esc($p1)."\\cell"
            ."\n\\pard\\intbl\\sb45\\sa45\\sl276\\slmult1 "
            ."\\b\\cf{$C_TEAL}\\fs15 ".($r*2+2).") \\b0\\cf{$C_DARK}\\fs20 ".$esc($p2)."\\cell"
            ."\\row\n";
    }
    $b .= $sp(2);

    // ── S6: Guiding Documents ─────────────────────────────────────────────────
    $b .= $sec('Guiding Documents Used', 'מסמכי הנחיה בשימוש');
    $DC=3; $dcw=(int)($W/$DC);
    foreach (array_chunk($DOCS,$DC) as $ri => $row_items) {
        $fill = $ri%2===0 ? $C_LGRAY : 4;
        $s = "\\trowd\\trgaph0\\trleft0\\trrh410";
        for ($ci=0;$ci<$DC;$ci++)
            $s .= "\\clcbpat{$fill}\\clcbpatraw{$fill}".$B()."\\cellx".($dcw*($ci+1));
        $s .= "\n";
        for ($ci=0;$ci<$DC;$ci++) {
            if ($ci < count($row_items)) {
                [$key,$en_t,$he_t] = $row_items[$ci];
                $chk = (bool)($form[$key]??false);
                $ck  = $chk ? '\\u9745?' : '\\u9744?';
                $bold = $chk ? '\\b' : '';
                $s .=
                    "\\pard\\intbl\\sb45\\sa20\\sl240\\slmult1 "
                    ."{$bold}\\cf".($chk?$C_TEAL:$C_DARK)."\\fs18 {$ck}  "
                    ."\\cf{$C_DARK}\\fs17 ".$esc($en_t)."\\b0\\par"
                    ."\\pard\\intbl\\sb0\\sa45\\sl240\\slmult1 "
                    ."\\rtlch\\cf{$C_GRAY5}\\fs15 ".$esc($he_t)."\\cell\n";
            } else {
                $s .= "\\pard\\intbl\\sb0\\sa0 \\cell\n";
            }
        }
        $s .= "\\row\n";
        $b .= $s;
    }
    if ($form['doc_other']??'') {
        $b .= "\\trowd\\trgaph0\\trleft0\\trrh320"
             ."\\clcbpat4\\clcbpatraw4".$B()."\\cellx{$W}"
             ."\n\\pard\\intbl\\sb40\\sa40 "
             ."\\b\\cf{$C_TEAL}\\fs16 Other / ".$esc('אחר').":  "
             ."\\b0\\cf{$C_DARK}\\fs17 ".$esc($form['doc_other'])."\\cell\\row\n";
    }
    $b .= $sp(2);

    // ── S7: Hazard & Control Measures ─────────────────────────────────────────
    $b .= $sec('Hazard & Control Measures', 'סיכונים ואמצעי בקרה מפורטים');
    $b .=
        "\\trowd\\trgaph0\\trleft0\\trrh300"
        ."\\clcbpat{$C_LGRAY}\\clcbpatraw{$C_LGRAY}".$B()."\\cellx{$W}"
        ."\n\\pard\\intbl\\qc\\sb40\\sa40 "
        ."\\i\\cf{$C_GRAY5}\\fs15 PPE IS THE LAST LINE OF DEFENSE  |  "
        .$esc('ציוד מגן אישי הינו המגן האחרון')."\\cell\\row\n";
    $b .= $subh([
        ['WHAT can hurt me? (Hazard)','מה עלול לפגוע בי (סיכון)'],
        ['HOW can I prevent Injury? (Controls)','כיצד ניתן למנוע פציעות (אמצעי בקרה)'],
    ]);
    $hw = $W>>1;
    $filled = array_values(array_filter($hc, fn($r) => ($r['hazard']??'')||($r['control']??'')));
    $nrows = max(5, count($filled));
    for ($i=0; $i<$nrows; $i++) {
        $hz = (string)($hc[$i]['hazard']??'');
        $ct = (string)($hc[$i]['control']??'');
        $fill = $i%2===0 ? $C_LGRAY : 4;
        $b .=
            "\\trowd\\trgaph0\\trleft0\\trrh360"
            ."\\clcbpat{$fill}\\clcbpatraw{$fill}".$B()."\\cellx{$hw}"
            ."\\clcbpat{$fill}\\clcbpatraw{$fill}".$B()."\\cellx{$W}"
            ."\n\\pard\\intbl\\sb45\\sa45\\sl276\\slmult1 \\cf{$C_DARK}\\fs18 ".$esc($hz)."\\cell"
            ."\n\\pard\\intbl\\sb45\\sa45\\sl276\\slmult1 \\cf{$C_DARK}\\fs18 ".$esc($ct)."\\cell"
            ."\\row\n";
    }
    $b .= $sp(2);

    // ── S8: Task Refocus ──────────────────────────────────────────────────────
    $b .= $sec('Task Refocus', 'מיקוד מחדש של המשימה');
    $rfw = (int)($W/3);
    $s = "\\trowd\\trgaph0\\trleft0\\trrh580";
    for ($i=0;$i<3;$i++)
        $s .= "\\clcbpat{$C_YELL}\\clcbpatraw{$C_YELL}".$B()."\\cellx".($rfw*($i+1));
    $s .= "\n";
    foreach (['refocus1_time','refocus2_time','refocus3_time'] as $idx=>$key) {
        $val = (string)($form[$key]??'');
        $bold = $val ? '\\b' : '';
        $s .=
            "\\pard\\intbl\\sb80\\sa30 \\b\\cf{$C_TEAL}\\fs17 Task Refocus #".($idx+1).":\\par"
            ."\\pard\\intbl\\sb0\\sa80 {$bold}\\cf{$C_DARK}\\fs22 Time: "
            .$esc($val ?: chr(8212))."\\cell\n";
    }
    $s .= "\\row\n";
    $b .= $s;
    $b .= $sp(2);

    // ── S9: Close Out ─────────────────────────────────────────────────────────
    $b .= $sec('Close Out', 'סגירה');
    $b .= $field([
        ['Is the task complete?','האם המשימה הסתיימה?',
         $tc?'YES / '.$esc('כן'):'NO / '.$esc('לא'),$W>>1,$tc?$C_GREEN:$C_RED,22,true],
        ['Were there any EHS Events?','האם אירעו אירועי בטיחות?',
         $eh?'YES / '.$esc('כן'):'NO / '.$esc('לא'),$W>>1,$eh?$C_RED:$C_GREEN,22,true],
    ]);
    $b .= $field([
        ['If yes, who was notified & what time?','אם כן, מי עודכן ובאיזו שעה?',
         $form['closeout_notified']??'',$W,4,20,false],
    ]);
    // Sign-off
    $b .=
        "\\trowd\\trgaph0\\trleft0\\trrh650"
        ."\\clcbpat{$C_BGRAY}\\clcbpatraw{$C_BGRAY}"
        ."\\clbrdrt\\brdrs\\brdrw10\\brdrcf{$C_TEAL}"
        ."\\clbrdrb\\brdrs\\brdrw10\\brdrcf{$C_TEAL}"
        ."\\clbrdrl\\brdrs\\brdrw10\\brdrcf{$C_TEAL}"
        ."\\clbrdrr\\brdrs\\brdrw10\\brdrcf{$C_TEAL}"
        ."\\cellx{$hw}"
        ."\\clcbpat{$C_BGRAY}\\clcbpatraw{$C_BGRAY}"
        ."\\clbrdrt\\brdrs\\brdrw10\\brdrcf{$C_TEAL}"
        ."\\clbrdrb\\brdrs\\brdrw10\\brdrcf{$C_TEAL}"
        ."\\clbrdrl\\brdrs\\brdrw10\\brdrcf{$C_TEAL}"
        ."\\clbrdrr\\brdrs\\brdrw10\\brdrcf{$C_TEAL}"
        ."\\cellx{$W}"
        ."\n\\pard\\intbl\\sb80\\sa30 \\b\\cf{$C_TEAL}\\fs16 CP / Task Leader Sign-off:\\par"
        ."\\pard\\intbl\\sb0\\sa80 \\b\\cf{$C_DARK}\\fs26 ".$esc($cp)."\\cell"
        ."\n\\pard\\intbl\\sb80\\sa30 \\b\\cf{$C_TEAL}\\fs16 Date & Time / ".$esc('תאריך ושעה').":\\par"
        ."\\pard\\intbl\\sb0\\sa80 \\cf{$C_DARK}\\fs21 ".$esc(substr((string)$ts,0,19))."\\cell"
        ."\\row\n";
    $b .= $sp(2);
    $b .= $motto();
    $b .= $sp(1);
    $b .= "\\pard\\qc\\sb60\\sa60\\cf{$C_GRAY5}\\fs14 "
         ."Revision 2025 01  |  Generated: ".$esc($gdt)
         ."  |  Form #{$fid}  |  GE Vernova SWAT Plugin\\par\n";

    // ── Assemble ──────────────────────────────────────────────────────────────
    return
        '{\\rtf1\\ansi\\ansicpg1255\\deff0\\deflang1037'."\n"
        .'{\\fonttbl{\\f0\\fswiss\\fcharset177 Arial;}}'."\n"
        .'{\\colortbl ;'.$COLOR_TBL.'}'."\n"
        .'\\widowctrl\\wpaper16838\\wpapr11906\\margl720\\margr720\\margt720\\margb720'."\n"
        .'\\f0\\fs18\\cf3'."\n"
        .$b."\n}";
}
