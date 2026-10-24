<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireAdmin();

$logs = $pdo->query("
    SELECT l.*, u.nama as user_nama, u.role 
    FROM log_aktivitas l 
    JOIN users u ON l.user_id = u.id 
    ORDER BY l.created_at DESC LIMIT 100
")->fetchAll();

$pageTitle = 'Log Aktivitas Sistem';
include __DIR__ . '/../includes/header.php';
?>

<div class="card fade-in">
    <h3>📝 Riwayat Aktivitas (100 Terakhir)</h3>
    <p style="color:#666; font-size:0.9rem; margin-bottom:20px;">Mencatat siapa melakukan apa (Tambah, Edit, Hapus, Transaksi).</p>
    
    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>Waktu</th>
                    <th>Pengguna</th>
                    <th>Aksi</th>
                    <th>Keterangan</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($logs as $l): ?>
                <tr>
                    <td><?= date('d/m/Y H:i:s', strtotime($l['created_at'])) ?></td>
                    <td>
                        <strong><?= e($l['user_nama']) ?></strong><br>
                        <span class="badge" style="background:#e2e8f0; color:#475569; font-size:0.65rem;"><?= $l['role'] ?></span>
                    </td>
                    <td>
                        <?php if ($l['aksi'] === 'TAMBAH'): ?>
                            <span class="badge badge-success">TAMBAH</span>
                        <?php elseif ($l['aksi'] === 'EDIT'): ?>
                            <span class="badge badge-warning">EDIT</span>
                        <?php elseif ($l['aksi'] === 'HAPUS'): ?>
                            <span class="badge badge-danger">HAPUS</span>
                        <?php else: ?>
                            <span class="badge" style="background:#e0f2fe; color:#0369a1;"><?= e($l['aksi']) ?></span>
                        <?php endif; ?>
                    </td>
                    <td><?= e($l['keterangan']) ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($logs)): ?>
                <tr><td colspan="4" style="text-align:center;">Belum ada aktivitas tercatat.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>