<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireSuperAdmin();

// Mode Edit
$edit_data = null;
if (isset($_GET['edit'])) {
    $edit_id = (int)$_GET['edit'];
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND role != 'super_admin'");
    $stmt->execute([$edit_id]);
    $edit_data = $stmt->fetch();
}

// Proses Simpan (Tambah/Edit)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['simpan'])) {
    $id = $_POST['id'] ?? null;
    $username = trim($_POST['username']);
    $nama = trim($_POST['nama']);
    $role = $_POST['role'];
    $password = $_POST['password'];

    // Cek username unik
    if ($id) {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
        $stmt->execute([$username, $id]);
    } else {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->execute([$username]);
    }

    if ($stmt->fetch()) {
        flash('error', 'Username sudah digunakan, silakan pilih yang lain.');
    } else {
        if ($id) {
            // Update
            if (!empty($password)) {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE users SET username=?, nama=?, role=?, password=? WHERE id=?");
                $stmt->execute([$username, $nama, $role, $hash, $id]);
            } else {
                $stmt = $pdo->prepare("UPDATE users SET username=?, nama=?, role=? WHERE id=?");
                $stmt->execute([$username, $nama, $role, $id]);
            }
            catat_log('EDIT', 'Super Admin mengubah akun: ' . $username);
            flash('success', 'Akun berhasil diperbarui!');
        } else {
            // Insert
            if (empty($password)) {
                flash('error', 'Password wajib diisi untuk akun baru!');
                redirect('kelola_akun.php');
            }
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users (username, password, nama, role) VALUES (?, ?, ?, ?)");
            $stmt->execute([$username, $hash, $nama, $role]);
            catat_log('TAMBAH', 'Super Admin membuat akun baru: ' . $username);
            flash('success', 'Akun baru berhasil dibuat!');
        }
    }
    redirect('kelola_akun.php');
}

// Proses Hapus
if (isset($_GET['hapus'])) {
    $id = (int)$_GET['hapus'];
    $stmt = $pdo->prepare("SELECT username FROM users WHERE id = ? AND role != 'super_admin'");
    $stmt->execute([$id]);
    $u = $stmt->fetch();
    if ($u) {
        $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$id]);
        catat_log('HAPUS', 'Super Admin menghapus akun: ' . $u['username']);
        flash('success', 'Akun berhasil dihapus!');
    }
    redirect('kelola_akun.php');
}

$users = $pdo->query("SELECT * FROM users WHERE role != 'super_admin' ORDER BY role ASC, nama ASC")->fetchAll();
$pageTitle = 'Kelola Akun Pengguna';
include __DIR__ . '/../includes/header.php';
?>

<div class="admin-layout fade-in">
    <!-- KIRI: Form -->
    <div class="card">
        <h3><?= $edit_data ? '✏️ Edit Akun' : '➕ Tambah Akun Baru' ?></h3>
        <form method="POST">
            <?php if($edit_data): ?><input type="hidden" name="id" value="<?= $edit_data['id'] ?>"><?php endif; ?>
            
            <div class="form-group">
                <label>Nama Lengkap</label>
                <input type="text" name="nama" required value="<?= $edit_data ? e($edit_data['nama']) : '' ?>" placeholder="Cth: Budi Santoso">
            </div>
            <div class="form-group">
                <label>Username (Untuk Login)</label>
                <input type="text" name="username" required value="<?= $edit_data ? e($edit_data['username']) : '' ?>" placeholder="Cth: budi_penjual">
            </div>
            <div class="form-group">
                <label>Role / Hak Akses</label>
                <select name="role">
                    <option value="admin" <?= ($edit_data && $edit_data['role']=='admin') ? 'selected' : '' ?>>Admin</option>
                    <option value="penjual" <?= ($edit_data && $edit_data['role']=='penjual') ? 'selected' : '' ?>>Penjual</option>
                </select>
            </div>
            <div class="form-group">
                <label>Password <?= $edit_data ? '(Kosongkan jika tidak ingin ganti)' : '' ?></label>
                <input type="text" name="password" <?= $edit_data ? '' : 'required' ?> placeholder="Masukkan password baru">
            </div>

            <div style="display:flex; gap:10px; margin-top:15px;">
                <?php if($edit_data): ?>
                    <a href="kelola_akun.php" class="btn" style="flex:1; background:#e2e8f0; color:#475569;">Batal</a>
                    <button type="submit" name="simpan" class="btn btn-primary" style="flex:2;">Update Akun</button>
                <?php else: ?>
                    <button type="submit" name="simpan" class="btn btn-primary btn-block">Buat Akun</button>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <!-- KANAN: Tabel User -->
    <div class="card">
        <h3>Daftar Akun</h3>
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Nama</th>
                        <th>Username</th>
                        <th>Role</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $u): ?>
                    <tr>
                        <td><strong><?= e($u['nama']) ?></strong></td>
                        <td><?= e($u['username']) ?></td>
                        <td>
                            <?php if($u['role'] === 'admin'): ?>
                                <span class="badge" style="background:#e0f2fe; color:#0369a1;">ADMIN</span>
                            <?php else: ?>
                                <span class="badge" style="background:#fef08a; color:#854d0e;">PENJUAL</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <a href="?edit=<?= $u['id'] ?>" class="btn btn-sm" style="background:#e0f2fe; color:#0369a1;">Edit</a>
                            <a href="?hapus=<?= $u['id'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('Yakin hapus akun ini?')">Hapus</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>