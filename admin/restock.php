<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireAdmin();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['aksi'])) {
    $id = (int)$_POST['id'];
    $aksi = $_POST['aksi'];

    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare("SELECT p.*, b.nama as bahan_nama, b.satuan FROM pengajuan_restock p JOIN bahan_baku b ON p.bahan_baku_id = b.id WHERE p.id = ? AND p.status = 'pending'");
        $stmt->execute([$id]);
        $pengajuan = $stmt->fetch();

        if ($pengajuan) {
            if ($aksi === 'approve') {
                $pdo->prepare("UPDATE pengajuan_restock SET status = 'approved' WHERE id = ?")->execute([$id]);
                $pdo->prepare("UPDATE bahan_baku SET stok = stok + ? WHERE id = ?")->execute([$pengajuan['jumlah'], $pengajuan['bahan_baku_id']]);
                
                if ($pengajuan['total_biaya'] > 0) {
                    $keterangan = "Restock " . $pengajuan['bahan_nama'] . " (" . (float)$pengajuan['jumlah'] . " " . $pengajuan['satuan'] . ")";
                    $pdo->prepare("INSERT INTO pengeluaran (keterangan, jumlah, user_id) VALUES (?, ?, ?)")->execute([$keterangan, $pengajuan['total_biaya'], $_SESSION['user_id']]);
                }
                catat_log('RESTOCK', 'Menyetujui restock: ' . $pengajuan['bahan_nama'] . ' sejumlah ' . $pengajuan['jumlah']);
                flash('success', 'Pengajuan disetujui! Stok telah ditambah dan biaya otomatis masuk ke Pengeluaran.');
            } else {
                $pdo->prepare("UPDATE pengajuan_restock SET status = 'rejected' WHERE id = ?")->execute([$id]);
                catat_log('RESTOCK', 'Menolak restock: ' . $pengajuan['bahan_nama'] . ' sejumlah ' . $pengajuan['jumlah']);
                flash('success', 'Pengajuan ditolak.');
            }
        }
        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
        flash('error', 'Terjadi kesalahan sistem: ' . $e->getMessage());
    }
    redirect('restock.php');
}

$stmt = $pdo->query("SELECT p.*, b.nama as bahan_nama, b.satuan, u.nama as penjual_nama FROM pengajuan_restock p JOIN bahan_baku b ON p.bahan_baku_id = b.id JOIN users u ON p.user_id = u.id ORDER BY p.status ASC, p.created_at DESC");
$pengajuan_list = $stmt->fetchAll();

$pageTitle = 'Pengajuan Restock';
include __DIR__ . '/../includes/header.php';
?>

<div class="card fade-in">
    <h3>Daftar Pengajuan Restock</h3>
    <div class="table-responsive">
        <table>
            <thead>
                <tr><th>Tanggal</th><th>Penjual</th><th>Barang</th><th>Jumlah</th><th>Biaya</th><th>Catatan</th><th>Status</th><th>Aksi</th></tr>
            </thead>
            <tbody>
                <?php foreach ($pengajuan_list as $p): ?>
                <tr>
                    <td><?= date('d/m H:i', strtotime($p['created_at'])) ?></td><td><?= e($p['penjual_nama']) ?></td>
                    <td><strong><?= e($p['bahan_nama']) ?></strong></td><td><?= (float)$p['jumlah'] ?> <?= e($p['satuan']) ?></td>
                    <td style="font-weight:bold; color:#dc3545;"><?= rupiah($p['total_biaya'] ?? 0) ?></td><td><?= e($p['catatan'] ?: '-') ?></td>
                    <td><?php if ($p['status'] === 'pending'): ?><span class="badge badge-warning">Menunggu</span><?php elseif ($p['status'] === 'approved'): ?><span class="badge badge-success">Disetujui</span><?php else: ?><span class="badge badge-danger">Ditolak</span><?php endif; ?></td>
                    <td>
                        <?php if ($p['status'] === 'pending'): ?>
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="id" value="<?= $p['id'] ?>">
                                <button type="submit" name="aksi" value="approve" class="btn btn-success btn-sm" onclick="return confirm('Setujui pengajuan ini? Biaya akan otomatis dicatat sebagai pengeluaran.')">Setujui</button>
                                <button type="submit" name="aksi" value="reject" class="btn btn-danger btn-sm" onclick="return confirm('Tolak pengajuan ini?')">Tolak</button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($pengajuan_list)): ?><tr><td colspan="8" style="text-align:center;">Belum ada pengajuan restock.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>