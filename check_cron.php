<?php
/**
 * Script untuk cek dan setup cron job untuk run_schedule.php
 * Jalankan: php check_cron.php
 */

date_default_timezone_set("Asia/Jakarta");

echo "=== Cron Job Checker untuk Stream Dashboard ===\n\n";

$scriptPath = __DIR__ . '/run_schedule.php';
$currentUser = get_current_user();
$phpPath = PHP_BINARY;

echo "📁 Script path: $scriptPath\n";
echo "👤 Current user: $currentUser\n";
echo "🐘 PHP binary: $phpPath\n";
echo "⏰ Waktu sekarang: " . date("Y-m-d H:i:s") . "\n\n";

// Cek apakah script ada
if (!file_exists($scriptPath)) {
    echo "❌ ERROR: run_schedule.php tidak ditemukan di $scriptPath\n";
    exit(1);
}

echo "✅ run_schedule.php ditemukan\n\n";

// Cek crontab
echo "📋 Cek crontab untuk user: $currentUser\n";
$crontab = shell_exec("crontab -l 2>&1");

if (strpos($crontab, 'run_schedule.php') !== false) {
    echo "✅ Cron job ditemukan di crontab:\n";
    $lines = explode("\n", $crontab);
    foreach ($lines as $line) {
        if (strpos($line, 'run_schedule.php') !== false) {
            echo "   $line\n";
        }
    }
} else {
    echo "⚠️  Cron job TIDAK ditemukan di crontab!\n\n";
    echo "📝 Untuk menambahkan cron job, jalankan:\n";
    echo "   crontab -e\n\n";
    echo "Lalu tambahkan baris berikut:\n";
    echo "   * * * * * cd " . escapeshellarg(__DIR__) . " && $phpPath run_schedule.php > /dev/null 2>&1\n\n";
    
    // Tanya apakah ingin auto-setup
    echo "❓ Ingin setup cron job otomatis? (y/n): ";
    $handle = fopen("php://stdin", "r");
    $line = fgets($handle);
    $answer = trim(strtolower($line));
    fclose($handle);
    
    if ($answer === 'y' || $answer === 'yes') {
        $cronLine = "* * * * * cd " . escapeshellarg(__DIR__) . " && $phpPath run_schedule.php > /dev/null 2>&1\n";
        
        // Backup crontab dulu
        $backup = shell_exec("crontab -l 2>/dev/null");
        if ($backup) {
            file_put_contents(__DIR__ . '/crontab_backup.txt', $backup);
            echo "💾 Backup crontab disimpan ke crontab_backup.txt\n";
        }
        
        // Tambahkan ke crontab
        if ($backup) {
            $newCrontab = $backup . $cronLine;
        } else {
            $newCrontab = $cronLine;
        }
        
        $tempFile = tempnam(sys_get_temp_dir(), 'crontab');
        file_put_contents($tempFile, $newCrontab);
        exec("crontab $tempFile 2>&1", $output, $return);
        unlink($tempFile);
        
        if ($return === 0) {
            echo "✅ Cron job berhasil ditambahkan!\n";
        } else {
            echo "❌ Gagal menambahkan cron job. Error: " . implode("\n", $output) . "\n";
            echo "   Silakan tambahkan manual dengan: crontab -e\n";
        }
    }
}

echo "\n";

// Test run script
echo "🧪 Test menjalankan run_schedule.php...\n";
$output = [];
$return = 0;
exec("$phpPath $scriptPath 2>&1", $output, $return);

if ($return === 0) {
    echo "✅ Script berhasil dijalankan\n";
    if (!empty($output)) {
        echo "   Output:\n";
        foreach ($output as $line) {
            echo "   $line\n";
        }
    }
} else {
    echo "❌ Script gagal dijalankan. Error:\n";
    foreach ($output as $line) {
        echo "   $line\n";
    }
}

echo "\n";

// Cek log file
$logFile = __DIR__ . '/schedule_run.log';
if (file_exists($logFile)) {
    echo "📄 Log file ditemukan: $logFile\n";
    $logSize = filesize($logFile);
    echo "   Ukuran: " . round($logSize / 1024, 2) . " KB\n";
    
    if ($logSize > 0) {
        echo "\n   📋 10 baris terakhir log:\n";
        $lines = file($logFile);
        $lastLines = array_slice($lines, -10);
        foreach ($lastLines as $line) {
            echo "   " . trim($line) . "\n";
        }
    }
} else {
    echo "⚠️  Log file belum ada: $logFile\n";
    echo "   (Akan dibuat saat run_schedule.php pertama kali dijalankan)\n";
}

echo "\n=== Selesai ===\n";
echo "\n💡 Tips:\n";
echo "1. Pastikan cron berjalan setiap menit: * * * * *\n";
echo "2. Test manual: php run_schedule.php\n";
echo "3. Cek log: tail -f schedule_run.log\n";
echo "4. Cek crontab: crontab -l\n";

