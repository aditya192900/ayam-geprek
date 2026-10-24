<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requirePenjual();

$user_id = $_SESSION['user_id'];
$hari_ini = date('Y-m-d');

$stmt = $pdo->prepare("SELECT * FROM tutup_kasir WHERE user_id = ? AND tanggal = ?");
$stmt->execute([$user_id, $hari_ini]);
$sudah_tutup = $stmt->fetch();

// Tambahkan AND status != 'batal'
$stmt = $pdo->prepare("SELECT SUM(total) as total FROM penjualan WHERE user_id = ? AND DATE(created_at) = ? AND status != 'batal'");
$stmt->execute([$user_id, $hari_ini]);
$total_penjualan = $stmt->fetch()['total'] ?? 0;

$stmt = $pdo->prepare("SELECT SUM(total_biaya) as total FROM pengajuan_restock WHERE user_id = ? AND status = 'approved' AND DATE(created_at) = ?");
$stmt->execute([$user_id, $hari_ini]);
$total_restock = $stmt->fetch()['total'] ?? 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tutup'])) {
    $kas_awal = (float)$_POST['kas_awal'];
    $uang_fisik = (float)$_POST['uang_fisik'];
    $catatan = trim($_POST['catatan']);

    $ekspektasi = $kas_awal + $total_penjualan - $total_restock;
    $selisih = $uang_fisik - $ekspektasi;

    if (!$sudah_tutup) {
        $stmt = $pdo->prepare("INSERT INTO tutup_kasir (user_id, tanggal, total_penjualan, total_restock, kas_awal, uang_fisik, selisih, catatan) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$user_id, $hari_ini, $total_penjualan, $total_restock, $kas_awal, $uang_fisik, $selisih, $catatan]);
        
        if ($selisih < 0) flash('error', 'Tutup kasir berhasil. Tapi ada SELISIH MINUS ' . rupiah(abs($selisih)) . '. Hati-hati!');
        else flash('success', 'Tutup kasir berhasil! Uang sesuai.');
    }
    redirect('tutup_kasir.php');
}

$stmt = $pdo->prepare("SELECT * FROM tutup_kasir WHERE user_id = ? ORDER BY tanggal DESC LIMIT 7");
$stmt->execute([$user_id]);
$riwayat = $stmt->fetchAll();

$pageTitle = 'Tutup Kasir & Audit Uang';
include __DIR__ . '/../includes/header.php';
?>

<div class="pos-layout fade-in">
    <div class="card">
        <h3>🔒 Tutup Kasir Hari Ini</h3>
        <p style="color:#666; font-size:0.9rem; margin-bottom:20px;">Hitung uang fisik yang ada di laci/tangan Anda, lalu masukkan angkanya.</p>

        <?php if ($sudah_tutup): ?>
            <div class="alert alert-success" style="margin-bottom:20px;">✅ Anda sudah melakukan Tutup Kasir hari ini pada pukul <?= date('H:i', strtotime($sudah_tutup['created_at'])) ?>.</div>
            <table style="margin-bottom:20px;">
                <tr><td>Kas Awal</td><td class="text-right"><?= rupiah($sudah_tutup['kas_awal']) ?></td></tr>
                <tr><td>Penjualan</td><td class="text-right" style="color:#28a745;">+ <?= rupiah($sudah_tutup['total_penjualan']) ?></td></tr>
                <tr><td>Belanja (Restock)</td><td class="text-right" style="color:#dc3545;">- <?= rupiah($sudah_tutup['total_restock']) ?></td></tr>
                <tr style="background:#f8fafc; border-top:2px solid #cbd5e1;"><th>Ekspektasi Uang</th><th class="text-right"><?= rupiah($sudah_tutup['kas_awal'] + $sudah_tutup['total_penjualan'] - $sudah_tutup['total_restock']) ?></th></tr>
                <tr><td><strong>Uang Fisik Diserahkan</strong></td><td class="text-right"><strong><?= rupiah($sudah_tutup['uang_fisik']) ?></strong></td></tr>
                <tr style="background:<?= $sudah_tutup['selisih'] < 0 ? '#fee2e2' : '#dcfce7' ?>;"><th>SELISIH</th><th class="text-right" style="color:<?= $sudah_tutup['selisih'] < 0 ? '#991b1b' : '#166534' ?>;"><?= rupiah($sudah_tutup['selisih']) ?></th></tr>
            </table>
            <p style="font-size:0.85rem; color:#64748b;">Catatan: <?= e($sudah_tutup['catatan'] ?: '-') ?></p>
        <?php else: ?>
            <div class="alert alert-success">
                <strong>Total Penjualan Hari Ini:</strong> <?= rupiah($total_penjualan) ?><br>
                <strong>Potongan Restock (ACC):</strong> <span style="color:red;">- <?= rupiah($total_restock) ?></span>
            </div>
            <form method="POST">
                <div class="form-group"><label>Kas Awal (Uang Modal di Laci)</label><input type="number" name="kas_awal" required min="0" value="0" id="kas_awal" onkeyup="hitungEkspektasi()" onchange="hitungEkspektasi()"></div>
                <div class="form-group"><label>Uang Fisik yang Ada Saat Ini (Rp)</label><input type="number" name="uang_fisik" required min="0" value="0" id="uang_fisik" onkeyup="hitungSelisih()" onchange="hitungSelisih()"></div>
                <div class="form-group"><label>Catatan (Opsional)</label><input type="text" name="catatan" placeholder="Cth: Uang dipinjam bos, dll"></div>
                <div style="background:#f8fafc; padding:15px; border-radius:10px; margin-bottom:20px; text-align:center;">
                    <span style="font-size:0.9rem; color:#64748b;">Ekspektasi Uang (Kas Awal + Jualan - Restock)</span>
                    <h3 id="labelEkspektasi" style="margin:5px 0 0 0; color:#1e293b;">Rp 0</h3>
                    <hr style="border:0; border-top:1px dashed #cbd5e1; margin:10px 0;">
                    <span style="font-size:0.9rem; color:#64748b;">Selisih (Uang Fisik - Ekspektasi)</span>
                    <h3 id="labelSelisih" style="margin:5px 0 0 0; color:#1e293b;">Rp 0</h3>
                </div>
                <button type="submit" name="tutup" class="btn btn-primary btn-block" onclick="return confirm('Yakin tutup kasir? Data tidak bisa diubah lagi.')">Kunci & Tutup Kasir</button>
            </form>
        <?php endif; ?>
    </div>

    <div class="card">
        <h3>📋 Riwayat Tutup Kasir (7 Hari Terakhir)</h3>
        <div class="table-responsive">
            <table>
                <thead><tr><th>Tanggal</th><th>Ekspektasi</th><th>Fisik</th><th>Selisih</th></tr></thead>
                <tbody>
                    <?php foreach ($riwayat as $r): $ekspektasi = $r['kas_awal'] + $r['total_penjualan'] - $r['total_restock']; ?>
                    <tr><td><?= date('d/m/Y', strtotime($r['tanggal'])) ?></td><td><?= rupiah($ekspektasi) ?></td><td><?= rupiah($r['uang_fisik']) ?></td><td style="font-weight:bold; color:<?= $r['selisih'] < 0 ? '#dc3545' : '#28a745' ?>;"><?= rupiah($r['selisih']) ?></td></tr>
                    <?php endforeach; ?>
                    <?php if (empty($riwayat)): ?><tr><td colspan="4" style="text-align:center;">Belum ada riwayat.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<script>
    let totalPenjualan = <?= $total_penjualan ?>;
    let totalRestock = <?= $total_restock ?>;
    function formatRupiah(angka) { return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(angka); }
    function hitungEkspektasi() { let kasAwal = parseFloat(document.getElementById('kas_awal').value) || 0; let ekspektasi = kasAwal + totalPenjualan - totalRestock; document.getElementById('labelEkspektasi').innerText = formatRupiah(ekspektasi); hitungSelisih(); }
    function hitungSelisih() {
        let kasAwal = parseFloat(document.getElementById('kas_awal').value) || 0; let ekspektasi = kasAwal + totalPenjualan - totalRestock;
        let uangFisik = parseFloat(document.getElementById('uang_fisik').value) || 0; let selisih = uangFisik - ekspektasi;
        let label = document.getElementById('labelSelisih'); label.innerText = formatRupiah(selisih);
        if (selisih < 0) label.style.color = '#dc3545'; else if (selisih > 0) label.style.color = '#f59e0b'; else label.style.color = '#28a745';
    }
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>