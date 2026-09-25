<?php
/**
 * GET  ?employee_id=EMP-0001  -> the competencies to rate for this employee's position + area.
 *                                Standards are withheld until the employee submits (DOH LDNA guide).
 * POST {employee_id, ratings: {competency_id: 1..4}} -> saves and returns results with gaps.
 */
require __DIR__ . '/cors.php';
require __DIR__ . '/../lib/domain.php';
require __DIR__ . '/../lib/portal.php';
require_method('GET', 'POST');

const CYCLE_YEAR = 2026;

$isPost = $_SERVER['REQUEST_METHOD'] === 'POST';
$body = $isPost ? read_json_body() : [];
$employeeId = (string) ($isPost ? ($body['employee_id'] ?? '') : ($_GET['employee_id'] ?? ''));

try {
    $portalEmployee = portal_employee($employeeId);
} catch (PortalException $e) {
    respond(['error' => $e->getMessage()], 502);
}
if (!$portalEmployee) respond(['error' => 'Employee not found in the portal.'], 404);
$emp = portal_match($portalEmployee);

$profile = $emp['matched'] ? find_profile($emp['position_id'], $emp['office_id']) : null;
if (!$profile) {
    respond(['employee' => $emp, 'profile_found' => false,
             'message' => $emp['unmatched_message']
                 ?? 'No competency profile exists for this position and area yet. Ask CETAR to add it in the Competency Map.']);
}

function results(array $profile, array $assessment, array $emp): array
{
    $gaps = compute_gaps($profile['standards'], $assessment['ratings']);
    return [
        'submitted_at' => $assessment['submitted_at'],
        'gaps' => $gaps,
        'recommendations' => recommend_trainings($gaps, $emp, $profile['designated_training_ids'] ?? []),
    ];
}

if (!$isPost) {
    simulate_latency();
    $existing = latest_assessment($employeeId);
    // Only count it if it was made for the employee's current profile.
    if ($existing && $existing['profile_id'] !== $profile['id']) $existing = null;
    respond([
        'employee' => $emp,
        'profile_found' => true,
        'profile_id' => $profile['id'],
        'competency_ids' => array_map('intval', array_keys($profile['standards'])),
        'ratings' => $existing['ratings'] ?? (object) [],
        'results' => $existing ? results($profile, $existing, $emp) : null,
    ]);
}

// POST
$ratings = $body['ratings'] ?? [];
$missing = array_diff(array_keys($profile['standards']), array_keys((array) $ratings));
if ($missing) respond(['error' => 'Please rate every competency before submitting.'], 422);
foreach ($ratings as $cid => $lvl) {
    if (!in_array((int) $lvl, [1, 2, 3, 4], true)) respond(['error' => 'Ratings must be 1 to 4.'], 422);
}

$assessments = load('assessments');
// One assessment per employee per cycle: replace this cycle's if it exists.
$assessments = array_values(array_filter($assessments,
    fn ($a) => !($a['employee_id'] === $employeeId && $a['cycle_year'] === CYCLE_YEAR)));
$a = [
    'id' => next_id($assessments),
    'employee_id' => $employeeId,
    'cycle_year' => CYCLE_YEAR,
    'position_id' => $emp['position_id'],   // snapshot: survives transfers
    'office_id' => $emp['office_id'],
    'profile_id' => $profile['id'],
    'ratings' => array_map('intval', array_intersect_key($ratings, $profile['standards'])),
    'submitted_at' => date(DATE_ATOM),
];
$assessments[] = $a;
save('assessments', $assessments);

respond(['employee' => $emp, 'results' => results($profile, $a, $emp)], 201);
