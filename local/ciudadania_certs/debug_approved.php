<?php
/**
 * Diagnostic script — accessible via web (admin only) or CLI.
 *
 * Web:  https://yourdomain/local/ciudadania_certs/debug_approved.php?userid=X&courseid=Y
 * CLI:  docker exec moodle-moodle-1 php /var/www/html/local/ciudadania_certs/debug_approved.php userid=X courseid=Y
 */

$is_cli = (php_sapi_name() === 'cli');

if ($is_cli) {
    define('CLI_SCRIPT', true);
    require_once(__DIR__ . '/../../../config.php');
    $userid   = 0;
    $courseid = 0;
    foreach (array_slice($argv, 1) as $arg) {
        [$k, $v] = explode('=', $arg) + [null, null];
        if ($k === 'userid')   $userid   = (int)$v;
        if ($k === 'courseid') $courseid = (int)$v;
    }
} else {
    require_once(__DIR__ . '/../../../config.php');
    require_login();
    require_capability('moodle/site:config', context_system::instance());
    $userid   = required_param('userid', PARAM_INT);
    $courseid = required_param('courseid', PARAM_INT);
}

require_once($CFG->libdir . '/completionlib.php');

$out = [];
$out[] = "=== Diagnostic: userid=$userid courseid=$courseid ===";

$mandatory_idnumbers = ['M1', 'M2', 'M3', 'M4', 'M10'];

$course     = get_course($courseid);
$completion = new completion_info($course);
$modinfo    = get_fast_modinfo($courseid);

$gradeitems = $DB->get_records('grade_items', ['courseid' => $courseid, 'itemtype' => 'mod']);

$out[] = '';
$out[] = '--- Modules in course ---';
$completedmodules = [];

foreach ($modinfo->cms as $cm) {
    if ($cm->completion == COMPLETION_TRACKING_NONE) {
        $out[] = "  [{$cm->idnumber}] {$cm->name} → SKIP (no completion tracking)";
        continue;
    }

    $data        = $completion->get_data($cm, true, $userid);
    $state_label = match($data->completionstate) {
        COMPLETION_INCOMPLETE    => 'INCOMPLETE',
        COMPLETION_COMPLETE      => 'COMPLETE',
        COMPLETION_COMPLETE_PASS => 'COMPLETE_PASS',
        COMPLETION_COMPLETE_FAIL => 'COMPLETE_FAIL',
        default                  => "UNKNOWN({$data->completionstate})",
    };

    if ($data->completionstate != COMPLETION_COMPLETE && $data->completionstate != COMPLETION_COMPLETE_PASS) {
        $out[] = "  [{$cm->idnumber}] {$cm->name} → SKIP (state={$state_label})";
        continue;
    }

    $gradeitem = null;
    foreach ($gradeitems as $gi) {
        if ($gi->itemmodule == $cm->modname && $gi->iteminstance == $cm->instance) {
            $gradeitem = $gi;
            break;
        }
    }

    if (!$gradeitem) {
        $out[] = "  [{$cm->idnumber}] {$cm->name} → SKIP (no grade item, modname={$cm->modname})";
        continue;
    }

    $grade = $DB->get_record('grade_grades', ['itemid' => $gradeitem->id, 'userid' => $userid]);

    if (!$grade || is_null($grade->finalgrade)) {
        $out[] = "  [{$cm->idnumber}] {$cm->name} → SKIP (grade is null, itemid={$gradeitem->id})";
        continue;
    }

    $rounded = round($grade->finalgrade, 0);
    $out[]   = "  [{$cm->idnumber}] {$cm->name} → OK (state={$state_label}, grade={$rounded})";

    $completedmodules[] = [
        'cmid'         => $cm->id,
        'idnumber'     => $cm->idnumber,
        'name'         => $cm->name,
        'grade'        => $rounded,
        'timemodified' => $data->timemodified ?? 0,
    ];
}

$out[] = '';
$out[] = '--- Eligible modules (grade >= 35) ---';
$eligible = array_values(array_filter($completedmodules, fn($m) => $m['grade'] >= 35));
foreach ($eligible as $m) {
    $out[] = "  [{$m['idnumber']}] {$m['name']} grade={$m['grade']}";
}

$out[]  = '';
$out[]  = '--- Mandatory check ---';
$eligible_idnumbers = array_column($eligible, 'idnumber');
$all_ok = true;
foreach ($mandatory_idnumbers as $mid) {
    $ok    = in_array($mid, $eligible_idnumbers);
    $out[] = "  $mid: " . ($ok ? 'OK' : 'MISSING');
    if (!$ok) $all_ok = false;
}

$avg = count($eligible) > 0 ? array_sum(array_column($eligible, 'grade')) / count($eligible) : 0;
$out[] = '';
$out[] = '--- Average check ---';
$out[] = '  avg=' . number_format($avg, 2) . ' (need >= 70)';
$out[] = '';

if (!$all_ok) {
    $out[] = 'RESULT: FAIL — mandatory modules missing or below 35';
} elseif ($avg < 70) {
    $out[] = 'RESULT: FAIL — average below 70';
} else {
    $out[] = 'RESULT: OK — ' . count($eligible) . ' eligible modules, avg=' . number_format($avg, 1);
}

$text = implode("\n", $out);

if ($is_cli) {
    echo $text . "\n";
} else {
    echo '<html><head><meta charset="utf-8"><title>Diagnòstic CiutadanIA</title></head><body>';
    echo '<pre style="font-family:monospace;font-size:14px;padding:20px">' . htmlspecialchars($text) . '</pre>';
    echo '</body></html>';
}
