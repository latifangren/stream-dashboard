<?php
session_start();
if (!isset($_SESSION['user'])) {
    http_response_code(403);
    exit(" Akses ditolak.");
}

$username = $_SESSION['user'];
$basePath = "users/$username/";

$slot = isset($_GET['slot']) ? (int)$_GET['slot'] : 0;
$slots = ($slot >= 1 && $slot <= 2) ? [$slot] : [1, 2];

foreach ($slots as $i) {
    $statusFile = $basePath . "status-$i.json";
    $logFile = $basePath . "log-$i.txt";

    if (file_exists($statusFile)) {
        $status = json_decode(file_get_contents($statusFile), true);
        $pid = (int)($status['pid'] ?? 0);

        if ($pid > 0 && posix_kill($pid, 0)) {
            exec("kill -9 $pid"); // paksa kill ffmpeg
            file_put_contents($logFile, " Streaming dihentikan (PID: $pid)\n", FILE_APPEND);
        }

        unlink($statusFile);
    }

    if (file_exists($logFile)) {
        unlink($logFile);
    }
}

header("Location: index.php#streaming");
exit;