<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireSuperAdmin();

$total_admin = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn();
$total_penjual = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'penjual'")->fetchColumn();

$pageTitle = 'Dashboard Super Admin';
include __DIR__ . '/../includes/header.php';
?>

<div class="alert alert-success fade-in">
    Selamat datang, <strong>Super Admin</strong>! Di sini Anda bisa mengelola akun Admin dan Penjual.
</div>

<div class="grid grid-3" style="margin-bottom:20px;">
    <div class="stat fade-in">
        <div class="label">Total Admin</div>
        <div class="value"><?= $total_admin ?> <span style="font-size:1rem; font-weight:400;">Akun</span></div>
    </div>
    <div class="stat fade-in" style="border-left-color: #28a745;">
        <div class="label">Total Penjual</div>
        <div class="value"><?= $total_penjual ?> <span style="font-size:1rem; font-weight:400;">Akun</span></div>
    </div>
</div>

<div class="card fade-in">
    <h3>Aksi Cepat</h3>
    <div style="display:flex; gap:15px; flex-wrap:wrap;">
        <a href="kelola_akun.php" class="btn btn-primary">👥 Kelola Akun Pengguna</a>
        
        <!-- TOMBOL BACKUP DATABASE -->
        <a href="backup.php" class="btn" style="background:#dc3545; color:#fff;" onclick="return confirm('Mulai unduh backup database? Proses ini mungkin memakan waktu beberapa detik.')">
            🗄️ Backup Database (.sql)
        </a>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>