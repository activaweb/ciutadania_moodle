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
    if (!is_siteadmin()) {
        throw new moodle_exception('accessdenied', 'admin');
    }
    $userid   = required_param('userid', PARAM_INT);
    $courseid = required_param('courseid', PARAM_INT);
}

require_once($CFG->libdir . '/completionlib.php');

// Output helpers — flush after each line so output appears progressively.
function pr(string $line, bool $is_cli): void {
    if ($is_cli) {
        echo $line . "\n";
    } else {
        echo htmlspecialchars($line) . "\n";
        if (ob_get_level()) ob_flush();
        flush();
    }
}

if (!$is_cli) {
    echo '<html><head><meta charset="utf-8"><title>Diagnòstic CiutadanIA</title></head><body>';
    echo '<pre style="font-family:monospace;font-size:14px;padding:20px">';
    if (ob_get_level()) ob_flush();
    flush();
}

pr("=== Diagnostic: userid=$userid courseid=$courseid ===", $is_cli);
pr("", $is_cli);

$mandatory_idnumbers = ['M1', 'M2', 'M3', 'M4', 'M10'];

pr("Loading course...", $is_cli);
$course = get_course($courseid);
pr("Loading completion info...", $is_cli);
$completion = new completion_info($course);
pr("Loading modinfo...", $is_cli);
$modinfo = get_fast_modinfo($courseid);
pr("Loading grade items...", $is_cli);
$gradeitems = $DB->get_records('grade_items', ['courseid' => $courseid, 'itemtype' => 'mod']);
pr("Done loading. " . count($gradeitems) . " grade items found.", $is_cli);
pr("", $is_cli);
pr("--- Modules in course ---", $is_cli);

$completedmodules = [];

foreach ($modinfo->cms as $cm) {
    if ($cm->completion == COMPLETION_TRACKING_NONE) {
        pr("  [{$cm->idnumber}] {$cm->name} → SKIP (no completion tracking)", $is_cli);
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
        pr("  [{$cm->idnumber}] {$cm->name} → SKIP (state={$state_label})", $is_cli);
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
        pr("  [{$cm->idnumber}] {$cm->name} → SKIP (no grade item, modname={$cm->modname})", $is_cli);
        continue;
    }

    $grade = $DB->get_record('grade_grades', ['itemid' => $gradeitem->id, 'userid' => $userid]);

    if (!$grade || is_null($grade->finalgrade)) {
        pr("  [{$cm->idnumber}] {$cm->name} → SKIP (grade is null, itemid={$gradeitem->id})", $is_cli);
        continue;
    }

    $rounded = round($grade->finalgrade, 0);
    pr("  [{$cm->idnumber}] {$cm->name} → OK (state={$state_label}, grade={$rounded})", $is_cli);

    $completedmodules[] = [
        'cmid'         => $cm->id,
        'idnumber'     => $cm->idnumber,
        'name'         => $cm->name,
        'grade'        => $rounded,
        'timemodified' => $data->timemodified ?? 0,
    ];
}

pr("", $is_cli);
pr("--- Eligible modules (grade >= 35) ---", $is_cli);
$eligible = array_values(array_filter($completedmodules, fn($m) => $m['grade'] >= 35));
foreach ($eligible as $m) {
    pr("  [{$m['idnumber']}] {$m['name']} grade={$m['grade']}", $is_cli);
}

pr("", $is_cli);
pr("--- Mandatory check ---", $is_cli);
$eligible_idnumbers = array_column($eligible, 'idnumber');
$all_ok = true;
foreach ($mandatory_idnumbers as $mid) {
    $ok = in_array($mid, $eligible_idnumbers);
    pr("  $mid: " . ($ok ? 'OK' : 'MISSING'), $is_cli);
    if (!$ok) $all_ok = false;
}

$avg = count($eligible) > 0 ? array_sum(array_column($eligible, 'grade')) / count($eligible) : 0;
pr("", $is_cli);
pr("--- Average check ---", $is_cli);
pr("  avg=" . number_format($avg, 2) . " (need >= 70)", $is_cli);
pr("", $is_cli);

if (!$all_ok) {
    pr("RESULT: FAIL — mandatory modules missing or below 35", $is_cli);
} elseif ($avg < 70) {
    pr("RESULT: FAIL — average below 70", $is_cli);
} else {
    pr("RESULT: OK — " . count($eligible) . " eligible modules, avg=" . number_format($avg, 1), $is_cli);
}

if (!$is_cli) {
    echo '</pre></body></html>';
}
