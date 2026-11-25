<?php
// engine/discovery.php
declare(strict_types=1);

function run_discovery(array $input, array $config): array {
  $provider = $input['provider'] ?? 'osm_overpass';
  $area = $input['area'] ?? '';
  $sector = $input['sector'] ?? '';
  $radiusKm = (float)($input['radiusKm'] ?? 5);
  $limit = (int)($input['limit'] ?? 20);

  if ($provider === 'osm_overpass') {
    require_once __DIR__ . '/providers/osm_overpass.php';
    $result = osm_overpass_discover([
      'area' => $area,
      'sector' => $sector,
      'radiusKm' => $radiusKm,
      'limit' => $limit,
    ], $config);
    return $result;
  }

  throw new RuntimeException('Unsupported provider: ' . $provider);
}
