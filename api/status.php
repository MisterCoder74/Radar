<?php
// api/status.php
declare(strict_types=1);

$config = require __DIR__ . '/../config.php';
header('Content-Type: application/json; charset=utf-8');

function json_out(array $arr, int $status=200): void {
  http_response_code($status);
  echo json_encode($arr, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
  exit;
}

$job_id = $_GET['job_id'] ?? '';
$job_id = is_string($job_id) ? trim($job_id) : '';
if ($job_id === '') json_out(['ok'=>false, 'error'=>'job_id required'], 400);

$jobFile = $config['paths']['jobs_dir'] . DIRECTORY_SEPARATOR . $job_id . '.json';
if (!file_exists($jobFile)) json_out(['ok'=>false, 'error'=>'job not found'], 404);

$raw = file_get_contents($jobFile);
$job = json_decode($raw ?? '', true);
if (!is_array($job)) json_out(['ok'=>false, 'error'=>'invalid job JSON'], 500);

json_out([
  'ok' => true,
  'status' => $job['status'] ?? 'unknown',
  'meta' => [
    'job_id' => $job['job_id'] ?? $job_id,
    'phase' => $job['phase'] ?? null,
    'provider' => $job['provider'] ?? null,
    'message' => $job['message'] ?? null,
  ],
  'stats' => $job['stats'] ?? ['found'=>0,'dedup'=>0],
  'error' => $job['error'] ?? null,
]);
