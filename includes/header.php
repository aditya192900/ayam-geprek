<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/functions.php';

$user = currentUser();
$currentPage = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= APP_NAME ?></title>
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css">
</head>
<body>
<div class="app-layout">
    <aside class="sidebar" id="sidebar">
        <div class="brand">🍗 <?= APP_NAME ?></div>
        
        <?php if (isSuperAdmin()): ?>
            <a href="<?= BASE_URL ?>super_admin/dashboard.php" class="<?= $currentPage=='dashboard.php'?'active':'' ?>">📊 Dashboard</a>
            <a href="<?= BASE_URL ?>super_admin/kelola_akun.php" class="<?= $currentPage=='kelola_akun.php'?'active':'' ?>">👥 Kelola Akun</a>
        <?php elseif (isAdmin()): ?>
            <a href="<?= BASE_URL ?>admin/dashboard.php" class="<?= $currentPage=='dashboard.php'?'active':'' ?>">📊 Dashboard</a>
            <a href="<?= BASE_URL ?>admin/bahan_baku.php" class="<?= $currentPage=='bahan_baku.php'?'active':'' ?>">📦 Bahan Baku</a>
            <a href="<?= BASE_URL ?>admin/produk.php" class="<?= $currentPage=='produk.php'?'active':'' ?>">🍽️ Produk & Resep</a>
            <a href="<?= BASE_URL ?>admin/penjualan.php" class="<?= $currentPage=='penjualan.php'?'active':'' ?>">💰 Penjualan</a>
            <a href="<?= BASE_URL ?>admin/pengeluaran.php" class="<?= $currentPage=='pengeluaran.php'?'active':'' ?>">💸 Pengeluaran</a>
            <a href="<?= BASE_URL ?>admin/restock.php" class="<?= $currentPage=='restock.php'?'active':'' ?>">🔄 Pengajuan Restock</a>
            <a href="<?= BASE_URL ?>admin/laporan.php" class="<?= $currentPage=='laporan.php'?'active':'' ?>">📈 Laporan Laba</a>
            <a href="<?= BASE_URL ?>admin/laporan_kasir.php" class="<?= $currentPage=='laporan_kasir.php'?'active':'' ?>">🔍 Audit Kasir</a>
            <a href="<?= BASE_URL ?>admin/log.php" class="<?= $currentPage=='log.php'?'active':'' ?>">📝 Log Aktivitas</a>
        <?php else: ?>
            <a href="<?= BASE_URL ?>penjual/dashboard.php" class="<?= $currentPage=='dashboard.php'?'active':'' ?>">📊 Dashboard</a>
            <a href="<?= BASE_URL ?>penjual/stok.php" class="<?= $currentPage=='stok.php'?'active':'' ?>">📦 Cek Stok</a>
            <a href="<?= BASE_URL ?>penjual/penjualan.php" class="<?= $currentPage=='penjualan.php'?'active':'' ?>">💰 Catat Penjualan</a>
            <a href="<?= BASE_URL ?>penjual/laporan_saya.php" class="<?= $currentPage=='laporan_saya.php'?'active':'' ?>">📈 Laporan Saya</a>
            <a href="<?= BASE_URL ?>penjual/pengajuan.php" class="<?= $currentPage=='pengajuan.php'?'active':'' ?>">🔄 Ajukan Restock</a>
            <a href="<?= BASE_URL ?>penjual/tutup_kasir.php" class="<?= $currentPage=='tutup_kasir.php'?'active':'' ?>">🔒 Tutup Kasir</a>
        <?php endif; ?>
        <a href="<?= BASE_URL ?>logout.php" style="margin-top:20px; border-top:1px solid #334155;">🚪 Logout</a>
    </aside>

    <main class="main-content">
        <div class="topbar">
            <div style="display:flex; align-items:center; gap:15px;">
                <button class="menu-toggle" onclick="toggleSidebar()">☰</button>
                <h2><?= $pageTitle ?? 'Dashboard' ?></h2>
            </div>
            <div style="font-size: 0.9rem;">Halo, <strong><?= e($user['nama']) ?></strong></div>
        </div>
        
        <?php if ($msg = flash('success')): ?>
            <div class="alert alert-success"><?= e($msg) ?></div>
        <?php endif; ?>
        <?php if ($msg = flash('error')): ?>
            <div class="alert alert-danger"><?= e($msg) ?></div>
        <?php endif; ?>