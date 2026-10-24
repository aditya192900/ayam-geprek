<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requirePenjual();

// Proses Pengajuan
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajukan'])) {
    $bahan_id = (int)$_POST['bahan_baku_id'];
    $jumlah = (float)$_POST['jumlah'];
    $total_biaya = (float)$_POST['total_biaya'];
    $catatan = trim($_POST['catatan']);
    $user_id = $_SESSION['user_id'];

    if ($bahan_id > 0 && $jumlah > 0) {
        $stmt = $pdo->prepare("INSERT INTO pengajuan_restock (bahan_baku_id, jumlah, total_biaya, user_id, catatan) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$bahan_id, $jumlah, $total_biaya, $user_id, $catatan]);

        flash('success', 'Pengajuan restock berhasil dikirim ke Admin!');
    } else {
        flash('error', 'Isi bahan dan jumlah dengan benar.');
    }
    redirect('pengajuan.php');
}

$bahan_list = $pdo->query("SELECT * FROM bahan_baku ORDER BY nama ASC")->fetchAll();

$stmt = $pdo->prepare("
    SELECT p.*, b.nama as bahan_nama, b.satuan 
    FROM pengajuan_restock p 
    JOIN bahan_baku b ON p.bahan_baku_id = b.id 
    WHERE p.user_id = ? 
    ORDER BY p.created_at DESC
");
$stmt->execute([$_SESSION['user_id']]);
$riwayat = $stmt->fetchAll();

$pageTitle = 'Ajukan Restock';
include __DIR__ . '/../includes/header.php';
?>

<div class="card fade-in">
    <h3>Form Pengajuan Restock</h3>
    <p style="color:#666; font-size:0.9rem; margin-bottom:20px;">Gunakan form ini jika stok bahan baku atau operasional (misal: Gas, Sendok) habis.</p>
    
    <!-- Perbaikan Layout Grid Form agar responsif dan tidak berhimpitan -->
    <form method="POST" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 15px; align-items: end;">
        <div class="form-group" style="margin-bottom:0;">
            <label>Pilih Barang / Bahan</label>
            <select name="bahan_baku_id" required>
                <option value="">-- Pilih --</option>
                <?php foreach ($bahan_list as $b): ?>
                    <option value="<?= $b['id'] ?>"><?= e($b['nama']) ?> (Sisa: <?= (float)$b['stok'] ?> <?= e($b['satuan']) ?>)</option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group" style="margin-bottom:0;">
            <label>Jumlah Beli</label>
            <input type="number" step="0.01" name="jumlah" required min="0.1" placeholder="Cth: 5">
        </div>
        <div class="form-group" style="margin-bottom:0;">
            <label>Total Biaya (Rp)</label>
            <input type="number" name="total_biaya" required min="0" placeholder="Cth: 25000">
        </div>
        <div class="form-group" style="margin-bottom:0;">
            <label>Catatan (Opsional)</label>
            <input type="text" name="catatan" placeholder="Cth: Untuk besok">
        </div>
        <div class="form-group" style="margin-bottom:0;">
            <button type="submit" name="ajukan" class="btn btn-primary" style="width:100%; padding: 12px 15px;">Ajukan</button>
        </div>
    </form>
</div>

<div class="card fade-in" style="animation-delay: 0.2s;">
    <h3>Riwayat Pengajuan Saya</h3>
    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>Tanggal</th>
                    <th>Barang</th>
                    <th>Jumlah</th>
                    <th>Biaya</th>
                    <th>Catatan</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($riwayat as $r): ?>
                <tr>
                    <td><?= date('d/m/Y H:i', strtotime($r['created_at'])) ?></td>
                    <td><strong><?= e($r['bahan_nama']) ?></strong></td>
                    <td><?= (float)$r['jumlah'] ?> <?= e($r['satuan']) ?></td>
                    <td><?= rupiah($r['total_biaya'] ?? 0) ?></td>
                    <td><?= e($r['catatan'] ?: '-') ?></td>
                    <td>
                        <?php if ($r['status'] === 'pending'): ?>
                            <span class="badge badge-warning">Menunggu</span>
                        <?php elseif ($r['status'] === 'approved'): ?>
                            <span class="badge badge-success">Disetujui</span>
                        <?php else: ?>
                            <span class="badge badge-danger">Ditolak</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($riwayat)): ?>
                <tr><td colspan="6" style="text-align:center;">Belum ada pengajuan.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>