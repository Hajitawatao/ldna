<?php
/**
 * Business rules.
 *
 * Assessment levels (level legend, stored in data/levels.json, editable via api/levels.php):
 *   each level covers a range of salary grades.
 *   An employee's level comes from their salary grade (portal).
 *   Every training has a level (1..4).
 *
 * Enrolment gate, checked in this order:
 *   1. Level    - the employee's level must be at least the training's level.
 *   2. Position - if the training lists positions, the employee's position must be one of them.
 *   3. Area     - if the training lists areas, the employee's area must be one of them.
 * After the gate, the employee rates themselves on the competencies of their position profile
 * (position + area, set by CETAR) and CETAR validates the request.
 */
declare(strict_types=1);

require_once __DIR__ . '/store.php';

function levels(): array
{
    $levels = load('levels');
    usort($levels, fn ($a, $b) => (int) $a['level'] <=> (int) $b['level']);
    return $levels;
}

/** Level (1..4) for a salary grade, or null when the grade is unknown. */
function level_for_sg(?int $sg): ?int
{
    if ($sg === null) return null;
    foreach (levels() as $l) {
        if ($sg >= $l['min_sg'] && $sg <= $l['max_sg']) return (int) $l['level'];
    }
    return $sg > max(array_column(levels(), 'max_sg')) ? (int) max(array_column(levels(), 'level')) : null;
}

function level_text(int $level): string
{
    foreach (levels() as $l) {
        if ((int) $l['level'] === $level) return "Level {$level} (SG {$l['min_sg']}-{$l['max_sg']})";
    }
    return "Level {$level}";
}

/** Display label derived from the settings: general / area / position. */
function training_category(array $t): string
{
    if (empty($t['all_positions']) && !empty($t['position_ids'])) return 'position';
    if (!empty($t['office_ids'])) return 'area';
    return 'general';
}

/** Reasons an employee can't enrol in a training (empty array = allowed). */
function training_gate(array $t, array $emp): array
{
    $reasons = [];
    $need = (int) ($t['level'] ?? 1);
    $mine = $emp['assessment_level'] ?? null;

    // 1. level (employees with no salary grade in the portal can take Level 1 only)
    if ($need > ($mine ?? 1)) {
        $reasons[] = 'Requires ' . level_text($need) . ' or higher; '
            . ($mine ? 'you are ' . level_text($mine) . " with SG {$emp['salary_grade']}." : 'the employee portal has no salary grade for you.');
    }
    // 2. position
    if (empty($t['all_positions']) && !empty($t['position_ids'])
        && !in_array((int) ($emp['position_id'] ?? 0), array_map('intval', $t['position_ids']), true)) {
        $reasons[] = 'Only for: ' . implode(', ', position_labels($t['position_ids'])) . '.';
    }
    // 3. area
    if (!empty($t['office_ids']) && !in_array((int) ($emp['office_id'] ?? 0), array_map('intval', $t['office_ids']), true)) {
        $reasons[] = 'Only for employees in ' . implode('; ', area_labels($t['office_ids'])) . '.';
    }
    return $reasons;
}

/** "FINANCE SERVICE: BILLING, CLAIMS" - division on top, its areas after (no division codes). */
function area_labels(array $officeIds): array
{
    $offices = index_by(load('offices'));
    $divisions = index_by(load('divisions'));
    $groups = [];
    foreach ($officeIds as $id) {
        $o = $offices[(int) $id] ?? null;
        if (!$o) continue;
        $groups[$divisions[$o['division_id'] ?? 0]['name'] ?? 'OTHER'][] = $o['area'];
    }
    $out = [];
    foreach ($groups as $division => $areas) $out[] = $division . ': ' . implode(', ', $areas);
    return $out;
}

function position_labels(array $positionIds): array
{
    $positions = index_by(load('positions'));
    return array_values(array_map(fn ($id) => $positions[(int) $id]['title'] ?? "position {$id}", $positionIds));
}

function find_profile(?int $positionId, ?int $officeId): ?array
{
    if (!$positionId || !$officeId) return null;
    foreach (load('profiles') as $p) {
        if ($p['position_id'] === $positionId && $p['office_id'] === $officeId) return $p;
    }
    return null;
}

/**
 * DOH LDNA tally (LDNA Administration Guide / Annual Plan Guide):
 *   Gap = Standard competency level - Actual competency level
 *   standard = the level CETAR set for the employee's position in their area
 *   actual   = the employee's self-rating (Competency Dictionary levels 1-4)
 *   gap > 0: below standard (needs an intervention), 0: met, < 0: above standard
 */
function profile_tally(array $profile, array $ratings): array
{
    $rows = [];
    foreach ($profile['standards'] as $cid => $std) {
        $actual = isset($ratings[$cid]) ? (int) $ratings[$cid] : (isset($ratings[(string) $cid]) ? (int) $ratings[(string) $cid] : null);
        $rows[] = ['competency_id' => (int) $cid, 'standard' => (int) $std, 'actual' => $actual,
                   'gap' => $actual === null ? null : (int) $std - $actual];
    }
    return $rows;
}
