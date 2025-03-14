<?php
// 現在のUTC時刻とユーザー情報の取得
$current_utc = format_utc_datetime();
$current_user = get_current_username();
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+JP:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo ASSETS_URL; ?>/css/style.css">
</head>
<body>
    <header class="site-header">
        <div class="header-content">
            <h1 class="site-title">
                <a href="<?php echo SITE_URL; ?>"><?php echo SITE_NAME; ?></a>
            </h1>
            <nav class="site-nav">
                <div class="user-welcome">
                    UTC: <?php echo $current_utc; ?> | 
                    ユーザー: <?php echo $current_user; ?>
                </div>
                <?php if (isset($_SESSION['user_id'])): ?>
                    <a href="<?php echo SITE_URL; ?>/admin" class="nav-link">管理画面</a>
                    <a href="<?php echo SITE_URL; ?>/admin/profile.php" class="nav-link">プロフィール</a>
                    <a href="<?php echo SITE_URL; ?>/logout.php" class="nav-link">ログアウト</a>
                <?php else: ?>
                    <a href="<?php echo SITE_URL; ?>/login.php" class="nav-link">ログイン</a>
                    <a href="<?php echo SITE_URL; ?>/signup.php" class="nav-link">アカウント登録</a>
                <?php endif; ?>
            </nav>
        </div>
    </header>