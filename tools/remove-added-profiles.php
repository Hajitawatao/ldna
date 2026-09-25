<?php
/**
 * Removes every Competency Map profile added through the app, keeping only the original
 * 2023 map (source = "map-2023"). Positions and areas that were created for those added
 * profiles, and are no longer used, are removed too. Profiles with assessments are kept
 * and listed, so no assessment loses its profile.
 *
 * Run from the project root:   php tools/remove-added-profiles.php
 */
declare(strict_types=1);

require __DIR__ . '/../backend/lib/store.php';

$profiles = load('profiles');
$assessed = array_count_values(array_column(load('assessments'), 'profile_id'));

$keep = [];
$removed = [];
$blocked = [];
foreach ($profiles as $p) {
    if (($p['source'] ?? '') === 'map-2023') { $keep[] = $p; continue; }
    if (!empty($assessed[$p['id']])) { $keep[] = $p; $blocked[] = $p; continue; }
    $removed[] = $p;
}
save('profiles', $keep);

// Positions / areas not used by any remaining profile and not part of the original seed
$seedPositions = array_column(json_decode(file_get_contents(SEED_DIR . '/positions.json'), true), 'id');
$seedOffices = array_column(json_decode(file_get_contents(SEED_DIR . '/offices.json'), true), 'id');
$usedPositions = array_column($keep, 'position_id');
$usedOffices = array_column($keep, 'office_id');
save('positions', array_values(array_filter(load('positions'),
    fn ($p) => in_array($p['id'], $seedPositions, true) || in_array($p['id'], $usedPositions, true))));
save('offices', array_values(array_filter(load('offices'),
    fn ($o) => in_array($o['id'], $seedOffices, true) || in_array($o['id'], $usedOffices, true))));

$positions = index_by(load('positions'));
$offices = index_by(load('offices'));
$label = fn ($p) => ($positions[$p['position_id']]['title'] ?? '?') . ' / ' . ($offices[$p['office_id']]['name'] ?? '?');

echo 'Removed ' . count($removed) . " added profile(s).\n";
foreach ($blocked as $p) echo '  Kept (has assessments): #' . $p['id'] . ' ' . $label($p) . "\n";
echo 'Profiles now: ' . count($keep) . "\n";
