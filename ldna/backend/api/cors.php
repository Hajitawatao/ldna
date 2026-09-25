<?php
/**
 * Shared bootstrap for every endpoint:
 *   - JSON content type
 *   - CORS headers (only needed outside the Vite proxy, e.g. production on another origin)
 *   - OPTIONS preflight short-circuit
 *   - respond() helper
 */
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

// Set CORS_ORIGIN in your server env for production (e.g. https://ldna.example.gov.ph).
$origin = getenv('CORS_ORIGIN') ?: '*';
header("Access-Control-Allow-Origin: {$origin}");
header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('Access-Control-Max-Age: 86400');
if ($origin !== '*') {
    header('Vary: Origin');
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

function respond(mixed $data, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function require_method(string ...$methods): void
{
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    if (!in_array($method, $methods, true)) {
        header('Allow: ' . implode(', ', $methods));
        respond(['error' => "Method {$method} not allowed"], 405);
    }
}

// Simulated latency so the loading skeletons are visible in dev. Remove when real data lands.
function simulate_latency(int $ms = 250): void
{
    if (getenv('APP_ENV') !== 'production') {
        usleep($ms * 1000);
    }
}
