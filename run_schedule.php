<?php
date_default_timezone_set("Asia/Jakarta");

$usersDir = __DIR__ . '/users';
$now = date("Y-m-d H:i");
$logFile = __DIR__ . '/schedule_run.log';

// Fungsi untuk logging
function logSchedule($message) {
    global $logFile;
    $timestamp = date("Y-m-d H:i:s");
    file_put_contents($logFile, "[$timestamp] $message\n", FILE_APPEND);
}

logSchedule("=== Memulai pengecekan jadwal ===");
logSchedule("Waktu saat ini: $now");

// Ambil semua user
$users = array_filter(scandir($usersDir), fn($d) => $d !== '.' && $d !== '..' && is_dir("$usersDir/$d"));

foreach ($users as $username) {
    $base = "$usersDir/$username";
    $scheduleFile = "$base/schedule.json";

    if (!file_exists($scheduleFile)) {
        logSchedule("User $username: schedule.json tidak ditemukan");
        continue;
    }

    $schedules = json_decode(file_get_contents($scheduleFile), true);
    if (!is_array($schedules) || count($schedules) === 0) {
        logSchedule("User $username: Tidak ada jadwal");
        continue;
    }

    logSchedule("User $username: Ditemukan " . count($schedules) . " jadwal");

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
        if (empty($rawTime)) {
            logSchedule("User $username: Jadwal #$i tidak memiliki waktu");
            continue;
        }

        // Normalisasi format waktu
        // Cek dulu apakah format sudah benar (YYYY-MM-DD HH:MM)
        if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $rawTime)) {
            // Format sudah benar, langsung pakai
            $normalizedTime = $rawTime;
        } else {
            // Format belum benar, normalisasi
            // Ganti T dengan spasi (untuk format ISO datetime-local)
            $normalizedTime = str_replace('T', ' ', $rawTime);
            
            // Hapus detik HANYA jika ada format HH:MM:SS (ada 2 tanda : sebelum akhir)
            if (preg_match('/:\d{2}:\d{2}$/', $normalizedTime)) {
                // Ada detik (format HH:MM:SS), hapus bagian detik
                $normalizedTime = preg_replace('/:\d{2}$/', '', $normalizedTime);
                logSchedule("User $username: Jadwal #$i - Detik dihapus dari '$rawTime' menjadi '$normalizedTime'");
            }
            
            // Perbaiki format jika tidak lengkap (YYYY-MM-DD HH -> YYYY-MM-DD HH:00)
            // Hanya jika benar-benar tidak ada menit (tidak ada : sama sekali di bagian waktu)
            if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}$/', $normalizedTime)) {
                $normalizedTime .= ':00';
                logSchedule("User $username: Jadwal #$i - Format waktu diperbaiki dari '$rawTime' ke '$normalizedTime' (menit ditambahkan)");
            }
        }
        
        // Validasi format akhir harus: YYYY-MM-DD HH:MM
        if (!preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $normalizedTime)) {
            logSchedule("User $username: Jadwal #$i - Format waktu tidak valid: $rawTime (normalized: $normalizedTime)");
            continue;
        }
        
        // Parse waktu
        $timeTs = strtotime($normalizedTime);
        if ($timeTs === false) {
            logSchedule("User $username: Jadwal #$i - Gagal parse waktu: $rawTime (normalized: $normalizedTime)");
            continue;
        }
        
        $scheduleTime = date("Y-m-d H:i", $timeTs);
        $nowTs = time();
        $diffSeconds = $timeTs - $nowTs;
        $diffMinutes = round($diffSeconds / 60);
        
        logSchedule("User $username: Jadwal #$i - Waktu jadwal: $scheduleTime (dari: $rawTime)");
        logSchedule("User $username: Jadwal #$i - Selisih waktu: $diffMinutes menit ($diffSeconds detik)");
        
        // Toleransi: eksekusi jika waktu jadwal sudah lewat (maks 5 menit) atau dalam 1 menit ke depan
        // Ini untuk handle jika cron berjalan sedikit terlambat atau lebih awal
        // Cek apakah jadwal dalam menit yang sama atau sudah lewat (tapi belum terlalu lama)
        if ($diffSeconds < -300) {
            // Sudah lewat lebih dari 5 menit, skip (terlalu lama)
            logSchedule("User $username: Jadwal #$i - Sudah lewat lebih dari 5 menit, skip");
            continue;
        } elseif ($diffSeconds > 60) {
            // Masih lebih dari 1 menit lagi, belum waktunya
            logSchedule("User $username: Jadwal #$i - Belum waktunya (masih $diffMinutes menit lagi)");
            continue;
        }
        
        // Waktu cocok (sudah lewat maks 5 menit ATAU akan datang dalam 1 menit)
        logSchedule("User $username: Jadwal #$i - WAKTU COCOK! (dalam toleransi -5 menit sampai +1 menit) Memulai streaming...");

        // Cari slot kosong
        $availableSlot = null;
        foreach ($slotStatus as $slot => $isActive) {
            if (!$isActive) {
                $availableSlot = $slot;
                break;
            }
        }

        if (!$availableSlot) {
            logSchedule("User $username: Jadwal #$i - Semua slot penuh, tidak bisa menjalankan streaming");
            break; // Semua slot penuh
        }

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

        logSchedule("User $username: Menjalankan command: $cmd");
        exec($cmd . " > /dev/null 2>&1 &");
        $slotStatus[$availableSlot] = true;

        logSchedule("User $username: Streaming dimulai di slot $availableSlot");

        // Hapus jadwal dari queue
        unset($schedules[$i]);
        file_put_contents($scheduleFile, json_encode(array_values($schedules), JSON_PRETTY_PRINT));
        
        logSchedule("User $username: Jadwal #$i telah dihapus dari queue");

        break; // Jalankan satu jadwal per waktu
    }
}

logSchedule("=== Selesai pengecekan jadwal ===\n");