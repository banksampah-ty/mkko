<?php
session_start();
require 'config.php';

if ($_SESSION['role'] != 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Akses ditolak']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$penarikan_id = $input['id'];
$admin_id = $_SESSION['user_id'];

if (approvePenarikan($penarikan_id, $admin_id)) {
    echo json_encode(['success' => true, 'message' => 'Penarikan berhasil diapprove']);
} else {
    echo json_encode(['success' => false, 'message' => 'Gagal mengapprove penarikan']);
}
?>