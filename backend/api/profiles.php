<?php
/**
 * Position profiles: the competencies (and levels) a plantilla POSITION has in a specific AREA.
 * The same position can have different competencies in different areas
 * (e.g. ADMINISTRATIVE ASSISTANT II in MANAGEMENT INFORMATION SERVICES vs in SECURITY OFFICE).
 * CETAR creates and edits these. When an employee requests a training, their profile (found from their
 * portal position + area) is compared with the training's competency standards: match, pass or lack.
 *
 * GET                     -> all profiles (2023 map profiles have source "map-2023", reference only)
 * POST   {position_id, office_id, standards}      -> add
 * PUT    {id, position_id, office_id, standards}  -> edit
 * DELETE ?id=
 */
require __DIR__ . '/cors.php';
require __DIR__ . '/../lib/store.php';
require_method('GET', 'POST', 'PUT', 'DELETE');

$method = $_SERVER['REQUEST_METHOD'];
$profiles = load('profiles');

if ($method === 'GET') {
    simulate_latency();
    respond(['profiles' => $profiles]);
}

if ($method === 'DELETE') {
    $id = (int) ($_GET['id'] ?? 0);
    save('profiles', array_values(array_filter($profiles, fn ($p) => $p['id'] !== $id)));
    respond(['deleted' => $id]);
}

$b = read_json_body();
$id = $method === 'PUT' ? (int) ($b['id'] ?? 0) : 0;
if ($method === 'PUT' && !isset(index_by($profiles)[$id])) respond(['error' => 'Profile not found.'], 404);

$positionId = (int) ($b['position_id'] ?? 0);
$officeId = (int) ($b['office_id'] ?? 0);
$positions = index_by(load('positions'));
$offices = index_by(load('offices'));
$competencies = index_by(load('competencies'));

$errors = [];
if (!isset($positions[$positionId])) $errors['position'] = 'Choose the plantilla position.';
if (!isset($offices[$officeId]) || ($offices[$officeId]['source'] ?? '') !== 'official-2026') $errors['office'] = 'Choose an area from the official list.';
$standards = [];
foreach ((array) ($b['standards'] ?? []) as $cid => $lvl) {
    if (!isset($competencies[(int) $cid]) || (int) $lvl < 1 || (int) $lvl > 4) {
        $errors['standards'] = 'Every competency needs a level from 1 to 4.';
        continue;
    }
    $standards[(string) (int) $cid] = (int) $lvl;
}
if (!$standards) $errors['standards'] = $errors['standards'] ?? 'Designate at least one competency.';
foreach ($profiles as $p) {
    if ($p['id'] !== $id && $p['position_id'] === $positionId && $p['office_id'] === $officeId) {
        $errors['office'] = 'This position already has a profile in this area. Edit that one instead.';
    }
}
if ($errors) respond(['error' => 'Please fix the highlighted fields.', 'fields' => $errors], 422);

$row = ['position_id' => $positionId, 'office_id' => $officeId, 'standards' => $standards];
if ($method === 'PUT') {
    foreach ($profiles as $i => $p) if ($p['id'] === $id) { $profiles[$i] = array_merge($p, $row, ['source' => 'cetar']); $saved = $profiles[$i]; }
} else {
    $saved = ['id' => next_id($profiles)] + $row + ['source' => 'cetar'];
    $profiles[] = $saved;
}
save('profiles', $profiles);
respond(['profile' => $saved], $method === 'POST' ? 201 : 200);
