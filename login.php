<?php
require_once 'config/database.php';
require_once 'includes/functions.php';
session_start();

if (isset($_SESSION['user_id'])) {
    redirect($_SESSION['role'] === 'admin' ? BASE_URL . 'admin/dashboard.php' : BASE_URL . 'penjual/dashboard.php');
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        $error = 'Username dan password wajib diisi.';
    } else {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            session_regenerate_id(true);
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['nama']    = $user['nama'];
            $_SESSION['role']    = $user['role'];
            $_SESSION['no_wa']   = $user['no_wa'];
            
            // --- LOGIKA BADGE "NEW" ---
            // Simpan waktu login sebelumnya ke session
            $_SESSION['last_login_time'] = $user['last_login']; 
            
            // Update waktu login di database menjadi waktu sekarang
            $update_stmt = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
            $update_stmt->execute([$user['id']]);
            // ---------------------------

            redirect($user['role'] === 'admin' ? BASE_URL . 'admin/dashboard.php' : BASE_URL . 'penjual/dashboard.php');
        } else {
            $error = 'Username atau password salah.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?= APP_NAME ?></title>
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css">
</head>
<body>
<div class="login-wrap">
    <div class="login-box">
        <h1>🍗 <?= APP_NAME ?></h1>
        <p class="subtitle">Silakan login untuk melanjutkan</p>
        <?php if ($error): ?>
            <div class="alert alert-danger"><?= e($error) ?></div>
        <?php endif; ?>
        <form method="POST">
            <div class="form-group">
                <label>Username</label>
                <input type="text" name="username" required autofocus placeholder="Masukkan username">
            </div>
            <div class="form-group">
                <label>Password</label>
                <input type="password" name="password" required placeholder="Masukkan password">
            </div>
            <button type="submit" class="btn btn-primary btn-block">Login Sekarang</button>
        </form>
    </div>
</div>
</body>
</html>