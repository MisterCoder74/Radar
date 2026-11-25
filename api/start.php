<?php
// api/start.php
declare(strict_types=1);

$config = require __DIR__ . '/../config.php';

header('Content-Type: application/json; charset=utf-8');

function json_out(array $arr, int $status=200): void {
  http_response_code($status);
  echo json_encode($arr, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
  exit;
}

function ensure_dir(string $dir): void {
  if (!is_dir($dir)) {
    mkdir($dir, 0755, true);
  }
}

function clean_str($v, int $maxLen=120): string {
  $v = trim((string)$v);
  if (mb_strlen($v) > $maxLen) $v = mb_substr($v, 0, $maxLen);
  return $v;
}

$raw = file_get_contents('php://input');
$payload = json_decode($raw ?? '', true);
if (!is_array($payload)) {
  json_out(['ok'=>false, 'error'=>'Invalid JSON body'], 400);
}

$area = clean_str($payload['area'] ?? '', 120);
$sector = clean_str($payload['sector'] ?? '', 120);
$radiusKm = isset($payload['radiusKm']) ? (float)$payload['radiusKm'] : 0;
$limit = isset($payload['limit']) ? (int)$payload['limit'] : 0;
$provider = clean_str($payload['provider'] ?? 'osm_overpass', 40);

if ($area === '' || $sector === '') {
  json_out(['ok'=>false, 'error'=>'area and sector are required'], 400);
}

if ($radiusKm < (float)$config['overpass']['min_radius_km'] || $radiusKm > (float)$config['overpass']['max_radius_km']) {
  json_out(['ok'=>false, 'error'=>'radiusKm out of allowed range'], 400);
}

$maxLimit = (int)$config['overpass']['max_limit'];
if ($limit < 1 || $limit > $maxLimit) {
  json_out(['ok'=>false, 'error'=>'limit out of allowed range'], 400);
}

ensure_dir($config['paths']['jobs_dir']);
ensure_dir($config['paths']['results_dir']);
ensure_dir($config['paths']['reports_dir']);

$job_id = 'job_' . bin2hex(random_bytes(10));
$created_at = gmdate('c');

$jobFile = $config['paths']['jobs_dir'] . DIRECTORY_SEPARATOR . $job_id . '.json';
$resultFile = $config['paths']['results_dir'] . DIRECTORY_SEPARATOR . $job_id . '.json';

$job = [
  'job_id' => $job_id,
  'status' => 'queued',
  'phase' => 'queued',
  'provider' => $provider,
  'input' => [
    'area' => $area,
    'sector' => $sector,
    'radiusKm' => $radiusKm,
    'limit' => $limit,
  ],
  'stats' => [
    'found' => 0,
    'dedup' => 0,
  ],
  'message' => null,
  'error' => null,
  'created_at' => $created_at,
  'updated_at' => $created_at,
  'started_at' => null,
  'checked_at' => null,
];

file_put_contents($jobFile, json_encode($job, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

/**
 * MVP: esegui subito discovery in questo request.
 * In prod: spostare su worker/cron per evitare timeout.
 */
$enginePath = __DIR__ . '/../engine/discovery.php';
require_once $enginePath;

try{
  $job['status'] = 'running';
  $job['phase'] = 'discovery';
  $job['started_at'] = gmdate('c');
  $job['updated_at'] = $job['started_at'];
  $job['message'] = 'Eseguo discovery su OSM/Overpass...';
  file_put_contents($jobFile, json_encode($job, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

  $res = run_discovery([
    'provider' => $provider,
    'area' => $area,
    'sector' => $sector,
    'radiusKm' => $radiusKm,
    'limit' => $limit,
  ], $config);

  $job['status'] = 'completed';
  $job['phase'] = 'completed';
  $job['stats']['found'] = $res['stats']['found'] ?? 0;
  $job['stats']['dedup'] = $res['stats']['dedup'] ?? 0;
  $job['message'] = 'Discovery completata.';
  $job['checked_at'] = gmdate('c');
  $job['updated_at'] = $job['checked_at'];

  file_put_contents($jobFile, json_encode($job, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
  file_put_contents($resultFile, json_encode([
    'job_id' => $job_id,
    'provider' => $provider,
    'items' => $res['items'] ?? [],
    'meta' => $res['meta'] ?? [],
    'generated_at' => gmdate('c')
  ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

  json_out([
    'ok' => true,
    'job_id' => $job_id,
    'meta' => ['job_id'=>$job_id, 'phase'=>$job['phase'], 'provider'=>$provider],
  ]);
}catch(Throwable $e){
  $job['status'] = 'failed';
  $job['phase'] = 'failed';
  $job['error'] = $e->getMessage();
  $job['updated_at'] = gmdate('c');
  file_put_contents($jobFile, json_encode($job, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
  json_out([
    'ok' => false,
    'error' => 'Discovery failed',
    'details' => $e->getMessage()
  ], 500);
}
