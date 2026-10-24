<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/database.php';

function isLogin(): bool {
    return isset($_SESSION['user_id']);
}

function isSuperAdmin(): bool {
    return isLogin() && $_SESSION['role'] === 'super_admin';
}

function isAdmin(): bool {
    return isLogin() && $_SESSION['role'] === 'admin';
}

function isPenjual(): bool {
    return isLogin() && $_SESSION['role'] === 'penjual';
}

function requireLogin() {
    if (!isLogin()) {
        header('Location: ' . BASE_URL . 'login.php');
        exit;
    }
}

function requireSuperAdmin() {
    requireLogin();
    if (!isSuperAdmin()) {
        $redirect = isAdmin() ? 'admin/dashboard.php' : 'penjual/dashboard.php';
        header('Location: ' . BASE_URL . $redirect);
        exit;
    }
}

function requireAdmin() {
    requireLogin();
    if (!isAdmin() && !isSuperAdmin()) { // Super admin bisa masuk ke admin
        header('Location: ' . BASE_URL . 'penjual/dashboard.php');
        exit;
    }
}

function requirePenjual() {
    requireLogin();
    if (!isPenjual()) {
        $redirect = isSuperAdmin() ? 'super_admin/dashboard.php' : 'admin/dashboard.php';
        header('Location: ' . BASE_URL . $redirect);
        exit;
    }
}

function currentUser(): array {
    return [
        'id'   => $_SESSION['user_id'] ?? 0,
        'nama' => $_SESSION['nama'] ?? '',
        'role' => $_SESSION['role'] ?? '',
        'no_wa'=> $_SESSION['no_wa'] ?? '',
    ];
}