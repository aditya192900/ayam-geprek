<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireSuperAdmin();

// Nama file backup
$filename = "backup_geprek_ara_juara_" . date('Y-m-d_H-i-s') . ".sql";

// Header untuk memaksa browser mendownload file
header('Content-Type: application/sql');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

set_time_limit(0); // Mencegah timeout jika database besar

// Mulai mencetak isi file SQL
echo "-- Backup Database Geprek Ara Juara\n";
echo "-- Waktu: " . date('Y-m-d H:i:s') . "\n\n";
echo "SET SQL_MODE = \"NO_AUTO_VALUE_ON_ZERO\";\n";
echo "SET time_zone = \"+00:00\";\n\n";
echo "SET FOREIGN_KEY_CHECKS = 0;\n\n";

// Ambil semua nama tabel
$tables = [];
$stmt = $pdo->query("SHOW TABLES");
while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
    $tables[] = $row[0];
}

// Looping setiap tabel
foreach ($tables as $table) {
    // 1. Buat struktur tabel (CREATE TABLE)
    $stmt = $pdo->query("SHOW CREATE TABLE `$table`");
    $row = $stmt->fetch(PDO::FETCH_NUM);
    echo "\n\n-- Struktur tabel `$table`\n";
    echo "DROP TABLE IF EXISTS `$table`;\n";
    echo $row[1] . ";\n\n";

    // 2. Ambil semua data (INSERT INTO)
    $stmt = $pdo->query("SELECT * FROM `$table`");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (count($rows) > 0) {
        echo "-- Data tabel `$table`\n";
        foreach ($rows as $data) {
            $keys = array_keys($data);
            $values = array_values($data);
            
            // Escape string agar aman jika ada tanda kutip
            $escaped_values = array_map(function($val) use ($pdo) {
                if (is_null($val)) return 'NULL';
                return $pdo->quote($val);
            }, $values);

            $insert_query = "INSERT INTO `$table` (`" . implode("`, `", $keys) . "`) VALUES (" . implode(", ", $escaped_values) . ");\n";
            echo $insert_query;
        }
    }
}

echo "\nSET FOREIGN_KEY_CHECKS = 1;\n";

// Catat log aktivitas
require_once __DIR__ . '/../includes/functions.php';
catat_log('BACKUP', 'Mengunduh backup database');
exit;