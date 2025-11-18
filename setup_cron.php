<?php
/**
 * Endpoint untuk setup cron job secara otomatis via web
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

$result = [
    'success' => false,
    'message' => '',
    'error' => ''
];

$scriptPath = __DIR__ . '/run_schedule.php';
$scriptDir = __DIR__;

// Cari PHP binary dengan absolute path
$phpPath = PHP_BINARY;
if (!file_exists($phpPath) || !is_executable($phpPath)) {
    // Coba cari PHP di PATH
    $phpFromPath = shell_exec("which php 2>/dev/null");
    if ($phpFromPath) {
        $phpPath = trim($phpFromPath);
    } else {
        // Fallback ke php biasa (diharapkan ada di PATH)
        $phpPath = 'php';
    }
}

// Pastikan menggunakan absolute path untuk PHP jika memungkinkan
if (file_exists($phpPath) && is_executable($phpPath)) {
    $phpPath = realpath($phpPath);
}

$currentUser = get_current_user();

// Cek apakah script ada
if (!file_exists($scriptPath)) {
    $result['error'] = 'run_schedule.php tidak ditemukan';
    echo json_encode($result);
    exit;
}

// Cek apakah crontab sudah ada
$crontab = shell_exec("crontab -l 2>/dev/null");
$cronFound = false;
$cronLines = [];

if ($crontab) {
    $lines = explode("\n", $crontab);
    foreach ($lines as $line) {
        $trimmed = trim($line);
        if (strpos($line, 'run_schedule.php') !== false && $trimmed !== '' && substr($trimmed, 0, 1) !== '#') {
            $cronFound = true;
            $cronLines[] = $trimmed;
        }
    }
}

// Jika sudah ada, cek apakah perlu diupdate (jika menggunakan ~ atau relative path)
$needsUpdate = false;
if ($cronFound) {
    foreach ($cronLines as $line) {
        // Jika menggunakan ~ atau relative path, perlu diupdate
        if (strpos($line, '~/') !== false || (strpos($line, 'cd ') !== false && strpos($line, $scriptDir) === false)) {
            $needsUpdate = true;
            break;
        }
    }
}

if ($cronFound && !$needsUpdate) {
    $result['success'] = true;
    $result['message'] = 'Cron job sudah terpasang dengan benar';
    echo json_encode($result);
    exit;
}

// Setup/Update cron job dengan absolute path dan environment
// Tambahkan PATH dan environment variables untuk memastikan PHP bisa dijalankan
$cronLine = "* * * * * PATH=\$PATH:/usr/local/bin:/usr/bin:/bin && cd " . escapeshellarg($scriptDir) . " && " . escapeshellarg($phpPath) . " run_schedule.php >> " . escapeshellarg($scriptDir . '/cron_output.log') . " 2>&1\n";

// Backup crontab dulu
$backup = shell_exec("crontab -l 2>/dev/null");
if ($backup) {
    file_put_contents(__DIR__ . '/crontab_backup.txt', $backup);
}

// Jika perlu update, hapus cron job lama dulu
if ($cronFound && $needsUpdate) {
    $lines = explode("\n", $backup);
    $newLines = [];
    foreach ($lines as $line) {
        $trimmed = trim($line);
        // Skip line yang mengandung run_schedule.php
        if (strpos($line, 'run_schedule.php') === false || $trimmed === '' || substr($trimmed, 0, 1) === '#') {
            $newLines[] = $line;
        }
    }
    $backup = implode("\n", $newLines);
    if ($backup && substr($backup, -1) !== "\n") {
        $backup .= "\n";
    }
}

// Tambahkan ke crontab
if ($backup && trim($backup) !== '') {
    $newCrontab = $backup . $cronLine;
} else {
    $newCrontab = $cronLine;
}

$tempFile = tempnam(sys_get_temp_dir(), 'crontab');
file_put_contents($tempFile, $newCrontab);
exec("crontab $tempFile 2>&1", $output, $return);
unlink($tempFile);

if ($return === 0) {
    $result['success'] = true;
    if ($needsUpdate) {
        $result['message'] = 'Cron job berhasil diupdate dengan absolute path!';
    } else {
        $result['message'] = 'Cron job berhasil dipasang!';
    }
    $result['cron_line'] = trim($cronLine);
    $result['php_path'] = $phpPath;
    $result['script_dir'] = $scriptDir;
} else {
    $result['error'] = 'Gagal memasang cron job: ' . implode("\n", $output);
    $result['message'] = 'Silakan setup manual dengan menjalankan: php check_cron.php';
}

echo json_encode($result, JSON_PRETTY_PRINT);

