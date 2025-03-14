<?php
// アップロードディレクトリの設定
function setupUploadDirectories() {
    $directories = [
        '../uploads',
        '../uploads/posts',
        '../uploads/profiles',
        '../uploads/content'
    ];

    foreach ($directories as $dir) {
        if (!file_exists($dir)) {
            if (!mkdir($dir, 0755, true)) {
                error_log("Failed to create directory: " . $dir);
                throw new Exception('アップロードディレクトリの作成に失敗しました。');
            }
        }
    }
}

// パーミッションの設定
function setDirectoryPermissions() {
    $directories = [
        '../uploads' => 0755,
        '../uploads/posts' => 0755,
        '../uploads/profiles' => 0755,
        '../uploads/content' => 0755
    ];

    foreach ($directories as $dir => $permission) {
        if (file_exists($dir)) {
            if (!chmod($dir, $permission)) {
                error_log("Failed to set permissions for directory: " . $dir);
                throw new Exception('ディレクトリのパーミッション設定に失敗しました。');
            }
        }
    }
}