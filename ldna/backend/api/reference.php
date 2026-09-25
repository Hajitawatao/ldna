<?php
// Lookup data every page needs: level legend, divisions, competencies, positions, offices.
require __DIR__ . '/cors.php';
require __DIR__ . '/../lib/domain.php';
require_method('GET');
simulate_latency();

respond([
    'version' => data_version(),
    'levels' => levels(),
    'divisions' => load('divisions'),
    'competencies' => load('competencies'),
    'positions' => load('positions'),
    'offices' => load('offices'),
]);
