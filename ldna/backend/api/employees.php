<?php
/**
 * Employee roster from the portal (staging: the mock portal in /mock-portal).
 *
 * GET              -> full roster (CETAR views)
 * GET ?q=2019-15   -> up to 8 matches by ID number or name, minimal fields (self-assessment lookup)
 */
require __DIR__ . '/cors.php';
require __DIR__ . '/../lib/portal.php';
require_method('GET');

try {
    $roster = portal_roster();
} catch (PortalException $e) {
    respond(['error' => $e->getMessage()], 502);
}

// mbstring isn't enabled on every PHP install, so fall back to the byte-based functions.
function lower(string $s): string
{
    return function_exists('mb_strtolower') ? mb_strtolower($s) : strtolower($s);
}

if (isset($_GET['q'])) {
    $q = lower(trim((string) $_GET['q']));
    if (strlen($q) < 2) respond(['matches' => []]);

    $scored = [];
    foreach ($roster as $e) {
        $id = lower($e['employee_id']);
        $name = lower("{$e['first_name']} {$e['surname']} {$e['full_name']}");
        $score = $id === $q ? 0 : (str_starts_with($id, $q) ? 1 : (str_contains($id, $q) ? 2 : (str_contains($name, $q) ? 3 : null)));
        if ($score !== null) $scored[] = [$score, $e];
    }
    usort($scored, fn ($a, $b) => $a[0] <=> $b[0] ?: strcmp($a[1]['full_name'], $b[1]['full_name']));

    respond(['matches' => array_map(fn ($s) => [
        'employee_id' => $s[1]['employee_id'],
        'full_name' => $s[1]['full_name'],
        'position' => $s[1]['position'],
        'division_code' => $s[1]['division_code'],
        'area' => $s[1]['area'],
    ], array_slice($scored, 0, 8))]);
}

simulate_latency();
respond(['employees' => $roster]);
