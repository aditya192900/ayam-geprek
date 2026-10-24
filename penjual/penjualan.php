<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requirePenjual();

// Proses Simpan Keranjang (Cart)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['simpan_penjualan'])) {
    $cart_data = json_decode($_POST['cart_data'], true);
    $user_id = $_SESSION['user_id'];
    $kode_invoice = 'INV-' . date('YmdHis') . '-' . rand(100, 999);

    if (!empty($cart_data)) {
        try {
            $pdo->beginTransaction();

            // 1. Cek Stok Semua Item Dulu
            foreach ($cart_data as $item) {
                $produk_id = (int)$item['id'];
                $jumlah_porsi = (int)$item['qty'];

                $stmt = $pdo->prepare("SELECT r.jumlah, b.nama as bahan_nama, b.stok, b.satuan FROM resep r JOIN bahan_baku b ON r.bahan_baku_id = b.id WHERE r.produk_id = ?");
                $stmt->execute([$produk_id]);
                $resep = $stmt->fetchAll();

                foreach ($resep as $r) {
                    $kebutuhan = $r['jumlah'] * $jumlah_porsi;
                    if ($r['stok'] < $kebutuhan) {
                        throw new Exception("Stok <strong>" . e($r['bahan_nama']) . "</strong> kurang! Sisa: " . (float)$r['stok'] . " " . e($r['satuan']) . " (untuk menu " . $item['nama'] . ")");
                    }
                }
            }

            // 2. Jika Aman, Potong Stok & Simpan Transaksi
            foreach ($cart_data as $item) {
                $produk_id = (int)$item['id'];
                $jumlah_porsi = (int)$item['qty'];
                $harga_satuan = (float)$item['harga'];
                $total_harga = $harga_satuan * $jumlah_porsi;

                // Potong Stok
                $stmt = $pdo->prepare("SELECT r.jumlah, b.id as bahan_id FROM resep r JOIN bahan_baku b ON r.bahan_baku_id = b.id WHERE r.produk_id = ?");
                $stmt->execute([$produk_id]);
                $resep = $stmt->fetchAll();

                $stmt_update = $pdo->prepare("UPDATE bahan_baku SET stok = stok - ? WHERE id = ?");
                foreach ($resep as $r) {
                    $stmt_update->execute([$r['jumlah'] * $jumlah_porsi, $r['bahan_id']]);
                }

                // Simpan ke Database
                $stmt_ins = $pdo->prepare("INSERT INTO penjualan (kode_invoice, produk_id, jumlah, harga_satuan, total, user_id) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt_ins->execute([$kode_invoice, $produk_id, $jumlah_porsi, $harga_satuan, $total_harga, $user_id]);
            }

            $pdo->commit();
            catat_log('TRANSAKSI', 'Transaksi baru (' . $kode_invoice . ')');
           flash('success', "Transaksi berhasil disimpan dengan Kode Invoice: $kode_invoice");
        } catch (Exception $e) {
            $pdo->rollBack();
            flash('error', $e->getMessage());
        }
    }
    redirect('penjualan.php');
}

$produk_list = $pdo->query("SELECT * FROM produk ORDER BY nama ASC")->fetchAll();

// Riwayat hari ini dikelompokkan berdasarkan Invoice
$stmt = $pdo->prepare("
    SELECT kode_invoice, GROUP_CONCAT(CONCAT(pr.nama, ' (', p.jumlah, 'x)') SEPARATOR '<br>') as detail_menu, SUM(p.total) as total_bayar, MAX(p.created_at) as waktu 
    FROM penjualan p JOIN produk pr ON p.produk_id = pr.id 
    WHERE p.user_id = ? AND DATE(p.created_at) = CURDATE() AND p.status != 'batal'
    GROUP BY kode_invoice ORDER BY waktu DESC
");
$stmt->execute([$_SESSION['user_id']]);
$riwayat = $stmt->fetchAll();

$pageTitle = 'Kasir Penjualan';
include __DIR__ . '/../includes/header.php';
?>

<div class="pos-layout fade-in">
    <!-- KIRI: Menu Makanan -->
    <div class="card">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
            <h3 style="margin:0;">Pilih Menu</h3>
            <input type="text" id="searchKasir" placeholder="Cari menu..." style="padding:8px 15px; border-radius:20px; border:1px solid #cbd5e1; outline:none;" onkeyup="filterKasir()">
        </div>
        <div class="product-grid" id="gridKasir">
            <?php foreach ($produk_list as $p): ?>
                <div class="product-card" onclick="tambahKeKeranjang(<?= $p['id'] ?>, '<?= e($p['nama']) ?>', <?= $p['harga_jual'] ?>, this)">
                    <?php if (!empty($p['foto']) && file_exists(__DIR__ . '/../assets/uploads/' . $p['foto'])): ?><img src="<?= BASE_URL ?>assets/uploads/<?= $p['foto'] ?>" alt="<?= e($p['nama']) ?>"><?php else: ?><div style="height:150px; background:#e2e8f0; display:flex; align-items:center; justify-content:center; font-size:3rem;">🍽️</div><?php endif; ?>
                    <div class="product-info">
                        <span class="product-cat"><?= e($p['kategori'] ?? 'Makanan') ?></span>
                        <div class="product-title"><?= e($p['nama']) ?></div>
                        <div class="product-price"><?= rupiah($p['harga_jual']) ?></div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- KANAN: Keranjang Belanja -->
    <div>
        <form method="POST" class="checkout-box" id="formCheckout" style="position: sticky; top: 20px;">
            <h3>🛒 Keranjang Pesanan</h3>
            
            <div id="keranjangKosong" style="text-align:center; color:#94a3b8; padding: 30px 0;">
                <span style="font-size:3rem;">🛍️</span><br>Belum ada pesanan.<br><small>Klik menu di kiri untuk menambah.</small>
            </div>

            <div id="listKeranjang" style="max-height: 300px; overflow-y: auto; margin-bottom: 15px;"></div>

            <div style="border-top: 1px dashed #cbd5e1; margin: 15px 0;"></div>
            
            <div style="display:flex; justify-content:space-between; margin-bottom:10px;">
                <span style="color:#64748b; font-size:1.1rem;">Total Bayar:</span>
                <span id="totalKeseluruhan" style="font-size:1.8rem; font-weight:bold; color:#ff6b35;">Rp 0</span>
            </div>

            <!-- Input Hidden untuk kirim data ke PHP -->
            <input type="hidden" name="cart_data" id="inputCartData">
            
            <button type="submit" name="simpan_penjualan" class="btn btn-primary btn-block" id="btnSimpan" disabled style="padding:15px; font-size:1.1rem;">Proses Pembayaran</button>
            <button type="button" class="btn btn-block" style="margin-top:10px; background:#fee2e2; color:#991b1b;" onclick="kosongkanKeranjang()">Kosongkan Keranjang</button>
        </form>
    </div>
</div>

<!-- TABEL RIWAYAT -->
<div class="card fade-in" style="margin-top:20px;">
    <h3>Riwayat Transaksi Hari Ini</h3>
    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>Waktu</th>
                    <th>Kode Invoice</th>
                    <th>Rincian Menu</th>
                    <th>Total</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($riwayat as $r): ?>
                <tr>
                    <td><?= date('H:i', strtotime($r['waktu'])) ?></td>
                    <td><span class="badge" style="background:#e2e8f0; color:#475569;"><?= e($r['kode_invoice']) ?></span></td>
                    <td><?= $r['detail_menu'] ?></td>
                    <td style="font-weight:bold; color:#28a745;"><?= rupiah($r['total_bayar']) ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($riwayat)): ?><tr><td colspan="4" style="text-align:center;">Belum ada transaksi hari ini.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
    let keranjang = [];

    function tambahKeKeranjang(id, nama, harga) {
        // Cek apakah barang sudah ada di keranjang
        let index = keranjang.findIndex(item => item.id === id);
        
        if (index !== -1) {
            // Jika sudah ada, tambah qty
            keranjang[index].qty += 1;
        } else {
            // Jika belum, masukkan data baru
            keranjang.push({ id: id, nama: nama, harga: harga, qty: 1 });
        }
        renderKeranjang();
    }

    function hapusDariKeranjang(index) {
        keranjang.splice(index, 1);
        renderKeranjang();
    }

    function ubahQty(index, qty) {
        if (qty < 1) qty = 1;
        keranjang[index].qty = parseInt(qty);
        renderKeranjang();
    }

    function kosongkanKeranjang() {
        if (confirm('Kosongkan semua pesanan?')) {
            keranjang = [];
            renderKeranjang();
        }
    }

    function renderKeranjang() {
        let list = document.getElementById('listKeranjang');
        let kosong = document.getElementById('keranjangKosong');
        let totalLabel = document.getElementById('totalKeseluruhan');
        let btnSimpan = document.getElementById('btnSimpan');
        let inputCart = document.getElementById('inputCartData');

        list.innerHTML = '';
        let total = 0;

        if (keranjang.length === 0) {
            kosong.style.display = 'block';
            btnSimpan.disabled = true;
            totalLabel.innerText = 'Rp 0';
            inputCart.value = '';
            return;
        }

        kosong.style.display = 'none';
        btnSimpan.disabled = false;

        keranjang.forEach((item, index) => {
            let subtotal = item.harga * item.qty;
            total += subtotal;

            list.innerHTML += `
                <div style="display:flex; justify-content:space-between; align-items:center; padding:10px 0; border-bottom:1px solid #f1f5f9;">
                    <div style="flex:1;">
                        <div style="font-weight:bold; font-size:0.9rem; color:#1e293b;">${item.nama}</div>
                        <div style="font-size:0.8rem; color:#64748b;">Rp ${item.harga.toLocaleString('id-ID')}</div>
                    </div>
                    <div style="display:flex; align-items:center; gap:5px;">
                        <input type="number" min="1" value="${item.qty}" onchange="ubahQty(${index}, this.value)" style="width:50px; padding:5px; text-align:center; border:1px solid #cbd5e1; border-radius:5px;">
                        <button type="button" onclick="hapusDariKeranjang(${index})" style="background:#fee2e2; color:#991b1b; border:none; padding:5px 8px; border-radius:5px; cursor:pointer;">✖</button>
                    </div>
                </div>
            `;
        });

        totalLabel.innerText = 'Rp ' + total.toLocaleString('id-ID');
        
        // Simpan data ke hidden input dalam format JSON
        inputCart.value = JSON.stringify(keranjang);
    }

    function filterKasir() {
        let input = document.getElementById('searchKasir').value.toLowerCase();
        let cards = document.getElementsByClassName('product-card');
        for (let i = 0; i < cards.length; i++) {
            let title = cards[i].getElementsByClassName('product-title')[0].innerText.toLowerCase();
            cards[i].style.display = title.includes(input) ? "" : "none";
        }
    }
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>