<?php
/**
 * Endpoint untuk test run schedule via web
 * Hanya untuk testing, tidak untuk production use
 */

session_start();
if (!isset($_SESSION['user'])) {
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

header('Content-Type: application/json');
date_default_timezone_set("Asia/Jakarta");

// Jalankan run_schedule.php
$scriptPath = __DIR__ . '/run_schedule.php';
$phpPath = PHP_BINARY;

if (!file_exists($scriptPath)) {
    echo json_encode(['success' => false, 'error' => 'run_schedule.php not found']);
    exit;
}

// Jalankan script dan capture output
$output = [];
$return = 0;
exec("$phpPath $scriptPath 2>&1", $output, $return);

$result = [
    'success' => $return === 0,
    'return_code' => $return,
    'output' => $output,
    'timestamp' => date("Y-m-d H:i:s")
];

echo json_encode($result, JSON_PRETTY_PRINT);

