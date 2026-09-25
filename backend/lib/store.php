<?php
/**
 * Temporary JSON-file storage so the UI works before the MySQL schema is decided.
 * backend/seed/*.json is the pristine seed (built from the Excel competency map);
 * backend/data/*.json is the working copy, created on first read. Delete data/ to reset.
 *
 * When moving to MySQL, only this file and the endpoint queries change;
 * the JSON shapes returned by the endpoints stay the same.
 */
declare(strict_types=1);

const SEED_DIR = __DIR__ . '/../seed';
const DATA_DIR = __DIR__ . '/../data';

function store_path(string $name): string
{
    if (!preg_match('/^[a-z_]+$/', $name)) {
        throw new InvalidArgumentException('Bad store name');
    }
    if (!is_dir(DATA_DIR)) {
        mkdir(DATA_DIR, 0775, true);
    }
    sync_reference_lists();
    $path = DATA_DIR . "/{$name}.json";
    if (!file_exists($path)) {
        $seed = SEED_DIR . "/{$name}.json";
        is_file($seed) ? copy($seed, $path) : file_put_contents($path, '[]');   // new stores start empty
    }
    return $path;
}

/**
 * The official lists (divisions, areas, positions, competencies, level legend) come from backend/seed,
 * which is rebuilt from tools/reference/*.xlsx. When the seed is newer than backend/data (after an
 * update), the lists are copied over again and every record that points at them (trainings, position
 * profiles, enrolments) is re-linked by name - so an old data folder can never show old area names.
 */
function sync_reference_lists(): void
{
    static $done = false;
    if ($done) return;
    $done = true;

    $seedVersion = trim((string) @file_get_contents(SEED_DIR . '/VERSION'));
    $dataVersion = trim((string) @file_get_contents(DATA_DIR . '/VERSION'));
    if ($seedVersion === '' || $seedVersion === $dataVersion) return;

    $read = fn (string $dir, string $name) => is_file("{$dir}/{$name}.json")
        ? (json_decode((string) file_get_contents("{$dir}/{$name}.json"), true) ?: []) : null;
    $norm = fn (string $s) => preg_replace('/[^a-z0-9]/', '', strtolower(str_replace(['systems', 'therapy', '&'], ['system', 'therapist', 'and'], strtolower($s))));

    $oldOffices = $read(DATA_DIR, 'offices');
    $oldPositions = $read(DATA_DIR, 'positions');
    $oldDivisions = $read(DATA_DIR, 'divisions') ?? [];
    $newOffices = $read(SEED_DIR, 'offices');
    $newPositions = $read(SEED_DIR, 'positions');
    $newDivisions = $read(SEED_DIR, 'divisions');

    // old office id -> new office id (same label, or same division + area)
    $officeMap = [];
    if ($oldOffices !== null) {
        $divName = array_column($oldDivisions, 'name', 'id');
        $newDivByName = [];
        foreach ($newDivisions as $d) $newDivByName[$norm($d['name'])] = $d['id'];
        foreach ($oldOffices as $o) {
            $hit = null;
            foreach ($newOffices as $n) {
                if (strcasecmp($n['name'], $o['name'] ?? '') === 0) { $hit = $n['id']; break; }
            }
            if ($hit === null && isset($o['area'], $o['division_id'])) {
                $did = $newDivByName[$norm((string) ($divName[$o['division_id']] ?? ''))] ?? null;
                foreach ($newOffices as $n) {
                    if ($n['division_id'] === $did && $norm($n['area']) === $norm($o['area'])) { $hit = $n['id']; break; }
                }
            }
            if ($hit !== null) $officeMap[$o['id']] = $hit;
        }
    }
    // old position id -> new position id (same title, ignoring case and spelling variants)
    $positionMap = [];
    if ($oldPositions !== null) {
        $byTitle = [];
        foreach ($newPositions as $p) $byTitle[$norm($p['title'])] = $p['id'];
        foreach ($oldPositions as $p) {
            if (isset($byTitle[$norm($p['title'])])) $positionMap[$p['id']] = $byTitle[$norm($p['title'])];
        }
    }

    foreach (['divisions', 'offices', 'positions', 'competencies', 'levels'] as $name) {
        if (is_file(SEED_DIR . "/{$name}.json")) copy(SEED_DIR . "/{$name}.json", DATA_DIR . "/{$name}.json");
    }
    $write = fn (string $name, array $rows) => file_put_contents(DATA_DIR . "/{$name}.json",
        json_encode(array_values($rows), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
    $mapIds = fn (array $ids, array $map) => array_values(array_unique(array_filter(array_map(fn ($i) => $map[$i] ?? null, $ids))));

    if (($trainings = $read(DATA_DIR, 'trainings')) !== null && $oldOffices !== null) {
        foreach ($trainings as $i => $t) {
            $trainings[$i]['office_ids'] = $mapIds($t['office_ids'] ?? [], $officeMap);
            if ($oldPositions !== null) $trainings[$i]['position_ids'] = $mapIds($t['position_ids'] ?? [], $positionMap);
        }
        $write('trainings', $trainings);
    }
    if (($profiles = $read(DATA_DIR, 'profiles')) !== null) {
        $kept = [];
        foreach ($profiles as $p) {
            $p['office_id'] = $oldOffices !== null ? ($officeMap[$p['office_id']] ?? null) : $p['office_id'];
            $p['position_id'] = $oldPositions !== null ? ($positionMap[$p['position_id']] ?? null) : $p['position_id'];
            if ($p['office_id'] && $p['position_id']) $kept[] = $p;
        }
        // add seed profiles for position + area pairs the data doesn't have yet
        $have = array_map(fn ($p) => $p['position_id'] . '|' . $p['office_id'], $kept);
        $next = ($kept ? max(array_column($kept, 'id')) : 0) + 1;
        foreach ($read(SEED_DIR, 'profiles') ?? [] as $p) {
            if (!in_array($p['position_id'] . '|' . $p['office_id'], $have, true)) { $p['id'] = $next++; $kept[] = $p; }
        }
        $write('profiles', $kept);
    }
    if (($enrollments = $read(DATA_DIR, 'enrollments')) !== null) {
        foreach ($enrollments as $i => $e) {
            if ($oldOffices !== null && isset($e['office_id'])) $enrollments[$i]['office_id'] = $officeMap[$e['office_id']] ?? null;
            if ($oldPositions !== null && isset($e['position_id'])) $enrollments[$i]['position_id'] = $positionMap[$e['position_id']] ?? null;
        }
        $write('enrollments', $enrollments);
    }
    file_put_contents(DATA_DIR . '/VERSION', $seedVersion);
}

/** The version of the reference lists in use (shown in the app). */
function data_version(): string
{
    return trim((string) @file_get_contents(SEED_DIR . '/VERSION'));
}

function load(string $name): array
{
    $data = json_decode((string) file_get_contents(store_path($name)), true);
    $data = is_array($data) ? $data : [];
    return migrate($name, $data);
}

/**
 * Data written by an older version of the app is missing fields added since
 * (divisions, salary grades, eligibility). Fill them in on read, so an existing
 * backend/data folder keeps working after an update instead of throwing warnings.
 */
function migrate(string $name, array $rows): array
{
    if ($name === 'offices') {
        foreach ($rows as $i => $o) {
            if (!array_key_exists('area', $o) || $o['area'] === null) {
                preg_match('/^(.*?)\s*\((.*)\)\s*$/', (string) ($o['name'] ?? ''), $m);
                $rows[$i]['area'] = $m[2] ?? ($o['section'] ?? $o['name'] ?? '');
                $rows[$i]['division_prefix'] = $m[1] ?? ($o['division'] ?? '');
            }
            if (!array_key_exists('division_id', $o)) {
                $prefix = strtoupper((string) ($rows[$i]['division_prefix'] ?? ''));
                $rows[$i]['division_id'] = division_id_for_prefix($prefix);
            }
        }
    }
    if ($name === 'positions') {
        foreach ($rows as $i => $p) {
            $rows[$i] += ['portal_code' => null, 'salary_grade' => null, 'current' => true];
            if (!array_key_exists('level', $p)) {
                // Older data: take the level from the seed, where it was derived from the map's core level
                static $seedLevels = null;
                $seedLevels ??= array_column(json_decode((string) file_get_contents(SEED_DIR . '/positions.json'), true), 'level', 'id');
                $rows[$i]['level'] = $seedLevels[$p['id']] ?? null;
            }
        }
    }
    if ($name === 'offices') {
        foreach ($rows as $i => $o) $rows[$i] += ['source' => 'added'];
    }
    if ($name === 'profiles') {
        foreach ($rows as $i => $p) {
            $rows[$i] += ['designated_training_ids' => []];
        }
    }
    if ($name === 'trainings') {
        foreach ($rows as $i => $t) {
            $rows[$i] += ['office_ids' => [], 'position_ids' => []];
            if (empty($t['level'])) {
                // older data used a minimum salary grade; turn it into the level whose band contains it
                $sg = (int) ($t['min_salary_grade'] ?? 1);
                $rows[$i]['level'] = $sg >= 24 ? 4 : ($sg >= 18 ? 3 : ($sg >= 11 ? 2 : 1));
            }
            unset($rows[$i]['min_salary_grade']);
            if (!array_key_exists('all_positions', $t)) $rows[$i]['all_positions'] = empty($t['position_ids']);
            unset($rows[$i]['plantilla_only'], $rows[$i]['category']);
            // Competency standards (required level per linked competency); default 1 for older data
            $std = (array) ($t['standards'] ?? []);
            foreach ($t['competency_ids'] ?? [] as $cid) $std[(string) $cid] ??= 1;
            $rows[$i]['standards'] = $std;
            $rows[$i]['competency_ids'] = array_map('intval', array_keys($std));
        }
    }
    if ($name === 'enrollments') {
        foreach ($rows as $i => $r) $rows[$i] += ['status' => 'approved', 'review_note' => null, 'reviewed_at' => null];
    }
    return $rows;
}

function division_id_for_prefix(string $prefix): ?int
{
    $map = ['HOPSS' => 1, 'PETRO' => 1, 'MS' => 2, 'NS' => 3, 'FS' => 4, 'FINANCE' => 4,
            'ALLIED' => 5, 'MCCO' => 6, 'OMCC' => 6];
    if (!isset($map[$prefix])) {
        return null;
    }
    // Confirm the division actually exists in this install before pointing at it.
    foreach (load('divisions') as $d) {
        if ($d['id'] === $map[$prefix]) return $d['id'];
    }
    return null;
}

function save(string $name, array $data): void
{
    $path = store_path($name);
    $fh = fopen($path, 'c+');
    flock($fh, LOCK_EX);
    ftruncate($fh, 0);
    fwrite($fh, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
    fflush($fh);
    flock($fh, LOCK_UN);
    fclose($fh);
}

function next_id(array $rows): int
{
    return $rows ? max(array_column($rows, 'id')) + 1 : 1;
}

function index_by(array $rows, string $key = 'id'): array
{
    $out = [];
    foreach ($rows as $r) {
        $out[$r[$key]] = $r;
    }
    return $out;
}

function read_json_body(): array
{
    $body = json_decode((string) file_get_contents('php://input'), true);
    if (!is_array($body)) {
        respond(['error' => 'Request body must be JSON'], 400);
    }
    return $body;
}
