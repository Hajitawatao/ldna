<?php
/**
 * Employee photo, fetched from the portal on the server side.
 * GET /api/photo.php?employee_id=20041137
 */
declare(strict_types=1);

require __DIR__ . '/../lib/portal.php';

$id = (string) ($_GET['employee_id'] ?? '');
try {
    $photo = preg_match('/^[A-Za-z0-9_-]+$/', $id) ? portal_photo($id) : null;
} catch (PortalException $e) {
    $photo = null;
}

if (!$photo) {
    http_response_code(404);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'No photo.']);
    exit;
}
[$bytes, $type] = $photo;
header('Content-Type: ' . $type);
header('Content-Length: ' . strlen($bytes));
header('Cache-Control: private, max-age=3600');   // URL carries a version, so a new photo gets a new URL
header('X-Content-Type-Options: nosniff');
echo $bytes;
