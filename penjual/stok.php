<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requirePenjual();

$bahan = $pdo->query("SELECT * FROM bahan_baku ORDER BY nama ASC")->fetchAll();
$pageTitle = 'Cek Stok Bahan Baku';
include __DIR__ . '/../includes/header.php';
?>

<div class="card fade-in">
    <h3>Sisa Stok Bahan Baku</h3>
    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>Nama Bahan</th>
                    <th>Sisa Stok</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($bahan as $b): 
                    $isLow = $b['stok'] <= $b['stok_minimum'];
                ?>
                <tr>
                    <td><strong><?= e($b['nama']) ?></strong></td>
                    <td><?= (float)$b['stok'] ?> <?= e($b['satuan']) ?></td>
                    <td>
                        <?php if ($isLow): ?>
                            <span class="badge badge-danger">Menipis / Habis</span>
                        <?php else: ?>
                            <span class="badge badge-success">Aman</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>