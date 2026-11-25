<?php
// engine/providers/osm_overpass.php
declare(strict_types=1);

function osm_overpass_discover(array $input, array $config): array {
  $area = trim((string)($input['area'] ?? ''));
  $sector = trim((string)($input['sector'] ?? ''));
  $radiusKm = (float)($input['radiusKm'] ?? 5);
  $limit = (int)($input['limit'] ?? 20);

  $os = [
    'user_agent' => $config['overpass']['user_agent'],
    'endpoint' => $config['overpass']['endpoint'],
    'timeout' => (int)$config['overpass']['timeout_seconds'],
    'min_radius_km' => (float)$config['overpass']['min_radius_km'],
    'max_radius_km' => (float)$config['overpass']['max_radius_km'],
    'max_limit' => (int)$config['overpass']['max_limit'],
  ];

  if ($radiusKm < $os['min_radius_km'] || $radiusKm > $os['max_radius_km']) {
    throw new RuntimeException('radiusKm out of allowed range.');
  }
  if ($limit < 1 || $limit > $os['max_limit']) {
    throw new RuntimeException('limit out of allowed range.');
  }

  // Geocoding testuale (Nominatim) per ottenere lat/lon.
  $coords = nominatim_geocode_center($area, $config);
  $lat = (float)$coords['lat'];
  $lon = (float)$coords['lon'];

  // Mappa settore -> tag OSM (MVP semplice, migliorabile dopo).
  $tag = map_sector_to_osm_tags($sector);

  $meters = (int)round($radiusKm * 1000);
  $overpass_query = build_overpass_query($lat, $lon, $meters, $tag, $limit);

  $raw = overpass_post($overpass_query, $os);

  $json = json_decode($raw ?? '', true);
  if (!is_array($json) || !isset($json['elements']) || !is_array($json['elements'])) {
    throw new RuntimeException('Invalid Overpass response.');
  }

  $found = count($json['elements']);

  // Normalize + dedup
  $items = [];
  $seen = []; // key => item
  $deduped = 0;

  foreach ($json['elements'] as $el) {
    $type = $el['type'] ?? '';
    $id = $el['id'] ?? null;
    if (!$id || ($type !== 'node' && $type !== 'way' && $type !== 'relation')) continue;

    $tags = $el['tags'] ?? [];
    $name = $tags['name'] ?? null;

    $provider_id = ($type . ':' . $id);

    $address = null;
    if (isset($tags['addr:full'])) {
      $address = (string)$tags['addr:full'];
    } else {
      $parts = [];
      foreach (['addr:housenumber','addr:street','addr:city'] as $k) {
        if (!empty($tags[$k])) $parts[] = (string)$tags[$k];
      }
      $address = $parts ? implode(', ', $parts) : null;
    }

    $source_url = 'https://www.openstreetmap.org/' . $type . '/' . urlencode((string)$id);
    $source_label = 'OpenStreetMap';

    // Fallback di categoria
    $category = $tags['shop'] ?? $tags['amenity'] ?? $tags['craft'] ?? $sector;

    // Dedup: provider_id è il migliore (MVP)
    $key = $provider_id;
    if (isset($seen[$key])) continue;

    // Se non ha name, lo lasciamo ma con un placeholder coerente
    $finalName = $name ?: ($tags['brand'] ?? null);
    if (!$finalName) $finalName = 'Attività OSM';

    $items[] = [
      'id' => 'lead_' . bin2hex(random_bytes(6)), // solo per UI; lead definitivo in fasi successive
      'job_id' => null,
      'provider' => 'osm_overpass',
      'provider_id' => $provider_id,
      'name' => $finalName,
      'category' => (string)$category,
      'city' => $area,
      'address' => $address,
      'phone' => null,
      'email' => null,
      'website_status' => 'uncertain',
      'website_url' => null,
      'social_profiles' => [],
      'description' => null,
      'opportunity_summary' => null,
      'lead_score' => null,
      'confidence' => null,
      'sources' => [[
        'type' => 'osm_poi',
        'id' => $provider_id,
        'url' => $source_url,
      ]],
      'review_status' => 'unreviewed',
      'created_at' => gmdate('c'),
      'checked_at' => null,
      'source_url' => $source_url,
      'source_label' => $source_label,
      'debug' => [
        'matched_tag' => $tag,
      ],
    ];

    $seen[$key] = true;
    $deduped++;
    if ($deduped >= $limit) break;
  }

  return [
    'items' => $items,
    'stats' => [
      'found' => $found,
      'dedup' => $deduped,
    ],
    'meta' => [
      'area_query' => $area,
      'coords' => $coords,
      'sector' => $sector,
      'overpass_limit' => $limit,
      'overpass_meters' => $meters,
      'overpass_query' => $overpass_query, // utile per debug; puoi rimuoverla in prod
    ],
  ];
}

function nominatim_geocode_center(string $area, array $config): array {
  $endpoint = $config['nominatim']['endpoint'];
  $timeout = (int)$config['nominatim']['timeout_seconds'];
  $ua = (string)$config['nominatim']['user_agent'];

  $q = http_build_query([
    'q' => $area,
    'format' => 'json',
    'limit' => 1,
  ]);

  $url = $endpoint . '?' . $q;

  $ch = curl_init($url);
  curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => $timeout,
    CURLOPT_CONNECTTIMEOUT => 10,
    CURLOPT_USERAGENT => $ua,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_MAXREDIRS => 4,
  ]);

  $res = curl_exec($ch);
  $err = curl_error($ch);
  $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
  curl_close($ch);

  if ($res === false || $code < 200 || $code >= 300) {
    throw new RuntimeException('Geocoding failed: ' . ($err ?: ('HTTP ' . $code)));
  }

  $json = json_decode($res, true);
  if (!is_array($json) || empty($json[0])) {
    throw new RuntimeException('Geocoding returned empty response.');
  }

  $lat = $json[0]['lat'] ?? null;
  $lon = $json[0]['lon'] ?? null;
  if ($lat === null || $lon === null) throw new RuntimeException('Geocoding missing coordinates.');

  return ['lat' => (float)$lat, 'lon' => (float)$lon, 'display_name' => (string)($json[0]['display_name'] ?? $area)];
}

function map_sector_to_osm_tags(string $sector): array {
  $s = mb_strtolower($sector);

  // MVP heuristics: puoi espanderle dopo.
  if (str_contains($s, 'parrucch') || str_contains($s, 'barb') || str_contains($s, 'salone')) {
    return ['kind' => 'shop_or_amenity', 'shop' => 'hairdresser', 'amenity' => 'studio'];
  }
  if (str_contains($s, 'ristor') || str_contains($s, 'pizzeria') || str_contains($s, 'bar ')) {
    return ['kind' => 'amenity', 'amenity' => 'restaurant'];
  }
  if (str_contains($s, 'officin') || str_contains($s, 'meccan') || str_contains($s, 'aut') ) {
    return ['kind' => 'shop_or_craft', 'shop' => 'car_repair', 'craft' => 'carpentry'];
  }

  // fallback: cerca shop generico per stringa nel campo name (Overpass non fa ML; usiamo fallback by tag categories)
  return ['kind' => 'fallback', 'shop' => null, 'amenity' => null, 'craft' => null, 'sector' => $sector];
}

function build_overpass_query(float $lat, float $lon, int $meters, array $tag, int $limit): string {
  // Query semplice per nodi/way con tag rilevanti.
  // Nota: Overpass potrebbe essere lento; in prod serve caching e query più mirate.
  $center = $lat . ',' . $lon;

  $filter = '';

  if (($tag['kind'] ?? '') === 'shop_or_amenity') {
    $shop = $tag['shop'] ?? null;
    $amenity = $tag['amenity'] ?? null;
    if ($shop) {
      $filter .= 'nwr["shop"="' . addslashes((string)$shop) . '"](around:' . $meters . ',' . $center . ');';
    }
    if ($amenity) {
      $filter .= 'nwr["amenity"="' . addslashes((string)$amenity) . '"](around:' . $meters . ',' . $center . ');';
    }
  } elseif (($tag['kind'] ?? '') === 'amenity') {
    $amenity = $tag['amenity'] ?? null;
    if ($amenity) {
      $filter = 'nwr["amenity"="' . addslashes((string)$amenity) . '"](around:' . $meters . ',' . $center . ');';
    }
  } elseif (($tag['kind'] ?? '') === 'shop_or_craft') {
    $shop = $tag['shop'] ?? null;
    $craft = $tag['craft'] ?? null;
    if ($shop) {
      $filter .= 'nwr["shop"="' . addslashes((string)$shop) . '"](around:' . $meters . ',' . $center . ');';
    }
    if ($craft) {
      $filter .= 'nwr["craft"="' . addslashes((string)$craft) . '"](around:' . $meters . ',' . $center . ');';
    }
  }

  if ($filter === '') {
    // fallback: tutto shopping/amenity/craft intorno e poi limiti + UI mostra comunque categoria.
    $filter = 'nwr["shop"](around:' . $meters . ',' . $center . ');
               out center;';
    // fallback leggermente diverso: useremo query completa qui sotto
    $filter = 'nwr["shop"](around:' . $meters . ',' . $center . ');
               nwr["amenity"](around:' . $meters . ',' . $center . ');
               nwr["craft"](around:' . $meters . ',' . $center . ');';
  }

  $query = <<<OV
[out:json][timeout:45];
(
  $filter
);
out center tags limit $limit;
OV;

  return $query;
}

function overpass_post(string $query, array $os): string {
  $endpoint = $os['endpoint'];
  $timeout = (int)$os['timeout'];
  $ua = (string)$os['user_agent'];

  $ch = curl_init($endpoint);
  curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => $timeout,
    CURLOPT_CONNECTTIMEOUT => 10,
    CURLOPT_USERAGENT => $ua,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_MAXREDIRS => 3,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => http_build_query(['data' => $query]),
    CURLOPT_HTTPHEADER => [
      'Content-Type: application/x-www-form-urlencoded; charset=UTF-8',
      'Accept: application/json',
    ],
  ]);

  $res = curl_exec($ch);
  $err = curl_error($ch);
  $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
  curl_close($ch);

  if ($res === false || $code < 200 || $code >= 300) {
    throw new RuntimeException('Overpass request failed: ' . ($err ?: ('HTTP ' . $code)));
  }

  return (string)$res;
}
