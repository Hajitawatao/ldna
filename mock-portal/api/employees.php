<?php
// Mock portal endpoint. Mirrors what the real employee portal is expected to return.
declare(strict_types=1);

require __DIR__ . '/../lib/data.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$employees = mp_records();

if (isset($_GET['employee_id'])) {
    foreach ($employees as $e) {
        if ($e['employee_id'] === $_GET['employee_id']) {
            echo json_encode(['employee' => $e], JSON_UNESCAPED_SLASHES);
            exit;
        }
    }
    http_response_code(404);
    echo json_encode(['error' => 'Employee not found in the portal.']);
    exit;
}

echo json_encode(['employees' => $employees], JSON_UNESCAPED_SLASHES);
