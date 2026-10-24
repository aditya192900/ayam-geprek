<?php
function rupiah($angka): string {
    return 'Rp ' . number_format((float)$angka, 0, ',', '.');
}

function e($str): string {
    return htmlspecialchars((string)$str, ENT_QUOTES, 'UTF-8');
}

function redirect(string $url) {
    header('Location: ' . $url);
    exit;
}

function flash(string $key, ?string $msg = null) {
    if ($msg !== null) {
        $_SESSION['flash'][$key] = $msg;
        return null;
    }
    $val = $_SESSION['flash'][$key] ?? null;
    unset($_SESSION['flash'][$key]);
    return $val;
}

// FUNGSI WHATSAPP
function kirimWA($target, $pesan) {
    $token = FONNTE_TOKEN ?? '';
    if (empty($token) || $token === 'TOKEN_FONNTE_KAMU_DISINI') return false;

    $curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_URL => 'https://api.fonnte.com/send',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS => array('target' => $target, 'message' => $pesan),
        CURLOPT_HTTPHEADER => array('Authorization: ' . $token),
    ));
    $response = curl_exec($curl);
    curl_close($curl);
    return $response;
}

// FUNGSI LOG AKTIVITAS
function catat_log($aksi, $keterangan) {
    global $pdo;
    if (isset($_SESSION['user_id'])) {
        $stmt = $pdo->prepare("INSERT INTO log_aktivitas (user_id, aksi, keterangan) VALUES (?, ?, ?)");
        $stmt->execute([$_SESSION['user_id'], $aksi, $keterangan]);
    }
}