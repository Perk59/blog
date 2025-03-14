<?php
require_once 'config/config.php';
require_once 'config/database.php';
require_once 'includes/functions.php';

$db = new Database();
$errors = [];
$user = null;
$posts = [];

try {
    $user_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
    if (!$user_id) {
        throw new Exception('無効なユーザーIDです。');
    }

    // ユーザー情報を取得
    $user_query = "
        SELECT id, username, display_name, profile_image, bio, created_at
        FROM users 
        WHERE id = :id
    ";

    $user_result = $db->query($user_query, [':id' => $user_id]);
    if ($user_result === false) {
        throw new Exception('ユーザー情報の取得に失敗しました。');
    }

    $user = $user_result->fetchArray(SQLITE3_ASSOC);
    if (!$user) {
        throw new Exception('ユーザーが見つかりません。');
    }

    // ユーザーの投稿を取得
    $posts_query = "
        SELECT 
            p.*,
            (
                SELECT GROUP_CONCAT(c.name) 
                FROM categories c 
                JOIN post_categories pc ON c.id = pc.category_id 
                WHERE pc.post_id = p.id
            ) as categories
        FROM posts p
        WHERE p.user_id = :user_id 
        AND p.status = 'published'
        ORDER BY p.created_at DESC
    ";

    $posts_result = $db->query($posts_query, [':user_id' => $user_id]);
    while ($row = $posts_result->fetchArray(SQLITE3_ASSOC)) {
        $posts[] = $row;
    }

} catch (Exception $e) {
    $errors[] = $e->getMessage();
}

// 安全な表示用の変数を準備
$display_name = $user['display_name'] ?? $user['username'] ?? '名前未設定';
$user_initial = !empty($user['username']) ? strtoupper(mb_substr($user['username'], 0, 1)) : '?';
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($display_name); ?> - <?php echo SITE_NAME; ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+JP:wght@400;500;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #2563eb;
            --primary-dark: #1d4ed8;
            --secondary-color: #0f172a;
            --accent-color: #f97316;
            --text-color: #1e293b;
            --text-light: #64748b;
            --background-color: #f8fafc;
            --card-background: #ffffff;
            --border-radius: 16px;
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            --shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1);
        }

        body {
            font-family: 'Noto Sans JP', sans-serif;
            line-height: 1.8;
            color: var(--text-color);
            background: var(--background-color);
            margin: 0;
        }

        .site-header {
            background: var(--card-background);
            box-shadow: var(--shadow);
            position: sticky;
            top: 0;
            z-index: 1000;
            backdrop-filter: blur(8px);
            background: rgba(255, 255, 255, 0.9);
        }

        .header-content {
            max-width: 1200px;
            margin: 0 auto;
            padding: 1rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .back-to-home {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            color: var(--text-light);
            text-decoration: none;
            padding: 0.5rem 1rem;
            border-radius: var(--border-radius);
            transition: var(--transition);
        }

        .back-to-home:hover {
            background: var(--primary-color);
            color: white;
        }

        .user-profile {
            background: var(--card-background);
            padding: 3rem 2rem;
            margin-bottom: 2rem;
            box-shadow: var(--shadow);
        }

        .profile-content {
            max-width: 800px;
            margin: 0 auto;
            display: flex;
            gap: 2rem;
            align-items: center;
        }

        .profile-image {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            overflow: hidden;
            background: var(--primary-color);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 2.5rem;
            font-weight: 700;
        }

        .profile-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .profile-info {
            flex: 1;
        }

        .profile-name {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .profile-bio {
            color: var(--text-light);
            margin-bottom: 1rem;
        }

        .profile-stats {
            display: flex;
            gap: 2rem;
            color: var(--text-light);
            font-size: 0.9rem;
        }

        .posts-section {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem;
        }

        .section-title {
            font-size: 1.5rem;
            margin-bottom: 2rem;
            color: var(--text-color);
            text-align: center;
        }

        .posts-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 2rem;
        }

        .post-card {
            background: var(--card-background);
            border-radius: var(--border-radius);
            overflow: hidden;
            box-shadow: var(--shadow);
            transition: var(--transition);
        }

        .post-card:hover {
            transform: translateY(-5px);
        }

        .post-image {
            aspect-ratio: 16/9;
            overflow: hidden;
        }

        .post-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .post-content {
            padding: 1.5rem;
        }

        .post-title {
            font-size: 1.25rem;
            margin-bottom: 1rem;
            color: var(--text-color);
        }

        .post-title a {
            color: inherit;
            text-decoration: none;
        }

        .post-meta {
            display: flex;
            justify-content: space-between;
            color: var(--text-light);
            font-size: 0.9rem;
        }

        .post-categories {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            margin-top: 1rem;
        }

        .category-tag {
            background: var(--primary-color);
            color: white;
            padding: 0.25rem 1rem;
            border-radius: 9999px;
            font-size: 0.875rem;
        }

        .error-container {
            max-width: 800px;
            margin: 2rem auto;
            padding: 2rem;
            background: var(--card-background);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
        }

        .error-message {
            color: #dc2626;
            background: #fef2f2;
            padding: 1rem;
            border-radius: 8px;
            border-left: 4px solid #dc2626;
        }

        @media (prefers-color-scheme: dark) {
            :root {
                --text-color: #e2e8f0;
                --text-light: #94a3b8;
                --background-color: #0f172a;
                --card-background: #1e293b;
            }

            .site-header {
                background: rgba(30, 41, 59, 0.9);
            }

            .error-message {
                background: #422424;
            }
        }

        @media (max-width: 768px) {
            .profile-content {
                flex-direction: column;
                text-align: center;
            }

            .profile-stats {
                justify-content: center;
            }

            .posts-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <header class="site-header">
        <div class="header-content">
            <a href="<?php echo SITE_URL; ?>" class="back-to-home">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                </svg>
                ホームに戻る
            </a>
        </div>
    </header>

    <?php if (!empty($errors)): ?>
        <div class="error-container">
            <?php foreach ($errors as $error): ?>
                <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <section class="user-profile">
            <div class="profile-content">
                <div class="profile-image">
                    <?php if (!empty($user['profile_image'])): ?>
                        <img src="<?php echo SITE_URL . '/' . $user['profile_image']; ?>" 
                             alt="<?php echo htmlspecialchars($display_name); ?>">
                    <?php else: ?>
                        <?php echo htmlspecialchars($user_initial); ?>
                    <?php endif; ?>
                </div>
                <div class="profile-info">
                    <h1 class="profile-name"><?php echo htmlspecialchars($display_name); ?></h1>
                    <?php if (!empty($user['bio'])): ?>
                        <p class="profile-bio"><?php echo nl2br(htmlspecialchars($user['bio'])); ?></p>
                    <?php endif; ?>
                    <div class="profile-stats">
                        <span>登録日: <?php echo date('Y年n月j日', strtotime($user['created_at'])); ?></span>
                        <span>投稿数: <?php echo count($posts); ?>件</span>
                    </div>
                </div>
            </div>
        </section>

        <section class="posts-section">
            <h2 class="section-title">投稿一覧</h2>
            <?php if (empty($posts)): ?>
                <p class="no-posts">まだ投稿がありません。</p>
            <?php else: ?>
                <div class="posts-grid">
                    <?php foreach ($posts as $post): ?>
                        <article class="post-card">
                            <?php if (!empty($post['featured_image'])): ?>
                                <div class="post-image">
                                    <img src="<?php echo SITE_URL . '/' . $post['featured_image']; ?>" 
                                         alt="<?php echo htmlspecialchars($post['title']); ?>">
                                </div>
                            <?php endif; ?>
                            <div class="post-content">
                                <h3 class="post-title">
                                    <a href="<?php echo SITE_URL; ?>/post.php?id=<?php echo $post['id']; ?>">
                                        <?php echo htmlspecialchars($post['title']); ?>
                                    </a>
                                </h3>
                                <div class="post-meta">
                                    <time datetime="<?php echo $post['created_at']; ?>">
                                        <?php echo date('Y年n月j日', strtotime($post['created_at'])); ?>
                                    </time>
                                </div>
                                <?php if (!empty($post['categories'])): ?>
                                    <div class="post-categories">
                                        <?php foreach (explode(',', $post['categories']) as $category): ?>
                                            <span class="category-tag">
                                                <?php echo htmlspecialchars($category); ?>
                                            </span>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    <?php endif; ?>
</body>
</html>