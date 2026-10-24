<?php
require_once 'config/database.php';
session_start();

if (isset($_SESSION['role'])) {
    header('Location: ' . ($_SESSION['role'] === 'admin' ? 'admin/dashboard.php' : 'penjual/dashboard.php'));
} else {
    header('Location: login.php');
}
exit;