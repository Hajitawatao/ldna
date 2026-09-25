<?php
/**
 * Training enrolment ("shopping"). Every enrolment is a row in the training_session pivot:
 *   employee_id + position_id + office_id (snapshot) + training_id + cycle_year
 *
 * The gate runs BEFORE anything is saved (lib/domain.php training_gate):
 *   1. training level <= the employee's level (the band their salary grade falls in)
 *   2. if the training lists positions, the employee's position must be one of them
 *   3. if the training lists areas, the employee's area must be one of them
 *
 * GET                      -> all enrolments (CETAR)
 * GET ?employee_id=        -> the employee, their position level, and the whole catalogue with
 *                             "eligible" + reasons for each training, and what they're enrolled in
 * POST {employee_id, training_id, ratings} -> request enrolment. After the gate (level, position, area),
 *      the employee rates themselves (actual level) on every competency of their position profile
 *      (position + area, set by CETAR). The tally (standard, actual, gap = standard - actual) is saved
 *      with the request as PENDING; CETAR validates it.
 * DELETE ?employee_id=&training_id=        -> cancel
 */
require __DIR__ . '/cors.php';
require __DIR__ . '/../lib/portal.php';
require_method('GET', 'POST', 'DELETE');

const ENROLMENT_YEAR = 2026;

$method = $_SERVER['REQUEST_METHOD'];
$enrollments = load('enrollments');

/** The employee's latest self-rating per competency this cycle (pre-fills the next request). */
function latest_ratings(array $mine): array
{
    usort($mine, fn ($a, $b) => strcmp($a['enrolled_at'], $b['enrolled_at']));
    $out = [];
    foreach ($mine as $r) foreach ((array) ($r['ratings'] ?? []) as $cid => $lvl) $out[(string) $cid] = (int) $lvl;
    return $out;
}

function employee_or_fail(string $id): array
{
    try {
        $e = portal_employee($id);
    } catch (PortalException $ex) {
        respond(['error' => $ex->getMessage()], 502);
    }
    if (!$e) respond(['error' => 'Employee not found in the portal.'], 404);
    return portal_match($e);
}

if ($method === 'GET' && !isset($_GET['employee_id'])) {
    respond(['enrollments' => $enrollments]);
}

if ($method === 'GET') {
    simulate_latency();
    $emp = employee_or_fail((string) $_GET['employee_id']);
    $mine = array_values(array_filter($enrollments, fn ($r) => $r['employee_id'] === $emp['employee_id'] && $r['cycle_year'] === ENROLMENT_YEAR));
    // A rejected request doesn't block applying again
    $active = array_filter($mine, fn ($r) => ($r['status'] ?? 'approved') !== 'rejected');
    $statusOf = [];
    foreach ($mine as $r) $statusOf[$r['training_id']] = $r['status'] ?? 'approved';
    $mineIds = array_column($active, 'training_id');

    $catalog = array_map(function ($t) use ($emp, $mineIds, $statusOf) {
        $reasons = training_gate($t, $emp);
        return $t + [
            'category' => training_category($t),
            'eligible' => !$reasons,
            'reasons' => $reasons,
            'enrolled' => in_array($t['id'], $mineIds, true),
            'status' => $statusOf[$t['id']] ?? null,          // pending | approved | rejected
        ];
    }, load('trainings'));

    respond(['employee' => $emp, 'catalog' => $catalog, 'enrollments' => $mine, 'cycle_year' => ENROLMENT_YEAR, 'levels' => levels(),
             'profile' => find_profile($emp['position_id'], $emp['office_id']),
             'latest_ratings' => (object) latest_ratings($mine)]);
}

if ($method === 'DELETE') {
    $eid = (string) ($_GET['employee_id'] ?? '');
    $tid = (int) ($_GET['training_id'] ?? 0);
    save('enrollments', array_values(array_filter($enrollments,
        fn ($r) => !($r['employee_id'] === $eid && $r['training_id'] === $tid && $r['cycle_year'] === ENROLMENT_YEAR
                     && ($r['status'] ?? 'approved') !== 'rejected'))));
    respond(['cancelled' => ['employee_id' => $eid, 'training_id' => $tid]]);
}

// POST: validate first, insert only if every check passes
$b = read_json_body();
$emp = employee_or_fail((string) ($b['employee_id'] ?? ''));
$training = index_by(load('trainings'))[(int) ($b['training_id'] ?? 0)] ?? null;
if (!$training) respond(['error' => 'Training not found.'], 404);

$reasons = training_gate($training, $emp);
if ($reasons) respond(['error' => 'Not eligible for this training.', 'reasons' => $reasons], 422);

foreach ($enrollments as $r) {
    if ($r['employee_id'] === $emp['employee_id'] && $r['training_id'] === $training['id'] && $r['cycle_year'] === ENROLMENT_YEAR
        && ($r['status'] ?? 'approved') !== 'rejected') {
        respond(['error' => 'You already have a request for this training.'], 409);
    }
}

// Self-rating on every competency of the employee's position profile (position + area)
// No position profile yet (CETAR hasn't set this position + area): the request can still be sent;
// there is simply no self-assessment to attach, and CETAR sees that when validating.
$profile = find_profile($emp['position_id'], $emp['office_id']);
$ratings = [];
$given = (array) ($b['ratings'] ?? []);
foreach (array_keys($profile['standards'] ?? []) as $cid) {
    $lvl = (int) ($given[$cid] ?? $given[(string) $cid] ?? 0);
    if ($lvl < 1 || $lvl > 4) respond(['error' => 'Rate yourself on every competency before submitting.', 'missing' => (int) $cid], 422);
    $ratings[(string) $cid] = $lvl;
}
$tally = $profile ? profile_tally($profile, $ratings) : [];

$row = [
    'id' => next_id($enrollments),
    'employee_id' => $emp['employee_id'],
    'position_id' => $emp['position_id'],     // snapshot at enrolment
    'office_id' => $emp['office_id'],
    'salary_grade' => $emp['salary_grade'],
    'assessment_level' => $emp['assessment_level'],
    'training_id' => $training['id'],
    'profile_id' => $emp['profile_id'],
    'ratings' => (object) $ratings,          // actual level (self-rating) per competency
    'tally' => $tally,                       // standard, actual, gap per competency (standard from the profile)
    'no_profile' => $profile === null,       // position + area not set up in LDNA yet
    'status' => 'pending',                   // CETAR approves or rejects
    'review_note' => null,
    'reviewed_at' => null,
    'cycle_year' => ENROLMENT_YEAR,
    'enrolled_at' => date(DATE_ATOM),
];
$enrollments[] = $row;
save('enrollments', $enrollments);
respond(['enrollment' => $row, 'tally' => $tally], 201);
