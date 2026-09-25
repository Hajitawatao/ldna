<?php
/**
 * No-Node mode: serves the built app AND the API from one PHP server.
 * Use this when Node.js can't run on the machine (blocked native binaries, locked-down policy).
 *
 *   php -S 0.0.0.0:5177 server.php        (from the project root)
 *
 * /api/*.php  -> backend/api/*.php
 * everything else -> frontend/dist (with SPA fallback to index.html)
 */
declare(strict_types=1);

$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$uri = rawurldecode($uri);

// --- API ------------------------------------------------------------------
if (str_starts_with($uri, '/api/')) {
    $file = __DIR__ . '/backend' . $uri;
    $real = realpath($file);
    if ($real && str_starts_with($real, realpath(__DIR__ . '/backend/api')) && is_file($real)) {
        require $real;
        return true;
    }
    http_response_code(404);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Unknown endpoint.']);
    return true;
}

// --- Built front end ------------------------------------------------------
$dist = __DIR__ . '/frontend/dist';
if (!is_dir($dist)) {
    http_response_code(500);
    echo '<h1>The app has not been built yet</h1><p>Run <code>npm run build</code> in the frontend folder on a machine that can run Node, then copy <code>frontend/dist</code> here.</p>';
    return true;
}

$path = realpath($dist . ($uri === '/' ? '/index.html' : $uri));
if ($path && str_starts_with($path, realpath($dist)) && is_file($path)) {
    $types = [
        'html' => 'text/html; charset=utf-8', 'js' => 'text/javascript', 'css' => 'text/css',
        'json' => 'application/json', 'svg' => 'image/svg+xml', 'png' => 'image/png',
        'jpg' => 'image/jpeg', 'ico' => 'image/x-icon', 'woff2' => 'font/woff2', 'map' => 'application/json',
    ];
    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    header('Content-Type: ' . ($types[$ext] ?? 'application/octet-stream'));
    header($ext === 'html' ? 'Cache-Control: no-store' : 'Cache-Control: public, max-age=31536000, immutable');
    readfile($path);
    return true;
}

// Vue Router history mode: any other path returns index.html (never cached, so an update shows at once)
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store');
readfile($dist . '/index.html');
return true;
