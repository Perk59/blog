<?php
require_once '../includes/security_headers.php';
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/setup.php';

// セッションチェック
if (!isset($_SESSION['user_id'])) {
    $_SESSION['redirect_after_login'] = SITE_URL . '/admin/posts';
    header('Location: ' . SITE_URL . '/login');
    exit;
}

$db = new Database();
$errors = [];
$success_messages = [];
$post = [
    'title' => '',
    'content' => '',
    'status' => 'draft',
    'featured_image' => ''
];

// 投稿IDがある場合は編集モード
$post_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$is_edit_mode = $post_id !== false && $post_id > 0;

if ($is_edit_mode) {
    $result = $db->query(
        "SELECT * FROM posts WHERE id = :id AND user_id = :user_id",
        [':id' => $post_id, ':user_id' => $_SESSION['user_id']]
    );
    
    if ($result) {
        $post = $result->fetchArray(SQLITE3_ASSOC);
        if (!$post) {
            header('Location: ' . SITE_URL . '/admin');
            exit;
        }
    }
}

try {
    setupUploadDirectories();
    setDirectoryPermissions();
} catch (Exception $e) {
    $errors[] = $e->getMessage();
}

// フォーム送信時の処理
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $post['title'] = filter_input(INPUT_POST, 'title', FILTER_SANITIZE_STRING);
    $post['content'] = $_POST['content'] ?? '';
    $post['status'] = $_POST['status'] ?? 'draft';

    // バリデーション
    if (empty($post['title'])) {
        $errors[] = 'タイトルを入力してください。';
    }
    if (empty($post['content'])) {
        $errors[] = '本文を入力してください。';
    }

    // ファイルアップロード処理の修正
if (!empty($_FILES['featured_image']['name'])) {
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
    $max_size = 5 * 1024 * 1024; // 5MB

    if (!in_array($_FILES['featured_image']['type'], $allowed_types)) {
        $errors[] = '画像はJPEG、PNG、GIF形式のみアップロード可能です。';
    } elseif ($_FILES['featured_image']['size'] > $max_size) {
        $errors[] = '画像サイズは5MB以下にしてください。';
    } else {
        $upload_dir = dirname(__DIR__) . '/uploads/posts/';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }

        $ext = pathinfo($_FILES['featured_image']['name'], PATHINFO_EXTENSION);
        $new_filename = 'post_' . uniqid() . '.' . $ext;
        $upload_path = $upload_dir . $new_filename;

        if (!move_uploaded_file($_FILES['featured_image']['tmp_name'], $upload_path)) {
            error_log("Upload failed: " . error_get_last()['message']);
            $errors[] = 'ファイルのアップロードに失敗しました。';
        } else {
            $post['featured_image'] = 'uploads/posts/' . $new_filename;
        }
    }
}

    if (empty($errors)) {
        try {
            $db->beginTransaction();

            if ($is_edit_mode) {
                // 投稿の更新
                $result = $db->query(
                    "UPDATE posts SET 
                    title = :title,
                    content = :content,
                    status = :status,
                    featured_image = :featured_image,
                    updated_at = CURRENT_TIMESTAMP
                    WHERE id = :id AND user_id = :user_id",
                    [
                        ':title' => $post['title'],
                        ':content' => $post['content'],
                        ':status' => $post['status'],
                        ':featured_image' => $post['featured_image'],
                        ':id' => $post_id,
                        ':user_id' => $_SESSION['user_id']
                    ]
                );
                
                if ($result) {
                    $success_messages[] = '投稿を更新しました。';
                } else {
                    throw new Exception('投稿の更新に失敗しました。');
                }
            } else {
                // 新規投稿
                $result = $db->query(
                    "INSERT INTO posts (
                        user_id, title, content, status, featured_image, 
                        created_at, updated_at
                    ) VALUES (
                        :user_id, :title, :content, :status, :featured_image, 
                        CURRENT_TIMESTAMP, CURRENT_TIMESTAMP
                    )",
                    [
                        ':user_id' => $_SESSION['user_id'],
                        ':title' => $post['title'],
                        ':content' => $post['content'],
                        ':status' => $post['status'],
                        ':featured_image' => $post['featured_image']
                    ]
                );
                
                if ($result) {
                    $post_id = $db->lastInsertId();
                    $success_messages[] = '新しい投稿を作成しました。';
                } else {
                    throw new Exception('投稿の作成に失敗しました。');
                }
            }

            $db->commit();
            
            // 保存後に記事一覧へリダイレクト（オプション）
            if (!empty($success_messages)) {
                header('Location: ' . SITE_URL . '/admin?success=' . urlencode($success_messages[0]));
                exit;
            }
        } catch (Exception $e) {
            $db->rollback();
            $errors[] = $e->getMessage();
        }
    }
}

$page_title = $is_edit_mode ? '投稿の編集' : '新規投稿';
$current_page = 'posts';
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title ?? 'ブログ管理画面'; ?></title>
    <link rel="stylesheet" href="<?php echo ASSETS_URL; ?>/css/style.css">
    <link rel="stylesheet" href="<?php echo ASSETS_URL; ?>/css/admin.css">
    <!-- TinyMCEの設定 -->
    <script src="https://cdn.tiny.cloud/1/<?php echo TINYMCE_API_KEY; ?>/tinymce/6/tinymce.min.js" referrerpolicy="origin"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            tinymce.init({
                selector: '#content',
                plugins: 'anchor autolink charmap codesample emoticons image link lists media searchreplace table visualblocks wordcount',
                toolbar: 'undo redo | blocks fontfamily fontsize | bold italic underline strikethrough | link image media table | align lineheight | numlist bullist indent outdent | emoticons charmap | removeformat',
                height: 500,
                promotion: false,
                branding: false,
                document_base_url: '<?php echo SITE_URL; ?>',
                images_upload_url: '<?php echo SITE_URL; ?>/admin/upload-image.php',
                images_upload_credentials: true,
                setup: function (editor) {
                    editor.on('init', function (e) {
                        console.log('Editor initialized');
                    });
                }
            });
        });
    </script>
</head>
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

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">
                        <?php echo $is_edit_mode ? '更新する' : '投稿する'; ?>
                    </button>
                    <a href="<?php echo SITE_URL; ?>/admin" class="btn btn-secondary">キャンセル</a>
                </div>
            </form>
        </main>
    </div>
</body>
</html>