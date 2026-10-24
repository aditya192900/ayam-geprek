<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireAdmin();

$tgl_mulai = $_GET['tgl_mulai'] ?? date('Y-m-01');
$tgl_akhir = $_GET['tgl_akhir'] ?? date('Y-m-d');

header("Content-type: application/vnd-ms-excel");
header("Content-Disposition: attachment; filename=Laporan_Keuangan_" . $tgl_mulai . "_sd_" . $tgl_akhir . ".xls");
header("Pragma: no-cache");
header("Expires: 0");

$stmt = $pdo->prepare("SELECT p.*, pr.nama as produk_nama, u.nama as penjual_nama FROM penjualan p JOIN produk pr ON p.produk_id = pr.id JOIN users u ON p.user_id = u.id WHERE DATE(p.created_at) BETWEEN ? AND ? AND p.status != 'batal' ORDER BY p.created_at ASC");
$stmt->execute([$tgl_mulai, $tgl_akhir]); $penjualan = $stmt->fetchAll();

$stmt = $pdo->prepare("SELECT * FROM pengeluaran WHERE DATE(created_at) BETWEEN ? AND ? ORDER BY created_at ASC");
$stmt->execute([$tgl_mulai, $tgl_akhir]); $pengeluaran = $stmt->fetchAll();

$stmt = $pdo->prepare("SELECT b.nama as bahan_nama, b.satuan, b.harga_satuan, SUM(p.jumlah * r.jumlah) as total_terpakai, SUM(p.jumlah * r.jumlah * b.harga_satuan) as total_biaya FROM penjualan p JOIN resep r ON p.produk_id = r.produk_id JOIN bahan_baku b ON r.bahan_baku_id = b.id WHERE DATE(p.created_at) BETWEEN ? AND ? AND p.status != 'batal' GROUP BY b.id ORDER BY total_biaya DESC");
$stmt->execute([$tgl_mulai, $tgl_akhir]); $hpp = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html>
<head><style>table { border-collapse: collapse; width: 100%; font-family: Arial, sans-serif; } th, td { border: 1px solid #000; padding: 8px; text-align: left; } th { background-color: #f2f2f2; } .text-right { text-align: right; }</style></head>
<body>
    <h2>Laporan Keuangan <?= APP_NAME ?></h2>
    <p>Periode: <?= date('d/m/Y', strtotime($tgl_mulai)) ?> s/d <?= date('d/m/Y', strtotime($tgl_akhir)) ?></p>

    <h3>1. Detail Penjualan</h3>
    <table>
        <tr><th>No</th><th>Tanggal</th><th>Penjual</th><th>Menu</th><th>Qty</th><th>Total (Rp)</th></tr>
        <?php $no=1; foreach($penjualan as $p): ?>
        <tr><td><?= $no++ ?></td><td><?= date('d/m/Y H:i', strtotime($p['created_at'])) ?></td><td><?= $p['penjual_nama'] ?></td><td><?= $p['produk_nama'] ?></td><td><?= $p['jumlah'] ?></td><td class="text-right"><?= $p['total'] ?></td></tr>
        <?php endforeach; ?>
    </table>

    <br><h3>2. Rincian Pemakaian Bahan (HPP)</h3>
    <table>
        <tr><th>No</th><th>Nama Bahan</th><th>Terpakai</th><th>Harga Satuan</th><th>Total Biaya (Rp)</th></tr>
        <?php $no=1; foreach($hpp as $h): ?>
        <tr><td><?= $no++ ?></td><td><?= $h['bahan_nama'] ?></td><td><?= (float)$h['total_terpakai'] ?> <?= $h['satuan'] ?></td><td><?= $h['harga_satuan'] ?></td><td class="text-right"><?= $h['total_biaya'] ?></td></tr>
        <?php endforeach; ?>
    </table>

    <br><h3>3. Pengeluaran Operasional</h3>
    <table>
        <tr><th>No</th><th>Tanggal</th><th>Keterangan</th><th>Jumlah (Rp)</th></tr>
        <?php $no=1; foreach($pengeluaran as $p): ?>
        <tr><td><?= $no++ ?></td><td><?= date('d/m/Y', strtotime($p['created_at'])) ?></td><td><?= $p['keterangan'] ?></td><td class="text-right"><?= $p['jumlah'] ?></td></tr>
        <?php endforeach; ?>
    </table>
</body>
</html>