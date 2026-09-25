<?php
/**
 * Plantilla positions and their salary grade (the SG decides the assessment level).
 * GET                              -> positions, with their assessment level and how many trainings list them
 * POST {title, salary_grade}       -> add a position
 * PUT  {id, title, salary_grade}   -> edit a position
 * DELETE ?id=                      -> delete (refused if a training or enrolment uses it)
 */
require __DIR__ . '/cors.php';
require __DIR__ . '/../lib/domain.php';
require_method('GET', 'POST', 'PUT', 'DELETE');

$method = $_SERVER['REQUEST_METHOD'];
$positions = load('positions');

$trainingCount = [];
foreach (load('trainings') as $t) foreach ($t['position_ids'] ?? [] as $pid) $trainingCount[$pid] = ($trainingCount[$pid] ?? 0) + 1;

if ($method === 'GET') {
    respond(['positions' => array_map(fn ($p) => $p + [
        'trainings' => $trainingCount[$p['id']] ?? 0,
        'assessment_level' => level_for_sg($p['salary_grade'] ?? null),
    ], $positions)]);
}

if ($method === 'DELETE') {
    $id = (int) ($_GET['id'] ?? 0);
    $enrolled = count(array_filter(load('enrollments'), fn ($e) => $e['position_id'] === $id));
    if (($trainingCount[$id] ?? 0) || $enrolled) {
        respond(['error' => 'This position is used by a training or an enrolment, so it can\'t be deleted.'], 409);
    }
    save('positions', array_values(array_filter($positions, fn ($p) => $p['id'] !== $id)));
    respond(['deleted' => $id]);
}

$b = read_json_body();
$id = $method === 'PUT' ? (int) ($b['id'] ?? 0) : 0;
$title = trim(preg_replace('/\s+/', ' ', (string) ($b['title'] ?? '')));
$sg = $b['salary_grade'] ?? null;

$errors = [];
if ($title === '') $errors['title'] = 'Enter the position exactly as in the employee portal.';
if ($sg === null || $sg === '' || (int) $sg < 1 || (int) $sg > 33) $errors['salary_grade'] = 'Enter the salary grade (1 to 33).';
foreach ($positions as $p) {
    if ($p['id'] !== $id && strcasecmp($p['title'], $title) === 0) $errors['title'] = 'This position already exists.';
}
if ($method === 'PUT' && !isset(index_by($positions)[$id])) respond(['error' => 'Position not found.'], 404);
if ($errors) respond(['error' => 'Please fix the highlighted fields.', 'fields' => $errors], 422);

$row = ['title' => $title, 'salary_grade' => (int) $sg];
if ($method === 'PUT') {
    foreach ($positions as $i => $p) if ($p['id'] === $id) { $positions[$i] = array_merge($p, $row); $saved = $positions[$i]; }
} else {
    $saved = ['id' => next_id($positions), 'portal_code' => null, 'current' => true] + $row;
    $positions[] = $saved;
}
save('positions', $positions);
respond(['position' => $saved, 'positions' => $positions], $method === 'POST' ? 201 : 200);
