<?php
/**
 * Lookup lists for consumers of the portal (dropdowns, validation, matching):
 *   divisions -> each with its areas (official R1MC list)
 *   positions -> each current plantilla position with its salary grade (official R1MC list)
 *   plantilla -> each item with its slots and occupants (same as plantilla.php)
 */
declare(strict_types=1);

require __DIR__ . '/../lib/data.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

$records = mp_records();
$items = mp_load('plantilla_items');

// The official lists (every division's areas, every current plantilla position with its SG)
$divisions = REFERENCE['divisions'];
$positions = REFERENCE['positions'];

echo json_encode([
    'divisions' => $divisions,
    'positions' => $positions,
    'plantilla' => array_map(fn ($i) => [
        'item_id' => $i['id'], 'item_name' => $i['item_name'], 'position' => $i['title'],
        'salary_grade' => $i['salary_grade'], 'division_code' => $i['division_code'],
        'area' => $i['area_name'], 'quantity' => $i['quantity'],
    ], $items),
], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
