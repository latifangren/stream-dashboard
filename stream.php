<?php
// Atur timezone
date_default_timezone_set("Asia/Jakarta");

// Deteksi mode: CLI atau Web
$isCLI = (php_sapi_name() === 'cli');

if ($isCLI) {
    // Mode CLI (dari run_schedule.php)
    $username = $argv[1] ?? '';
    $platform = $argv[2] ?? 'youtube';
    $key = $argv[3] ?? '';
    $video = $argv[4] ?? '';
    $quality = $argv[5] ?? 'medium';
    $loop = isset($argv[6]) && $argv[6] === '1';
    $encoder_type = $argv[7] ?? 'cpu';
    $preset = $argv[8] ?? 'ultrafast';
    $duration_hours = $argv[9] ?? null;
} else {
    // Mode Web
    session_start();
    if (!isset($_SESSION['user'])) {
        http_response_code(403);
        exit("🚫 Akses ditolak.");
    }

    $username = $_SESSION['user'];
    $platform = $_POST['platform'] ?? 'youtube';
    $key = trim($_POST['stream_key']);
    $video = trim($_POST['video_file']);
    $quality = $_POST['quality'] ?? 'medium';
    $loop = isset($_POST['looping']);
    $encoder_type = $_POST['encoder_type'] ?? 'cpu';
    $preset = $_POST['preset'] ?? 'ultrafast';
    $duration_hours = $_POST['duration_hours'] ?? null;
}

$basePath = "users/$username/";
$debugFile = $basePath . "debug.txt";

// Validasi path
if (!is_dir($basePath)) mkdir($basePath, 0755, true);
if (!is_dir($basePath . "videos")) mkdir($basePath . "videos", 0755, true);
if (strpos($video, $basePath . 'videos/') !== 0 || !file_exists($video)) {
    file_put_contents($debugFile, "❌ Video tidak valid: $video\n");
    exit("❌ File video tidak valid.");
}

// Mapping platform ke URL RTMP
switch ($platform) {
    case 'facebook':
        $rtmp = "rtmps://live-api-s.facebook.com:443/rtmp/$key";
        break;
    case 'twitch':
        $rtmp = "rtmp://live.twitch.tv/app/$key";
        break;
    case 'custom':
        $rtmp = $key;
        break;
    case 'youtube':
    default:
        $rtmp = "rtmp://a.rtmp.youtube.com/live2/$key";
        break;
}

// Kualitas
switch ($quality) {
    case 'low':
        $video_bitrate = "1000k";
        $audio_bitrate = "96k";
        $bufsize = "2000k";
        break;
    case 'high':
        $video_bitrate = "6000k";  // Dinaikkan dari 4000k untuk kualitas HD/Full HD lebih baik
        $audio_bitrate = "192k";   // Dinaikkan dari 160k untuk audio lebih jernih
        $bufsize = "12000k";       // Dinaikkan dari 8000k (2x video bitrate)
        break;
    default:
        $video_bitrate = "2000k";
        $audio_bitrate = "128k";
        $bufsize = "4000k";
        break;
}

// Validasi durasi (jam)
$duration_hours = is_numeric($duration_hours) ? (int)$duration_hours : 1;
if ($duration_hours < 1) {
    $duration_hours = 1;
}
if ($duration_hours > 24) {
    $duration_hours = 24;
}
$duration_seconds = $duration_hours * 3600;
$duration_param = $duration_seconds > 0 ? "-t $duration_seconds" : "";

// Cari slot kosong (status-1.json / status-2.json)
$slot = null;
for ($i = 1; $i <= 2; $i++) {
    $statusFile = $basePath . "status-$i.json";
    if (!file_exists($statusFile)) {
        $slot = $i;
        break;
    }
    $status = json_decode(file_get_contents($statusFile), true);
    if (!isset($status['pid']) || !posix_kill((int)$status['pid'], 0)) {
        $slot = $i;
        break;
    }
}

if (!$slot) {
    exit("❌ Maksimal 2 streaming aktif. Stop salah satu terlebih dahulu.");
}

// Siapkan file log dan status
$logFile = $basePath . "log-$slot.txt";
$statusFile = $basePath . "status-$slot.json";
$loopFlag = $loop ? "-stream_loop -1" : "";

// Validasi preset
$valid_presets = ['ultrafast', 'superfast', 'veryfast', 'faster', 'fast', 'medium', 'slow', 'slower', 'veryslow'];
if (!in_array($preset, $valid_presets)) {
    $preset = 'ultrafast'; // fallback ke default
}

// Pilih encoder berdasarkan encoder_type
if ($encoder_type === 'gpu') {
    // Auto-detect hardware encoder yang tersedia
    // Prioritas: Android MediaCodec dulu (untuk Termux Android), lalu encoder lainnya
    $hw_encoders = [
        'h264_mediacodec' => 'Android MediaCodec (GPU Hardware)',
        'h264_vulkan' => 'Vulkan H.264 (GPU Hardware)',
        'h264_nvenc' => 'NVIDIA (NVENC)',
        'h264_qsv' => 'Intel Quick Sync (QSV)',
        'h264_v4l2m2m' => 'V4L2 M2M (Raspberry Pi/Android)',
        'h264_omx' => 'OpenMAX (Raspberry Pi)',
        'h264_videotoolbox' => 'VideoToolbox (macOS)'
    ];
    
    $video_encoder = null;
    $encoder_name = "Unknown";
    
    // Test encoder yang tersedia - kompatibel dengan Termux Android
    $encoders_output = shell_exec("ffmpeg -hide_banner -encoders 2>&1");
    
    if ($encoders_output) {
        foreach ($hw_encoders as $enc => $name) {
            if (stripos($encoders_output, $enc) !== false) {
                $video_encoder = $enc;
                $encoder_name = $name;
                break;
            }
        }
    }
    
    // Jika tidak ada hardware encoder yang ditemukan, fallback ke libx264
    if (!$video_encoder) {
        file_put_contents($debugFile, "⚠️ Hardware encoder tidak ditemukan, menggunakan libx264 (CPU)\n", FILE_APPEND);
        $video_encoder = "libx264";
        $encoder_name = "libx264 (CPU - fallback)";
        $preset_param = "-preset " . $preset;
        $crf_param = ""; // Untuk CPU, gunakan maxrate/bufsize
    } else {
        // Untuk hardware encoder, gunakan preset/parameter yang sesuai
        if ($video_encoder === 'h264_mediacodec') {
            // Android MediaCodec (Termux) - menggunakan hardware encoder Android
            // MediaCodec tidak menggunakan preset seperti libx264
            // Akan otomatis menggunakan GPU/VPU hardware Android untuk encoding
            // Banyak perangkat Android tidak mendukung yuv420p, gunakan nv12
            $preset_param = ""; // MediaCodec tidak menggunakan preset
            $pix_fmt = "nv12"; // MediaCodec umumnya memerlukan nv12 bukan yuv420p
            // MediaCodec: gunakan bitrate eksplisit (MediaCodec tidak mendukung -rc:v)
            // Gunakan -b:v untuk target bitrate dan -maxrate untuk kontrol bitrate
            $mediacodec_params = "-b:v $video_bitrate -maxrate $video_bitrate -bufsize $bufsize";
        } elseif ($video_encoder === 'h264_vulkan') {
            // Vulkan encoder (Android/Termux) - GPU hardware acceleration via Vulkan
            // Tidak menggunakan preset seperti libx264
            $preset_param = ""; // Vulkan encoder tidak menggunakan preset
            $pix_fmt = "yuv420p"; // Vulkan biasanya mendukung yuv420p
            $mediacodec_params = "";
        } elseif ($video_encoder === 'h264_nvenc') {
            // NVIDIA NVENC preset - gunakan llhq (low latency high quality) untuk streaming
            $preset_param = "-preset llhq -tune zerolatency";
            $pix_fmt = "yuv420p";
            $mediacodec_params = "";
        } elseif ($video_encoder === 'h264_qsv') {
            // Intel QSV preset
            $preset_param = "-preset veryfast";
            $pix_fmt = "yuv420p";
            $mediacodec_params = "";
        } elseif ($video_encoder === 'h264_v4l2m2m' || $video_encoder === 'h264_omx') {
            // Raspberry Pi / Android encoders - tidak ada preset
            $preset_param = "";
            $pix_fmt = "yuv420p";
            $mediacodec_params = "";
        } else {
            // Encoder hardware lainnya - tidak ada preset
            $preset_param = "";
            $pix_fmt = "yuv420p";
            $mediacodec_params = "";
        }
        $crf_param = ""; // Hardware encoder menggunakan bitrate
    }
} else {
    // CPU encoder menggunakan libx264
    $video_encoder = "libx264";
    $encoder_name = "libx264 (CPU)";
    $preset_param = "-preset " . $preset;
    $crf_param = ""; // Untuk live streaming, gunakan bitrate bukan CRF
    $pix_fmt = "yuv420p"; // libx264 menggunakan yuv420p
    $mediacodec_params = "";
}

// Pastikan pix_fmt sudah didefinisikan
if (!isset($pix_fmt)) {
    $pix_fmt = "yuv420p"; // Fallback default
}
if (!isset($mediacodec_params)) {
    $mediacodec_params = "";
}

// Perintah ffmpeg untuk live streaming
// Gunakan format yang mirip dengan contoh yang berhasil, tapi disesuaikan untuk streaming
// Kompatibel dengan Termux Android
// Note: Android MediaCodec memerlukan pix_fmt nv12, bukan yuv420p
if ($video_encoder === 'h264_mediacodec') {
    // MediaCodec: gunakan parameter khusus untuk bitrate yang tepat
    // MediaCodec tidak mendukung -rc:v, jadi gunakan -b:v dan -maxrate saja
    $cmd = "ffmpeg -re $loopFlag -i \"$video\" -c:v $video_encoder" . 
           " -b:v $video_bitrate" .  // Target bitrate
           " -maxrate $video_bitrate" .  // Maximum bitrate
           " -bufsize $bufsize" .
           " -pix_fmt $pix_fmt" .
           " -g 50" . // GOP size
           ($duration_param ? " $duration_param" : "") .
           " -profile:v baseline" . // Profile baseline untuk kompatibilitas lebih baik
           " -level 4.0" . // Level H.264
           " -c:a aac -b:a $audio_bitrate -ar 44100 -ac 2" .
           " -f flv \"$rtmp\" > \"$logFile\" 2>&1 & echo \$!";
} else {
    // Encoder lainnya: gunakan format standar
    $cmd = "ffmpeg -re $loopFlag -i \"$video\" -c:v $video_encoder" . 
           ($preset_param ? " $preset_param" : "") . 
           " -b:v $video_bitrate" .  // Bitrate video eksplisit
           " -maxrate $video_bitrate" .  // Maximum bitrate
           " -bufsize $bufsize" .
           " -pix_fmt $pix_fmt" .
           " -g 50" . // GOP size
           ($duration_param ? " $duration_param" : "") .
           " -c:a aac -b:a $audio_bitrate -ar 44100 -ac 2" .
           " -f flv \"$rtmp\" > \"$logFile\" 2>&1 & echo \$!";
}
$pid = trim(shell_exec($cmd));

// Tulis debug
file_put_contents($debugFile, "📄 CMD: $cmd\n🎥 File: $video\n📦 RTMP: $rtmp\n⚙️ Encoder: $encoder_name\n🎛️ Preset: " . ($preset_param ? $preset_param : ($encoder_type === 'gpu' ? 'N/A (Hardware)' : $preset)) . "\n⏱️ Durasi: {$duration_hours} jam\n🔁 Loop: " . ($loop ? "Ya" : "Tidak") . "\n🎯 PID: $pid\n");

// Simpan status
if (trim($pid)) {
    file_put_contents($statusFile, json_encode([
        "pid" => trim($pid),
        "key" => $key,
        "platform" => $platform,
        "video" => $video,
        "quality" => $quality,
        "loop" => $loop,
        "encoder_type" => $encoder_type,
        "encoder_name" => $encoder_name,
        "preset" => $encoder_type === 'cpu' ? $preset : ($preset_param ? $preset_param : null),
        "duration_hours" => $duration_hours
    ]));

    if (!$isCLI) {
        header("Location: index.php#streaming");
        exit;
    }
} else {
    file_put_contents($logFile, "❌ Gagal menjalankan ffmpeg atau PID kosong.\nPerintah: $cmd");
    exit("❌ Streaming gagal dijalankan.");
}