<?php
/**
 * Trainings CETAR designates for a profile. Everyone assessed under the profile gets them
 * as suggestions, on top of the ones picked automatically from their gaps.
 * POST {profile_id, training_ids: [..]}  -> replaces the profile's designated list
 */
require __DIR__ . '/cors.php';
require __DIR__ . '/../lib/store.php';
require_method('POST');

$b = read_json_body();
$profileId = (int) ($b['profile_id'] ?? 0);
$trainingIds = array_values(array_unique(array_map('intval', (array) ($b['training_ids'] ?? []))));

$known = array_column(load('trainings'), 'id');
$unknown = array_diff($trainingIds, $known);
if ($unknown) respond(['error' => 'Unknown training: ' . implode(', ', $unknown)], 422);

$profiles = load('profiles');
$found = false;
foreach ($profiles as $i => $p) {
    if ($p['id'] === $profileId) {
        $profiles[$i]['designated_training_ids'] = $trainingIds;
        $found = $profiles[$i];
    }
}
if (!$found) respond(['error' => 'Profile not found.'], 404);
save('profiles', $profiles);
respond(['profile' => $found]);
