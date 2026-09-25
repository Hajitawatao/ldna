<?php
/**
 * CETAR view.
 * GET               -> one row per employee with gap summary
 * GET ?employee_id= -> full gap detail + recommended trainings
 */
require __DIR__ . '/cors.php';
require __DIR__ . '/../lib/domain.php';
require __DIR__ . '/../lib/portal.php';
require_method('GET');
simulate_latency();

$positions = index_by(load('positions'));
$offices = index_by(load('offices'));
$profiles = index_by(load('profiles'));

function row_for(array $emp, array $positions, array $offices, array $profiles): array
{
    $a = $emp['matched'] ? latest_assessment($emp['employee_id']) : null;
    if (!$a) {
        return ['employee' => $emp, 'assessed' => false, 'gaps' => [], 'gap_count' => 0, 'max_gap' => 0, 'submitted_at' => null];
    }
    $profile = $profiles[$a['profile_id']];
    $gaps = compute_gaps($profile['standards'], $a['ratings']);
    $positive = array_values(array_filter($gaps, fn ($g) => $g['gap'] > 0));
    return [
        'employee' => $emp,
        'assessed' => true,
        'submitted_at' => $a['submitted_at'],
        'assessed_as' => [
            'position' => $positions[$a['position_id']]['title'] ?? null,
            'office' => $offices[$a['office_id']]['name'] ?? null,
        ],
        'profile_id' => $profile['id'],
        'gaps' => $gaps,
        'gap_count' => count($positive),
        'max_gap' => $positive ? max(array_column($positive, 'gap')) : 0,
    ];
}

try {
    $roster = portal_roster();
} catch (PortalException $e) {
    respond(['error' => $e->getMessage()], 502);
}

if (isset($_GET['employee_id'])) {
    $emp = null;
    foreach ($roster as $r) if ($r['employee_id'] === $_GET['employee_id']) $emp = $r;
    if (!$emp) respond(['error' => 'Employee not found in the portal.'], 404);
    $row = row_for($emp, $positions, $offices, $profiles);
    $designated = $row['assessed'] ? ($profiles[$row['profile_id']]['designated_training_ids'] ?? []) : [];
    $row['recommendations'] = $row['assessed'] ? recommend_trainings($row['gaps'], $emp, $designated) : [];
    respond($row);
}

respond(['rows' => array_map(fn ($e) => row_for($e, $positions, $offices, $profiles), $roster)]);
