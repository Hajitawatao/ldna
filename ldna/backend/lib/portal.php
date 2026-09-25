<?php
/**
 * The only file that talks to the employee portal.
 *
 * Staging uses the mock portal in /mock-portal. Production points at the real portal
 * by changing backend/config.php - no other file changes.
 *
 * Expected employee shape (the contract): see mock-portal/README.md
 *   employee_id, surname, first_name, middle_initial, employment_status, appointment, photo_url,
 *   position {title, salary_grade}, plantilla {item_id, item_name, slot} | null,
 *   division {code, name}, area {name}
 * Older flat fields are also accepted:
 *   employee_id, surname, first_name, middle_initial, employment_status,
 *   division_code, division_name, area_name, position_title,
 *   plantilla_item_id, plantilla_item_name, plantilla_slot,
 *   salary_grade, appointment, photo_url (optional)
 * (position_code / area_code are optional; used only if a position or area has a portal_code set)
 */
declare(strict_types=1);

require_once __DIR__ . '/store.php';
require_once __DIR__ . '/domain.php';

class PortalException extends RuntimeException {}

function portal_config(): array
{
    static $config = null;
    if ($config === null) {
        $config = require __DIR__ . '/../config.php';
    }
    return $config['portal'];
}

function portal_employees(): array
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }
    $c = portal_config();
    $rows = $c['driver'] === 'mysql' ? portal_fetch_mysql($c) : portal_fetch_http($c);
    return $cache = array_map('portal_normalize', $rows);
}

function portal_employee(string $id): ?array
{
    foreach (portal_employees() as $e) {
        if ($e['employee_id'] === $id) {
            return $e;
        }
    }
    return null;
}

function portal_fetch_http(array $c): array
{
    $headers = ['Accept: application/json'];
    if ($c['auth_header']) {
        $headers[] = 'Authorization: ' . $c['auth_header'];
    }
    $ctx = stream_context_create(['http' => [
        'method' => 'GET',
        'header' => implode("\r\n", $headers),
        'timeout' => $c['timeout'],
        'ignore_errors' => true,
    ]]);
    $url = rtrim($c['base_url'], '/') . '/employees.php';
    $body = @file_get_contents($url, false, $ctx);
    if ($body === false) {
        throw new PortalException("Could not reach the employee portal at {$url}.");
    }
    $data = json_decode($body, true);
    if (!is_array($data) || !isset($data['employees'])) {
        throw new PortalException('The employee portal returned an unexpected response.');
    }
    return $data['employees'];
}

function portal_fetch_mysql(array $c): array
{
    try {
        $pdo = new PDO($c['dsn'], $c['user'], $c['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        return $pdo->query($c['query'])->fetchAll();
    } catch (PDOException $e) {
        throw new PortalException('Could not read the employee portal database: ' . $e->getMessage());
    }
}

function portal_normalize(array $r): array
{
    // Portal format: position / plantilla / division / area grouped as arrays.
    // The older flat keys (position_title, area_name, ...) are still accepted.
    $pos = $r['position'] ?? [];
    $pl = $r['plantilla'] ?? null;
    $div = $r['division'] ?? [];
    $area = $r['area'] ?? [];
    $pos = is_array($pos) ? $pos : ['title' => $pos];
    $area = is_array($area) ? $area : ['name' => $area];

    return [
        'employee_id' => (string) ($r['employee_id'] ?? ''),
        'surname' => trim((string) ($r['surname'] ?? '')),
        'first_name' => trim((string) ($r['first_name'] ?? '')),
        'middle_initial' => trim((string) ($r['middle_initial'] ?? '')),
        'employment_status' => (string) ($r['employment_status'] ?? ''),
        'appointment' => (string) ($r['appointment'] ?? ''),
        'photo_url' => !empty($r['photo_url']) ? (string) $r['photo_url'] : null,
        'position_code' => (string) ($pos['code'] ?? $r['position_code'] ?? ''),
        'position_title' => trim((string) ($pos['title'] ?? $r['position_title'] ?? '')),
        'salary_grade' => ($sg = $pos['salary_grade'] ?? $r['salary_grade'] ?? null) !== null && $sg !== '' ? (int) $sg : null,
        'division_code' => trim((string) ($div['code'] ?? $r['division_code'] ?? '')),
        'division_name' => trim((string) ($div['name'] ?? $r['division_name'] ?? '')),
        'area_code' => (string) ($area['code'] ?? $r['area_code'] ?? ''),
        'area_name' => trim((string) ($area['name'] ?? $r['area_name'] ?? '')),
        'plantilla_item_id' => is_array($pl) ? ($pl['item_id'] ?? null) : ($r['plantilla_item_id'] ?? null),
        'plantilla_item_name' => is_array($pl) ? ($pl['item_name'] ?? null) : ($r['plantilla_item_name'] ?? $r['plantilla_item_no'] ?? null),
        'plantilla_slot' => is_array($pl) ? ($pl['slot'] ?? null) : ($r['plantilla_slot'] ?? null),   // internal; never shown in LDNA
    ];
}

/**
 * Matches a portal employee to a position and area in the competency map.
 * Tries the stored portal_code first, then the plain name.
 */
function portal_match(array $e): array
{
    $position = null;
    foreach (load('positions') as $p) {
        if ((!empty($p['portal_code']) && $p['portal_code'] === $e['position_code'])
            || strcasecmp($p['title'], $e['position_title']) === 0) {
            $position = $p;
            break;
        }
    }
    // Division + area is the real key. The old single label ("HOPSS (HRM)") still matches as a fallback.
    $divisionId = null;
    foreach (load('divisions') as $d) {
        if (strcasecmp($d['code'], $e['division_code']) === 0 || strcasecmp($d['name'], $e['division_name']) === 0) {
            $divisionId = $d['id'];
            break;
        }
    }
    $office = null;
    foreach (load('offices') as $o) {
        if (!empty($o['portal_code']) && $o['portal_code'] === $e['area_code']) { $office = $o; break; }
        if ($divisionId && ($o['division_id'] ?? null) === $divisionId
            && strcasecmp((string) ($o['area'] ?? ''), $e['area_name']) === 0) { $office = $o; break; }
        if (strcasecmp($o['name'], $e['area_name']) === 0) { $office = $o; break; }
    }
    $unmatched = [];
    if (!$position) $unmatched[] = "position \"{$e['position_title']}\"";
    if (!$office) {
        $where = $e['division_code'] ? "{$e['division_code']} - {$e['area_name']}" : $e['area_name'];
        $unmatched[] = "area \"{$where}\"";
    }

    $record = [
        'id' => $e['employee_id'],
        'employee_id' => $e['employee_id'],
        'surname' => $e['surname'],
        'first_name' => $e['first_name'],
        'middle_initial' => $e['middle_initial'],
        'employment_status' => $e['employment_status'],
        'plantilla_item_name' => $e['plantilla_item_name'],
        'salary_grade' => $e['salary_grade'],
        'appointment' => $e['appointment'],
        // Holds a plantilla slot. The official item number is optional, so it isn't the test.
        'is_plantilla' => !empty($e['plantilla_item_id']) || !empty($e['plantilla_item_name']),
        // Photos are fetched through LDNA's own /api/photo.php, never straight from the portal,
        // so browsers on the LAN don't need to reach the portal and portal credentials stay server-side.
        'photo' => $e['photo_url']
            ? '/api/photo.php?employee_id=' . rawurlencode($e['employee_id']) . '&v=' . substr(md5($e['photo_url']), 0, 8)
            : null,
        'full_name' => trim("{$e['surname']}, {$e['first_name']} {$e['middle_initial']}"),
        'position_id' => $position['id'] ?? null,
        'position' => $position['title'] ?? $e['position_title'],
        'office_id' => $office['id'] ?? null,
        'office' => $office['name'] ?? trim("{$e['division_code']} ({$e['area_name']})", ' ()'),
        'division_id' => $office['division_id'] ?? $divisionId,
        'division_code' => $e['division_code'],
        'division_name' => $e['division_name'],
        'area' => $office['area'] ?? $e['area_name'],
        'matched' => !$unmatched,
        'unmatched_message' => $unmatched
            ? 'The portal ' . implode(' and ', $unmatched) . (count($unmatched) > 1 ? ' are' : ' is') . ' not in the LDNA reference lists yet.'
            : null,
        // Exactly what the portal says (the match above is made from these values).
        'portal' => [
            'position' => $e['position_title'],
            'division_code' => $e['division_code'],
            'area' => $e['area_name'],
        ],
    ];
    // Assessment level from the salary grade (level legend): the enrolment gate uses it.
    $record['assessment_level'] = level_for_sg($record['salary_grade']);
    $profile = find_profile($record['position_id'], $record['office_id']);
    $record['profile_id'] = $profile['id'] ?? null;
    return $record;
}


/** @return array<int, array> all portal employees, matched to the map */
function portal_roster(): array
{
    return array_map('portal_match', portal_employees());
}

/**
 * Downloads an employee's photo from the portal. Returns [bytes, content-type] or null.
 * Relative photo URLs are resolved against the portal's base URL.
 */
function portal_photo(string $employeeId): ?array
{
    $e = portal_employee($employeeId);
    if (!$e || !$e['photo_url']) return null;

    $c = portal_config();
    $url = $e['photo_url'];
    if (!preg_match('#^https?://#i', $url)) {
        $parts = parse_url($c['base_url']);
        $origin = $parts['scheme'] . '://' . $parts['host'] . (isset($parts['port']) ? ':' . $parts['port'] : '');
        $url = $origin . '/' . ltrim($url, '/');
    }
    $headers = [];
    if ($c['auth_header']) $headers[] = 'Authorization: ' . $c['auth_header'];
    $ctx = stream_context_create(['http' => [
        'method' => 'GET', 'header' => implode("\r\n", $headers), 'timeout' => $c['timeout'], 'ignore_errors' => true,
    ]]);
    $bytes = @file_get_contents($url, false, $ctx);
    if ($bytes === false || $bytes === '') return null;

    $status = 0; $type = '';
    foreach ($http_response_header ?? [] as $h) {
        if (preg_match('#^HTTP/\S+\s+(\d+)#', $h, $m)) $status = (int) $m[1];
        if (stripos($h, 'Content-Type:') === 0) $type = trim(substr($h, 13));
    }
    if ($status !== 200 || !preg_match('#^image/(jpeg|png|webp|gif)#i', $type)) return null;
    return [$bytes, $type];
}

/**
 * The portal's own lists of positions and divisions/areas, so the Competency Map can be
 * filled in with exactly the values the portal sends. Uses the portal's reference endpoint
 * when it has one; otherwise builds the lists from the employee roster.
 */
function portal_reference(): array
{
    $c = portal_config();
    if ($c['driver'] === 'http') {
        $ctx = stream_context_create(['http' => [
            'method' => 'GET', 'timeout' => $c['timeout'], 'ignore_errors' => true,
            'header' => $c['auth_header'] ? 'Authorization: ' . $c['auth_header'] : '',
        ]]);
        $body = @file_get_contents(rtrim($c['base_url'], '/') . '/reference.php', false, $ctx);
        $data = $body ? json_decode($body, true) : null;
        if (is_array($data) && isset($data['divisions'], $data['positions'])) {
            return ['divisions' => $data['divisions'], 'positions' => array_column($data['positions'], 'title')];
        }
    }
    $divisions = [];
    $positions = [];
    foreach (portal_employees() as $e) {
        if ($e['position_title'] !== '') $positions[$e['position_title']] = true;
        if ($e['division_code'] !== '') {
            $divisions[$e['division_code']]['code'] = $e['division_code'];
            $divisions[$e['division_code']]['name'] = $e['division_name'];
            if ($e['area_name'] !== '') $divisions[$e['division_code']]['areas'][$e['area_name']] = true;
        }
    }
    $out = [];
    foreach ($divisions as $d) {
        $areas = array_keys($d['areas'] ?? []);
        sort($areas);
        $out[] = ['code' => $d['code'], 'name' => $d['name'], 'areas' => $areas];
    }
    $positions = array_keys($positions);
    sort($positions);
    return ['divisions' => $out, 'positions' => $positions];
}
