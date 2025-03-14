<?php
require_once 'config/config.php';
require_once 'config/database.php';
require_once 'includes/functions.php';

$db = new Database();
$errors = [];
$post = null;

try {
    $post_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
    if (!$post_id) {
        throw new Exception('無効な投稿IDです。');
    }

    // 投稿とその著者の情報を取得
    $query = "
        SELECT 
            p.*,
            u.id as author_id,
            u.username,
            u.display_name,
            u.profile_image,
            u.bio,
            (
                SELECT GROUP_CONCAT(c.name) 
                FROM categories c 
                JOIN post_categories pc ON c.id = pc.category_id 
                WHERE pc.post_id = p.id
            ) as categories
        FROM posts p
        LEFT JOIN users u ON p.user_id = u.id
        WHERE p.id = :id AND p.status = 'published'
    ";

    $result = $db->query($query, [':id' => $post_id]);
    if ($result === false) {
        throw new Exception('投稿の取得に失敗しました。');
    }

    $post = $result->fetchArray(SQLITE3_ASSOC);
    if (!$post) {
        throw new Exception('投稿が見つかりません。');
    }

    // ユーザー名を安全に取得
    $author_name = $post['display_name'] ?? $post['username'] ?? '名前未設定';
    $author_initial = !empty($post['username']) ? strtoupper(mb_substr($post['username'], 0, 1)) : '?';

} catch (Exception $e) {
    $errors[] = $e->getMessage();
}

// 現在のUTC時刻を取得
$current_utc = gmdate('Y-m-d H:i:s');
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($post['title'] ?? 'ブログ記事') . ' - ' . SITE_NAME; ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+JP:wght@400;500;700&display=swap" rel="stylesheet">
    <!-- 前回と同じCSSスタイル... -->
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
            --code-background: #f1f5f9;
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

        .post-header {
            background: var(--card-background);
            padding: 3rem 0;
            margin-bottom: 2rem;
            box-shadow: var(--shadow);
        }

        .post-header-content {
            max-width: 800px;
            margin: 0 auto;
            padding: 0 2rem;
        }

        .post-title {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 1.5rem;
            line-height: 1.3;
        }

        .post-meta {
            display: flex;
            align-items: center;
            gap: 2rem;
            color: var(--text-light);
        }

        .post-author {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .author-avatar {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            overflow: hidden;
        }

        .author-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .author-info {
            display: flex;
            flex-direction: column;
        }

        .author-name {
            font-weight: 500;
            color: var(--text-color);
        }

        .post-date {
            font-size: 0.9rem;
        }

        .post-content {
            max-width: 800px;
            margin: 0 auto;
            padding: 2rem;
            background: var(--card-background);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
        }

        .post-content img {
            max-width: 100%;
            height: auto;
            border-radius: 8px;
            margin: 2rem 0;
        }

        .post-content h2 {
            font-size: 1.8rem;
            margin: 2.5rem 0 1.5rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid var(--primary-color);
        }

        .post-content h3 {
            font-size: 1.4rem;
            margin: 2rem 0 1rem;
            color: var(--primary-dark);
        }

        .post-content p {
            margin: 1.5rem 0;
        }

        .post-content a {
            color: var(--primary-color);
            text-decoration: none;
            border-bottom: 1px solid transparent;
            transition: var(--transition);
        }

        .post-content a:hover {
            border-bottom-color: var(--primary-color);
        }

        .post-content pre {
            background: var(--code-background);
            padding: 1.5rem;
            border-radius: 8px;
            overflow-x: auto;
            font-size: 0.9rem;
        }

        .post-content code {
            background: var(--code-background);
            padding: 0.2em 0.4em;
            border-radius: 4px;
            font-size: 0.9em;
        }

        .post-content blockquote {
            margin: 2rem 0;
            padding: 1rem 2rem;
            border-left: 4px solid var(--primary-color);
            background: var(--background-color);
            border-radius: 0 8px 8px 0;
        }

        .post-content ul,
        .post-content ol {
            margin: 1.5rem 0;
            padding-left: 2rem;
        }

        .post-content li {
            margin: 0.5rem 0;
        }

        .post-categories {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
            margin: 2rem 0;
        }

        .category-tag {
            background: var(--primary-color);
            color: white;
            padding: 0.25rem 1rem;
            border-radius: 9999px;
            font-size: 0.875rem;
            font-weight: 500;
        }

        @media (prefers-color-scheme: dark) {
            :root {
                --text-color: #e2e8f0;
                --text-light: #94a3b8;
                --background-color: #0f172a;
                --card-background: #1e293b;
                --code-background: #2d3748;
            }

            .site-header {
                background: rgba(30, 41, 59, 0.9);
            }
        }

        @media (max-width: 768px) {
            .post-title {
                font-size: 2rem;
            }

            .post-header {
                padding: 2rem 0;
            }

            .post-meta {
                flex-direction: column;
                gap: 1rem;
                align-items: flex-start;
            }

            .post-content {
                padding: 1.5rem;
            }
        }
    </style>
    <style>
        /* 新しいスタイルを追加 */
        .user-info-bar {
            background: var(--card-background);
            padding: 0.5rem 1rem;
            position: fixed;
            bottom: 1rem;
            right: 1rem;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            display: flex;
            align-items: center;
            gap: 1rem;
            font-size: 0.875rem;
            z-index: 1000;
        }

        .user-info-bar .utc-time {
            color: var(--text-light);
            font-family: monospace;
        }

        .user-info-bar .user-name {
            color: var(--primary-color);
            font-weight: 500;
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
            margin: 1rem;
        }

        .back-to-home:hover {
            background: var(--primary-color);
            color: white;
        }

        .back-to-home svg {
            width: 20px;
            height: 20px;
        }

        /* エラー表示のスタイル */
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
            .error-message {
                background: #422424;
            }
            
            .user-info-bar {
                background: rgba(30, 41, 59, 0.9);
                backdrop-filter: blur(8px);
            }
        }
    </style>
</head>
<body>
    <header class="site-header">
        <div class="header-content">
            <a href="<?php echo SITE_URL; ?>" class="back-to-home">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
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
        <article>
            <header class="post-header">
                <div class="post-header-content">
                    <h1 class="post-title"><?php echo htmlspecialchars($post['title']); ?></h1>
                    <div class="post-meta">
                        <div class="post-author">
                            <div class="author-avatar">
                                <?php if (!empty($post['profile_image'])): ?>
                                    <img src="<?php echo SITE_URL . '/' . $post['profile_image']; ?>" 
                                         alt="<?php echo htmlspecialchars($author_name); ?>">
                                <?php else: ?>
                                    <div class="author-avatar-placeholder">
                                        <?php echo htmlspecialchars($author_initial); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <!-- post.phpの著者情報部分を修正 -->
<div class="author-info">
    <a href="<?php echo SITE_URL; ?>/users.php?id=<?php echo $post['author_id']; ?>" class="author-name-link">
        <span class="author-name">
            <?php echo htmlspecialchars($author_name); ?>
        </span>
    </a>
    <time datetime="<?php echo $post['created_at']; ?>" class="post-date">
        <?php echo date('Y年n月j日', strtotime($post['created_at'])); ?>
    </time>
</div>
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
                </div>
            </header>

            <div class="post-content">
                <?php echo $post['content']; ?>
            </div>
        </article>

        <div class="user-info-bar">
            <span class="utc-time">UTC: <?php echo $current_utc; ?></span>
            <span class="user-name">
                <?php echo isset($_SESSION['username']) ? htmlspecialchars($_SESSION['username']) : 'Guest'; ?>
            </span>
        </div>
    <?php endif; ?>
</body>
</html>