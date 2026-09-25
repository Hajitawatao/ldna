<?php
/**
 * Level legend: which salary grades each assessment level covers.
 * GET                         -> the four levels
 * PUT {levels: [...]}         -> replace them (must cover SG 1-33 in order, without gaps or overlaps)
 */
require __DIR__ . '/cors.php';
require __DIR__ . '/../lib/domain.php';
require_method('GET', 'PUT');

if ($_SERVER['REQUEST_METHOD'] === 'GET') respond(['levels' => levels()]);

$rows = (array) (read_json_body()['levels'] ?? []);
usort($rows, fn ($a, $b) => (int) $a['level'] <=> (int) $b['level']);
$expect = 1;
$clean = [];
foreach ($rows as $i => $r) {
    $min = (int) ($r['min_sg'] ?? 0);
    $max = (int) ($r['max_sg'] ?? 0);
    if ((int) $r['level'] !== $i + 1) respond(['error' => 'Levels must be 1, 2, 3 and 4.'], 422);
    if ($min !== $expect || $max < $min) {
        respond(['error' => "Level {$r['level']} must start at SG {$expect} and end at or after its start."], 422);
    }
    $clean[] = ['level' => $i + 1, 'label' => trim((string) ($r['label'] ?? '')) ?: "Level " . ($i + 1), 'min_sg' => $min, 'max_sg' => $max];
    $expect = $max + 1;
}
if (count($clean) !== 4 || $expect !== 34) respond(['error' => 'The four levels must cover SG 1 to 33 with no gaps.'], 422);
save('levels', $clean);
respond(['levels' => $clean]);
