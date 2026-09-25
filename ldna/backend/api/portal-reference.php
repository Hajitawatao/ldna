<?php
// Positions and divisions/areas exactly as the employee portal names them (for spelling checks).
require __DIR__ . '/cors.php';
require __DIR__ . '/../lib/portal.php';
require_method('GET');

try {
    respond(portal_reference());
} catch (PortalException $e) {
    respond(['error' => $e->getMessage(), 'divisions' => [], 'positions' => []], 502);
}
