<?php
// Dashboard: training enrolments for the current cycle.
require __DIR__ . '/cors.php';
require __DIR__ . '/../lib/portal.php';
require_method('GET');
simulate_latency();

try {
    $roster = portal_roster();
} catch (PortalException $e) {
    respond(['error' => $e->getMessage()], 502);
}
$trainings = index_by(load('trainings'));
$offices = index_by(load('offices'));
$divisions = index_by(load('divisions'));
$enrollments = load('enrollments');

$byTraining = array_count_values(array_column($enrollments, 'training_id'));
arsort($byTraining);
$byArea = [];
foreach ($enrollments as $e) {
    $o = $offices[$e['office_id'] ?? 0] ?? null;
    $division = $o ? ($divisions[$o['division_id'] ?? 0]['name'] ?? 'OTHER') : 'NOT IN THE AREA LIST';
    $key = $division . '|' . ($o['area'] ?? '');
    $byArea[$key] ??= ['division' => $division, 'area' => $o['area'] ?? '—', 'enrollments' => 0];
    $byArea[$key]['enrollments']++;
}
usort($byArea, fn ($a, $b) => [$a['division'], -$a['enrollments']] <=> [$b['division'], -$b['enrollments']]);
$levels = array_count_values(array_filter(array_map(fn ($r) => $r['assessment_level'], $roster)));
ksort($levels);

respond([
    'updated_at' => date(DATE_ATOM),
    'totals' => [
        'employees' => count($roster),
        'enrolled_employees' => count(array_unique(array_column($enrollments, 'employee_id'))),
        'enrollments' => count($enrollments),
        'pending' => count(array_filter($enrollments, fn ($e) => ($e['status'] ?? 'approved') === 'pending')),
        'approved' => count(array_filter($enrollments, fn ($e) => ($e['status'] ?? 'approved') === 'approved')),
        'trainings' => count($trainings),
    ],
    'top_trainings' => array_map(fn ($id, $n) => ['title' => $trainings[$id]['title'] ?? "Training {$id}", 'level' => $trainings[$id]['level'] ?? null, 'enrollments' => $n],
        array_slice(array_keys($byTraining), 0, 8), array_slice(array_values($byTraining), 0, 8)),
    'by_area' => array_values($byArea),
    'employees_by_level' => $levels,
]);
