<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requirePenjual();

$user_id = $_SESSION['user_id'];
$tgl_mulai = $_GET['tgl_mulai'] ?? date('Y-m-d');
$tgl_akhir = $_GET['tgl_akhir'] ?? date('Y-m-d');

// 1. Total Uang Masuk (Hanya yang tidak batal)
$stmt = $pdo->prepare("SELECT SUM(total) as total FROM penjualan WHERE user_id = ? AND DATE(created_at) BETWEEN ? AND ? AND status != 'batal'");
$stmt->execute([$user_id, $tgl_mulai, $tgl_akhir]);
$total_penjualan = $stmt->fetch()['total'] ?? 0;

// 2. Total Uang Keluar
$stmt = $pdo->prepare("SELECT SUM(total_biaya) as total FROM pengajuan_restock WHERE user_id = ? AND status = 'approved' AND DATE(created_at) BETWEEN ? AND ?");
$stmt->execute([$user_id, $tgl_mulai, $tgl_akhir]);
$total_restock = $stmt->fetch()['total'] ?? 0;

$pendapatan_bersih = $total_penjualan - $total_restock;

// 3. Rincian Menu Terjual
$stmt = $pdo->prepare("SELECT pr.nama, pr.kategori, SUM(p.jumlah) as total_qty, SUM(p.total) as total_uang FROM penjualan p JOIN produk pr ON p.produk_id = pr.id WHERE p.user_id = ? AND DATE(p.created_at) BETWEEN ? AND ? AND p.status != 'batal' GROUP BY p.produk_id");
$stmt->execute([$user_id, $tgl_mulai, $tgl_akhir]);
$menu_terjual = $stmt->fetchAll();

// 4. Rincian Pengeluaran Restock
$stmt = $pdo->prepare("SELECT p.*, b.nama as bahan_nama, b.satuan FROM pengajuan_restock p JOIN bahan_baku b ON p.bahan_baku_id = b.id WHERE p.user_id = ? AND p.status = 'approved' AND DATE(p.created_at) BETWEEN ? AND ?");
$stmt->execute([$user_id, $tgl_mulai, $tgl_akhir]);
$riwayat_restock = $stmt->fetchAll();

$pageTitle = 'Laporan Pendapatan Saya';
include __DIR__ . '/../includes/header.php';
?>

<div class="card fade-in">
    <h3>Filter Tanggal Laporan</h3>
    <form method="GET" class="grid grid-3" style="grid-template-columns: 1fr 1fr 1fr;">
        <div class="form-group"><label>Tanggal Mulai</label><input type="date" name="tgl_mulai" value="<?= $tgl_mulai ?>" required></div>
        <div class="form-group"><label>Tanggal Akhir</label><input type="date" name="tgl_akhir" value="<?= $tgl_akhir ?>" required></div>
        <div class="form-group" style="display:flex; align-items:flex-end;"><button type="submit" class="btn btn-primary" style="width:100%;">Tampilkan Laporan</button></div>
    </form>
</div>

<div class="grid grid-3" style="margin-bottom:20px;">
    <div class="stat fade-in"><div class="label">Total Uang Terkumpul (Jualan)</div><div class="value" style="color:#28a745;"><?= rupiah($total_penjualan) ?></div></div>
    <div class="stat fade-in" style="border-left-color: #dc3545;"><div class="label">Potongan Belanja (Restock ACC)</div><div class="value" style="color:#dc3545;">- <?= rupiah($total_restock) ?></div></div>
    <div class="stat fade-in" style="border-left-color: #0ea5e9;"><div class="label">Uang yang Harus Disetor</div><div class="value" style="color:#0ea5e9;"><?= rupiah($pendapatan_bersih) ?></div></div>
</div>

<div style="display:grid; grid-template-columns: 1fr 1fr; gap:20px;" class="responsive-grid">
    <div class="card fade-in"><h3>🍽️ Rincian Terjual</h3><div class="table-responsive"><table><thead><tr><th>Menu</th><th>Terjual</th><th>Total Uang</th></tr></thead><tbody><?php foreach ($menu_terjual as $m): ?><tr><td><span class="badge" style="background:#e2e8f0; color:#475569; font-size:0.65rem;"><?= e($m['kategori'] ?? 'Makanan') ?></span><br><strong><?= e($m['nama']) ?></strong></td><td><span class="badge badge-success"><?= $m['total_qty'] ?> Porsi</span></td><td style="font-weight:bold;"><?= rupiah($m['total_uang']) ?></td></tr><?php endforeach; ?><?php if (empty($menu_terjual)): ?><tr><td colspan="3" style="text-align:center;">Belum ada penjualan.</td></tr><?php endif; ?></tbody></table></div></div>
    <div class="card fade-in"><h3>💸 Rincian Potongan</h3><div class="table-responsive"><table><thead><tr><th>Barang</th><th>Jumlah</th><th>Biaya</th></tr></thead><tbody><?php foreach ($riwayat_restock as $r): ?><tr><td><strong><?= e($r['bahan_nama']) ?></strong></td><td><?= (float)$r['jumlah'] ?> <?= e($r['satuan']) ?></td><td style="color:#dc3545; font-weight:bold;">- <?= rupiah($r['total_biaya']) ?></td></tr><?php endforeach; ?><?php if (empty($riwayat_restock)): ?><tr><td colspan="3" style="text-align:center;">Tidak ada potongan.</td></tr><?php endif; ?></tbody></table></div></div>
</div>
<style>@media (max-width: 992px) { .responsive-grid { grid-template-columns: 1fr !important; } }</style>
<?php include __DIR__ . '/../includes/footer.php'; ?>