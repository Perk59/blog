<?php
require_once 'config/config.php';
require_once 'config/database.php';
require_once 'includes/functions.php';

$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);

    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = '有効なメールアドレスを入力してください。';
    } else {
        $db = new Database();
        
        // メールアドレスの存在確認
        $result = $db->query(
            "SELECT id, email FROM users WHERE email = :email",
            [':email' => $email]
        );
        
        $user = $result->fetchArray(SQLITE3_ASSOC);

        if ($user) {
            // トークンの生成と保存
            $token = generateToken();
            $expiry = date('Y-m-d H:i:s', strtotime('+24 hours'));

            $result = $db->query(
                "UPDATE users SET 
                reset_token = :token,
                reset_token_expires_at = :expiry
                WHERE id = :id",
                [
                    ':token' => $token,
                    ':expiry' => $expiry,
                    ':id' => $user['id']
                ]
            );

            if ($result && sendResetEmail($email, $token)) {
                $success = true;
            } else {
                $errors[] = 'メールの送信に失敗しました。しばらく経ってから再度お試しください。';
            }
        } else {
            // セキュリティのため、メールアドレスが存在しない場合でも同じメッセージを表示
            $success = true;
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
    <title>パスワードをお忘れの方 - ブログシステム</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="container">
        <div class="auth-form">
            <h1>パスワードの再設定</h1>
            
            <?php if ($success): ?>
                <div class="alert alert-success">
                    <p>パスワードリセットの手順を記載したメールを送信しました。</p>
                    <p>メールの内容に従って、パスワードの再設定を行ってください。</p>
                </div>
                <div class="auth-links">
                    <p><a href="login">ログインページに戻る</a></p>
                </div>
            <?php else: ?>
                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger">
                        <ul>
                            <?php foreach ($errors as $error): ?>
                                <li><?php echo htmlspecialchars($error); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <p class="form-info">
                    登録時に使用したメールアドレスを入力してください。<br>
                    パスワードリセット用のリンクをメールでお送りします。
                </p>

                <form method="POST" action="forgot-password" novalidate>
                    <div class="form-group">
                        <label for="email">メールアドレス</label>
                        <input type="email" id="email" name="email" required autofocus>
                    </div>

                    <button type="submit" class="btn btn-primary">送信</button>
                </form>

                <div class="auth-links">
                    <p><a href="login">ログインページに戻る</a></p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>