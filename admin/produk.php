<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireAdmin();

$edit_data = null;
if (isset($_GET['edit'])) {
    $edit_id = (int)$_GET['edit'];
    $stmt = $pdo->prepare("SELECT * FROM produk WHERE id = ?");
    $stmt->execute([$edit_id]);
    $edit_data = $stmt->fetch();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['simpan'])) {
    $id = $_POST['id'] ?? null;
    $nama = trim($_POST['nama']);
    $harga = (float)$_POST['harga_jual'];
    $kategori = trim($_POST['kategori']);
    $deskripsi = trim($_POST['deskripsi']);
    $stok = (int)$_POST['stok'];
    $foto = $edit_data ? $edit_data['foto'] : null;

    if (isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
        $ext = strtolower(pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) {
            $nama_file = uniqid('menu_') . '.' . $ext;
            if (move_uploaded_file($_FILES['foto']['tmp_name'], __DIR__ . '/../assets/uploads/' . $nama_file)) {
                if ($edit_data && $edit_data['foto'] && file_exists(__DIR__ . '/../assets/uploads/' . $edit_data['foto'])) {
                    unlink(__DIR__ . '/../assets/uploads/' . $edit_data['foto']);
                }
                $foto = $nama_file;
            }
        }
    }

    if ($nama) {
        if ($id) {
            $stmt = $pdo->prepare("UPDATE produk SET nama=?, harga_jual=?, kategori=?, deskripsi=?, stok=?, foto=? WHERE id=?");
            $stmt->execute([$nama, $harga, $kategori, $deskripsi, $stok, $foto, $id]);
            catat_log('EDIT', 'Mengubah menu: ' . $nama);
            flash('success', 'Menu berhasil diperbarui!');
        } else {
            $stmt = $pdo->prepare("INSERT INTO produk (nama, harga_jual, kategori, deskripsi, stok, foto) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$nama, $harga, $kategori, $deskripsi, $stok, $foto]);
            catat_log('TAMBAH', 'Menambahkan menu baru: ' . $nama);
            flash('success', 'Menu baru berhasil ditambahkan!');
        }
    }
    redirect('produk.php');
}

if (isset($_GET['hapus'])) {
    $id = (int)$_GET['hapus'];
    $stmt = $pdo->prepare("SELECT nama, foto FROM produk WHERE id = ?");
    $stmt->execute([$id]);
    $p = $stmt->fetch();
    if ($p) {
        if ($p['foto'] && file_exists(__DIR__ . '/../assets/uploads/' . $p['foto'])) {
            unlink(__DIR__ . '/../assets/uploads/' . $p['foto']);
        }
        $pdo->prepare("DELETE FROM produk WHERE id = ?")->execute([$id]);
        catat_log('HAPUS', 'Menghapus menu: ' . $p['nama']);
        flash('success', 'Menu berhasil dihapus!');
    }
    redirect('produk.php');
}

$produk = $pdo->query("SELECT * FROM produk ORDER BY created_at DESC")->fetchAll();
$total_produk = count($produk);
$pageTitle = 'Manajemen Menu';
include __DIR__ . '/../includes/header.php';
?>

<div class="admin-layout fade-in">
    <div class="card" style="<?= $edit_data ? 'border: 2px solid #0ea5e9;' : '' ?>">
        <h3 style="display:flex; align-items:center; gap:10px; color:<?= $edit_data ? '#0ea5e9' : '#0ea5e9' ?>;">
            <?= $edit_data ? '✏️ Edit Menu' : '➕ Tambah Menu Baru' ?>
        </h3>
        <form method="POST" enctype="multipart/form-data">
            <?php if($edit_data): ?><input type="hidden" name="id" value="<?= $edit_data['id'] ?>"><?php endif; ?>
            <div class="form-group">
                <label style="color:#64748b; font-size:0.8rem; text-transform:uppercase; letter-spacing:1px;">Foto Menu (Opsional)</label>
                <label for="file-upload" class="upload-box">
                    <i>📸</i><span><?= $edit_data && $edit_data['foto'] ? 'Ganti Foto Menu' : 'Pilih Foto Menu' ?></span><small>Format: JPG/PNG/WEBP (Maks 2MB)</small>
                    <input id="file-upload" type="file" name="foto" accept="image/*" style="display:none;">
                </label>
                <?php if ($edit_data && !empty($edit_data['foto'])): ?>
                    <div style="margin-top:10px; text-align:center;"><img src="<?= BASE_URL ?>assets/uploads/<?= $edit_data['foto'] ?>" style="max-height:100px; border-radius:8px;"><p style="font-size:0.8rem; color:#64748b; margin-top:5px;">Foto saat ini</p></div>
                <?php endif; ?>
            </div>
            <div class="form-group"><label style="color:#64748b; font-size:0.8rem; text-transform:uppercase; letter-spacing:1px;">Nama Menu</label><input type="text" name="nama" required placeholder="Cth: Indomie Goreng Spesial" value="<?= $edit_data ? e($edit_data['nama']) : '' ?>"></div>
            <div class="form-group"><label style="color:#64748b; font-size:0.8rem; text-transform:uppercase; letter-spacing:1px;">Deskripsi (Opsional)</label><textarea name="deskripsi" rows="2" placeholder="Cth: Indomie goreng dengan telur & sayuran segar"><?= $edit_data ? e($edit_data['deskripsi']) : '' ?></textarea></div>
            <div class="form-group">
                <label style="color:#64748b; font-size:0.8rem; text-transform:uppercase; letter-spacing:1px;">Kategori</label>
                <select name="kategori"><?php $kat = $edit_data ? $edit_data['kategori'] : 'Makanan'; ?>
                    <option value="Makanan" <?= $kat == 'Makanan' ? 'selected' : '' ?>>Makanan</option>
                    <option value="Minuman" <?= $kat == 'Minuman' ? 'selected' : '' ?>>Minuman</option>
                    <option value="Snack" <?= $kat == 'Snack' ? 'selected' : '' ?>>Snack</option>
                    <option value="Roti & Kue" <?= $kat == 'Roti & Kue' ? 'selected' : '' ?>>Roti & Kue</option>
                </select>
            </div>
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:15px;">
                <div class="form-group"><label style="color:#64748b; font-size:0.8rem; text-transform:uppercase; letter-spacing:1px;">Harga (Rp)</label><input type="number" name="harga_jual" required min="0" value="<?= $edit_data ? $edit_data['harga_jual'] : '0' ?>"></div>
                <div class="form-group"><label style="color:#64748b; font-size:0.8rem; text-transform:uppercase; letter-spacing:1px;">Stok</label><input type="number" name="stok" required min="0" value="<?= $edit_data ? $edit_data['stok'] : '0' ?>"></div>
            </div>
            <div style="display:flex; gap:10px; margin-top:10px;">
                <?php if($edit_data): ?>
                    <a href="produk.php" class="btn" style="flex:1; background:#e2e8f0; color:#475569;">Batal</a>
                    <button type="submit" name="simpan" class="btn btn-primary" style="flex:2;">Update Menu</button>
                <?php else: ?>
                    <button type="submit" name="simpan" class="btn btn-primary btn-block">Simpan Menu</button>
                <?php endif; ?>
            </div>
        </form>
    </div>
    <div>
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px;">
            <h3 style="margin:0; font-size:1.3rem;">📋 Daftar Menu</h3><span style="color:#64748b; font-size:0.9rem;"><?= $total_produk ?> item</span>
        </div>
        <div class="search-bar"><span>🔍</span><input type="text" id="searchInput" placeholder="Cari menu..." onkeyup="filterProducts()"></div>
        <div class="product-grid" id="productGrid">
            <?php foreach ($produk as $p): ?>
                <div class="product-card">
                    <?php if (!empty($p['foto']) && file_exists(__DIR__ . '/../assets/uploads/' . $p['foto'])): ?><img src="<?= BASE_URL ?>assets/uploads/<?= $p['foto'] ?>" alt="<?= e($p['nama']) ?>"><?php else: ?><div style="height:150px; background:#e2e8f0; display:flex; align-items:center; justify-content:center; font-size:3rem;">🍽️</div><?php endif; ?>
                    <div class="product-info">
                        <span class="product-cat"><?= e($p['kategori'] ?? 'Makanan') ?></span><div class="product-title"><?= e($p['nama']) ?></div>
                        <div class="product-price"><?= rupiah($p['harga_jual']) ?></div><div class="product-stock">Stok: <?= $p['stok'] ?? 0 ?></div>
                        <div class="product-actions" style="display:flex; gap:5px;">
                            <a href="resep.php?id=<?= $p['id'] ?>" class="btn" style="background:#fef08a; color:#854d0e; flex:1; padding:6px; font-size:0.75rem;">Resep</a>
                            <a href="?edit=<?= $p['id'] ?>" class="btn" style="background:#e0f2fe; color:#0369a1; flex:1; padding:6px; font-size:0.75rem;">Edit</a>
                            <a href="?hapus=<?= $p['id'] ?>" class="btn" style="background:#fee2e2; color:#991b1b; flex:1; padding:6px; font-size:0.75rem;" onclick="return confirm('Hapus menu ini?')">Hapus</a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<script>
function filterProducts() {
    let input = document.getElementById('searchInput').value.toLowerCase();
    let cards = document.getElementsByClassName('product-card');
    for (let i = 0; i < cards.length; i++) {
        let title = cards[i].getElementsByClassName('product-title')[0].innerText.toLowerCase();
        cards[i].style.display = title.includes(input) ? "" : "none";
    }
}
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>