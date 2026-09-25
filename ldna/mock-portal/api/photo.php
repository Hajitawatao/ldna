<?php
// Serves an employee's photo: GET /api/photo.php?employee_id=20041137
declare(strict_types=1);

require __DIR__ . '/../lib/data.php';

$file = mp_photo_path((string) ($_GET['employee_id'] ?? ''));
if (!$file) {
    http_response_code(404);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'No photo for this employee.']);
    exit;
}
$types = array_flip(PHOTO_TYPES);
header('Content-Type: ' . $types[pathinfo($file, PATHINFO_EXTENSION)]);
header('Content-Length: ' . filesize($file));
header('Cache-Control: private, max-age=300');
readfile($file);
