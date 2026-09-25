<?php
// The staffing pattern: every plantilla item with its quantity, and who fills each slot.
declare(strict_types=1);

require __DIR__ . '/../lib/data.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

$occ = mp_occupancy(mp_load('employees'));
$out = [];
foreach (mp_load('plantilla_items') as $i) {
    $slots = [];
    for ($n = 1; $n <= $i['quantity']; $n++) {
        $e = $occ[$i['id']][$n] ?? null;
        $slots[] = [
            'slot' => $n,
            'employee_id' => $e['employee_id'] ?? null,
            'occupied_by' => $e ? trim("{$e['surname']}, {$e['first_name']} {$e['middle_initial']}") : null,
        ];
    }
    $filled = count(array_filter($slots, fn ($s) => $s['employee_id']));
    $out[] = [
        'id' => $i['id'], 'item_name' => $i['item_name'], 'title' => $i['title'], 'salary_grade' => $i['salary_grade'],
        'division_code' => $i['division_code'], 'division_name' => $i['division_name'], 'area_name' => $i['area_name'],
        'quantity' => $i['quantity'], 'filled' => $filled, 'vacant' => $i['quantity'] - $filled, 'slots' => $slots,
    ];
}
echo json_encode(['items' => $out], JSON_UNESCAPED_SLASHES);
