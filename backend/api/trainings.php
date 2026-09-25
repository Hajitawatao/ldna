<?php
/**
 * Library of trainings. Required: assessment level (1-4), and who may apply (all positions, or listed positions).
 * Optional: areas. The category shown in the Library is derived:
 *   general  = all positions, no areas     area = all positions, some areas
 *   position = listed positions (with or without areas)
 *
 *   level          1..4  assessment level; each level covers a salary-grade band (level legend)
 *   all_positions  true = every position may apply
 *   position_ids   positions that may apply (when all_positions is false)
 *   office_ids     areas it is limited to (empty = every area)
 *   competency_ids competencies the training covers (optional; CETAR sees which gaps it addresses)
 *
 * GET | POST {...} | PUT {id, ...} | DELETE ?id=   (delete refused if anyone is enrolled)
 */
require __DIR__ . '/cors.php';
require __DIR__ . '/../lib/domain.php';
require_method('GET', 'POST', 'PUT', 'DELETE');

$method = $_SERVER['REQUEST_METHOD'];
$trainings = load('trainings');

if ($method === 'GET') {
    simulate_latency();
    $enrolled = array_count_values(array_column(load('enrollments'), 'training_id'));
    respond(['trainings' => array_map(fn ($t) => $t + ['category' => training_category($t), 'enrolled' => $enrolled[$t['id']] ?? 0], $trainings)]);
}

if ($method === 'DELETE') {
    $id = (int) ($_GET['id'] ?? 0);
    $count = count(array_filter(load('enrollments'), fn ($e) => $e['training_id'] === $id));
    if ($count) respond(['error' => "{$count} employee(s) are enrolled in this training, so it can't be deleted."], 409);
    save('trainings', array_values(array_filter($trainings, fn ($t) => $t['id'] !== $id)));
    respond(['deleted' => $id]);
}

$b = read_json_body();
$id = $method === 'PUT' ? (int) ($b['id'] ?? 0) : 0;
if ($method === 'PUT' && !isset(index_by($trainings)[$id])) respond(['error' => 'Training not found.'], 404);

$allPositions = !empty($b['all_positions']);
$level = (int) ($b['level'] ?? 0);
$officeIds = array_values(array_unique(array_map('intval', (array) ($b['office_ids'] ?? []))));
$positionIds = array_values(array_unique(array_map('intval', (array) ($b['position_ids'] ?? []))));
$title = trim((string) ($b['title'] ?? ''));

$errors = [];
if ($title === '') $errors['title'] = 'Enter a title.';
if (!in_array($b['mode'] ?? '', ['formal', 'non-formal', 'informal'], true)) $errors['mode'] = 'Choose a mode.';
if ($level < 1 || $level > 4) $errors['level'] = 'Choose the assessment level (1 to 4).';
if (!$allPositions && !$positionIds) $errors['position_ids'] = 'Choose which positions can apply, or "All positions".';
$standards = [];
foreach ((array) ($b['competency_ids'] ?? []) as $cid) $standards[(string) (int) $cid] = 1;
// Competencies are optional: they show CETAR which of an employee's gaps the training addresses.
if ($errors) respond(['error' => 'Please fix the highlighted fields.', 'fields' => $errors], 422);

$row = [
    'title' => $title,
    'mode' => $b['mode'],
    'type' => trim((string) ($b['type'] ?? '')),
    'provider' => trim((string) ($b['provider'] ?? '')),
    'hours' => isset($b['hours']) && $b['hours'] !== '' ? (int) $b['hours'] : null,
    'description' => trim((string) ($b['description'] ?? '')),
    'level' => $level,
    'all_positions' => $allPositions,
    'position_ids' => $allPositions ? [] : $positionIds,
    'office_ids' => $officeIds,                 // optional: empty = every area
    'competency_ids' => array_map('intval', array_keys($standards)),
    'standards' => $standards,
];

if ($method === 'PUT') {
    foreach ($trainings as $i => $t) if ($t['id'] === $id) $trainings[$i] = ['id' => $id] + $row;
    $saved = ['id' => $id] + $row;
} else {
    $saved = ['id' => next_id($trainings)] + $row;
    $trainings[] = $saved;
}
save('trainings', $trainings);
respond(['training' => $saved + ['category' => training_category($saved)]], $method === 'POST' ? 201 : 200);
