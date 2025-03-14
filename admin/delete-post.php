<?php
require_once '../includes/security_headers.php';
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

// セッションチェック
if (!isset($_SESSION['user_id'])) {
    header('Location: ' . SITE_URL . '/login');
    exit;
}

$post_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$post_id) {
    header('Location: ' . SITE_URL . '/admin');
    exit;
}

$db = new Database();

try {
    $db->beginTransaction();

    // 投稿の情報を取得（画像パスのため）
    $post_result = $db->query(
        "SELECT featured_image FROM posts WHERE id = :id AND user_id = :user_id",
        [':id' => $post_id, ':user_id' => $_SESSION['user_id']]
    );
    
    $post = $post_result->fetchArray(SQLITE3_ASSOC);
    
    if ($post) {
        // 関連する画像の削除
        if (!empty($post['featured_image'])) {
            $image_path = '../' . $post['featured_image'];
            if (file_exists($image_path)) {
                unlink($image_path);
            }
        }

        // カテゴリー関連の削除
        $db->query(
            "DELETE FROM post_categories WHERE post_id = :post_id",
            [':post_id' => $post_id]
        );

        // 投稿の削除
        $result = $db->query(
            "DELETE FROM posts WHERE id = :id AND user_id = :user_id",
            [':id' => $post_id, ':user_id' => $_SESSION['user_id']]
        );

        if ($result) {
            $db->commit();
            $_SESSION['success_message'] = '投稿を削除しました。';
        } else {
            throw new Exception('投稿の削除に失敗しました。');
        }
    }
} catch (Exception $e) {
    $db->rollback();
    $_SESSION['error_message'] = $e->getMessage();
}

header('Location: ' . SITE_URL . '/admin');
exit;