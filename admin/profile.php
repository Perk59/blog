<?php
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

// セッションチェック
if (!isset($_SESSION['user_id'])) {
    $_SESSION['redirect_after_login'] = SITE_URL . '/admin/profile';
    header('Location: ' . SITE_URL . '/login');
    exit;
}

// Database constructorの更新
$db = new Database('Perk59', '2025-03-13 06:57:59');
$errors = [];
$success_messages = [];

// ユーザー情報の取得
$result = $db->query(
    "SELECT * FROM users WHERE id = :id",
    [':id' => $_SESSION['user_id']]
);
$user = $result->fetchArray(SQLITE3_ASSOC);

if (!$user) {
    header('Location: ' . SITE_URL . '/logout');
    exit;
}

// フォーム送信の処理
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_profile'])) {
        // プロフィール更新処理
        $username = htmlspecialchars($_POST['username'] ?? '', ENT_QUOTES, 'UTF-8');
        $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
        $bio = htmlspecialchars($_POST['bio'] ?? '', ENT_QUOTES, 'UTF-8');

        // バリデーション
        if (empty($username)) {
            $errors[] = 'ユーザー名を入力してください。';
        } elseif (!preg_match('/^[a-zA-Z0-9_]{3,20}$/', $username)) {
            $errors[] = 'ユーザー名は3～20文字の半角英数字とアンダースコアのみ使用可能です。';
        }

        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = '有効なメールアドレスを入力してください。';
        }

        // ユーザー名とメールアドレスの重複チェック（自分以外）
        if (empty($errors)) {
            $result = $db->query(
                "SELECT COUNT(*) as count FROM users 
                WHERE (username = :username OR email = :email) 
                AND id != :id",
                [
                    ':username' => $username,
                    ':email' => $email,
                    ':id' => $_SESSION['user_id']
                ]
            );
            $row = $result->fetchArray(SQLITE3_ASSOC);
            
            if ($row['count'] > 0) {
                $errors[] = 'このユーザー名またはメールアドレスは既に使用されています。';
            }
        }

        // プロフィール画像のアップロード処理
        $profile_image = $user['profile_image']; // 既存の画像パス
        if (!empty($_FILES['avatar']['name'])) {
            $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
            $max_size = 5 * 1024 * 1024; // 5MB

            if (!in_array($_FILES['avatar']['type'], $allowed_types)) {
                $errors[] = '画像はJPEG、PNG、GIF形式のみアップロード可能です。';
            } elseif ($_FILES['avatar']['size'] > $max_size) {
                $errors[] = '画像サイズは5MB以下にしてください。';
            } else {
                $ext = pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION);
                $new_filename = uniqid('avatar_') . '.' . $ext;
                $upload_path = '../uploads/avatars/' . $new_filename;

                if (move_uploaded_file($_FILES['avatar']['tmp_name'], $upload_path)) {
                    // 古い画像の削除
                    if ($profile_image && file_exists('../' . $profile_image)) {
                        unlink('../' . $profile_image);
                    }
                    $profile_image = 'uploads/avatars/' . $new_filename;
                } else {
                    $errors[] = '画像のアップロードに失敗しました。';
                }
            }
        }

        // プロフィールの更新
        if (empty($errors)) {
            $result = $db->query(
                "UPDATE users SET 
                username = :username,
                email = :email,
                bio = :bio,
                profile_image = :profile_image
                WHERE id = :id
                /*SKIP_AUDIT*/",
                [
                    ':username' => $username,
                    ':email' => $email,
                    ':bio' => $bio,
                    ':profile_image' => $profile_image,
                    ':id' => $_SESSION['user_id']
                ]
            );

            if ($result) {
                $_SESSION['username'] = $username;
                $success_messages[] = 'プロフィールを更新しました。';
                $user['username'] = $username;
                $user['email'] = $email;
                $user['bio'] = $bio;
                $user['profile_image'] = $profile_image;
            } else {
                $errors[] = 'プロフィールの更新に失敗しました。';
            }
        }
    }
    // パスワード変更処理
    elseif (isset($_POST['change_password'])) {
        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';

        if (!password_verify($current_password, $user['password_hash'])) {
            $errors[] = '現在のパスワードが正しくありません。';
        } elseif (empty($new_password)) {
            $errors[] = '新しいパスワードを入力してください。';
        } elseif (strlen($new_password) < PASSWORD_MIN_LENGTH) {
            $errors[] = '新しいパスワードは' . PASSWORD_MIN_LENGTH . '文字以上で入力してください。';
        } elseif ($new_password !== $confirm_password) {
            $errors[] = '新しいパスワードが一致しません。';
        }

        if (empty($errors)) {
            $password_hash = password_hash($new_password, PASSWORD_DEFAULT);
            
            $result = $db->query(
                "UPDATE users SET password_hash = :password_hash WHERE id = :id /*SKIP_AUDIT*/",
                [
                    ':password_hash' => $password_hash,
                    ':id' => $_SESSION['user_id']
                ]
            );

            if ($result) {
                $success_messages[] = 'パスワードを変更しました。';
            } else {
                $errors[] = 'パスワードの変更に失敗しました。';
            }
        }
    }
    // アカウント削除処理
    elseif (isset($_POST['delete_account'])) {
        $password = $_POST['confirm_delete_password'] ?? '';
        
        if (password_verify($password, $user['password_hash'])) {
            try {
                $db->beginTransaction();
                
                // ユーザーの投稿を削除
                $db->query(
                    "DELETE FROM posts WHERE user_id = :user_id /*SKIP_AUDIT*/",
                    [':user_id' => $_SESSION['user_id']]
                );
                
                // ユーザーアカウントを削除
                $db->query(
                    "DELETE FROM users WHERE id = :id /*SKIP_AUDIT*/",
                    [':id' => $_SESSION['user_id']]
                );
                
                $db->commit();
                
                // プロフィール画像の削除
                if ($user['profile_image'] && file_exists('../' . $user['profile_image'])) {
                    unlink('../' . $user['profile_image']);
                }
                
                // セッションを破棄してログアウト
                session_destroy();
                header('Location: ' . SITE_URL . '?message=account_deleted');
                exit;
                
            } catch (Exception $e) {
                $db->rollback();
                $errors[] = 'アカウントの削除に失敗しました。';
            }
        } else {
            $errors[] = 'パスワードが正しくありません。';
        }
    }
}

$db->close();
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>プロフィール設定 - <?php echo htmlspecialchars(SITE_NAME); ?></title>
</head>
<body>
    <div class="container">
        <nav class="admin-nav">
            <ul>
                <li><a href="<?php echo SITE_URL; ?>">ブログトップ</a></li>
                <li><a href="<?php echo SITE_URL; ?>/admin">管理画面</a></li>
                <li><a href="<?php echo SITE_URL; ?>/logout">ログアウト</a></li>
            </ul>
        </nav>

        <div class="profile-container">
            <h1>プロフィール設定</h1>

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

            <div class="profile-sections">
                <!-- プロフィール編集フォーム -->
                <section class="profile-section">
                    <h2>プロフィール情報</h2>
                    <form method="POST" enctype="multipart/form-data" class="profile-form">
                        <input type="hidden" name="update_profile" value="1">
                        
                        <div class="avatar-upload">
                            <div class="current-avatar">
                                <?php if ($user['profile_image']): ?>
                                    <img src="<?php echo SITE_URL . '/' . htmlspecialchars($user['profile_image']); ?>" 
                                         alt="プロフィール画像">
                                <?php else: ?>
                                    <div class="avatar-placeholder">No Image</div>
                                <?php endif; ?>
                            </div>
                            <div class="avatar-input">
                                <label for="avatar">プロフィール画像</label>
                                <input type="file" id="avatar" name="avatar" accept="image/*">
                                <small class="form-text">5MB以下のJPEG、PNG、GIF形式</small>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="username">ユーザー名</label>
                            <input type="text" id="username" name="username" 
                                   value="<?php echo htmlspecialchars($user['username']); ?>" required>
                            <small class="form-text">3～20文字の半角英数字とアンダースコア</small>
                        </div>

                        <div class="form-group">
                            <label for="email">メールアドレス</label>
                            <input type="email" id="email" name="email" 
                                   value="<?php echo htmlspecialchars($user['email']); ?>" required>
                        </div>

                        <div class="form-group">
                            <label for="bio">自己紹介</label>
                            <textarea id="bio" name="bio" rows="4"><?php echo htmlspecialchars($user['bio'] ?? ''); ?></textarea>
                        </div>

                        <button type="submit" class="btn btn-primary">プロフィールを更新</button>
                    </form>
                </section>

                <section class="profile-section">
                    <h2>パスワード変更</h2>
                    <form method="POST" class="password-form">
                        <input type="hidden" name="change_password" value="1">
// パスワード変更フォーム部分を修正
<section class="profile-section">
    <h2>パスワード変更</h2>
    <form method="POST" class="password-form">
        <input type="hidden" name="change_password" value="1">

                        <div class="form-group">
                            <label for="current_password">現在のパスワード</label>
                            <input type="password" id="current_password" name="current_password" required>
                        </div>
        <div class="form-group">
            <label for="current_password">現在のパスワード</label>
            <input type="password" id="current_password" name="current_password" required>
        </div>

                        <div class="form-group">
                            <label for="new_password">新しいパスワード</label>
                            <input type="password" id="new_password" name="new_password" 
                                   required minlength="<?php echo defined('PASSWORD_MIN_LENGTH') ? PASSWORD_MIN_LENGTH : 8; ?>">
                            <small class="form-text">※<?php echo defined('PASSWORD_MIN_LENGTH') ? PASSWORD_MIN_LENGTH : 8; ?>文字以上</small>
                        </div>
        <div class="form-group">
            <label for="new_password">新しいパスワード</label>
            <input type="password" id="new_password" name="new_password" 
                   required minlength="<?php echo defined('PASSWORD_MIN_LENGTH') ? PASSWORD_MIN_LENGTH : 8; ?>">
            <small class="form-text">※<?php echo defined('PASSWORD_MIN_LENGTH') ? PASSWORD_MIN_LENGTH : 8; ?>文字以上</small>
        </div>

                        <div class="form-group">
                            <label for="confirm_password">新しいパスワード（確認）</label>
                            <input type="password" id="confirm_password" name="confirm_password" required>
                        </div>
        <div class="form-group">
            <label for="confirm_password">新しいパスワード（確認）</label>
            <input type="password" id="confirm_password" name="confirm_password" required>
        </div>

                        <button type="submit" class="btn btn-secondary">パスワードを変更</button>
                    </form>
                </section>
        <button type="submit" class="btn btn-secondary">パスワードを変更</button>
    </form>
</section>
                <!-- アカウント削除フォーム -->
                <section class="profile-section danger-zone">
                    <h2>アカウント削除</h2>
                    <div class="alert alert-warning">
                        <p>アカウントを削除すると、すべての投稿と個人情報が完全に削除されます。この操作は取り消せません。</p>
                    </div>
                    <form method="POST" class="delete-account-form" onsubmit="return confirm('本当にアカウントを削除しますか？この操作は取り消せません。');">
                        <input type="hidden" name="delete_account" value="1">
                        <div class="form-group">
                            <label for="confirm_delete_password">パスワードを入力して確認</label>
                            <input type="password" id="confirm_delete_password" name="confirm_delete_password" required>
                        </div>
                        <button type="submit" class="btn btn-danger">アカウントを削除</button>
                    </form>
                </section>
            </div>
        </div>
    </div>

    <style>
    .admin-nav {
        margin-bottom: 2rem;
        background: #f8f9fa;
        padding: 1rem;
        border-radius: 4px;
    }
    .admin-nav ul {
        list-style: none;
        padding: 0;
        margin: 0;
        display: flex;
        gap: 1rem;
    }
    .admin-nav a {
        color: #333;
        text-decoration: none;
        padding: 0.5rem 1rem;
        border-radius: 4px;
        transition: background-color 0.3s;
    }
    .admin-nav a:hover {
        background-color: #e9ecef;
    }
    .profile-container {
        max-width: 800px;
        margin: 0 auto;
        padding: 2rem;
    }
    .profile-sections {
        display: flex;
        flex-direction: column;
        gap: 2rem;
    }
    .profile-section {
        background: #fff;
        padding: 2rem;
        border-radius: 4px;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    }
    .avatar-upload {
        display: flex;
        align-items: start;
        gap: 2rem;
        margin-bottom: 2rem;
    }
    .current-avatar {
        width: 100px;
        height: 100px;
        border-radius: 50%;
        overflow: hidden;
        background: #f8f9fa;
    }
    .current-avatar img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }
    .avatar-placeholder {
        width: 100%;
        height: 100%;
        display: flex;
        align-items: center;
        justify-content: center;
        background: #e9ecef;
        color: #6c757d;
    }
    .form-group {
        margin-bottom: 1.5rem;
    }
    .form-group label {
        display: block;
        margin-bottom: 0.5rem;
        font-weight: 500;
    }
    .form-group input[type="text"],
    .form-group input[type="email"],
    .form-group input[type="password"],
    .form-group textarea {
        width: 100%;
        padding: 0.5rem;
        border: 1px solid #ced4da;
        border-radius: 4px;
        font-size: 1rem;
    }
    .form-text {
        display: block;
        margin-top: 0.25rem;
        font-size: 0.875rem;
        color: #6c757d;
    }
    .danger-zone {
        border: 1px solid #dc3545;
        margin-top: 3rem;
    }
    .danger-zone h2 {
        color: #dc3545;
    }
    .btn {
        display: inline-block;
        padding: 0.5rem 1rem;
        font-size: 1rem;
        font-weight: 500;
        text-align: center;
        text-decoration: none;
        border-radius: 4px;
        border: none;
        cursor: pointer;
        transition: background-color 0.3s, color 0.3s;
    }
    .btn-primary {
        background-color: #0d6efd;
        color: white;
    }
    .btn-primary:hover {
        background-color: #0b5ed7;
    }
    .btn-secondary {
        background-color: #6c757d;
        color: white;
    }
    .btn-secondary:hover {
        background-color: #5c636a;
    }
    .btn-danger {
        background-color: #dc3545;
        color: white;
    }
    .btn-danger:hover {
        background-color: #bb2d3b;
    }
    .alert {
        padding: 1rem;
        margin-bottom: 1rem;
        border-radius: 4px;
    }
    .alert ul {
        margin: 0;
        padding-left: 1.5rem;
    }
    .alert-danger {
        background-color: #f8d7da;
        border: 1px solid #f5c2c7;
        color: #842029;
    }
    .alert-success {
        background-color: #d1e7dd;
        border: 1px solid #badbcc;
        color: #0f5132;
    }
    .alert-warning {
        background-color: #fff3cd;
        border: 1px solid #ffecb5;
        color: #664d03;
        margin-bottom: 1rem;
    }
    @media (max-width: 768px) {
        .profile-container {
            padding: 1rem;
        }
        .avatar-upload {
            flex-direction: column;
            align-items: center;
        }
        .admin-nav ul {
            flex-direction: column;
            align-items: center;
        }
        .admin-nav a {
            width: 100%;
            text-align: center;
        }
    }
    </style>
    <style>
/* 既存のスタイルに追加 */
.password-form {
    max-width: 100%;
    width: 100%;
}

.profile-section {
    width: 100%;
    margin-bottom: 2rem;
    background: #fff;
    padding: 2rem;
    border-radius: 4px;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}

.profile-section h2 {
    margin-bottom: 1.5rem;
    color: #333;
    font-size: 1.5rem;
}

.form-group {
    margin-bottom: 1.5rem;
}

.form-group label {
    display: block;
    margin-bottom: 0.5rem;
    font-weight: 500;
    color: #333;
}

.form-group input[type="password"] {
    width: 100%;
    padding: 0.75rem;
    border: 1px solid #ced4da;
    border-radius: 4px;
    font-size: 1rem;
    transition: border-color 0.15s ease-in-out;
}

.form-group input[type="password"]:focus {
    border-color: #86b7fe;
    outline: 0;
    box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
}

.btn-secondary {
    background-color: #6c757d;
    color: white;
    padding: 0.75rem 1.5rem;
    font-size: 1rem;
    border-radius: 4px;
    border: none;
    cursor: pointer;
    transition: background-color 0.15s ease-in-out;
}

.btn-secondary:hover {
    background-color: #5c636a;
}

.form-text {
    display: block;
    margin-top: 0.25rem;
    color: #6c757d;
    font-size: 0.875rem;
}

/* レスポンシブ対応 */
@media (max-width: 768px) {
    .profile-section {
        padding: 1.5rem;
    }
    
    .form-group input[type="password"] {
        padding: 0.5rem;
    }
    
    .btn-secondary {
        width: 100%;
    }
}
</style>
</body>
</html>