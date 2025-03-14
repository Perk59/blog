<?php
require_once 'config/config.php';
require_once 'config/database.php';
require_once 'includes/functions.php';

$errors = [];
$success = false;
$token = $_GET['token'] ?? '';

if (empty($token)) {
    header('Location: ' . SITE_URL . '/login');
    exit;
}

$db = new Database();

// トークンの検証
$result = $db->query(
    "SELECT id, email FROM users 
    WHERE reset_token = :token 
    AND reset_token_expires_at > CURRENT_TIMESTAMP
    AND reset_token IS NOT NULL",
    [':token' => $token]
);

$user = $result->fetchArray(SQLITE3_ASSOC);

if (!$user) {
    $errors[] = 'このリンクは無効か、有効期限が切れています。';
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (empty($password)) {
        $errors[] = '新しいパスワードを入力してください。';
    } elseif (strlen($password) < PASSWORD_MIN_LENGTH) {
        $errors[] = 'パスワードは' . PASSWORD_MIN_LENGTH . '文字以上で入力してください。';
    }

    if ($password !== $confirm_password) {
        $errors[] = 'パスワードが一致しません。';
    }

    if (empty($errors)) {
        $password_hash = password_hash($password, PASSWORD_DEFAULT);
        
        $result = $db->query(
            "UPDATE users SET 
            password_hash = :password_hash,
            reset_token = NULL,
            reset_token_expires_at = NULL,
            login_attempts = 0
            WHERE id = :id",
            [
                ':password_hash' => $password_hash,
                ':id' => $user['id']
            ]
        );

        if ($result) {
            $success = true;
        } else {
            $errors[] = 'パスワードの更新に失敗しました。';
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
    <title>新しいパスワードの設定 - ブログシステム</title>
    <link rel="stylesheet" href="assets/css/styles.css">
</head>
<body>
    <div class="container">
        <div class="auth-form">
            <h1>新しいパスワードの設定</h1>

            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <ul>
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo htmlspecialchars($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success">
                    <p>パスワードが正常に更新されました。</p>
                    <p>新しいパスワードでログインしてください。</p>
                </div>
                <div class="auth-links">
                    <p><a href="login">ログインページへ</a></p>
                </div>
            <?php elseif (empty($errors) || isset($_POST['password'])): ?>
                <form method="POST" novalidate>
                    <div class="form-group">
                        <label for="password">新しいパスワード</label>
                        <input type="password" id="password" name="password"
                               required minlength="<?php echo PASSWORD_MIN_LENGTH; ?>">
                        <small class="form-text">※<?php echo PASSWORD_MIN_LENGTH; ?>文字以上</small>
                    </div>

                    <div class="form-group">
                        <label for="confirm_password">新しいパスワード（確認）</label>
                        <input type="password" id="confirm_password" name="confirm_password" required>
                    </div>

                    <button type="submit" class="btn btn-primary">パスワードを更新</button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>