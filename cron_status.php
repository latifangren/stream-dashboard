<?php
/**
 * API endpoint untuk mendapatkan status cron job
 * Mengembalikan data JSON
 */

header('Content-Type: application/json');
date_default_timezone_set("Asia/Jakarta");

$result = [
    'success' => true,
    'timestamp' => date("Y-m-d H:i:s"),
    'data' => []
];

$scriptPath = __DIR__ . '/run_schedule.php';
$logFile = __DIR__ . '/schedule_run.log';
$phpPath = PHP_BINARY;

// Cek apakah script ada
$result['data']['script_exists'] = file_exists($scriptPath);
$result['data']['script_path'] = $scriptPath;

// Cek crontab
$crontab = shell_exec("crontab -l 2>&1");
$cronFound = strpos($crontab, 'run_schedule.php') !== false;
$result['data']['cron_installed'] = $cronFound;

if ($cronFound) {
    $lines = explode("\n", $crontab);
    $cronLines = [];
    foreach ($lines as $line) {
        $trimmed = trim($line);
        if (strpos($line, 'run_schedule.php') !== false && $trimmed !== '' && substr($trimmed, 0, 1) !== '#') {
            $cronLines[] = $trimmed;
        }
    }
    $result['data']['cron_lines'] = $cronLines;
} else {
    $result['data']['cron_lines'] = [];
}

// Cek log file
$result['data']['log_exists'] = file_exists($logFile);
$result['data']['log_path'] = $logFile;

if (file_exists($logFile)) {
    $logSize = filesize($logFile);
    $result['data']['log_size'] = $logSize;
    $result['data']['log_size_kb'] = round($logSize / 1024, 2);
    
    // Ambil 20 baris terakhir
    $lines = file($logFile);
    $lastLines = array_slice($lines, -20);
    $result['data']['log_last_lines'] = array_map('trim', $lastLines);
    
    // Cek waktu terakhir dijalankan (dari log)
    $lastRun = null;
    foreach (array_reverse($lines) as $line) {
        if (preg_match('/\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\]/', $line, $matches)) {
            $lastRun = $matches[1];
            break;
        }
    }
    $result['data']['last_run'] = $lastRun;
} else {
    $result['data']['log_size'] = 0;
    $result['data']['log_size_kb'] = 0;
    $result['data']['log_last_lines'] = [];
    $result['data']['last_run'] = null;
}

// Test run script (non-blocking, hanya cek apakah bisa dijalankan)
$result['data']['php_path'] = $phpPath;
$result['data']['php_version'] = PHP_VERSION;

// Cek apakah bisa execute
$testCmd = "$phpPath -r 'echo \"OK\";' 2>&1";
$testOutput = shell_exec($testCmd);
$result['data']['php_executable'] = trim($testOutput) === 'OK';

// Info sistem
$result['data']['current_user'] = get_current_user();
$result['data']['timezone'] = date_default_timezone_get();
$result['data']['current_time'] = date("Y-m-d H:i:s");

// Cek status daemon (alternatif untuk cron)
$pidFile = __DIR__ . '/schedule_daemon.pid';
$daemonRunning = false;
$daemonPid = null;

if (file_exists($pidFile)) {
    $pid = (int)trim(file_get_contents($pidFile));
    if ($pid > 0) {
        if (function_exists('posix_kill')) {
            $daemonRunning = posix_kill($pid, 0);
        } else {
            $output = shell_exec("ps -p $pid 2>/dev/null");
            $daemonRunning = $output && strpos($output, (string)$pid) !== false;
        }
        if ($daemonRunning) {
            $daemonPid = $pid;
        }
    }
}

$result['data']['daemon_running'] = $daemonRunning;
$result['data']['daemon_pid'] = $daemonPid;
$result['data']['daemon_available'] = file_exists(__DIR__ . '/schedule_daemon.php');

echo json_encode($result, JSON_PRETTY_PRINT);

