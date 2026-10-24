<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireAdmin();

$hari_ini = date('Y-m-d');
$last_login = $_SESSION['last_login_time'] ?? '1970-01-01 00:00:00';

// 1. Hitung Total Pemasukan Hari Ini (Hanya yang tidak batal)
$stmt = $pdo->prepare("SELECT SUM(total) as total FROM penjualan WHERE DATE(created_at) = ? AND status != 'batal'");
$stmt->execute([$hari_ini]);
$pemasukan_hari_ini = $stmt->fetch()['total'] ?? 0;

// 2. Hitung Total HPP Hari Ini
$stmt = $pdo->prepare("
    SELECT SUM(p.jumlah * r.jumlah * b.harga_satuan) as total_hpp
    FROM penjualan p
    JOIN resep r ON p.produk_id = r.produk_id
    JOIN bahan_baku b ON r.bahan_baku_id = b.id
    WHERE DATE(p.created_at) = ? AND p.status != 'batal'
");
$stmt->execute([$hari_ini]);
$hpp_hari_ini = $stmt->fetch()['total_hpp'] ?? 0;

// 3. Hitung Laba Kotor Hari Ini
$laba_kotor = $pemasukan_hari_ini - $hpp_hari_ini;

// 4. Ambil Bahan Baku yang Menipis
$stok_menipis = $pdo->query("SELECT * FROM bahan_baku WHERE stok <= stok_minimum")->fetchAll();

// 5. Rincian Menu Terjual Hari Ini
$stmt = $pdo->prepare("
    SELECT pr.nama, SUM(p.jumlah) as total_porsi 
    FROM penjualan p 
    JOIN produk pr ON p.produk_id = pr.id 
    WHERE DATE(p.created_at) = ? AND p.status != 'batal'
    GROUP BY p.produk_id
");
$stmt->execute([$hari_ini]);
$menu_terjual = $stmt->fetchAll();

// 6. Ambil 10 Transaksi Terakhir (Hanya yang sukses, agar batal tidak masuk list)
$transaksi_terakhir = $pdo->query("
    SELECT p.*, pr.nama as produk_nama, u.nama as penjual_nama 
    FROM penjualan p 
    JOIN produk pr ON p.produk_id = pr.id 
    JOIN users u ON p.user_id = u.id 
    WHERE p.status != 'batal'
    ORDER BY p.created_at DESC LIMIT 10
")->fetchAll();

$pageTitle = 'Dashboard Admin';
include __DIR__ . '/../includes/header.php';
?>

<?php if (!empty($stok_menipis)): ?>
    <div class="alert alert-danger" style="display:flex; justify-content:space-between; align-items:center;">
        <span>⚠️ <strong>Peringatan!</strong> Ada <?= count($stok_menipis) ?> bahan baku yang stoknya menipis/habis.</span>
        <a href="restock.php" class="btn btn-danger btn-sm">Cek Pengajuan Restock</a>
    </div>
<?php endif; ?>

<div class="grid grid-3" style="margin-bottom:20px;">
    <div class="stat fade-in" style="animation-delay: 0.1s;">
        <div class="label">Pemasukan Hari Ini</div>
        <div class="value" style="color:#28a745;"><?= rupiah($pemasukan_hari_ini) ?></div>
    </div>
    <div class="stat fade-in" style="border-left-color: #17a2b8; animation-delay: 0.2s;">
        <div class="label">Laba Kotor Hari Ini</div>
        <div class="value"><?= rupiah($laba_kotor) ?></div>
    </div>
    <div class="stat fade-in" style="border-left-color: #ffc107; animation-delay: 0.3s;">
        <div class="label">Bahan Baku Menipis</div>
        <div class="value" style="color:<?= count($stok_menipis) > 0 ? '#dc3545' : '#2c3e50' ?>;">
            <?= count($stok_menipis) ?> Bahan
        </div>
    </div>
</div>

<div style="display:grid; grid-template-columns: 2fr 1fr; gap:20px;" class="responsive-grid">
    <div>
        <div class="card fade-in" style="animation-delay: 0.4s;">
            <h3>🍽️ Rincian Menu Terjual Hari Ini</h3>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr><th>Nama Menu</th><th>Total Porsi Terjual</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($menu_terjual as $m): ?>
                        <tr>
                            <td><strong><?= e($m['nama']) ?></strong></td>
                            <td><span class="badge badge-success"><?= $m['total_porsi'] ?> Porsi</span></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($menu_terjual)): ?><tr><td colspan="2" style="text-align:center;">Belum ada menu yang terjual hari ini.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card fade-in" style="animation-delay: 0.5s;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px;">
                <h3 style="margin:0;">🧾 Transaksi Terakhir</h3>
                <a href="penjualan.php" class="btn btn-sm" style="background:#e2e8f0; color:#1e293b;">Lihat Semua</a>
            </div>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr><th>Waktu</th><th>Penjual</th><th>Menu</th><th>Total</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($transaksi_terakhir as $t): 
                            $is_new = ($t['created_at'] > $last_login);
                        ?>
                        <tr style="<?= $is_new ? 'background-color: #fff7ed;' : '' ?>">
                            <td>
                                <?= date('H:i', strtotime($t['created_at'])) ?>
                                <?php if ($is_new): ?><span class="badge badge-danger" style="font-size:0.6rem; padding:2px 6px; margin-left:5px; animation: pulse 2s infinite;">NEW</span><?php endif; ?>
                            </td>
                            <td><span class="badge" style="background:#e2e8f0; color:#475569;"><?= e($t['penjual_nama']) ?></span></td>
                            <td><strong><?= e($t['produk_nama']) ?></strong> (<?= $t['jumlah'] ?>x)</td>
                            <td style="font-weight:bold;"><?= rupiah($t['total']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($transaksi_terakhir)): ?><tr><td colspan="4" style="text-align:center;">Belum ada transaksi.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div>
        <div class="card fade-in" style="animation-delay: 0.6s;">
            <h3>⚠️ Stok Perlu Dibeli</h3>
            <?php if (!empty($stok_menipis)): ?>
                <ul style="list-style:none; padding:0;">
                    <?php foreach ($stok_menipis as $s): ?>
                        <li style="padding:12px 0; border-bottom:1px solid #f1f5f9; display:flex; justify-content:space-between; align-items:center;">
                            <span><strong><?= e($s['nama']) ?></strong></span>
                            <span class="badge badge-danger">Sisa <?= (float)$s['stok'] ?> <?= e($s['satuan']) ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <a href="bahan_baku.php" class="btn btn-primary btn-block" style="margin-top:20px;">Kelola Bahan Baku</a>
            <?php else: ?>
                <div class="alert alert-success" style="text-align:center; margin:0;">✅ Semua stok aman terkendali.</div>
            <?php endif; ?>
        </div>
    </div>
</div>
<style>
@keyframes pulse { 0% { opacity: 1; } 50% { opacity: 0.5; } 100% { opacity: 1; } }
@media (max-width: 992px) { .responsive-grid { grid-template-columns: 1fr !important; } }
</style>
<?php include __DIR__ . '/../includes/footer.php'; ?>