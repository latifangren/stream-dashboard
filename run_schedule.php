<?php
date_default_timezone_set("Asia/Jakarta");

$usersDir = __DIR__ . '/users';
$now = date("Y-m-d H:i");

// Ambil semua user
$users = array_filter(scandir($usersDir), fn($d) => $d !== '.' && $d !== '..' && is_dir("$usersDir/$d"));

foreach ($users as $username) {
    $base = "$usersDir/$username";
    $scheduleFile = "$base/schedule.json";

    if (!file_exists($scheduleFile)) continue;

    $schedules = json_decode(file_get_contents($scheduleFile), true);
    if (!is_array($schedules) || count($schedules) === 0) {
        continue;
    }

    $validPlatforms = ['youtube', 'facebook', 'twitch', 'custom'];
    $validQualities = ['low', 'medium', 'high'];
    $validEncoders = ['cpu', 'gpu'];
    $validPresets = ['ultrafast', 'superfast', 'veryfast', 'faster', 'fast', 'medium', 'slow', 'slower', 'veryslow'];

    // Status per slot
    $slotStatus = [];
    for ($i = 1; $i <= 2; $i++) {
        $file = "$base/status-$i.json";
        if (file_exists($file)) {
            $data = json_decode(file_get_contents($file), true);
            if (isset($data['pid']) && posix_kill((int)$data['pid'], 0)) {
                $slotStatus[$i] = true; // Aktif
            } else {
                unlink($file); // Tidak aktif, bersihkan
                $slotStatus[$i] = false;
            }
        } else {
            $slotStatus[$i] = false;
        }
    }

    // Cari jadwal yang waktunya sesuai
    foreach ($schedules as $i => $item) {
        $rawTime = $item['time'] ?? '';
        $timeTs = strtotime($rawTime);
        if ($timeTs === false) {
            continue;
        }
        $scheduleTime = date("Y-m-d H:i", $timeTs);
        if ($scheduleTime !== $now) continue;

        // Cari slot kosong
        $availableSlot = null;
        foreach ($slotStatus as $slot => $isActive) {
            if (!$isActive) {
                $availableSlot = $slot;
                break;
            }
        }

        if (!$availableSlot) break; // Semua slot penuh

        // Siapkan parameter
        $platform = in_array($item['platform'] ?? 'youtube', $validPlatforms, true) ? $item['platform'] : 'youtube';
        $key = trim($item['stream_key']);
        $video = $item['video'];
        $quality = in_array($item['quality'] ?? 'medium', $validQualities, true) ? $item['quality'] : 'medium';
        $loop = $item['loop'] ?? false;
        $encoderType = in_array($item['encoder_type'] ?? 'cpu', $validEncoders, true) ? $item['encoder_type'] : 'cpu';
        $preset = in_array($item['preset'] ?? 'ultrafast', $validPresets, true) ? $item['preset'] : 'ultrafast';
        $durationHours = isset($item['duration_hours']) ? (int) $item['duration_hours'] : 1;
        if ($durationHours < 1) $durationHours = 1;
        if ($durationHours > 24) $durationHours = 24;

        // Panggil stream.php via CLI (tanpa parameter slot)
        $cmd = escapeshellcmd(PHP_BINARY) . " stream.php " .
            escapeshellarg($username) . " " .
            escapeshellarg($platform) . " " .
            escapeshellarg($key) . " " .
            escapeshellarg($video) . " " .
            escapeshellarg($quality) . " " .
            escapeshellarg($loop ? '1' : '0') . " " .
            escapeshellarg($encoderType) . " " .
            escapeshellarg($preset) . " " .
            escapeshellarg((string) $durationHours);

        exec($cmd . " > /dev/null 2>&1 &");
        $slotStatus[$availableSlot] = true;

        // Hapus jadwal dari queue
        unset($schedules[$i]);
        file_put_contents($scheduleFile, json_encode(array_values($schedules), JSON_PRETTY_PRINT));

        break; // Jalankan satu jadwal per waktu
    }
}