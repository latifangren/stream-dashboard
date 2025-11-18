<?php
session_start();
if (isset($_SESSION['user'])) {
    header("Location: index.php");
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    $usersFile = "users.json";
    if (file_exists($usersFile)) {
        $users = json_decode(file_get_contents($usersFile), true);

        if (isset($users[$username]) && password_verify($password, $users[$username]['password'])) {
            $_SESSION['user'] = $username;
            header("Location: index.php");
            exit;
        } else {
            $error = "❌ Username atau password salah.";
        }
    } else {
        $error = "⚠️ File users.json tidak ditemukan.";
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <title>Login | Streaming Dashboard</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="assets/theme.css?v=1" rel="stylesheet">
</head>
<body class="auth-body">
  <div class="auth-card">
    <h3>🔐 Streaming Dashboard</h3>
    <p>Masuk untuk mulai memantau & menjadwalkan livestream.</p>
    <?php if ($error): ?>
      <div class="alert alert-danger"><?= $error ?></div>
    <?php endif; ?>
    <form method="POST">
      <div class="mb-3">
        <label for="username" class="form-label">👤 Username</label>
        <input type="text" class="form-control" name="username" id="username" required autofocus>
      </div>
      <div class="mb-4">
        <label for="password" class="form-label">🔑 Password</label>
        <input type="password" class="form-control" name="password" id="password" required>
      </div>
      <button type="submit" class="btn btn-primary btn-glow w-100">🚀 Login</button>
    </form>
  </div>
</body>
</html>