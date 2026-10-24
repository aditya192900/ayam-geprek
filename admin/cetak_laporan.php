<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireAdmin();

$tgl_mulai = $_GET['tgl_mulai'] ?? date('Y-m-01');
$tgl_akhir = $_GET['tgl_akhir'] ?? date('Y-m-d');

$stmt = $pdo->prepare("SELECT SUM(total) as total FROM penjualan WHERE DATE(created_at) BETWEEN ? AND ? AND status != 'batal'");
$stmt->execute([$tgl_mulai, $tgl_akhir]);
$pemasukan = $stmt->fetch()['total'] ?? 0;

$stmt = $pdo->prepare("SELECT SUM(p.jumlah * r.jumlah * b.harga_satuan) as total_hpp FROM penjualan p JOIN resep r ON p.produk_id = r.produk_id JOIN bahan_baku b ON r.bahan_baku_id = b.id WHERE DATE(p.created_at) BETWEEN ? AND ? AND p.status != 'batal'");
$stmt->execute([$tgl_mulai, $tgl_akhir]);
$hpp = $stmt->fetch()['total_hpp'] ?? 0;

$stmt = $pdo->prepare("SELECT SUM(jumlah) as total FROM pengeluaran WHERE DATE(created_at) BETWEEN ? AND ?");
$stmt->execute([$tgl_mulai, $tgl_akhir]);
$pengeluaran = $stmt->fetch()['total'] ?? 0;

$laba_kotor = $pemasukan - $hpp;
$laba_bersih = $laba_kotor - $pengeluaran;

$stmt = $pdo->prepare("SELECT b.nama as bahan_nama, b.satuan, b.harga_satuan, SUM(p.jumlah * r.jumlah) as total_terpakai, SUM(p.jumlah * r.jumlah * b.harga_satuan) as total_biaya FROM penjualan p JOIN resep r ON p.produk_id = r.produk_id JOIN bahan_baku b ON r.bahan_baku_id = b.id WHERE DATE(p.created_at) BETWEEN ? AND ? AND p.status != 'batal' GROUP BY b.id ORDER BY total_biaya DESC");
$stmt->execute([$tgl_mulai, $tgl_akhir]); $detail_hpp = $stmt->fetchAll();

$stmt = $pdo->prepare("SELECT p.*, pr.nama as produk_nama FROM penjualan p JOIN produk pr ON p.produk_id = pr.id WHERE DATE(p.created_at) BETWEEN ? AND ? AND p.status != 'batal' ORDER BY p.created_at ASC");
$stmt->execute([$tgl_mulai, $tgl_akhir]); $detail_penjualan = $stmt->fetchAll();

$stmt = $pdo->prepare("SELECT * FROM pengeluaran WHERE DATE(created_at) BETWEEN ? AND ? ORDER BY created_at ASC");
$stmt->execute([$tgl_mulai, $tgl_akhir]); $detail_pengeluaran = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Laporan Keuangan - <?= APP_NAME ?></title>
    <style>
        body { font-family: Arial, sans-serif; color: #000; background: #fff; margin: 0; padding: 20px; }
        .header { text-align: center; border-bottom: 2px solid #000; padding-bottom: 20px; margin-bottom: 30px; }
        .header h1 { margin: 0 0 10px 0; font-size: 24px; }
        .header p { margin: 0; color: #555; }
        .summary-box { display: flex; justify-content: space-between; margin-bottom: 30px; }
        .summary-item { background: #f8f9fa; border: 1px solid #ddd; padding: 15px; width: 23%; border-radius: 5px; }
        .summary-item h4 { margin: 0 0 10px 0; font-size: 14px; color: #555; }
        .summary-item .value { font-size: 18px; font-weight: bold; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 30px; }
        table th, table td { border: 1px solid #000; padding: 8px 12px; font-size: 13px; }
        table th { background: #eee; text-align: left; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .footer { margin-top: 50px; display: flex; justify-content: space-between; }
        .ttd { text-align: center; width: 200px; }
        .ttd p { margin-bottom: 70px; }
        @media print { body { padding: 0; } .no-print { display: none !important; } .page-break { page-break-before: always; } }
        .btn-print { background: #ff6b35; color: #fff; padding: 12px 25px; border: none; font-size: 16px; font-weight: bold; border-radius: 5px; cursor: pointer; margin-bottom: 20px; }
    </style>
</head>
<body onload="window.print()">
    <button class="btn-print no-print" onclick="window.print()">🖨️ Cetak / Simpan PDF</button>
    <div class="header">
        <h1>LAPORAN KEUANGAN</h1>
        <p><?= APP_NAME ?></p>
        <p>Periode: <?= date('d F Y', strtotime($tgl_mulai)) ?> s/d <?= date('d F Y', strtotime($tgl_akhir)) ?></p>
    </div>

    <div class="summary-box">
        <div class="summary-item"><h4>Total Pemasukan</h4><div class="value"><?= rupiah($pemasukan) ?></div></div>
        <div class="summary-item"><h4>HPP (Modal Bahan)</h4><div class="value"><?= rupiah($hpp) ?></div></div>
        <div class="summary-item"><h4>Pengeluaran</h4><div class="value"><?= rupiah($pengeluaran) ?></div></div>
        <div class="summary-item" style="background:#e8f8f5; border-color:#2ecc71;"><h4 style="color:#27ae60;">Laba Bersih</h4><div class="value" style="color:#27ae60;"><?= rupiah($laba_bersih) ?></div></div>
    </div>

    <h3>Rincian Laba Rugi</h3>
    <table style="width: 60%;">
        <tr><td>Pemasukan Kotor</td><td class="text-right"><?= rupiah($pemasukan) ?></td></tr>
        <tr><td>HPP (Harga Pokok Penjualan)</td><td class="text-right">- <?= rupiah($hpp) ?></td></tr>
        <tr><th>LABA KOTOR</th><th class="text-right"><?= rupiah($laba_kotor) ?></th></tr>
        <tr><td>Pengeluaran Operasional</td><td class="text-right">- <?= rupiah($pengeluaran) ?></td></tr>
        <tr><th style="background:#e8f8f5;">LABA BERSIH</th><th class="text-right" style="background:#e8f8f5;"><?= rupiah($laba_bersih) ?></th></tr>
    </table>

    <h3>Rincian Pemakaian Bahan Baku (HPP)</h3>
    <table>
        <thead><tr><th width="5%" class="text-center">No</th><th>Nama Bahan</th><th class="text-center">Total Terpakai</th><th class="text-right">Harga Satuan</th><th class="text-right">Total Biaya</th></tr></thead>
        <tbody>
            <?php $no = 1; foreach ($detail_hpp as $dh): ?>
            <tr><td class="text-center"><?= $no++ ?></td><td><?= e($dh['bahan_nama']) ?></td><td class="text-center"><?= (float)$dh['total_terpakai'] ?> <?= e($dh['satuan']) ?></td><td class="text-right"><?= rupiah($dh['harga_satuan']) ?></td><td class="text-right"><?= rupiah($dh['total_biaya']) ?></td></tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <h3>Rincian Pengeluaran Operasional</h3>
    <table>
        <thead><tr><th width="5%" class="text-center">No</th><th>Tanggal</th><th>Keterangan</th><th class="text-right">Jumlah</th></tr></thead>
        <tbody>
            <?php $no = 1; foreach ($detail_pengeluaran as $dp): ?>
            <tr><td class="text-center"><?= $no++ ?></td><td><?= date('d/m/Y H:i', strtotime($dp['created_at'])) ?></td><td><?= e($dp['keterangan']) ?></td><td class="text-right"><?= rupiah($dp['jumlah']) ?></td></tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div class="page-break"></div>
    <h3>Detail Penjualan (Lampiran)</h3>
    <table>
        <thead><tr><th width="5%" class="text-center">No</th><th>Tanggal</th><th>Menu</th><th class="text-center">Qty</th><th class="text-right">Total</th></tr></thead>
        <tbody>
            <?php $no = 1; foreach ($detail_penjualan as $dp): ?>
            <tr><td class="text-center"><?= $no++ ?></td><td><?= date('d/m/Y H:i', strtotime($dp['created_at'])) ?></td><td><?= e($dp['produk_nama']) ?></td><td class="text-center"><?= $dp['jumlah'] ?></td><td class="text-right"><?= rupiah($dp['total']) ?></td></tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div class="footer">
        <div class="ttd"><p>Dibuat Oleh,</p><strong>Admin</strong></div>
        <div class="ttd"><p>Disetujui Oleh,</p><strong>Pemilik Warung</strong></div>
    </div>
</body>
</html>