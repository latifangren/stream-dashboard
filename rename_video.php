<?php
session_start();
if (!isset($_SESSION['user'])) {
  die("Akses ditolak");
}

$username = $_SESSION['user'];
$basePath = "users/$username/videos/";

if (isset($_POST['old_name'], $_POST['new_name'])) {
    $oldName = basename($_POST['old_name']);
    $newName = basename($_POST['new_name']);

    // Pastikan file berekstensi .mp4
    if (pathinfo($newName, PATHINFO_EXTENSION) !== 'mp4') {
        $newName .= ".mp4";
    }

    $oldPath = $basePath . $oldName;
    $newPath = $basePath . $newName;

    if (file_exists($oldPath)) {
        if (!file_exists($newPath)) {
            rename($oldPath, $newPath);
        } else {
            echo "Nama file sudah digunakan.";
            exit;
        }
    }
}

header("Location: index.php");
exit;