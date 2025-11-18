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

        // Normalisasi format waktu: datetime-local menghasilkan format dengan T, ubah ke format standar
        $timeInput = trim($_POST['time']);
        
        // Debug: log input yang diterima
        $debugFile = __DIR__ . '/schedule_debug.log';
        file_put_contents($debugFile, date("Y-m-d H:i:s") . " - Input waktu dari form: '$timeInput'\n", FILE_APPEND);
        
        // Gunakan DateTime untuk parsing yang lebih aman dan akurat
        // Format datetime-local: YYYY-MM-DDTHH:MM atau YYYY-MM-DDTHH:MM:SS
        try {
            // Coba parse dengan DateTime (mendukung format ISO dengan T)
            $dateTime = new DateTime($timeInput);
            // Format ke YYYY-MM-DD HH:MM (tanpa detik)
            $normalizedTime = $dateTime->format('Y-m-d H:i');
            file_put_contents($debugFile, date("Y-m-d H:i:s") . " - Parsed dengan DateTime: '$normalizedTime'\n", FILE_APPEND);
        } catch (Exception $e) {
            // Jika DateTime gagal, coba manual parsing
            file_put_contents($debugFile, date("Y-m-d H:i:s") . " - DateTime gagal, coba manual: " . $e->getMessage() . "\n", FILE_APPEND);
            
            // Ganti T dengan spasi
            $normalizedTime = str_replace('T', ' ', $timeInput);
            
            // Hapus detik jika ada (format YYYY-MM-DD HH:MM:SS -> YYYY-MM-DD HH:MM)
            // Hanya hapus jika ada 3 bagian waktu (HH:MM:SS)
            if (preg_match('/:\d{2}:\d{2}$/', $normalizedTime)) {
                // Ada detik, hapus bagian terakhir (detik)
                $normalizedTime = preg_replace('/:\d{2}$/', '', $normalizedTime);
            }
            
            // Validasi format akhir harus: YYYY-MM-DD HH:MM
            if (!preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $normalizedTime)) {
                // Jika hanya ada tanggal dan jam tanpa menit (YYYY-MM-DD HH), tambahkan :00
                if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}$/', $normalizedTime)) {
                    $normalizedTime .= ':00';
                } else {
                    // Format tidak valid, gunakan waktu sekarang sebagai fallback
                    $normalizedTime = date("Y-m-d H:i");
                }
            }
        }
        
        file_put_contents($debugFile, date("Y-m-d H:i:s") . " - Final normalized time: '$normalizedTime'\n\n", FILE_APPEND);

        $newSchedule = [
            "platform" => $platform,
            "stream_key" => trim($_POST['stream_key']),
            "time" => $normalizedTime,
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