<?php
require_once 'config/config.php';
require_once 'config/database.php';
require_once 'includes/functions.php';

$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = filter_input(INPUT_POST, 'username', FILTER_SANITIZE_STRING);
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    // バリデーション
    if (empty($username)) {
        $errors[] = 'ユーザー名を入力してください。';
    } elseif (!preg_match('/^[a-zA-Z0-9_]{3,20}$/', $username)) {
        $errors[] = 'ユーザー名は3～20文字の半角英数字とアンダースコアのみ使用可能です。';
    }

    if (empty($email)) {
        $errors[] = 'メールアドレスを入力してください。';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = '有効なメールアドレスを入力してください。';
    }

    if (empty($password)) {
        $errors[] = 'パスワードを入力してください。';
    } elseif (strlen($password) < PASSWORD_MIN_LENGTH) {
        $errors[] = 'パスワードは' . PASSWORD_MIN_LENGTH . '文字以上で入力してください。';
    }

    if ($password !== $confirm_password) {
        $errors[] = 'パスワードが一致しません。';
    }

    if (empty($errors)) {
        $db = new Database();
        
        // ユーザー名とメールアドレスの重複チェック
        $result = $db->query(
            "SELECT COUNT(*) as count FROM users WHERE username = :username OR email = :email",
            [':username' => $username, ':email' => $email]
        );
        $row = $result->fetchArray(SQLITE3_ASSOC);
        
        if ($row['count'] > 0) {
            $errors[] = 'このユーザー名またはメールアドレスは既に使用されています。';
        } else {
            // ユーザー登録処理
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            
            $result = $db->query(
                "INSERT INTO users (username, email, password_hash) VALUES (:username, :email, :password_hash)",
                [
                    ':username' => $username,
                    ':email' => $email,
                    ':password_hash' => $password_hash
                ]
            );

            if ($result) {
                $success = true;
                // 自動ログイン
                $_SESSION['user_id'] = $db->lastInsertId();
                $_SESSION['username'] = $username;
                
                header('Location: ' . SITE_URL . '/admin/profile');
                exit;
            } else {
                $errors[] = '登録に失敗しました。もう一度お試しください。';
            }
        }
        
        $db->close();
    }
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>新規登録 - ブログシステム</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="container">
        <div class="auth-form">
            <h1>新規登録</h1>
            
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
                    登録が完了しました。
                </div>
            <?php else: ?>
                <form method="POST" action="signup" novalidate>
                    <div class="form-group">
                        <label for="username">ユーザー名</label>
                        <input type="text" id="username" name="username" 
                               value="<?php echo htmlspecialchars($username ?? ''); ?>"
                               required pattern="[a-zA-Z0-9_]{3,20}"
                               title="3～20文字の半角英数字とアンダースコアのみ使用可能です">
                    </div>

                    <div class="form-group">
                        <label for="email">メールアドレス</label>
                        <input type="email" id="email" name="email"
                               value="<?php echo htmlspecialchars($email ?? ''); ?>"
                               required>
                    </div>

                    <div class="form-group">
                        <label for="password">パスワード</label>
                        <input type="password" id="password" name="password"
                               required minlength="<?php echo PASSWORD_MIN_LENGTH; ?>">
                        <small class="form-text">※<?php echo PASSWORD_MIN_LENGTH; ?>文字以上</small>
                    </div>

                    <div class="form-group">
                        <label for="confirm_password">パスワード（確認）</label>
                        <input type="password" id="confirm_password" name="confirm_password" required>
                    </div>

                    <button type="submit" class="btn btn-primary">登録</button>
                </form>

                <div class="auth-links">
                    <p>既にアカウントをお持ちですか？ <a href="login">ログイン</a></p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>