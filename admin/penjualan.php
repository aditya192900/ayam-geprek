<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireAdmin();

// Proses ACC / Tolak Batal
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['aksi_batal'])) {
    $id = (int)$_POST['id'];
    $aksi = $_POST['aksi_batal'];

    try {
        $pdo->beginTransaction();
        
        $stmt = $pdo->prepare("SELECT * FROM penjualan WHERE id = ? AND status = 'pending_batal'");
        $stmt->execute([$id]);
        $trx = $stmt->fetch();

        if ($trx) {
            if ($aksi === 'setujui') {
                // 1. Ubah status jadi batal
                $pdo->prepare("UPDATE penjualan SET status = 'batal' WHERE id = ?")->execute([$id]);
                
                // 2. Kembalikan Stok Bahan Baku berdasarkan Resep
                $stmt_resep = $pdo->prepare("SELECT r.jumlah as qty_resep, r.bahan_baku_id FROM resep r WHERE r.produk_id = ?");
                $stmt_resep->execute([$trx['produk_id']]);
                $reseps = $stmt_resep->fetchAll();
                
                foreach ($reseps as $r) {
                    $qty_kembali = $r['qty_resep'] * $trx['jumlah'];
                    $pdo->prepare("UPDATE bahan_baku SET stok = stok + ? WHERE id = ?")->execute([$qty_kembali, $r['bahan_baku_id']]);
                }
                
                catat_log('BATAL', 'Menyetujui pembatalan transaksi ID ' . $id);
                flash('success', 'Transaksi dibatalkan dan stok telah dikembalikan.');
            } else {
                $pdo->prepare("UPDATE penjualan SET status = 'sukses' WHERE id = ?")->execute([$id]);
                catat_log('BATAL', 'Menolak pembatalan transaksi ID ' . $id);
                flash('success', 'Pengajuan pembatalan ditolak.');
            }
        }
        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
        flash('error', 'Terjadi kesalahan sistem.');
    }
    redirect('penjualan.php');
}

// Ambil semua data penjualan (tampilkan yang pending batal paling atas)
$stmt = $pdo->query("
    SELECT p.*, pr.nama as produk_nama, u.nama as penjual_nama 
    FROM penjualan p 
    JOIN produk pr ON p.produk_id = pr.id 
    JOIN users u ON p.user_id = u.id 
    ORDER BY (status = 'pending_batal') DESC, p.created_at DESC
");
$riwayat = $stmt->fetchAll();

// Hitung total pendapatan (HANYA yang sukses)
$total_pendapatan = 0;
foreach ($riwayat as $r) {
    if ($r['status'] === 'sukses') {
        $total_pendapatan += $r['total'];
    }
}

$pageTitle = 'Riwayat Penjualan';
include __DIR__ . '/../includes/header.php';
?>

<div class="grid grid-3" style="margin-bottom:20px;">
    <div class="stat fade-in">
        <div class="label">Total Transaksi Sukses</div>
        <div class="value"><?= count(array_filter($riwayat, fn($r) => $r['status'] == 'sukses')) ?> <span style="font-size:1rem; font-weight:400;">Transaksi</span></div>
    </div>
    <div class="stat fade-in" style="border-left-color: #28a745;">
        <div class="label">Total Pendapatan (Sukses)</div>
        <div class="value" style="color:#28a745;"><?= rupiah($total_pendapatan) ?></div>
    </div>
</div>

<div class="card fade-in">
    <h3>Semua Riwayat Penjualan</h3>
    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>Waktu</th>
                    <th>Penjual</th>
                    <th>Menu</th>
                    <th>Total</th>
                    <th>Status</th>
                    <th>Aksi (Persetujuan)</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($riwayat as $r): ?>
                <tr style="<?= $r['status'] == 'pending_batal' ? 'background:#fff7ed;' : '' ?>">
                    <td><?= date('d/m H:i', strtotime($r['created_at'])) ?></td>
                    <td><span class="badge" style="background:#e2e8f0; color:#475569;"><?= e($r['penjual_nama']) ?></span></td>
                    <td><strong><?= e($r['produk_nama']) ?></strong> (<?= $r['jumlah'] ?>x)</td>
                    <td style="font-weight:bold;"><?= rupiah($r['total']) ?></td>
                    <td>
                        <?php if($r['status'] === 'sukses'): ?>
                            <span class="badge badge-success">Sukses</span>
                        <?php elseif($r['status'] === 'pending_batal'): ?>
                            <span class="badge badge-warning">Minta Batal</span>
                            <div style="font-size:0.75rem; color:#dc3545; margin-top:4px;">Alasan: <?= e($r['alasan_batal']) ?></div>
                        <?php else: ?>
                            <span class="badge badge-danger">Dibatalkan</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($r['status'] === 'pending_batal'): ?>
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="id" value="<?= $r['id'] ?>">
                                <button type="submit" name="aksi_batal" value="setujui" class="btn btn-success btn-sm" onclick="return confirm('Setujui pembatalan? Stok akan dikembalikan.')">Setujui</button>
                                <button type="submit" name="aksi_batal" value="tolak" class="btn btn-danger btn-sm" onclick="return confirm('Tolak pembatalan?')">Tolak</button>
                            </form>
                        <?php else: ?>
                            -
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>