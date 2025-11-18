<?php
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: login.php");
    exit;
}

$username = $_SESSION['user'];
$basePath = "users/$username/";
$scheduleFile = $basePath . "schedule.json";

if (!file_exists($basePath)) {
    mkdir($basePath, 0755, true);
}
if (!file_exists(dirname($scheduleFile))) {
    mkdir(dirname($scheduleFile), 0755, true);
}

$schedules = file_exists($scheduleFile) ? json_decode(file_get_contents($scheduleFile), true) : [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'add') {
        $validPlatforms = ['youtube', 'facebook', 'twitch', 'custom'];
        $validQualities = ['low', 'medium', 'high'];
        $validEncoders = ['cpu', 'gpu'];
        $validPresets = ['ultrafast', 'superfast', 'veryfast', 'faster', 'fast', 'medium', 'slow', 'slower', 'veryslow'];

        $platformInput = $_POST['platform'] ?? 'youtube';
        $qualityInput = $_POST['quality'] ?? 'medium';
        $encoderInput = $_POST['encoder_type'] ?? 'cpu';
        $presetInput = $_POST['preset'] ?? 'ultrafast';

        $platform = in_array($platformInput, $validPlatforms, true) ? $platformInput : 'youtube';
        $quality = in_array($qualityInput, $validQualities, true) ? $qualityInput : 'medium';
        $encoderType = in_array($encoderInput, $validEncoders, true) ? $encoderInput : 'cpu';
        $preset = in_array($presetInput, $validPresets, true) ? $presetInput : 'ultrafast';

        $durationHours = isset($_POST['duration_hours']) ? (int) $_POST['duration_hours'] : 1;
        if ($durationHours < 1) $durationHours = 1;
        if ($durationHours > 24) $durationHours = 24;

        $newSchedule = [
            "platform" => $platform,
            "stream_key" => trim($_POST['stream_key']),
            "time" => $_POST['time'],
            "video" => $_POST['video_file'],
            "quality" => $quality,
            "loop" => isset($_POST['looping']),
            "encoder_type" => $encoderType,
            "preset" => $preset,
            "duration_hours" => $durationHours
        ];
        $schedules[] = $newSchedule;
        file_put_contents($scheduleFile, json_encode($schedules, JSON_PRETTY_PRINT));
    }
} elseif (isset($_GET['delete'])) {
    $id = (int) $_GET['delete'];
    if (isset($schedules[$id])) {
        unset($schedules[$id]);
        $schedules = array_values($schedules); // Reset index
        file_put_contents($scheduleFile, json_encode($schedules, JSON_PRETTY_PRINT));
    }
}

header("Location: index.php#schedule");
exit;