<?php
namespace local_ciudadania_certs;

defined('MOODLE_INTERNAL') || die();

/**
 * Computes which "mencions" (itineraris definits per combinacions de mòduls) es
 * compleixen per a un conjunt de mòduls aprovats.
 *
 * Les definicions es llegeixen de l'ajust del plugin `mencions_definition`
 * (Site administration > Plugins > Local plugins > Certificacions CiutadanIA),
 * de manera que es poden afegir, treure o canviar mencions sense tocar codi.
 */
class mencions_helper {

    /** Definició per defecte, usada si l'ajust del plugin encara no s'ha configurat. */
    const DEFAULT_DEFINITION = "Cursos obligatoris = 1,2,3,4,10\n"
        . "Dades i suport a la decisió = 1,2,3,4,10,12,14\n"
        . "Percepció = 1,2,3,4,5,8,9,10,13\n"
        . "CiutadanIA 2.0 al complet = *";

    /**
     * Returns the names of the mencions fully satisfied by the given modules.
     *
     * @param array $modules Array of module arrays/objects with an 'idnumber' key, as
     *                        returned by snapshot_manager::get_current_approved_modules()
     *                        or snapshot_manager::get_certified_modules().
     * @param int|null $courseid Course id, needed to resolve "*" (tots els mòduls)
     *                           definitions. If omitted, those definitions are skipped.
     * @return string[] Noms de les mencions assolides, en l'ordre en què estan definides.
     */
    public static function get_earned_mencions(array $modules, ?int $courseid = null): array {
        $idnumbers = array_map(function ($m) {
            $idnumber = is_array($m) ? ($m['idnumber'] ?? '') : ($m->idnumber ?? '');
            return strtoupper($idnumber);
        }, $modules);

        $earned = [];
        foreach (self::get_definitions($courseid) as $name => $required) {
            if (empty(array_diff($required, $idnumbers))) {
                $earned[] = $name;
            }
        }

        return $earned;
    }

    /**
     * Parses the plugin configuration into "menció name" => "required idnumbers".
     *
     * Format (una menció per línia): "Nom de la menció = 1,2,3,4,10"
     * Els mòduls es poden llistar amb rangs ("1-4,10") i "*" vol dir "tots els
     * mòduls del curs" (resolt dinàmicament via $courseid, per seguir sent
     * correcte si s'afegeixen mòduls nous).
     *
     * @return array<string, string[]> Nom de la menció => idnumbers requerits (p.ex. 'M1').
     */
    private static function get_definitions(?int $courseid): array {
        $raw = get_config('local_ciudadania_certs', 'mencions_definition');
        if ($raw === false || trim((string) $raw) === '') {
            $raw = self::DEFAULT_DEFINITION;
        }

        $definitions = [];
        foreach (preg_split('/\r\n|\r|\n/', $raw) as $line) {
            $line = trim($line);
            if ($line === '' || strpos($line, '=') === false) {
                continue;
            }

            [$name, $modulesspec] = array_map('trim', explode('=', $line, 2));
            if ($name === '') {
                continue;
            }

            $modulesspec = trim($modulesspec);
            $required = $modulesspec === '*'
                ? self::get_all_course_idnumbers($courseid)
                : self::expand_module_list($modulesspec);

            if (!empty($required)) {
                $definitions[$name] = $required;
            }
        }

        return $definitions;
    }

    /**
     * Expands "1,2,3-5,10" into ['M1', 'M2', 'M3', 'M4', 'M5', 'M10'].
     *
     * @return string[]
     */
    private static function expand_module_list(string $spec): array {
        $numbers = [];
        foreach (explode(',', $spec) as $part) {
            $part = trim($part);
            if ($part === '') {
                continue;
            }
            if (preg_match('/^(\d+)\s*-\s*(\d+)$/', $part, $m)) {
                $numbers = array_merge($numbers, range((int) $m[1], (int) $m[2]));
            } else if (is_numeric($part)) {
                $numbers[] = (int) $part;
            }
        }

        return array_values(array_unique(array_map(fn($n) => 'M' . $n, $numbers)));
    }

    /**
     * Returns the idnumbers ('M1', 'M2', ...) of every module in the course that
     * follows the "M<number>" idnumber convention, for resolving "*" definitions.
     *
     * @return string[]
     */
    private static function get_all_course_idnumbers(?int $courseid): array {
        if (empty($courseid)) {
            return [];
        }

        global $DB;
        $idnumbers = $DB->get_fieldset_select('course_modules', 'idnumber',
            "course = :courseid AND idnumber IS NOT NULL AND idnumber <> ''",
            ['courseid' => $courseid]
        );

        $idnumbers = array_map('strtoupper', $idnumbers);
        return array_values(array_filter($idnumbers, fn($id) => preg_match('/^M\d+$/', $id)));
    }
}
