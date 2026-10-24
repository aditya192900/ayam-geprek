<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requirePenjual();

// Ambil bahan baku yang stoknya menipis
$stmt = $pdo->query("SELECT * FROM bahan_baku WHERE stok <= stok_minimum");
$stok_menipis = $stmt->fetchAll();

// Ambil total penjualan hari ini oleh user ini
$stmt = $pdo->prepare("SELECT COUNT(*) as total_transaksi, SUM(jumlah) as total_porsi FROM penjualan WHERE user_id = ? AND DATE(created_at) = CURDATE()");
$stmt->execute([$_SESSION['user_id']]);
$hari_ini = $stmt->fetch();

$pageTitle = 'Dashboard Penjual';
include __DIR__ . '/../includes/header.php';
?>

<?php if (!empty($stok_menipis)): ?>
    <div class="alert alert-danger fade-in">
        ⚠️ <strong>Perhatian!</strong> Ada <?= count($stok_menipis) ?> bahan baku yang stoknya menipis/habis. Segera ajukan restock ke Admin!
    </div>
<?php endif; ?>

<div class="grid grid-3" style="margin-bottom:20px;">
    <div class="stat fade-in" style="animation-delay: 0.1s;">
        <div class="label">Transaksi Saya Hari Ini</div>
        <div class="value"><?= $hari_ini['total_transaksi'] ?? 0 ?> <span style="font-size:1rem; font-weight:400; color:#64748b;">Kali</span></div>
    </div>
    <div class="stat fade-in" style="border-left-color: #28a745; animation-delay: 0.2s;">
        <div class="label">Porsi Terjual Hari Ini</div>
        <div class="value"><?= $hari_ini['total_porsi'] ?? 0 ?> <span style="font-size:1rem; font-weight:400; color:#64748b;">Porsi</span></div>
    </div>
</div>

<div class="card fade-in" style="animation-delay: 0.3s;">
    <h3>Aksi Cepat</h3>
    <p style="color:#64748b; margin-bottom:20px;">Akses cepat untuk melayani pelanggan.</p>
    <div style="display:flex; gap:15px; flex-wrap:wrap;">
        <a href="penjualan.php" class="btn btn-primary">➕ Catat Penjualan Baru</a>
        <a href="pengajuan.php" class="btn" style="background:#0ea5e9; color:#fff;">🔄 Ajukan Restock Bahan</a>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>