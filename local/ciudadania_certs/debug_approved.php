<?php
/**
 * Diagnostic script — accessible via web (admin only) or CLI.
 * Uses direct DB queries to avoid slow Moodle API calls.
 *
 * Web: https://yourdomain/local/ciudadania_certs/debug_approved.php?userid=X&courseid=Y
 * CLI: php debug_approved.php userid=X courseid=Y
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

// --- Direct SQL queries (fast, no Moodle API overhead) ---

$mandatory_idnumbers = ['M1', 'M2', 'M3', 'M4', 'M10'];

// 1. Get all course modules with idnumber and module name
$sql_cms = "
    SELECT cm.id AS cmid, cm.idnumber, cm.completion, cm.instance, m.name AS modname,
           COALESCE(cm.idnumber, '') AS idnum
    FROM {course_modules} cm
    JOIN {modules} m ON m.id = cm.module
    WHERE cm.course = :courseid AND cm.deletioninprogress = 0
    ORDER BY cm.section, cm.id
";
$cms = $DB->get_records_sql($sql_cms, ['courseid' => $courseid]);

// 2. Get completion data for this user in one query
$sql_comp = "
    SELECT coursemoduleid, completionstate, timemodified
    FROM {course_modules_completion}
    WHERE userid = :userid AND coursemoduleid IN (
        SELECT id FROM {course_modules} WHERE course = :courseid AND deletioninprogress = 0
    )
";
$completions = $DB->get_records_sql($sql_comp, ['userid' => $userid, 'courseid' => $courseid]);
$completions = array_column((array)$completions, null, 'coursemoduleid');

// 3. Get all grade items for this course
$grade_items = $DB->get_records('grade_items', ['courseid' => $courseid, 'itemtype' => 'mod']);
// Index by modname+instance for fast lookup
$gi_index = [];
foreach ($grade_items as $gi) {
    $gi_index[$gi->itemmodule . '_' . $gi->iteminstance] = $gi;
}

// 4. Get all grades for this user in one query
$itemids = array_column($grade_items, 'id');
$grades_by_item = [];
if (!empty($itemids)) {
    list($in_sql, $params) = $DB->get_in_or_equal($itemids);
    $params[] = $userid;
    $sql_grades = "SELECT itemid, finalgrade FROM {grade_grades} WHERE itemid $in_sql AND userid = ?";
    $raw_grades = $DB->get_records_sql($sql_grades, $params);
    foreach ($raw_grades as $g) {
        $grades_by_item[$g->itemid] = $g->finalgrade;
    }
}

// --- Build output ---
$lines   = [];
$lines[] = "=== Diagnostic: userid=$userid courseid=$courseid ===";
$lines[] = "";
$lines[] = "--- Modules in course (" . count($cms) . " total) ---";

$completedmodules = [];

foreach ($cms as $cm) {
    $cmid = $cm->cmid;

    if ((int)$cm->completion === 0) {
        $lines[] = "  [{$cm->idnumber}] ({$cm->modname}) → SKIP (no completion tracking)";
        continue;
    }

    $comp  = $completions[$cmid] ?? null;
    $state = $comp ? (int)$comp->completionstate : 0;
    $state_label = match($state) {
        0 => 'INCOMPLETE',
        1 => 'COMPLETE',
        2 => 'COMPLETE_PASS',
        3 => 'COMPLETE_FAIL',
        default => "UNKNOWN($state)",
    };

    if ($state !== 1 && $state !== 2) {
        $lines[] = "  [{$cm->idnumber}] ({$cm->modname}) → SKIP (state={$state_label})";
        continue;
    }

    $gi_key    = $cm->modname . '_' . $cm->instance;
    $gradeitem = $gi_index[$gi_key] ?? null;

    if (!$gradeitem) {
        $lines[] = "  [{$cm->idnumber}] ({$cm->modname}) → SKIP (no grade item)";
        continue;
    }

    $finalgrade = $grades_by_item[$gradeitem->id] ?? null;

    if (is_null($finalgrade)) {
        $lines[] = "  [{$cm->idnumber}] ({$cm->modname}) → SKIP (grade is null, itemid={$gradeitem->id})";
        continue;
    }

    $rounded = (int)round($finalgrade, 0);
    $lines[] = "  [{$cm->idnumber}] ({$cm->modname}) → OK (state={$state_label}, grade={$rounded})";

    $completedmodules[] = [
        'cmid'         => $cmid,
        'idnumber'     => $cm->idnumber,
        'name'         => $cm->modname,
        'grade'        => $rounded,
        'timemodified' => $comp ? (int)$comp->timemodified : 0,
    ];
}

$lines[] = "";
$lines[] = "--- Eligible modules (grade >= 35) ---";
$eligible = array_values(array_filter($completedmodules, fn($m) => $m['grade'] >= 35));
if (empty($eligible)) {
    $lines[] = "  (cap)";
} else {
    foreach ($eligible as $m) {
        $lines[] = "  [{$m['idnumber']}] grade={$m['grade']}";
    }
}

$lines[] = "";
$lines[] = "--- Mandatory check ---";
$eligible_idnumbers = array_column($eligible, 'idnumber');
$all_ok  = true;
foreach ($mandatory_idnumbers as $mid) {
    $ok      = in_array($mid, $eligible_idnumbers);
    $lines[] = "  $mid: " . ($ok ? 'OK' : 'MISSING');
    if (!$ok) $all_ok = false;
}

$avg = count($eligible) > 0
    ? array_sum(array_column($eligible, 'grade')) / count($eligible)
    : 0;
$lines[] = "";
$lines[] = "--- Average check ---";
$lines[] = "  avg=" . number_format($avg, 2) . " (need >= 70)";
$lines[] = "";

if (!$all_ok) {
    $lines[] = "RESULT: FAIL — mandatory modules missing or below 35";
} elseif ($avg < 70) {
    $lines[] = "RESULT: FAIL — average below 70 (avg=" . number_format($avg, 2) . ")";
} else {
    $lines[] = "RESULT: OK — " . count($eligible) . " eligible modules, avg=" . number_format($avg, 1);
}

$text = implode("\n", $lines);

if ($is_cli) {
    echo $text . "\n";
} else {
    echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Diagnòstic CiutadanIA</title></head><body>';
    echo '<pre style="font-family:monospace;font-size:14px;padding:20px">' . htmlspecialchars($text) . '</pre>';
    echo '</body></html>';
}
