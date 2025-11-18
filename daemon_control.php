<?php
/**
 * Endpoint untuk kontrol daemon schedule
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

$action = $_GET['action'] ?? $_POST['action'] ?? 'status';
$scriptDir = __DIR__;
$pidFile = $scriptDir . '/schedule_daemon.pid';
$daemonScript = $scriptDir . '/schedule_daemon.php';
$phpPath = PHP_BINARY;

$result = ['success' => false, 'message' => ''];

function isDaemonRunning($pidFile) {
    if (!file_exists($pidFile)) {
        return false;
    }
    
    $pid = (int)trim(file_get_contents($pidFile));
    if ($pid <= 0) {
        return false;
    }
    
    // Cek apakah process masih berjalan
    if (function_exists('posix_kill')) {
        return posix_kill($pid, 0);
    } else {
        // Fallback untuk Termux/Windows
        $output = shell_exec("ps -p $pid 2>/dev/null");
        return $output && strpos($output, (string)$pid) !== false;
    }
}

switch ($action) {
    case 'start':
        if (isDaemonRunning($pidFile)) {
            $result['success'] = true;
            $result['message'] = 'Daemon sudah berjalan';
            $result['pid'] = (int)trim(file_get_contents($pidFile));
        } else {
            // Start daemon di background
            $cmd = escapeshellcmd($phpPath) . " " . escapeshellarg($daemonScript) . " > /dev/null 2>&1 &";
            exec($cmd);
            sleep(1); // Tunggu sebentar untuk daemon start
            
            if (isDaemonRunning($pidFile)) {
                $result['success'] = true;
                $result['message'] = 'Daemon berhasil dijalankan';
                $result['pid'] = (int)trim(file_get_contents($pidFile));
            } else {
                $result['success'] = false;
                $result['message'] = 'Gagal menjalankan daemon';
            }
        }
        break;
        
    case 'stop':
        if (!isDaemonRunning($pidFile)) {
            $result['success'] = true;
            $result['message'] = 'Daemon tidak berjalan';
        } else {
            $pid = (int)trim(file_get_contents($pidFile));
            if (function_exists('posix_kill')) {
                posix_kill($pid, SIGTERM);
            } else {
                // Fallback untuk Termux/Windows
                exec("kill $pid 2>/dev/null");
            }
            sleep(1);
            
            if (!isDaemonRunning($pidFile)) {
                $result['success'] = true;
                $result['message'] = 'Daemon berhasil dihentikan';
            } else {
                $result['success'] = false;
                $result['message'] = 'Gagal menghentikan daemon';
            }
        }
        break;
        
    case 'status':
    default:
        $running = isDaemonRunning($pidFile);
        $result['success'] = true;
        $result['running'] = $running;
        if ($running) {
            $result['pid'] = (int)trim(file_get_contents($pidFile));
            $result['message'] = 'Daemon sedang berjalan';
        } else {
            $result['message'] = 'Daemon tidak berjalan';
        }
        break;
}

echo json_encode($result, JSON_PRETTY_PRINT);

