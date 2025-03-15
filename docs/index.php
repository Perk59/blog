<?php
require_once 'config/config.php';
require_once 'config/database.php';
require_once 'includes/functions.php';

$db = new Database();
$errors = [];

try {
    // ページネーション設定
    $posts_per_page = 9; // 3x3のグリッドに変更
    $current_page = max(1, filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT) ?? 1);
    $offset = ($current_page - 1) * $posts_per_page;

    // 投稿を取得
    $query = "
        SELECT 
            p.*,
            u.id as author_id,
            u.username,
            u.display_name,
            u.profile_image,
            (
                SELECT GROUP_CONCAT(c.name) 
                FROM categories c 
                JOIN post_categories pc ON c.id = pc.category_id 
                WHERE pc.post_id = p.id
            ) as categories,
            (SELECT COUNT(*) FROM posts WHERE status = 'published') as total_count
        FROM posts p
        LEFT JOIN users u ON p.user_id = u.id
        WHERE p.status = 'published'
        ORDER BY p.created_at DESC
        LIMIT :limit OFFSET :offset
    ";

    $result = $db->query($query, [
        ':limit' => $posts_per_page,
        ':offset' => $offset
    ]);

    if ($result === false) {
        throw new Exception('Failed to fetch posts');
    }

    $posts = [];
    $total_posts = 0;
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $posts[] = $row;
        if (isset($row['total_count'])) {
            $total_posts = $row['total_count'];
        }
    }

    $total_pages = ceil($total_posts / $posts_per_page);

} catch (Exception $e) {
    $errors[] = $e->getMessage();
    error_log($e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo SITE_NAME; ?></title>
    <!-- Googleフォントの読み込み -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+JP:wght@400;500;700&display=swap" rel="stylesheet">
    <!-- カスタムCSSの読み込み -->
<!-- headタグ内のスタイル部分を更新 -->
<style>
    :root {
        --primary-color: #2563eb;
        --primary-dark: #1d4ed8;
        --secondary-color: #0f172a;
        --accent-color: #f97316;
        --text-color: #1e293b;
        --text-light: #64748b;
        --background-color: #f1f5f9;
        --card-background: #ffffff;
        --border-radius: 16px;
        --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        --shadow-sm: 0 1px 2px 0 rgb(0 0 0 / 0.05);
        --shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1);
        --shadow-lg: 0 10px 15px -3px rgb(0 0 0 / 0.1), 0 4px 6px -4px rgb(0 0 0 / 0.1);
    }

    body {
        font-family: 'Noto Sans JP', -apple-system, BlinkMacSystemFont, sans-serif;
        background: var(--background-color);
        color: var(--text-color);
        line-height: 1.6;
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

    .site-title a {
        font-size: 1.5rem;
        font-weight: 800;
        background: linear-gradient(to right, var(--primary-color), var(--accent-color));
        -webkit-background-clip: text;
        color: transparent;
        text-decoration: none;
        letter-spacing: -0.5px;
    }

    .site-nav {
        display: flex;
        gap: 1.5rem;
        align-items: center;
    }

    .nav-link {
        color: var(--text-color);
        text-decoration: none;
        padding: 0.5rem 1rem;
        border-radius: 9999px;
        font-weight: 500;
        transition: var(--transition);
        font-size: 0.95rem;
    }

    .nav-link:hover {
        background: var(--primary-color);
        color: white;
    }

    .posts-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
        gap: 2rem;
        padding: 2rem;
        margin: 0 auto;
        max-width: 1400px;
    }

    .post-card {
        background: var(--card-background);
        border-radius: var(--border-radius);
        overflow: hidden;
        box-shadow: var(--shadow);
        transition: var(--transition);
        position: relative;
        isolation: isolate;
    }

    .post-card:hover {
        transform: translateY(-4px);
        box-shadow: var(--shadow-lg);
    }

    .post-image {
        position: relative;
        padding-top: 56.25%; /* 16:9 アスペクト比 */
    }

    .post-image img {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        object-fit: cover;
        transition: var(--transition);
    }

    .post-card:hover .post-image img {
        transform: scale(1.05);
    }

    .post-content {
        padding: 1.5rem;
    }

    .post-title {
        font-size: 1.25rem;
        font-weight: 700;
        color: var(--secondary-color);
        margin-bottom: 1rem;
        line-height: 1.4;
    }

    .post-meta {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 1rem;
    }

    .post-author {
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }

    .author-avatar,
    .author-avatar-placeholder {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        overflow: hidden;
        border: 2px solid var(--primary-color);
    }

    .author-avatar-placeholder {
        background: var(--primary-color);
        color: white;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 600;
        font-size: 1.2rem;
    }

    .author-name {
        font-weight: 500;
        color: var(--text-color);
    }

    .post-date {
        font-size: 0.875rem;
        color: var(--text-light);
    }

    .post-categories {
        display: flex;
        flex-wrap: wrap;
        gap: 0.5rem;
        margin-top: 1rem;
    }

    .category-tag {
        font-size: 0.75rem;
        padding: 0.25rem 0.75rem;
        background: var(--primary-color);
        color: white;
        border-radius: 9999px;
        font-weight: 500;
    }

    .user-welcome {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        font-size: 0.875rem;
        color: var(--text-light);
        background: var(--background-color);
        padding: 0.5rem 1rem;
        border-radius: 9999px;
    }

    .pagination {
        display: flex;
        justify-content: center;
        gap: 0.5rem;
        margin: 3rem 0;
    }

    .page-number,
    .page-nav {
        padding: 0.75rem 1.25rem;
        background: var(--card-background);
        border-radius: var(--border-radius);
        color: var(--text-color);
        text-decoration: none;
        font-weight: 500;
        transition: var(--transition);
        box-shadow: var(--shadow-sm);
    }

    .page-number.active {
        background: var(--primary-color);
        color: white;
    }

    .page-number:hover,
    .page-nav:hover {
        background: var(--primary-color);
        color: white;
        transform: translateY(-2px);
    }

    @media (prefers-color-scheme: dark) {
        :root {
            --text-color: #e2e8f0;
            --text-light: #94a3b8;
            --background-color: #0f172a;
            --card-background: #1e293b;
            --secondary-color: #e2e8f0;
        }

        .site-header {
            background: rgba(30, 41, 59, 0.9);
        }
    }

    @media (max-width: 768px) {
        .posts-grid {
            grid-template-columns: 1fr;
            padding: 1rem;
        }

        .header-content {
            flex-direction: column;
            gap: 1rem;
            padding: 1rem;
        }

        .site-nav {
            flex-wrap: wrap;
            justify-content: center;
        }

        .user-welcome {
            text-align: center;
            width: 100%;
            justify-content: center;
        }
    }
</style>
</head>
<body>
<header class="site-header">
    <div class="header-content">
        <h1 class="site-title">
            <a href="<?php echo SITE_URL; ?>"><?php echo SITE_NAME; ?></a>
        </h1>
        <nav class="site-nav">
            <span class="user-welcome">
                UTC: <?php echo date('Y-m-d H:i:s'); ?> | 
                ユーザー: <?php echo htmlspecialchars($_SESSION['username'] ?? 'Guest'); ?>
            </span>
            <?php if (isset($_SESSION['user_id'])): ?>
                <a href="<?php echo SITE_URL; ?>/admin" class="nav-link">管理画面</a>
                <a href="<?php echo SITE_URL; ?>/profile.php" class="nav-link">プロフィール</a>
                <a href="<?php echo SITE_URL; ?>/logout.php" class="nav-link">ログアウト</a>
            <?php else: ?>
                <a href="<?php echo SITE_URL; ?>/login.php" class="nav-link">ログイン</a>
            <?php endif; ?>
        </nav>
    </div>
</header>

    <main class="site-main">
        <?php if (!empty($errors)): ?>
            <div class="error-container">
                <?php foreach ($errors as $error): ?>
                    <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
                <?php endforeach; ?>
            </div>
        <?php elseif (empty($posts)): ?>
            <div class="no-posts">
                <h2>まだ投稿がありません</h2>
                <p>最初の投稿を作成してみましょう！</p>
            </div>
        <?php else: ?>
            <div class="posts-grid">
                <?php foreach ($posts as $post): ?>
<article class="post-card">
    <a href="<?php echo SITE_URL; ?>/post.php?id=<?php echo $post['id']; ?>" class="post-link">
        <div class="post-image">
            <?php if (!empty($post['featured_image'])): ?>
                <img src="<?php echo SITE_URL . '/' . $post['featured_image']; ?>" 
                     alt="<?php echo htmlspecialchars($post['title']); ?>">
            <?php else: ?>
                <div class="post-image-placeholder">
                    <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" fill="currentColor" viewBox="0 0 16 16">
                        <path d="M4 0h8a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V2a2 2 0 0 1 2-2zm0 1a1 1 0 0 0-1 1v12a1 1 0 0 0 1 1h8a1 1 0 0 0 1-1V2a1 1 0 0 0-1-1H4z"/>
                        <path d="M8 11a2.5 2.5 0 1 1 0-5 2.5 2.5 0 0 1 0 5zm0 1a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7z"/>
                    </svg>
                </div>
            <?php endif; ?>
        </div>
        <div class="post-content">
            <h2 class="post-title">
                <?php echo htmlspecialchars($post['title']); ?>
            </h2>
            <div class="post-meta">
                <div class="post-author">
                    <?php if (!empty($post['profile_image'])): ?>
                        <img src="<?php echo SITE_URL . '/' . $post['profile_image']; ?>" 
                             alt="" class="author-avatar">
                    <?php else: ?>
                        <div class="author-avatar-placeholder">
                            <?php echo !empty($post['username']) ? strtoupper(substr($post['username'], 0, 1)) : '?'; ?>
                        </div>
                    <?php endif; ?>
                    <span class="author-name">
                        <?php echo htmlspecialchars($post['display_name'] ?? $post['username'] ?? '名前未設定'); ?>
                    </span>
                </div>
                <time datetime="<?php echo $post['created_at']; ?>" class="post-date">
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
    </a>
</article>
                <?php endforeach; ?>
            </div>

            <?php if ($total_pages > 1): ?>
                <div class="pagination">
                    <?php if ($current_page > 1): ?>
                        <a href="?page=<?php echo $current_page - 1; ?>" class="page-nav prev">前へ</a>
                    <?php endif; ?>
                    
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <a href="?page=<?php echo $i; ?>" 
                           class="page-number <?php echo $current_page === $i ? 'active' : ''; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>
                    
                    <?php if ($current_page < $total_pages): ?>
                        <a href="?page=<?php echo $current_page + 1; ?>" class="page-nav next">次へ</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </main>

    <footer class="site-footer">
        <div class="footer-content">
            <div class="footer-info">
                <p>&copy; <?php echo date('Y'); ?> <?php echo SITE_NAME; ?>. All rights reserved.</p>
                <p class="current-time"><?php echo date('Y-m-d H:i:s'); ?> UTC</p>
            </div>
        </div>
    </footer>
</body>
</html>
