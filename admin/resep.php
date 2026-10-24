<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireAdmin();

$produk_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($produk_id === 0) redirect('produk.php');

$stmt = $pdo->prepare("SELECT * FROM produk WHERE id = ?");
$stmt->execute([$produk_id]);
$produk = $stmt->fetch();
if (!$produk) redirect('produk.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tambah'])) {
    $bahan_id = (int)$_POST['bahan_baku_id'];
    $jumlah = (float)$_POST['jumlah'];

    if ($bahan_id > 0 && $jumlah > 0) {
        $cek = $pdo->prepare("SELECT id FROM resep WHERE produk_id = ? AND bahan_baku_id = ?");
        $cek->execute([$produk_id, $bahan_id]);
        
        if ($cek->fetch()) {
            flash('error', 'Bahan ini sudah ada di resep. Hapus dulu jika ingin mengubah jumlah.');
        } else {
            $stmt = $pdo->prepare("INSERT INTO resep (produk_id, bahan_baku_id, jumlah) VALUES (?, ?, ?)");
            $stmt->execute([$produk_id, $bahan_id, $jumlah]);
            flash('success', 'Bahan berhasil ditambahkan ke resep!');
        }
    }
    redirect("resep.php?id=$produk_id");
}

if (isset($_GET['hapus'])) {
    $id = (int)$_GET['hapus'];
    $pdo->prepare("DELETE FROM resep WHERE id = ?")->execute([$id]);
    flash('success', 'Bahan dihapus dari resep!');
    redirect("resep.php?id=$produk_id");
}

$bahan_list = $pdo->query("SELECT * FROM bahan_baku ORDER BY nama ASC")->fetchAll();

$stmt = $pdo->prepare("
    SELECT r.*, b.nama, b.satuan 
    FROM resep r 
    JOIN bahan_baku b ON r.bahan_baku_id = b.id 
    WHERE r.produk_id = ?
");
$stmt->execute([$produk_id]);
$resep = $stmt->fetchAll();

$pageTitle = 'Resep: ' . $produk['nama'];
include __DIR__ . '/../includes/header.php';
?>

<div style="margin-bottom: 15px;">
    <a href="produk.php" class="btn btn-sm" style="background:#64748b; color:#fff;">&larr; Kembali ke Produk</a>
</div>

<div class="card fade-in">
    <h3>Tambah Bahan ke Resep</h3>
    <p style="color:#666; font-size:0.9rem; margin-bottom:15px;">Tentukan berapa banyak bahan baku yang terpakai untuk membuat <strong>1 porsi <?= e($produk['nama']) ?></strong>.</p>
    
    <?php if (empty($bahan_list)): ?>
        <div class="alert alert-danger">Belum ada data bahan baku. <a href="bahan_baku.php">Tambahkan bahan baku dulu</a>.</div>
    <?php else: ?>
    <form method="POST" class="grid grid-3" style="grid-template-columns: 2fr 1fr 1fr;">
        <div class="form-group">
            <label>Pilih Bahan</label>
            <select name="bahan_baku_id" required>
                <option value="">-- Pilih Bahan --</option>
                <?php foreach ($bahan_list as $b): ?>
                    <option value="<?= $b['id'] ?>"><?= e($b['nama']) ?> (<?= e($b['satuan']) ?>)</option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label>Jumlah Terpakai</label>
            <input type="number" step="0.001" name="jumlah" required min="0.001" placeholder="Cth: 0.25">
        </div>
        <div class="form-group" style="display:flex; align-items:flex-end;">
            <button type="submit" name="tambah" class="btn btn-primary" style="width:100%;">Tambah Bahan</button>
        </div>
    </form>
    <?php endif; ?>
</div>

<div class="card fade-in" style="animation-delay: 0.2s;">
    <h3>Komposisi Resep</h3>
    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>Bahan Baku</th>
                    <th>Jumlah Terpakai per Porsi</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($resep as $r): ?>
                <tr>
                    <td><strong><?= e($r['nama']) ?></strong></td>
                    <td><?= (float)$r['jumlah'] ?> <?= e($r['satuan']) ?></td>
                    <td>
                        <a href="?id=<?= $produk_id ?>&hapus=<?= $r['id'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('Hapus bahan ini dari resep?')">Hapus</a>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($resep)): ?>
                <tr><td colspan="3" style="text-align:center;">Resep masih kosong. Tambahkan bahan di atas.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>