<?php
require_once '../includes/security_headers.php';
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

// セッションチェック
if (!isset($_SESSION['user_id'])) {
    $_SESSION['redirect_after_login'] = SITE_URL . '/admin/edit-post';
    header('Location: ' . SITE_URL . '/login');
    exit;
}

$db = new Database();
$errors = [];
$success_messages = [];

// 投稿IDの取得と検証
$post_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$post_id) {
    header('Location: ' . SITE_URL . '/admin');
    exit;
}

// 投稿データの取得
$result = $db->query(
    "SELECT * FROM posts WHERE id = :id AND user_id = :user_id",
    [
        ':id' => $post_id,
        ':user_id' => $_SESSION['user_id']
    ]
);

if (!$result) {
    header('Location: ' . SITE_URL . '/admin');
    exit;
}

$post = $result->fetchArray(SQLITE3_ASSOC);
if (!$post) {
    header('Location: ' . SITE_URL . '/admin');
    exit;
}

// カテゴリーの取得
$categories_result = $db->query(
    "SELECT c.* FROM categories c 
    LEFT JOIN post_categories pc ON c.id = pc.category_id 
    WHERE pc.post_id = :post_id",
    [':post_id' => $post_id]
);

$selected_categories = [];
while ($row = $categories_result->fetchArray(SQLITE3_ASSOC)) {
    $selected_categories[] = $row['id'];
}

// フォーム送信時の処理
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF対策のトークン検証
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $errors[] = '不正なリクエストです。';
    } else {
        $title = filter_input(INPUT_POST, 'title', FILTER_SANITIZE_STRING);
        $content = $_POST['content'] ?? '';
        $status = $_POST['status'] ?? 'draft';
        $categories = $_POST['categories'] ?? [];

        // バリデーション
        if (empty($title)) {
            $errors[] = 'タイトルを入力してください。';
        }
        if (empty($content)) {
            $errors[] = '本文を入力してください。';
        }
        if (!in_array($status, ['draft', 'published'])) {
            $errors[] = '無効な公開ステータスです。';
        }

        // エラーがなければ更新処理
        if (empty($errors)) {
            try {
                $db->beginTransaction();

                // 投稿の更新
                $update_result = $db->query(
                    "UPDATE posts SET 
                    title = :title,
                    content = :content,
                    status = :status,
                    updated_at = CURRENT_TIMESTAMP,
                    updated_by = :updated_by
                    WHERE id = :id AND user_id = :user_id",
                    [
                        ':title' => $title,
                        ':content' => $content,
                        ':status' => $status,
                        ':updated_by' => $_SESSION['username'],
                        ':id' => $post_id,
                        ':user_id' => $_SESSION['user_id']
                    ]
                );

                if ($update_result) {
                    // アイキャッチ画像の処理
                    if (!empty($_FILES['featured_image']['name'])) {
                        $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
                        $max_size = 5 * 1024 * 1024; // 5MB

                        if (!in_array($_FILES['featured_image']['type'], $allowed_types)) {
                            throw new Exception('画像はJPEG、PNG、GIF形式のみアップロード可能です。');
                        }
                        if ($_FILES['featured_image']['size'] > $max_size) {
                            throw new Exception('画像サイズは5MB以下にしてください。');
                        }

                        $ext = pathinfo($_FILES['featured_image']['name'], PATHINFO_EXTENSION);
                        $new_filename = uniqid('post_') . '.' . $ext;
                        $upload_path = '../uploads/posts/' . $new_filename;

                        if (move_uploaded_file($_FILES['featured_image']['tmp_name'], $upload_path)) {
                            // 古い画像の削除
                            if (!empty($post['featured_image'])) {
                                $old_image_path = '../' . $post['featured_image'];
                                if (file_exists($old_image_path)) {
                                    unlink($old_image_path);
                                }
                            }

                            // 画像パスの更新
                            $db->query(
                                "UPDATE posts SET featured_image = :image WHERE id = :id",
                                [
                                    ':image' => 'uploads/posts/' . $new_filename,
                                    ':id' => $post_id
                                ]
                            );
                        }
                    }

                    // カテゴリーの更新
                    $db->query(
                        "DELETE FROM post_categories WHERE post_id = :post_id",
                        [':post_id' => $post_id]
                    );

                    foreach ($categories as $category_id) {
                        $db->query(
                            "INSERT INTO post_categories (post_id, category_id) VALUES (:post_id, :category_id)",
                            [
                                ':post_id' => $post_id,
                                ':category_id' => $category_id
                            ]
                        );
                    }

                    $db->commit();
                    $success_messages[] = '投稿を更新しました。';

                    // 更新後のデータを再取得
                    $result = $db->query(
                        "SELECT * FROM posts WHERE id = :id",
                        [':id' => $post_id]
                    );
                    $post = $result->fetchArray(SQLITE3_ASSOC);

                    // 選択されたカテゴリーを更新
                    $selected_categories = $categories;
                } else {
                    throw new Exception('投稿の更新に失敗しました。');
                }
            } catch (Exception $e) {
                $db->rollback();
                $errors[] = $e->getMessage();
            }
        }
    }
}

// CSRFトークンの生成
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));

// 全カテゴリーの取得
$all_categories_result = $db->query("SELECT * FROM categories ORDER BY name");
$all_categories = [];
while ($row = $all_categories_result->fetchArray(SQLITE3_ASSOC)) {
    $all_categories[] = $row;
}

$page_title = '投稿の編集';
$current_page = 'edit-post';
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - ブログシステム</title>
    <link rel="stylesheet" href="<?php echo ASSETS_URL; ?>/css/style.css">
    <link rel="stylesheet" href="<?php echo ASSETS_URL; ?>/css/admin.css">
    <script src="https://cdn.tiny.cloud/1/<?php echo TINYMCE_API_KEY; ?>/tinymce/6/tinymce.min.js" referrerpolicy="origin"></script>
    <script>
        tinymce.init({
            selector: '#content',
            plugins: 'anchor autolink charmap codesample emoticons image link lists media searchreplace table visualblocks wordcount',
            toolbar: 'undo redo | blocks fontfamily fontsize | bold italic underline strikethrough | link image media table | align lineheight | numlist bullist indent outdent | emoticons charmap | removeformat',
            height: 500,
            promotion: false,
            branding: false,
            // ドメイン設定
            document_base_url: 'https://kondyprog000.xsrv.jp',
            // 画像アップロード設定
            images_upload_url: '<?php echo SITE_URL; ?>/admin/upload-image.php',
            images_upload_credentials: true,
            // エラーハンドリング
            setup: function (editor) {
                editor.on('init', function (e) {
                    console.log('Editor initialized');
                });
            }
        });
    </script>
</head>
<body>
    <div class="admin-container">
        <header class="admin-header">
            <div class="header-content">
                <h1><?php echo $page_title; ?></h1>
                <div class="header-actions">
                    <a href="<?php echo SITE_URL; ?>/admin" class="btn btn-secondary">記事一覧に戻る</a>
                </div>
            </div>
        </header>

        <main class="admin-main">
            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <ul>
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo htmlspecialchars($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <?php if (!empty($success_messages)): ?>
                <div class="alert alert-success">
                    <ul>
                        <?php foreach ($success_messages as $message): ?>
                            <li><?php echo htmlspecialchars($message); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <form method="POST" enctype="multipart/form-data" class="post-form">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                
                <div class="form-group">
                    <label for="title">タイトル</label>
                    <input type="text" id="title" name="title" 
                           value="<?php echo htmlspecialchars($post['title']); ?>" required>
                </div>

                <div class="form-group">
                    <label for="content">本文</label>
                    <textarea id="content" name="content"><?php echo htmlspecialchars($post['content']); ?></textarea>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="featured_image">アイキャッチ画像</label>
                        <?php if (!empty($post['featured_image'])): ?>
                            <div class="current-image">
                                <img src="<?php echo SITE_URL . '/' . htmlspecialchars($post['featured_image']); ?>" 
                                     alt="現在の画像">
                            </div>
                        <?php endif; ?>
                        <input type="file" id="featured_image" name="featured_image" accept="image/*">
                    </div>

                    <div class="form-group">
                        <label for="status">公開状態</label>
                        <select id="status" name="status">
                            <option value="draft" <?php echo $post['status'] === 'draft' ? 'selected' : ''; ?>>下書き</option>
                            <option value="published" <?php echo $post['status'] === 'published' ? 'selected' : ''; ?>>公開</option>
                        </select>
                    </div>
                </div>

                <?php if (!empty($all_categories)): ?>
                <div class="form-group">
                    <label>カテゴリー</label>
                    <div class="categories-list">
                        <?php foreach ($all_categories as $category): ?>
                            <label class="category-item">
                                <input type="checkbox" name="categories[]" 
                                       value="<?php echo $category['id']; ?>"
                                       <?php echo in_array($category['id'], $selected_categories) ? 'checked' : ''; ?>>
                                <?php echo htmlspecialchars($category['name']); ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">更新する</button>
                    <a href="<?php echo SITE_URL; ?>/admin" class="btn btn-secondary">キャンセル</a>
                </div>
            </form>
        </main>
    </div>
</body>
</html>