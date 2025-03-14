<?php
require_once '../config/config.php';
require_once '../includes/security_headers.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    exit;
}

if ($_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['error' => 'ファイルのアップロードに失敗しました。']);
    exit;
}

$allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
$max_size = 5 * 1024 * 1024; // 5MB

if (!in_array($_FILES['file']['type'], $allowed_types)) {
    echo json_encode(['error' => '許可されていないファイル形式です。']);
    exit;
}

if ($_FILES['file']['size'] > $max_size) {
    echo json_encode(['error' => 'ファイルサイズが大きすぎます。']);
    exit;
}

$upload_dir = '../uploads/content/';
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

$ext = pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION);
$filename = uniqid('content_') . '.' . $ext;
$filepath = $upload_dir . $filename;

if (move_uploaded_file($_FILES['file']['tmp_name'], $filepath)) {
    echo json_encode([
        'location' => SITE_URL . '/uploads/content/' . $filename
    ]);
} else {
    echo json_encode(['error' => 'ファイルの保存に失敗しました。']);
}