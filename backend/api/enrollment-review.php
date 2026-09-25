<?php
/**
 * CETAR validation of enrolment requests.
 * GET ?status=pending|approved|rejected|all   -> requests with employee, training and the employee's tally (standard, actual, gap)
 * POST {id, decision: approve|reject, note}   -> decide (a note is required to reject)
 */
require __DIR__ . '/cors.php';
require __DIR__ . '/../lib/portal.php';
require_method('GET', 'POST');

$enrollments = load('enrollments');

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    simulate_latency();
    $status = (string) ($_GET['status'] ?? 'pending');
    try {
        $roster = array_column(portal_roster(), null, 'employee_id');
    } catch (PortalException $e) {
        $roster = [];
    }
    $trainings = index_by(load('trainings'));
    $rows = [];
    foreach ($enrollments as $r) {
        $st = $r['status'] ?? 'approved';
        if ($status !== 'all' && $st !== $status) continue;
        $t = $trainings[$r['training_id']] ?? null;
        $rows[] = $r + [
            'employee' => $roster[$r['employee_id']] ?? ['employee_id' => $r['employee_id'], 'full_name' => $r['employee_id']],
            'training' => $t ? ['id' => $t['id'], 'title' => $t['title'], 'level' => $t['level']] : null,
            'tally' => $r['tally'] ?? [],
        ];
    }
    usort($rows, fn ($a, $b) => strcmp($b['enrolled_at'], $a['enrolled_at']));
    $counts = array_count_values(array_map(fn ($r) => $r['status'] ?? 'approved', $enrollments));
    respond(['requests' => $rows, 'counts' => $counts + ['pending' => 0, 'approved' => 0, 'rejected' => 0]]);
}

$b = read_json_body();
$id = (int) ($b['id'] ?? 0);
$decision = (string) ($b['decision'] ?? '');
$note = trim((string) ($b['note'] ?? ''));
if (!in_array($decision, ['approve', 'reject'], true)) respond(['error' => 'Choose approve or reject.'], 422);
if ($decision === 'reject' && $note === '') respond(['error' => 'Give a reason when rejecting, so the employee knows why.', 'fields' => ['note' => 'Required.']], 422);

$found = null;
foreach ($enrollments as $i => $r) {
    if ($r['id'] === $id) {
        $enrollments[$i]['status'] = $decision === 'approve' ? 'approved' : 'rejected';
        $enrollments[$i]['review_note'] = $note ?: null;
        $enrollments[$i]['reviewed_at'] = date(DATE_ATOM);
        $found = $enrollments[$i];
    }
}
if (!$found) respond(['error' => 'Request not found.'], 404);
save('enrollments', $enrollments);
respond(['enrollment' => $found]);
