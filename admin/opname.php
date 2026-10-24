<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireAdmin();

// Proses Simpan Opname
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['simpan'])) {
    $bahan_id = (int)$_POST['bahan_baku_id'];
    $stok_fisik = (float)$_POST['stok_fisik'];
    $catatan = trim($_POST['catatan']);
    $user_id = $_SESSION['user_id'];

    // Ambil stok sistem saat ini
    $stmt = $pdo->prepare("SELECT stok FROM bahan_baku WHERE id = ?");
    $stmt->execute([$bahan_id]);
    $bahan = $stmt->fetch();
    
    if ($bahan) {
        $stok_sistem = $bahan['stok'];
        $selisih = $stok_fisik - $stok_sistem;

        try {
            $pdo->beginTransaction();

            // 1. Catat riwayat opname
            $stmt = $pdo->prepare("INSERT INTO stock_opname (bahan_baku_id, user_id, stok_sistem, stok_fisik, selisih, catatan) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$bahan_id, $user_id, $stok_sistem, $stok_fisik, $selisih, $catatan]);

            // 2. Sesuaikan stok sistem dengan stok fisik yang baru
            $pdo->prepare("UPDATE bahan_baku SET stok = ? WHERE id = ?")->execute([$stok_fisik, $bahan_id]);

            $pdo->commit();
            
            if ($selisih < 0) {
                flash('error', 'Opname tersimpan. Tapi ada SELISIH MINUS ' . abs($selisih) . '. Ada barang hilang/terbuang!');
            } else {
                flash('success', 'Opname berhasil! Stok sistem telah disesuaikan dengan fisik.');
            }
        } catch (Exception $e) {
            $pdo->rollBack();
            flash('error', 'Terjadi kesalahan sistem.');
        }
    }
    redirect('opname.php');
}

// Ambil data bahan baku untuk form
$bahan_list = $pdo->query("SELECT * FROM bahan_baku ORDER BY nama ASC")->fetchAll();

// Ambil riwayat opname
$stmt = $pdo->query("
    SELECT o.*, b.nama as bahan_nama, b.satuan, u.nama as admin_nama 
    FROM stock_opname o 
    JOIN bahan_baku b ON o.bahan_baku_id = b.id 
    JOIN users u ON o.user_id = u.id 
    ORDER BY o.created_at DESC LIMIT 20
");
$riwayat = $stmt->fetchAll();

$pageTitle = 'Stock Opname (Cek Fisik)';
include __DIR__ . '/../includes/header.php';
?>

<div class="admin-layout fade-in" style="grid-template-columns: 1fr 1.5fr;">
    <!-- KIRI: Form Opname -->
    <div class="card">
        <h3>📝 Form Cek Fisik</h3>
        <p style="color:#666; font-size:0.9rem; margin-bottom:20px;">Masukkan jumlah barang yang <strong>benar-benar ada di gudang/kulkas</strong>.</p>

        <form method="POST">
            <div class="form-group">
                <label>Pilih Bahan Baku</label>
                <select name="bahan_baku_id" id="pilihBahan" required onchange="updateStokSistem()">
                    <option value="">-- Pilih Bahan --</option>
                    <?php foreach ($bahan_list as $b): ?>
                        <option value="<?= $b['id'] ?>" data-stok="<?= $b['stok'] ?>" data-satuan="<?= $b['satuan'] ?>">
                            <?= e($b['nama']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div style="background:#f8fafc; padding:15px; border-radius:10px; margin-bottom:15px; border:1px solid #e2e8f0;">
                <div style="display:flex; justify-content:space-between; margin-bottom:5px;">
                    <span style="color:#64748b;">Stok di Sistem:</span>
                    <strong id="labelStokSistem" style="color:#1e293b;">0</strong>
                </div>
                <div style="display:flex; justify-content:space-between;">
                    <span style="color:#64748b;">Stok Fisik (Input):</span>
                    <strong id="labelStokFisik" style="color:#1e293b;">0</strong>
                </div>
                <hr style="border:0; border-top:1px dashed #cbd5e1; margin:10px 0;">
                <div style="display:flex; justify-content:space-between;">
                    <span style="color:#64748b;">Selisih:</span>
                    <strong id="labelSelisih" style="color:#1e293b;">0</strong>
                </div>
            </div>

            <div class="form-group">
                <label>Stok Fisik Asli (Hasil Hitung)</label>
                <input type="number" step="0.01" name="stok_fisik" id="inputStokFisik" required min="0" value="0" onkeyup="hitungSelisih()" onchange="hitungSelisih()">
            </div>

            <div class="form-group">
                <label>Catatan (Alasan selisih)</label>
                <input type="text" name="catatan" placeholder="Cth: Tumpah, busuk, dll">
            </div>

            <button type="submit" name="simpan" class="btn btn-primary btn-block" onclick="return confirm('Sesuaikan stok sistem dengan angka ini?')">Simpan & Sesuaikan Stok</button>
        </form>
    </div>

    <!-- KANAN: Riwayat Opname -->
    <div class="card">
        <h3>📋 Riwayat Cek Fisik Terakhir</h3>
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Waktu</th>
                        <th>Bahan</th>
                        <th>Sistem</th>
                        <th>Fisik</th>
                        <th>Selisih</th>
                        <th>Catatan</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($riwayat as $r): ?>
                    <tr>
                        <td><?= date('d/m H:i', strtotime($r['created_at'])) ?></td>
                        <td><strong><?= e($r['bahan_nama']) ?></strong></td>
                        <td><?= (float)$r['stok_sistem'] ?></td>
                        <td><?= (float)$r['stok_fisik'] ?></td>
                        <td style="font-weight:bold; background:<?= $r['selisih'] < 0 ? '#fee2e2' : ($r['selisih'] > 0 ? '#fef08a' : '#dcfce7') ?>;">
                            <?= (float)$r['selisih'] ?> <?= e($r['satuan']) ?>
                        </td>
                        <td><?= e($r['catatan'] ?: '-') ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($riwayat)): ?>
                    <tr><td colspan="6" style="text-align:center;">Belum ada riwayat opname.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
    let stokSistem = 0;
    let satuan = '';

    function updateStokSistem() {
        let select = document.getElementById('pilihBahan');
        let option = select.options[select.selectedIndex];
        
        stokSistem = parseFloat(option.getAttribute('data-stok')) || 0;
        satuan = option.getAttribute('data-satuan') || '';
        
        document.getElementById('labelStokSistem').innerText = stokSistem + ' ' + satuan;
        hitungSelisih();
    }

    function hitungSelisih() {
        let fisik = parseFloat(document.getElementById('inputStokFisik').value) || 0;
        let selisih = fisik - stokSistem;
        
        document.getElementById('labelStokFisik').innerText = fisik + ' ' + satuan;
        
        let label = document.getElementById('labelSelisih');
        label.innerText = selisih + ' ' + satuan;
        
        if (selisih < 0) {
            label.style.color = '#dc3545'; // Merah jika barang hilang
        } else if (selisih > 0) {
            label.style.color = '#f59e0b'; // Oranye jika barang lebih
        } else {
            label.style.color = '#28a745'; // Hijau jika pas
        }
    }
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>