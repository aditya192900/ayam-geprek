<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireAdmin();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tambah'])) {
    $nama = trim($_POST['nama']);
    $satuan = trim($_POST['satuan']);
    $stok = (float)$_POST['stok'];
    $stok_min = (float)$_POST['stok_minimum'];
    $harga = (float)$_POST['harga_satuan'];

    if ($nama && $satuan) {
        $stmt = $pdo->prepare("INSERT INTO bahan_baku (nama, satuan, stok, stok_minimum, harga_satuan) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$nama, $satuan, $stok, $stok_min, $harga]);
        flash('success', 'Bahan baku berhasil ditambahkan!');
    } else {
        flash('error', 'Nama dan satuan wajib diisi!');
    }
    redirect('bahan_baku.php');
}

if (isset($_GET['hapus'])) {
    $id = (int)$_GET['hapus'];
    try {
        $pdo->prepare("DELETE FROM bahan_baku WHERE id = ?")->execute([$id]);
        flash('success', 'Bahan baku berhasil dihapus!');
    } catch (PDOException $e) {
        flash('error', 'Gagal menghapus! Bahan ini mungkin sedang dipakai di resep.');
    }
    redirect('bahan_baku.php');
}

$bahan = $pdo->query("SELECT * FROM bahan_baku ORDER BY nama ASC")->fetchAll();
$pageTitle = 'Master Bahan Baku';
include __DIR__ . '/../includes/header.php';
?>

<div class="card fade-in">
    <h3>Tambah Bahan Baku Baru</h3>
    <form method="POST" class="grid grid-3">
        <div class="form-group">
            <label>Nama Bahan</label>
           <input type="text" name="nama" required placeholder="Cth: Ayam Potong">
        </div>
        <div class="form-group">
            <label>Satuan</label>
            <input type="text" name="satuan" required placeholder="Cth: Kg / Liter / Pcs">
        </div>
        <div class="form-group">
            <label>Harga per Satuan (Rp)</label>
            <input type="number" name="harga_satuan" required min="0" value="0">
        </div>
        <div class="form-group">
            <label>Stok Awal</label>
            <input type="number" step="0.01" name="stok" required min="0" value="0">
        </div>
        <div class="form-group">
            <label>Batas Minimum Stok</label>
            <input type="number" step="0.01" name="stok_minimum" required min="0" value="5">
        </div>
        <div class="form-group" style="display:flex; align-items:flex-end;">
            <button type="submit" name="tambah" class="btn btn-primary" style="width:100%;">Tambah Bahan</button>
        </div>
    </form>
</div>

<div class="card fade-in" style="animation-delay: 0.2s;">
    <h3>Daftar Bahan Baku</h3>
    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>Nama Bahan</th>
                    <th>Stok</th>
                    <th>Batas Min.</th>
                    <th>Harga Satuan</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($bahan as $b): 
                    $isLow = $b['stok'] <= $b['stok_minimum'];
                ?>
                <tr>
                    <td><strong><?= e($b['nama']) ?></strong></td>
                    <td>
                        <span class="badge <?= $isLow ? 'badge-danger' : 'badge-success' ?>">
                            <?= (float)$b['stok'] ?> <?= e($b['satuan']) ?>
                        </span>
                    </td>
                    <td><?= (float)$b['stok_minimum'] ?> <?= e($b['satuan']) ?></td>
                    <td><?= rupiah($b['harga_satuan']) ?></td>
                    <td>
                        <a href="?hapus=<?= $b['id'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('Yakin hapus bahan ini?')">Hapus</a>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($bahan)): ?>
                <tr><td colspan="5" style="text-align:center;">Belum ada data bahan baku.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>