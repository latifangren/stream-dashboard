<?php
/**
 * Script untuk test dan debug jadwal streaming
 * Jalankan: php test_schedule.php
 */

date_default_timezone_set("Asia/Jakarta");

echo "=== Test Schedule Debugging ===\n\n";

// Cek timezone
echo "Timezone: " . date_default_timezone_get() . "\n";
echo "Waktu sekarang: " . date("Y-m-d H:i:s") . "\n";
echo "Waktu sekarang (format jadwal): " . date("Y-m-d H:i") . "\n\n";

// Cek users directory
$usersDir = __DIR__ . '/users';
if (!is_dir($usersDir)) {
    echo "❌ Folder users tidak ditemukan: $usersDir\n";
    exit(1);
}

echo "📁 Users directory: $usersDir\n\n";

// Ambil semua user
$users = array_filter(scandir($usersDir), fn($d) => $d !== '.' && $d !== '..' && is_dir("$usersDir/$d"));

if (empty($users)) {
    echo "⚠️ Tidak ada user ditemukan\n";
    exit(0);
}

echo "👥 Ditemukan " . count($users) . " user(s)\n\n";

foreach ($users as $username) {
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "User: $username\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    
    $base = "$usersDir/$username";
    $scheduleFile = "$base/schedule.json";
    
    if (!file_exists($scheduleFile)) {
        echo "⚠️ schedule.json tidak ditemukan\n\n";
        continue;
    }
    
    $schedules = json_decode(file_get_contents($scheduleFile), true);
    if (!is_array($schedules) || count($schedules) === 0) {
        echo "⚠️ Tidak ada jadwal\n\n";
        continue;
    }
    
    echo "📅 Ditemukan " . count($schedules) . " jadwal:\n\n";
    
    foreach ($schedules as $i => $item) {
        echo "  Jadwal #$i:\n";
        echo "    Platform: " . ($item['platform'] ?? 'N/A') . "\n";
        echo "    Video: " . ($item['video'] ?? 'N/A') . "\n";
        $rawTime = $item['time'] ?? '';
        echo "    Waktu (raw): $rawTime\n";
        
        // Normalisasi waktu
        $normalizedTime = str_replace('T', ' ', $rawTime);
        $normalizedTime = preg_replace('/:\d{2}$/', '', $normalizedTime);
        
        if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}$/', $normalizedTime)) {
            $normalizedTime .= ':00';
            echo "    ⚠️ Format waktu diperbaiki: $normalizedTime\n";
        }
        
        if (!preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $normalizedTime)) {
            echo "    ❌ Format waktu TIDAK VALID: $normalizedTime\n";
            echo "    Format harus: YYYY-MM-DD HH:MM\n\n";
            continue;
        }
        
        $timeTs = strtotime($normalizedTime);
        if ($timeTs === false) {
            echo "    ❌ Gagal parse waktu\n\n";
            continue;
        }
        
        $scheduleTime = date("Y-m-d H:i", $timeTs);
        $now = date("Y-m-d H:i");
        
        echo "    Waktu (parsed): $scheduleTime\n";
        echo "    Waktu sekarang: $now\n";
        
        $diff = $timeTs - time();
        $diffMinutes = round($diff / 60);
        
        if ($scheduleTime === $now) {
            echo "    ✅ WAKTU COCOK! (Sekarang waktunya)\n";
        } elseif ($diffMinutes > 0) {
            echo "    ⏳ Akan berjalan dalam $diffMinutes menit\n";
        } else {
            echo "    ⏰ Sudah lewat " . abs($diffMinutes) . " menit yang lalu\n";
        }
        
        // Cek slot
        $slotAvailable = false;
        for ($slot = 1; $slot <= 2; $slot++) {
            $statusFile = "$base/status-$slot.json";
            if (!file_exists($statusFile)) {
                $slotAvailable = true;
                echo "    ✅ Slot $slot tersedia\n";
                break;
            }
            
            $status = json_decode(file_get_contents($statusFile), true);
            $pid = $status['pid'] ?? 0;
            if ($pid > 0 && function_exists('posix_kill') && posix_kill($pid, 0)) {
                echo "    🔴 Slot $slot sedang digunakan (PID: $pid)\n";
            } else {
                $slotAvailable = true;
                echo "    ✅ Slot $slot tersedia\n";
                break;
            }
        }
        
        if (!$slotAvailable) {
            echo "    ⚠️ Semua slot penuh\n";
        }
        
        echo "\n";
    }
}

echo "\n=== Selesai ===\n";
echo "\n💡 Tips:\n";
echo "1. Pastikan format waktu: YYYY-MM-DD HH:MM (contoh: 2025-11-19 00:30)\n";
echo "2. Pastikan cron job berjalan: crontab -l\n";
echo "3. Test manual: php run_schedule.php\n";
echo "4. Cek log: cat schedule_run.log\n";

