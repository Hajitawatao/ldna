<?php
/**
 * Tiny JSON store for the staging mock portal. Not part of the LDNA system.
 *
 * Plantilla model:
 *   an item  = one plantilla item name (e.g. OSEC-DOHB-ADAS1-12-2004) for one position in one
 *              division/area, with a quantity (e.g. 3)
 *   a slot   = one of those, numbered #1..#quantity internally; one employee per slot
 */
declare(strict_types=1);

const DATA = __DIR__ . '/../data';
const PHOTOS = __DIR__ . '/../photos';
const PHOTO_TYPES = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
const PHOTO_MAX_BYTES = 2 * 1024 * 1024;

// Official R1MC divisions, areas and current plantilla positions (data/reference.json, built from
// tools/reference/*.xlsx). The admin screen only offers these values, so what the portal sends
// always matches what LDNA expects.
define('REFERENCE', json_decode((string) file_get_contents(__DIR__ . '/../data/reference.json'), true));
define('DIVISIONS', array_map(fn ($d) => ['code' => $d['code'], 'name' => $d['name']], REFERENCE['divisions']));

function mp_areas(string $divisionCode): array
{
    foreach (REFERENCE['divisions'] as $d) if ($d['code'] === $divisionCode) return $d['areas'];
    return [];
}

function mp_position_sg(string $title): ?int
{
    foreach (REFERENCE['positions'] as $p) if (strcasecmp($p['title'], $title) === 0) return $p['salary_grade'];
    return null;
}

function mp_load(string $name): array
{
    $file = DATA . "/{$name}.json";
    return is_file($file) ? (json_decode((string) file_get_contents($file), true) ?: []) : [];
}

function mp_save(string $name, array $rows): void
{
    $fh = fopen(DATA . "/{$name}.json", 'c+');
    flock($fh, LOCK_EX);
    ftruncate($fh, 0);
    fwrite($fh, json_encode(array_values($rows), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    fflush($fh);
    flock($fh, LOCK_UN);
    fclose($fh);
}

function mp_division_name(string $code): string
{
    foreach (DIVISIONS as $d) {
        if ($d['code'] === $code) return $d['name'];
    }
    return $code;
}

function mp_items_by_id(): array
{
    return array_column(mp_load('plantilla_items'), null, 'id');
}

/**
 * "Supervising Administrative Officer #2" - internal label, shown only in this admin screen.
 * The official DOH item name (e.g. OSEC-DOHB-SADOF-9-1998) is never changed or suffixed.
 */
function mp_slot_label(array $item, int $slot): string
{
    return "{$item['title']} #{$slot}";
}

/** [item_id][slot] => employee, for every occupied slot. */
function mp_occupancy(array $employees): array
{
    $map = [];
    foreach ($employees as $e) {
        if (!empty($e['item_id'])) $map[$e['item_id']][$e['slot']] = $e;
    }
    return $map;
}

// ---- photos ---------------------------------------------------------------

function mp_photo_path(string $employeeId): ?string
{
    if (!preg_match('/^[A-Za-z0-9_-]+$/', $employeeId)) return null;
    foreach (PHOTO_TYPES as $ext) {
        $file = PHOTOS . "/{$employeeId}.{$ext}";
        if (is_file($file)) return $file;
    }
    return null;
}

function mp_delete_photo(string $employeeId): void
{
    while ($file = mp_photo_path($employeeId)) unlink($file);
}

function mp_photo_url(string $employeeId): ?string
{
    $file = mp_photo_path($employeeId);
    if (!$file) return null;
    $host = $_SERVER['HTTP_HOST'] ?? '127.0.0.1:8100';
    return "http://{$host}/api/photo.php?employee_id=" . urlencode($employeeId) . '&v=' . filemtime($file);
}

/** Validates and stores an uploaded photo. Returns an error message, or null. */
function mp_store_photo(string $employeeId, array $upload): ?string
{
    if (($upload['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return null;
    if ($upload['error'] !== UPLOAD_ERR_OK) return 'The photo could not be uploaded (error ' . $upload['error'] . ').';
    if ($upload['size'] > PHOTO_MAX_BYTES) return 'The photo is larger than 2 MB.';
    $type = @getimagesize($upload['tmp_name'])['mime'] ?? '';
    if (!isset(PHOTO_TYPES[$type])) return 'The photo must be a JPG, PNG or WebP image.';
    if (!is_dir(PHOTOS)) mkdir(PHOTOS, 0775, true);
    mp_delete_photo($employeeId);
    move_uploaded_file($upload['tmp_name'], PHOTOS . "/{$employeeId}." . PHOTO_TYPES[$type]);
    return null;
}

// ---- API record -------------------------------------------------------------

/**
 * The employee as the portal API publishes it. Position, plantilla, division and area are
 * grouped into their own arrays so a consumer can read e.g. $e['position']['title'] or
 * $e['plantilla']['item_name'] directly. plantilla is null for non-plantilla staff.
 * The slot number (#1, #2) is internal and is never shown to employees in LDNA.
 */
function mp_employee_record(array $e, array $itemsById): array
{
    $item = !empty($e['item_id']) ? ($itemsById[$e['item_id']] ?? null) : null;
    return [
        'employee_id' => $e['employee_id'],
        'surname' => $e['surname'],
        'first_name' => $e['first_name'],
        'middle_initial' => $e['middle_initial'],
        'employment_status' => $item ? 'Regular' : 'Contractual',
        'appointment' => $e['appointment'],
        'photo_url' => mp_photo_url($e['employee_id']),
        'position' => [
            'title' => $item ? $item['title'] : ($e['position_title'] ?? null),
            'salary_grade' => $item ? $item['salary_grade'] : ($e['salary_grade'] ?? null),
        ],
        'plantilla' => $item ? [
            'item_id' => $item['id'],
            'item_name' => $item['item_name'],
            'slot' => (int) $e['slot'],
        ] : null,
        'division' => [
            'code' => $item ? $item['division_code'] : ($e['division_code'] ?? null),
            'name' => $item ? $item['division_name'] : ($e['division_name'] ?? null),
        ],
        'area' => [
            'name' => $item ? $item['area_name'] : ($e['area_name'] ?? null),
        ],
    ];
}

function mp_records(): array
{
    $items = mp_items_by_id();
    return array_map(fn ($e) => mp_employee_record($e, $items), mp_load('employees'));
}
