<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireAdmin();

// Cek mode Edit
$edit_data = null;
if (isset($_GET['edit'])) {
    $edit_id = (int)$_GET['edit'];
    $stmt = $pdo->prepare("SELECT * FROM pengeluaran WHERE id = ?");
    $stmt->execute([$edit_id]);
    $edit_data = $stmt->fetch();
}

// Proses Simpan (Tambah / Edit)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['simpan'])) {
    $id = $_POST['id'] ?? null;
    $keterangan = trim($_POST['keterangan']);
    $jumlah = (float)$_POST['jumlah'];
    $user_id = $_SESSION['user_id'];

    if ($keterangan && $jumlah > 0) {
        if ($id) {
            // Update
            $stmt = $pdo->prepare("UPDATE pengeluaran SET keterangan = ?, jumlah = ? WHERE id = ?");
            $stmt->execute([$keterangan, $jumlah, $id]);
            catat_log('EDIT', 'Mengubah pengeluaran: ' . $keterangan);
            flash('success', 'Data pengeluaran berhasil diperbarui!');
        } else {
            // Insert Baru
            $stmt = $pdo->prepare("INSERT INTO pengeluaran (keterangan, jumlah, user_id) VALUES (?, ?, ?)");
            $stmt->execute([$keterangan, $jumlah, $user_id]);
            catat_log('TAMBAH', 'Menambah pengeluaran: ' . $keterangan);
            flash('success', 'Pengeluaran berhasil dicatat!');
        }
    } else {
        flash('error', 'Keterangan dan jumlah wajib diisi dengan benar!');
    }
    redirect('pengeluaran.php');
}

// Proses Hapus
if (isset($_GET['hapus'])) {
    $id = (int)$_GET['hapus'];
    $stmt = $pdo->prepare("SELECT keterangan FROM pengeluaran WHERE id = ?");
    $stmt->execute([$id]);
    $p = $stmt->fetch();
    if ($p) {
        $pdo->prepare("DELETE FROM pengeluaran WHERE id = ?")->execute([$id]);
        catat_log('HAPUS', 'Menghapus pengeluaran: ' . $p['keterangan']);
        flash('success', 'Data pengeluaran dihapus!');
    }
    redirect('pengeluaran.php');
}

$pengeluaran = $pdo->query("
    SELECT p.*, u.nama as admin_nama 
    FROM pengeluaran p 
    JOIN users u ON p.user_id = u.id 
    ORDER BY p.created_at DESC
")->fetchAll();

$pageTitle = 'Catat Pengeluaran';
include __DIR__ . '/../includes/header.php';
?>

<div class="card fade-in" style="<?= $edit_data ? 'border: 2px solid #0ea5e9;' : '' ?>">
    <h3 style="color:<?= $edit_data ? '#0ea5e9' : '#1e293b' ?>;">
        <?= $edit_data ? '✏️ Edit Pengeluaran' : '➕ Tambah Pengeluaran Baru' ?>
    </h3>
    <form method="POST" class="grid grid-3" style="grid-template-columns: 2fr 1fr 1fr;">
        <?php if($edit_data): ?>
            <input type="hidden" name="id" value="<?= $edit_data['id'] ?>">
        <?php endif; ?>
        
        <div class="form-group">
            <label>Keterangan Pengeluaran</label>
            <input type="text" name="keterangan" required placeholder="Cth: Beli Gas LPG 3kg / Bayar Listrik" value="<?= $edit_data ? e($edit_data['keterangan']) : '' ?>">
        </div>
        <div class="form-group">
            <label>Jumlah (Rp)</label>
            <input type="number" name="jumlah" required min="1" placeholder="Cth: 25000" value="<?= $edit_data ? $edit_data['jumlah'] : '' ?>">
        </div>
        <div class="form-group" style="display:flex; align-items:flex-end; gap:10px;">
            <?php if($edit_data): ?>
                <a href="pengeluaran.php" class="btn" style="flex:1; background:#e2e8f0; color:#475569;">Batal</a>
                <button type="submit" name="simpan" class="btn btn-primary" style="flex:2;">Update Data</button>
            <?php else: ?>
                <button type="submit" name="simpan" class="btn btn-primary" style="width:100%;">Simpan Pengeluaran</button>
            <?php endif; ?>
        </div>
    </form>
</div>

<div class="card fade-in" style="animation-delay: 0.2s;">
    <h3>Riwayat Pengeluaran</h3>
    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>Tanggal</th>
                    <th>Keterangan</th>
                    <th>Jumlah</th>
                    <th>Dicatat Oleh</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($pengeluaran as $p): ?>
                <tr>
                    <td><?= date('d/m/Y H:i', strtotime($p['created_at'])) ?></td>
                    <td><strong><?= e($p['keterangan']) ?></strong></td>
                    <td style="color:#dc3545; font-weight:bold;">- <?= rupiah($p['jumlah']) ?></td>
                    <td><?= e($p['admin_nama']) ?></td>
                    <td>
                        <!-- TOMBOL EDIT BARU -->
                        <a href="?edit=<?= $p['id'] ?>" class="btn btn-sm" style="background:#e0f2fe; color:#0369a1;">Edit</a>
                        <a href="?hapus=<?= $p['id'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('Hapus pengeluaran ini?')">Hapus</a>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($pengeluaran)): ?>
                <tr><td colspan="5" style="text-align:center;">Belum ada data pengeluaran.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>