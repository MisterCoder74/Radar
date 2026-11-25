<?php
// api/review.php
declare(strict_types=1);

$config = require __DIR__ . '/../config.php';
header('Content-Type: application/json; charset=utf-8');

function json_out(array $arr, int $status=200): void {
  http_response_code($status);
  echo json_encode($arr, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
  exit;
}

$action = $_GET['action'] ?? '';
$job_id = $_GET['job_id'] ?? '';

$action = is_string($action) ? trim($action) : '';
$job_id = is_string($job_id) ? trim($job_id) : '';

if ($action !== 'cancel' || $job_id === '') {
  json_out(['ok'=>false, 'error'=>'Unsupported action'], 400);
}

$jobFile = $config['paths']['jobs_dir'] . DIRECTORY_SEPARATOR . $job_id . '.json';
if (!file_exists($jobFile)) json_out(['ok'=>false, 'error'=>'job not found'], 404);

$raw = file_get_contents($jobFile);
$job = json_decode($raw ?? '', true);
if (!is_array($job)) json_out(['ok'=>false, 'error'=>'invalid job JSON'], 500);

$job['status'] = 'cancelled';
$job['phase'] = 'cancelled';
$job['message'] = 'Cancellation requested.';
$job['updated_at'] = gmdate('c');

file_put_contents($jobFile, json_encode($job, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

json_out(['ok'=>true]);
