<?php
/**
 * Daemon process untuk menjalankan run_schedule.php setiap menit
 * Alternatif untuk cron di Termux
 * 
 * Jalankan: php schedule_daemon.php
 * Atau di background: nohup php schedule_daemon.php > /dev/null 2>&1 &
 */

date_default_timezone_set("Asia/Jakarta");

$scriptDir = __DIR__;
$pidFile = $scriptDir . '/schedule_daemon.pid';
$logFile = $scriptDir . '/schedule_daemon.log';
$runScript = $scriptDir . '/run_schedule.php';
$phpPath = PHP_BINARY;

// Fungsi logging
function daemonLog($message) {
    global $logFile;
    $timestamp = date("Y-m-d H:i:s");
    file_put_contents($logFile, "[$timestamp] $message\n", FILE_APPEND);
}

// Cek apakah daemon sudah berjalan
if (file_exists($pidFile)) {
    $pid = (int)trim(file_get_contents($pidFile));
    if ($pid > 0) {
        // Cek apakah process masih berjalan
        if (function_exists('posix_kill')) {
            if (posix_kill($pid, 0)) {
                echo "Daemon sudah berjalan dengan PID: $pid\n";
                exit(1);
            }
        } else {
            // Fallback: cek dengan ps (untuk Windows/Termux)
            $output = shell_exec("ps -p $pid 2>/dev/null");
            if ($output && strpos($output, (string)$pid) !== false) {
                echo "Daemon sudah berjalan dengan PID: $pid\n";
                exit(1);
            }
        }
        // PID file ada tapi process tidak berjalan, hapus file
        unlink($pidFile);
    }
}

// Simpan PID
$pid = getmypid();
file_put_contents($pidFile, $pid);
daemonLog("Daemon started dengan PID: $pid");

// Signal handler untuk graceful shutdown
if (function_exists('pcntl_signal')) {
    pcntl_async_signals(true);
    pcntl_signal(SIGTERM, function() use ($pidFile) {
        daemonLog("Daemon stopped (SIGTERM)");
        if (file_exists($pidFile)) {
            unlink($pidFile);
        }
        exit(0);
    });
    pcntl_signal(SIGINT, function() use ($pidFile) {
        daemonLog("Daemon stopped (SIGINT)");
        if (file_exists($pidFile)) {
            unlink($pidFile);
        }
        exit(0);
    });
}

daemonLog("Daemon loop started");

// Main loop: jalankan setiap 60 detik
while (true) {
    // Jalankan run_schedule.php
    if (file_exists($runScript)) {
        daemonLog("Executing run_schedule.php");
        $output = [];
        $return = 0;
        exec("$phpPath $runScript 2>&1", $output, $return);
        if ($return !== 0) {
            daemonLog("Error executing run_schedule.php: " . implode("\n", $output));
        }
    } else {
        daemonLog("ERROR: run_schedule.php tidak ditemukan!");
    }
    
    // Tunggu 60 detik sebelum eksekusi berikutnya
    sleep(60);
}

