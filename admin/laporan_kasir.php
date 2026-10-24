<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireAdmin();

$tgl_mulai = $_GET['tgl_mulai'] ?? date('Y-m-01');
$tgl_akhir = $_GET['tgl_akhir'] ?? date('Y-m-d');

// 1. Hitung Total Pemasukan (Hanya yang tidak batal)
$stmt = $pdo->prepare("SELECT SUM(total) as total FROM penjualan WHERE DATE(created_at) BETWEEN ? AND ? AND status != 'batal'");
$stmt->execute([$tgl_mulai, $tgl_akhir]);
$pemasukan = $stmt->fetch()['total'] ?? 0;

// 2. Hitung HPP
$stmt = $pdo->prepare("
    SELECT SUM(p.jumlah * r.jumlah * b.harga_satuan) as total_hpp
    FROM penjualan p
    JOIN resep r ON p.produk_id = r.produk_id
    JOIN bahan_baku b ON r.bahan_baku_id = b.id
    WHERE DATE(p.created_at) BETWEEN ? AND ? AND p.status != 'batal'
");
$stmt->execute([$tgl_mulai, $tgl_akhir]);
$hpp = $stmt->fetch()['total_hpp'] ?? 0;

// 3. Hitung Total Pengeluaran Operasional
$stmt = $pdo->prepare("SELECT SUM(jumlah) as total FROM pengeluaran WHERE DATE(created_at) BETWEEN ? AND ?");
$stmt->execute([$tgl_mulai, $tgl_akhir]);
$pengeluaran = $stmt->fetch()['total'] ?? 0;

// 4. Kalkulasi Laba
$laba_kotor = $pemasukan - $hpp;
$laba_bersih = $laba_kotor - $pengeluaran;

$pageTitle = 'Laporan Keuangan & Laba';
include __DIR__ . '/../includes/header.php';
?>

<div class="card fade-in">
    <h3>Filter Laporan</h3>
    <form method="GET" class="grid grid-3" style="grid-template-columns: 1fr 1fr 2fr;">
        <div class="form-group"><label>Tanggal Mulai</label><input type="date" name="tgl_mulai" value="<?= $tgl_mulai ?>" required></div>
        <div class="form-group"><label>Tanggal Akhir</label><input type="date" name="tgl_akhir" value="<?= $tgl_akhir ?>" required></div>
        <div class="form-group" style="display:flex; align-items:flex-end; gap: 10px;">
            <button type="submit" class="btn btn-primary" style="flex: 1;">Tampilkan</button>
            <a href="cetak_laporan.php?tgl_mulai=<?= $tgl_mulai ?>&tgl_akhir=<?= $tgl_akhir ?>" target="_blank" class="btn" style="background:#dc3545; color:#fff; flex: 1;">🖨️ PDF</a>
            <a href="export_excel.php?tgl_mulai=<?= $tgl_mulai ?>&tgl_akhir=<?= $tgl_akhir ?>" class="btn" style="background:#16a34a; color:#fff; flex: 1;">📊 Excel</a>
        </div>
    </form>
</div>

<div class="grid grid-3" style="margin-bottom:20px;">
    <div class="stat fade-in"><div class="label">Total Pemasukan (Penjualan)</div><div class="value" style="color:#28a745;"><?= rupiah($pemasukan) ?></div></div>
    <div class="stat fade-in" style="border-left-color: #dc3545;"><div class="label">Total Pengeluaran Operasional</div><div class="value" style="color:#dc3545;"><?= rupiah($pengeluaran) ?></div></div>
    <div class="stat fade-in" style="border-left-color: #0ea5e9;"><div class="label">HPP (Modal Bahan Terpakai)</div><div class="value"><?= rupiah($hpp) ?></div></div>
</div>

<div class="card fade-in">
    <h3>Rincian Laba</h3>
    <div class="table-responsive">
        <table style="font-size: 1.05rem;">
            <tbody>
                <tr><td style="width: 70%;"><strong>Pemasukan Kotor</strong></td><td style="text-align: right; color: #28a745;"><strong><?= rupiah($pemasukan) ?></strong></td></tr>
                <tr><td>Dikurangi: HPP (Modal Bahan Baku)</td><td style="text-align: right; color: #dc3545;">- <?= rupiah($hpp) ?></td></tr>
                <tr style="background: #f8fafc; border-top: 2px solid #cbd5e1;"><td><strong>LABA KOTOR</strong></td><td style="text-align: right; font-weight: bold; color: #1e293b; font-size:1.2rem;"><?= rupiah($laba_kotor) ?></td></tr>
                <tr><td>Dikurangi: Pengeluaran Operasional</td><td style="text-align: right; color: #dc3545;">- <?= rupiah($pengeluaran) ?></td></tr>
                <tr style="background: #dcfce7; border-top: 2px solid #22c55e;"><td><strong>LABA BERSIH</strong></td><td style="text-align: right; font-weight: bold; color: #166534; font-size:1.3rem;"><?= rupiah($laba_bersih) ?></td></tr>
            </tbody>
        </table>
    </div>
</div>

<?php
// Ambil data dropdown
$stmt = $pdo->prepare("SELECT b.nama as bahan_nama, b.satuan, b.harga_satuan, SUM(p.jumlah * r.jumlah) as total_terpakai, SUM(p.jumlah * r.jumlah * b.harga_satuan) as total_biaya FROM penjualan p JOIN resep r ON p.produk_id = r.produk_id JOIN bahan_baku b ON r.bahan_baku_id = b.id WHERE DATE(p.created_at) BETWEEN ? AND ? AND p.status != 'batal' GROUP BY b.id ORDER BY total_biaya DESC");
$stmt->execute([$tgl_mulai, $tgl_akhir]); $detail_hpp = $stmt->fetchAll();

$stmt = $pdo->prepare("SELECT * FROM pengeluaran WHERE DATE(created_at) BETWEEN ? AND ? ORDER BY created_at ASC");
$stmt->execute([$tgl_mulai, $tgl_akhir]); $detail_pengeluaran = $stmt->fetchAll();

$stmt = $pdo->prepare("SELECT p.*, pr.nama as produk_nama FROM penjualan p JOIN produk pr ON p.produk_id = pr.id WHERE DATE(p.created_at) BETWEEN ? AND ? AND p.status != 'batal' ORDER BY p.created_at ASC");
$stmt->execute([$tgl_mulai, $tgl_akhir]); $detail_penjualan = $stmt->fetchAll();
?>

<div class="card fade-in"><details><summary style="cursor: pointer; font-weight: bold; font-size: 1.1rem; color: #1e293b; padding: 10px; background: #f8fafc; border-radius: 8px; border: 1px solid #e2e8f0;">🥩 Lihat Rincian Pemakaian Bahan Baku (HPP)</summary><div style="margin-top: 15px;"><table><thead><tr><th>Nama Bahan</th><th>Total Terpakai</th><th>Harga Satuan</th><th style="text-align: right;">Total Biaya</th></tr></thead><tbody><?php foreach ($detail_hpp as $dh): ?><tr><td><strong><?= e($dh['bahan_nama']) ?></strong></td><td><?= (float)$dh['total_terpakai'] ?> <?= e($dh['satuan']) ?></td><td><?= rupiah($dh['harga_satuan']) ?> / <?= e($dh['satuan']) ?></td><td style="text-align: right; color: #dc3545; font-weight: bold;"><?= rupiah($dh['total_biaya']) ?></td></tr><?php endforeach; ?><?php if (empty($detail_hpp)): ?><tr><td colspan="4" style="text-align: center;">Tidak ada data.</td></tr><?php endif; ?></tbody></table></div></details></div>

<div class="card fade-in"><details><summary style="cursor: pointer; font-weight: bold; font-size: 1.1rem; color: #1e293b; padding: 10px; background: #f8fafc; border-radius: 8px; border: 1px solid #e2e8f0;">📉 Lihat Rincian Pengeluaran Operasional</summary><div style="margin-top: 15px;"><table><thead><tr><th>Tanggal</th><th>Keterangan</th><th style="text-align: right;">Jumlah</th></tr></thead><tbody><?php foreach ($detail_pengeluaran as $dp): ?><tr><td><?= date('d/m/Y H:i', strtotime($dp['created_at'])) ?></td><td><?= e($dp['keterangan']) ?></td><td style="text-align: right; color: #dc3545;"><?= rupiah($dp['jumlah']) ?></td></tr><?php endforeach; ?><?php if (empty($detail_pengeluaran)): ?><tr><td colspan="3" style="text-align: center;">Tidak ada data.</td></tr><?php endif; ?></tbody></table></div></details></div>

<div class="card fade-in"><details><summary style="cursor: pointer; font-weight: bold; font-size: 1.1rem; color: #1e293b; padding: 10px; background: #f8fafc; border-radius: 8px; border: 1px solid #e2e8f0;">📈 Lihat Rincian Transaksi Penjualan</summary><div style="margin-top: 15px;"><table><thead><tr><th>Tanggal</th><th>Menu</th><th>Qty</th><th style="text-align: right;">Total</th></tr></thead><tbody><?php foreach ($detail_penjualan as $pj): ?><tr><td><?= date('d/m/Y H:i', strtotime($pj['created_at'])) ?></td><td><?= e($pj['produk_nama']) ?></td><td><?= $pj['jumlah'] ?> Porsi</td><td style="text-align: right; color: #28a745;"><?= rupiah($pj['total']) ?></td></tr><?php endforeach; ?><?php if (empty($detail_penjualan)): ?><tr><td colspan="4" style="text-align: center;">Tidak ada data.</td></tr><?php endif; ?></tbody></table></div></details></div>

<?php include __DIR__ . '/../includes/footer.php'; ?>