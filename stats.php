<?php
header('Content-Type: application/json');

$cacheFile = __DIR__ . '/stats_cache.json';
$cache = [];
$cacheDirty = false;

if (file_exists($cacheFile)) {
    $decoded = json_decode(file_get_contents($cacheFile), true);
    if (is_array($decoded)) {
        $cache = $decoded;
    }
}

function cacheSet($key, $value) {
    global $cache, $cacheDirty;
    $cache[$key] = $value;
    $cacheDirty = true;
}

function getCPUUsage() {
    // Ambil load average via command uptime (aman di Android)
    $out = shell_exec("uptime 2>/dev/null");
    if (!$out) return 0;

    // Ambil load average 1 menit
    if (preg_match('/load average:\s+([\d\.]+),/', $out, $m)) {
        $load1 = floatval($m[1]);

        // Deteksi jumlah core (fallback 4)
        $cores = (int) shell_exec("nproc 2>/dev/null || echo 4");
        if ($cores <= 0) $cores = 4;

        // Rumus konversi loadavg → CPU usage %
        $cpu = ($load1 / $cores) * 100;

        return round($cpu, 1);
    }

    return 0;
}


function getRAMUsage() {
    // Untuk Termux Android, gunakan /proc/meminfo
    if (file_exists('/proc/meminfo')) {
        $meminfo = file_get_contents('/proc/meminfo');
        preg_match('/MemTotal:\s+(\d+)\s+kB/', $meminfo, $totalMatch);
        preg_match('/MemAvailable:\s+(\d+)\s+kB/', $meminfo, $availMatch);
        
        if (isset($totalMatch[1]) && isset($availMatch[1])) {
            $total = (int)$totalMatch[1] * 1024; // Convert to bytes
            $available = (int)$availMatch[1] * 1024; // Convert to bytes
            $used = $total - $available;
            return round($used / $total * 100, 1);
        }
        
        // Fallback jika MemAvailable tidak ada
        preg_match('/MemFree:\s+(\d+)\s+kB/', $meminfo, $freeMatch);
        preg_match('/Buffers:\s+(\d+)\s+kB/', $meminfo, $bufMatch);
        preg_match('/Cached:\s+(\d+)\s+kB/', $meminfo, $cacheMatch);
        
        if (isset($totalMatch[1]) && isset($freeMatch[1])) {
            $total = (int)$totalMatch[1] * 1024;
            $free = (int)$freeMatch[1] * 1024;
            $buffers = isset($bufMatch[1]) ? (int)$bufMatch[1] * 1024 : 0;
            $cached = isset($cacheMatch[1]) ? (int)$cacheMatch[1] * 1024 : 0;
            $used = $total - $free - $buffers - $cached;
            return round($used / $total * 100, 1);
        }
    }
    
    // Fallback ke free command jika tersedia
    $free = shell_exec('free -m 2>/dev/null');
    if ($free) {
        preg_match('/Mem:\s+(\d+)\s+(\d+)/', $free, $matches);
        if (isset($matches[1], $matches[2])) {
            $total = (int)$matches[1];
            $used = (int)$matches[2];
            return round($used / $total * 100, 1);
        }
    }
    
    return 0;
}

function getDiskUsage() {
    // Gunakan PWD atau home directory untuk Termux
    $path = getcwd() ?: (getenv('HOME') ?: '/');
    
    $df = @disk_free_space($path);
    $dt = @disk_total_space($path);
    
    if ($df !== false && $dt !== false && $dt > 0) {
        return round((1 - $df / $dt) * 100, 1);
    }
    
    // Fallback ke df command jika tersedia
    $dfOutput = shell_exec("df -k $path 2>/dev/null | tail -1");
    if ($dfOutput) {
        preg_match('/\s+(\d+)\s+(\d+)\s+(\d+)/', $dfOutput, $matches);
        if (isset($matches[1], $matches[2])) {
            $total = (int)$matches[1] * 1024;
            $used = (int)$matches[2] * 1024;
            return round($used / $total * 100, 1);
        }
    }
    
    return 0;
}

function getProcNetTotals() {
    if (!file_exists('/proc/net/dev')) {
        return null;
    }

    $lines = @file('/proc/net/dev');
    if (!$lines) {
        return null;
    }

    $rxTotal = 0;
    $txTotal = 0;

    foreach ($lines as $line) {
        if (strpos($line, ':') === false) continue;
        [$iface, $data] = array_map('trim', explode(':', $line, 2));
        if ($iface === 'lo' || $iface === '') continue;

        $parts = preg_split('/\s+/', trim($data));
        if (count($parts) >= 16) {
            $rxTotal += (int)$parts[0];
            $txTotal += (int)$parts[8];
        }
    }

    if ($rxTotal === 0 && $txTotal === 0) {
        return null;
    }

    return [$rxTotal, $txTotal];
}

function parseIpBytesLine($line) {
    $line = trim($line);
    if ($line === '') return null;
    $parts = preg_split('/\s+/', $line);
    if (!$parts || !is_numeric($parts[0])) {
        return null;
    }
    return (float)$parts[0];
}

function getIpLinkTotals() {
    $output = shell_exec("ip -s link 2>/dev/null");
    if (!$output) return null;

    $rxTotal = 0;
    $txTotal = 0;
    $lines = explode("\n", $output);
    $count = count($lines);

    for ($i = 0; $i < $count; $i++) {
        if (preg_match('/^\d+:\s*([^:]+):/', $lines[$i], $ifaceMatch)) {
            $iface = trim($ifaceMatch[1]);
            $iface = preg_replace('/@.*/', '', $iface);
            if ($iface === 'lo' || $iface === '') continue;

            for ($j = 1; $j <= 6; $j++) {
                if (!isset($lines[$i + $j])) continue;
                $line = $lines[$i + $j];

                if (preg_match('/RX:\s*bytes/i', $line)) {
                    $dataLine = $lines[$i + $j + 1] ?? '';
                    $value = parseIpBytesLine($dataLine);
                    if ($value !== null) {
                        $rxTotal += $value;
                    }
                }

                if (preg_match('/TX:\s*bytes/i', $line)) {
                    $dataLine = $lines[$i + $j + 1] ?? '';
                    $value = parseIpBytesLine($dataLine);
                    if ($value !== null) {
                        $txTotal += $value;
                    }
                }

                if (preg_match('/RX:\s*bytes\s*(\d+)/i', $line, $rxMatch)) {
                    $rxTotal += (float)$rxMatch[1];
                }
                if (preg_match('/TX:\s*bytes\s*(\d+)/i', $line, $txMatch)) {
                    $txTotal += (float)$txMatch[1];
                }
            }
        }
    }

    if ($rxTotal === 0 && $txTotal === 0) {
        return null;
    }

    return [$rxTotal, $txTotal];
}

function getNetworkTotals() {
    $totals = getProcNetTotals();
    if ($totals) {
        return $totals;
    }
    return getIpLinkTotals();
}

function getNetworkUsage() {
    global $cache, $cacheDirty;

    // Ambil nilai sebelumnya dari cache file
    $prevRx = $cache["prevRx"] ?? 0;
    $prevTx = $cache["prevTx"] ?? 0;
    $prevTime = $cache["prevTime"] ?? 0;

    $totals = getNetworkTotals();
    if (!$totals) {
        return ["rx" => 0, "tx" => 0];
    }

    [$rxTotal, $txTotal] = $totals;

    $now = microtime(true);

    if ($prevTime > 0) {
        $dt = max(0.001, $now - $prevTime); // cegah division by zero

        $rxDelta = max(0, $rxTotal - $prevRx);
        $txDelta = max(0, $txTotal - $prevTx);

        $rxSpeed = ($rxDelta / 1024 / 1024) / $dt;
        $txSpeed = ($txDelta / 1024 / 1024) / $dt;
    } else {
        // Pertama kali = tidak ada speed
        $rxSpeed = 0;
        $txSpeed = 0;
    }

    // Simpan nilai baru ke cache file
    cacheSet("prevRx", $rxTotal);
    cacheSet("prevTx", $txTotal);
    cacheSet("prevTime", $now);

    return [
        "rx" => round($rxSpeed, 2),
        "tx" => round($txSpeed, 2)
    ];
}




echo json_encode([
    "cpu" => getCPUUsage(),
    "mem" => getRAMUsage(),
    "disk" => getDiskUsage(),
    "net" => getNetworkUsage()
]);

if ($cacheDirty) {
    @file_put_contents($cacheFile, json_encode($cache));
}