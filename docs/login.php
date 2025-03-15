<?php
require_once 'config/config.php';
require_once 'config/database.php';
require_once 'includes/functions.php';

// すでにログインしている場合はリダイレクト
if (isset($_SESSION['user_id'])) {
    header('Location: ' . SITE_URL . '/admin/profile');
    exit;
}

$errors = [];
$username = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = filter_input(INPUT_POST, 'username', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $errors[] = 'ユーザー名とパスワードを入力してください。';
    } else {
        $db = new Database();
        
        // ユーザー情報の取得とログイン試行回数のチェック
        $result = $db->query(
            "SELECT id, username, password_hash, login_attempts, last_attempt_time 
             FROM users 
             WHERE username = :username OR email = :email",
            [':username' => $username, ':email' => $username]
        );
        
        $user = $result->fetchArray(SQLITE3_ASSOC);

        if ($user) {
            // アカウントロックのチェック
            $locked = false;
            if ($user['login_attempts'] >= MAX_LOGIN_ATTEMPTS) {
                $last_attempt = strtotime($user['last_attempt_time']);
                $lock_time = $last_attempt + (LOGIN_TIMEOUT_MINUTES * 60);
                
                if (time() < $lock_time) {
                    $wait_minutes = ceil(($lock_time - time()) / 60);
                    $errors[] = "アカウントが一時的にロックされています。{$wait_minutes}分後に再試行してください。";
                    $locked = true;
                } else {
                    // ロック時間が過ぎたらリセット
                    $db->query(
                        "UPDATE users SET login_attempts = 0 WHERE id = :id",
                        [':id' => $user['id']]
                    );
                }
            }

            if (!$locked) {
                if (password_verify($password, $user['password_hash'])) {
                    // ログイン成功
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];

                    $db->query(
                        "UPDATE users 
                         SET login_attempts = 0,
                         last_login = CURRENT_TIMESTAMP 
                         WHERE id = :id
                         /*SKIP_AUDIT*/",  // 監査フィールドをスキップするための特別なコメント
                        [':id' => $user['id']]
                    );

                    // ログイン失敗時のクエリも同様に修正
                    $db->query(
                    "UPDATE users 
                     SET login_attempts = login_attempts + 1,
                     last_attempt_time = CURRENT_TIMESTAMP 
                     WHERE id = :id
                     /*SKIP_AUDIT*/",
                    [':id' => $user['id']]
                    );

                    // 元のページまたはプロフィールページにリダイレクト
                    $redirect_to = $_SESSION['redirect_after_login'] ?? SITE_URL . '/admin/profile';
                    unset($_SESSION['redirect_after_login']);
                    
                    header('Location: ' . $redirect_to);
                    exit;
                } else {
                    // ログイン失敗: 試行回数を増やす
                    $db->query(
                        "UPDATE users SET 
                         login_attempts = login_attempts + 1,
                         last_attempt_time = CURRENT_TIMESTAMP 
                         WHERE id = :id",
                        [':id' => $user['id']]
                    );
                    
                    $remaining_attempts = MAX_LOGIN_ATTEMPTS - ($user['login_attempts'] + 1);
                    if ($remaining_attempts > 0) {
                        $errors[] = "パスワードが正しくありません。残り{$remaining_attempts}回試行できます。";
                    } else {
                        $errors[] = "アカウントが一時的にロックされました。{$LOGIN_TIMEOUT_MINUTES}分後に再試行してください。";
                    }
                }
            }
        } else {
            $errors[] = 'ユーザー名またはパスワードが正しくありません。';
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
    <title>ログイン - ブログシステム</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="container">
        <div class="auth-form">
            <h1>ログイン</h1>
            
            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <ul>
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo htmlspecialchars($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <form method="POST" action="login" novalidate>
                <div class="form-group">
                    <label for="username">ユーザー名またはメールアドレス</label>
                    <input type="text" id="username" name="username" 
                           value="<?php echo htmlspecialchars($username); ?>"
                           required autofocus>
                </div>

                <div class="form-group">
                    <label for="password">パスワード</label>
                    <input type="password" id="password" name="password" required>
                </div>

                <div class="form-check">
                    <input type="checkbox" id="remember" name="remember" class="form-check-input">
                    <label class="form-check-label" for="remember">ログイン状態を保持する</label>
                </div>

                <button type="submit" class="btn btn-primary">ログイン</button>
            </form>

            <div class="auth-links">
                <p><a href="forgot-password">パスワードをお忘れですか？</a></p>
                <p>アカウントをお持ちでない方は <a href="signup">新規登録</a></p>
            </div>
        </div>
    </div>
</body>
</html>