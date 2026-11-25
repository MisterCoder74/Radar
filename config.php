<?php
// config.php
declare(strict_types=1);

return [
  'app' => [
    'name' => 'Radar Local Business Prospector (MVP - Fase A)',
    'environment' => 'production',
  ],
  'paths' => [
    'data_root' => __DIR__ . DIRECTORY_SEPARATOR . 'data',
    'jobs_dir' => __DIR__ . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'jobs',
    'results_dir' => __DIR__ . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'results',
    'reports_dir' => __DIR__ . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'reports',
    'assets_dir' => __DIR__ . DIRECTORY_SEPARATOR . 'assets',
  ],
  'overpass' => [
    // Endpoint Overpass pubblico. In prod valuta un mirror affidabile.
    'endpoint' => 'https://overpass-api.de/api/interpreter',
    'timeout_seconds' => 45,
    'max_radius_km' => 30,
    'min_radius_km' => 0.5,
    'max_limit' => 50,
    'user_agent' => 'RadarLocalBusinessProspector/1.0 (dev; contact not set)',
  ],
  'nominatim' => [
    // Per geocoding testuale della città: usato solo per MVP.
    'endpoint' => 'https://nominatim.openstreetmap.org/search',
    'timeout_seconds' => 20,
    'user_agent' => 'RadarLocalBusinessProspector/1.0 (dev; contact not set)',
  ],
];
