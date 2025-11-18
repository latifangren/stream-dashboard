<?php
session_start();
if (!isset($_SESSION['user'])) {
  header("Location: login.php");
  exit;
}

$username = $_SESSION['user'];
$basePath = "users/$username/";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['file'])) {
    $file = $_POST['file'];
    $realFile = realpath($file);
    $expectedDir = realpath($basePath . "videos");

    // Cek agar tidak bisa hapus file di luar folder pengguna
    if ($realFile && strpos($realFile, $expectedDir) === 0 && file_exists($realFile)) {
        unlink($realFile);
    }
}

header("Location: index.php#gallery");
exit;