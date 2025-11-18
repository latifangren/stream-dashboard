<?php
session_start();

// Hanya admin bisa akses, tambahkan pengecekan login admin jika perlu
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if ($username === '' || $password === '') {
        die("❌ Username dan password tidak boleh kosong.");
    }

    $usersFile = "users.json";
    $users = file_exists($usersFile) ? json_decode(file_get_contents($usersFile), true) : [];

    if (isset($users[$username])) {
        die("⚠️ Username sudah digunakan.");
    }

    // Simpan user baru
    $users[$username] = [
        "password" => password_hash($password, PASSWORD_DEFAULT),
        "created" => date("Y-m-d H:i:s")
    ];
    file_put_contents($usersFile, json_encode($users, JSON_PRETTY_PRINT));

    // Buat folder dan subfolder user
    $userPath = "users/$username";
    @mkdir($userPath, 0777, true);
    @mkdir("$userPath/videos");
    file_put_contents("$userPath/schedule.json", "[]");

    echo "✅ User '$username' berhasil ditambahkan!";
} else {
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <title>Tambah Pengguna</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="assets/theme.css?v=1" rel="stylesheet">
</head>
<body class="auth-body">
<div class="auth-card wide text-start">
  <h3 class="text-center">➕ Tambah Pengguna Baru</h3>
  <p>Masukkan username & password untuk membuat akun baru.</p>
  <form method="POST">
    <div class="mb-3">
      <label class="form-label">👤 Username</label>
      <input type="text" name="username" class="form-control" required>
    </div>
    <div class="mb-4">
      <label class="form-label">🔒 Password</label>
      <input type="password" name="password" class="form-control" required>
    </div>
    <div class="d-grid gap-2">
      <button type="submit" class="btn btn-success btn-glow">✅ Tambah Pengguna</button>
      <a href="login.php" class="btn btn-outline-light btn-pill">🔙 Kembali</a>
    </div>
  </form>
</div>
</body>
</html>
<?php } ?>