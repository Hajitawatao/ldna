<?php
/**
 * Profile assignments: CETAR assesses an employee under a profile other than the one their
 * plantilla position and area point to (e.g. a Nursing Attendant whose item is in HOPSS but who
 * works in Nursing Service).
 *
 * GET                                        -> all assignments
 * POST {employee_id, profile_id, reason}     -> create or replace
 * DELETE ?employee_id=                       -> remove (the employee goes back to the portal match)
 */
require __DIR__ . '/cors.php';
require __DIR__ . '/../lib/portal.php';
require_method('GET', 'POST', 'DELETE');

$method = $_SERVER['REQUEST_METHOD'];
$rows = load('assignments');

if ($method === 'GET') {
    respond(['assignments' => $rows]);
}

if ($method === 'DELETE') {
    $id = (string) ($_GET['employee_id'] ?? '');
    save('assignments', array_values(array_filter($rows, fn ($a) => $a['employee_id'] !== $id)));
    respond(['removed' => $id]);
}

$b = read_json_body();
$employeeId = (string) ($b['employee_id'] ?? '');
$profileId = (int) ($b['profile_id'] ?? 0);
$reason = trim((string) ($b['reason'] ?? ''));

try {
    if (!portal_employee($employeeId)) respond(['error' => 'Employee not found in the portal.'], 404);
} catch (PortalException $e) {
    respond(['error' => $e->getMessage()], 502);
}
if (!isset(index_by(load('profiles'))[$profileId])) {
    respond(['error' => 'Choose a competency profile.', 'fields' => ['profile_id' => 'Choose a profile.']], 422);
}
if ($reason === '') {
    respond(['error' => 'Give a reason, e.g. "Works in Nursing Service; item is under HOPSS".', 'fields' => ['reason' => 'Required.']], 422);
}

$rows = array_values(array_filter($rows, fn ($a) => $a['employee_id'] !== $employeeId));
$row = ['employee_id' => $employeeId, 'profile_id' => $profileId, 'reason' => $reason, 'assigned_at' => date(DATE_ATOM)];
$rows[] = $row;
save('assignments', $rows);
respond(['assignment' => $row], 201);
