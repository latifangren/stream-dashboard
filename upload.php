<?php
session_start();
if (!isset($_SESSION['user'])) {
    http_response_code(403);
    exit("🚫 Akses ditolak.");
}

$username = $_SESSION['user'];
$targetDir = "users/$username/videos/";

if (!is_dir($targetDir)) {
    mkdir($targetDir, 0755, true);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['video'])) {
    $file = $_FILES['video'];
    
    if ($file['error'] === UPLOAD_ERR_OK) {
        $filename = basename($file['name']);
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        // Hanya izinkan file .mp4
        if ($ext !== 'mp4') {
            echo "❌ Hanya file .mp4 yang diperbolehkan.";
            exit;
        }

        $destination = $targetDir . $filename;

        if (move_uploaded_file($file['tmp_name'], $destination)) {
            header("Location: index.php#gallery");
            exit;
        } else {
            echo "❌ Gagal memindahkan file.";
        }
    } else {
        echo "❌ Upload gagal. Error code: " . $file['error'];
    }
} else {
    echo "❌ Akses tidak valid.";
}