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

// Fungsi untuk deteksi platform
function detectPlatform() {
    $osId = '';
    if (file_exists('/etc/os-release')) {
        $content = file_get_contents('/etc/os-release');
        if (preg_match('/^ID=(.+)$/m', $content, $matches)) {
            $osId = strtolower(trim($matches[1], '"\''));
        }
    }
    
    if (strpos($osId, 'android') !== false || file_exists('/data/data/com.termux')) {
        return 'termux';
    } elseif (strpos($osId, 'alpine') !== false) {
        return 'alpine';
    } elseif (in_array($osId, ['debian', 'ubuntu'])) {
        return 'vps';
    }
    
    return 'unknown';
}

// Fungsi untuk cek apakah crontab tersedia
function isCrontabAvailable() {
    $output = shell_exec("which crontab 2>/dev/null");
    return !empty(trim($output));
}

// Fungsi untuk install cron
function installCron($platform) {
    $output = [];
    $return = 0;
    
    switch ($platform) {
        case 'termux':
            // Install cronie untuk Termux
            exec("pkg install -y cronie 2>&1", $output, $return);
            if ($return === 0) {
                // Start cronie service
                exec("sv-enable crond 2>&1", $output2, $return2);
                exec("sv start crond 2>&1", $output3, $return3);
                return ['success' => true, 'message' => 'cronie berhasil diinstall dan diaktifkan'];
            }
            break;
            
        case 'alpine':
            // Install dcron untuk Alpine
            exec("apk add --no-cache dcron 2>&1", $output, $return);
            if ($return === 0) {
                // Start crond service
                exec("rc-service crond start 2>&1", $output2, $return2);
                exec("rc-update add crond 2>&1", $output3, $return3);
                return ['success' => true, 'message' => 'dcron berhasil diinstall dan diaktifkan'];
            }
            break;
            
        case 'vps':
            // Untuk VPS, coba install dengan sudo jika diperlukan
            // Biasanya cron sudah terinstall di VPS
            $cmd = "command -v apt-get >/dev/null 2>&1 && (sudo apt-get update && sudo apt-get install -y cron) 2>&1 || (command -v yum >/dev/null 2>&1 && sudo yum install -y cronie) 2>&1";
            exec($cmd, $output, $return);
            if ($return === 0) {
                // Start cron service
                exec("sudo systemctl enable cron 2>&1 || sudo systemctl enable crond 2>&1", $output2, $return2);
                exec("sudo systemctl start cron 2>&1 || sudo systemctl start crond 2>&1", $output3, $return3);
                return ['success' => true, 'message' => 'cron berhasil diinstall dan diaktifkan'];
            } else {
                // Mungkin cron sudah terinstall, coba start service saja
                exec("sudo systemctl start cron 2>&1 || sudo systemctl start crond 2>&1", $output4, $return4);
                if ($return4 === 0) {
                    return ['success' => true, 'message' => 'cron service berhasil diaktifkan (sudah terinstall)'];
                }
            }
            break;
            
        default:
            return ['success' => false, 'message' => 'Platform tidak dikenali'];
    }
    
    return [
        'success' => false,
        'message' => 'Gagal install cron: ' . implode("\n", $output),
        'output' => $output
    ];
}

// Cek apakah crontab tersedia, jika tidak coba install
$crontabAvailable = isCrontabAvailable();
if (!$crontabAvailable) {
    $platform = detectPlatform();
    $result['platform_detected'] = $platform;
    $result['cron_installing'] = true;
    
    if ($platform !== 'unknown') {
        $installResult = installCron($platform);
        if ($installResult['success']) {
            // Tunggu sebentar untuk service start
            sleep(2);
            // Cek lagi apakah crontab sekarang tersedia
            $crontabAvailable = isCrontabAvailable();
            if ($crontabAvailable) {
                $result['cron_installed'] = true;
                $result['install_message'] = $installResult['message'];
            } else {
                $result['error'] = 'Cron berhasil diinstall tapi crontab masih tidak tersedia. Silakan restart terminal atau jalankan manual.';
                $result['install_message'] = $installResult['message'];
                echo json_encode($result, JSON_PRETTY_PRINT);
                exit;
            }
        } else {
            $result['error'] = $installResult['message'];
            $result['install_failed'] = true;
            // Tetap lanjutkan, mungkin cron sudah ada tapi tidak di PATH
        }
    } else {
        $result['error'] = 'Platform tidak dikenali. Silakan install cron manual.';
        $result['install_failed'] = true;
    }
}

// Cek apakah crontab sudah ada (setelah install jika perlu)
$crontab = shell_exec("crontab -l 2>&1");
$crontabError = false;
// "no crontab" adalah normal jika belum ada crontab, bukan error
if ($crontab === null || (stripos($crontab, 'error') !== false && stripos($crontab, 'no crontab') === false)) {
    $crontabError = true;
    $crontab = '';
} elseif (stripos($crontab, 'no crontab') !== false) {
    // Tidak ada crontab, tapi ini normal (belum ada jadwal)
    $crontab = '';
}
$cronFound = false;
$cronLines = [];

if ($crontab && !$crontabError) {
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
    $message = '';
    if (isset($result['cron_installed'])) {
        $message = 'Cron berhasil diinstall dan ';
    }
    if ($needsUpdate) {
        $message .= 'Cron job berhasil diupdate dengan absolute path!';
    } else {
        $message .= 'Cron job berhasil dipasang!';
    }
    $result['message'] = $message;
    $result['cron_line'] = trim($cronLine);
    $result['php_path'] = $phpPath;
    $result['script_dir'] = $scriptDir;
} else {
    $errorMsg = 'Gagal memasang cron job: ' . implode("\n", $output);
    if (!$crontabAvailable) {
        $errorMsg .= "\n\nCron mungkin belum terinstall. Silakan install manual:\n";
        $platform = detectPlatform();
        switch ($platform) {
            case 'termux':
                $errorMsg .= "  pkg install -y cronie\n  sv-enable crond\n  sv start crond";
                break;
            case 'alpine':
                $errorMsg .= "  apk add dcron\n  rc-service crond start";
                break;
            case 'vps':
                $errorMsg .= "  apt-get install -y cron\n  systemctl start cron";
                break;
        }
    }
    $result['error'] = $errorMsg;
    $result['message'] = 'Silakan setup manual dengan menjalankan: php check_cron.php';
}

echo json_encode($result, JSON_PRETTY_PRINT);

