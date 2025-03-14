<?php
require_once '../includes/security_headers.php';
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

// セッションチェック
if (!isset($_SESSION['user_id'])) {
    $_SESSION['redirect_after_login'] = SITE_URL . '/admin';
    header('Location: ' . SITE_URL . '/login');
    exit;
}

$db = new Database();
$errors = [];
$success_messages = [];

// ページネーション設定
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = 10;
$offset = ($page - 1) * $per_page;

// 検索条件の取得
$search = filter_input(INPUT_GET, 'search', FILTER_SANITIZE_STRING) ?? '';
$status = filter_input(INPUT_GET, 'status', FILTER_SANITIZE_STRING) ?? '';
$sort = filter_input(INPUT_GET, 'sort', FILTER_SANITIZE_STRING) ?? 'newest';

// クエリの構築
$where_conditions = ['user_id = :user_id'];
$params = [':user_id' => $_SESSION['user_id']];

if (!empty($search)) {
    $where_conditions[] = '(title LIKE :search OR content LIKE :search)';
    $params[':search'] = '%' . $search . '%';
}

if (!empty($status) && in_array($status, ['draft', 'published'])) {
    $where_conditions[] = 'status = :status';
    $params[':status'] = $status;
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// ソート順の設定
$order_by = match($sort) {
    'oldest' => 'created_at ASC',
    'title' => 'title ASC',
    'views' => 'view_count DESC',
    default => 'created_at DESC'
};

try {
    $count_result = $db->query(
        "SELECT COUNT(*) as total FROM posts {$where_clause}",
        $params
    );
    
    if ($count_result === false) {
        throw new Exception('投稿数の取得に失敗しました');
    }
    
    $row = $count_result->fetchArray(SQLITE3_ASSOC);
    if ($row === false) {
        $total_posts = 0;
    } else {
        $total_posts = $row['total'];
    }
} catch (Exception $e) {
    $errors[] = $e->getMessage();
    $total_posts = 0;
}

$total_pages = max(1, ceil($total_posts / $per_page));

// 記事の取得部分も同様に修正
try {
    $params[':limit'] = $per_page;
    $params[':offset'] = $offset;
    
    $result = $db->query(
        "SELECT * FROM posts 
        {$where_clause} 
        ORDER BY {$order_by} 
        LIMIT :limit OFFSET :offset",
        $params
    );
    
    if ($result === false) {
        throw new Exception('記事の取得に失敗しました');
    }

    $posts = [];
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $posts[] = $row;
    }
} catch (Exception $e) {
    $errors[] = $e->getMessage();
    $posts = [];
}

// ステータス別の記事数取得も修正
try {
    $status_counts = [
        'all' => $total_posts,
        'published' => 0,
        'draft' => 0
    ];

    $status_result = $db->query(
        "SELECT status, COUNT(*) as count 
        FROM posts 
        WHERE user_id = :user_id 
        GROUP BY status",
        [':user_id' => $_SESSION['user_id']]
    );
    
    if ($status_result === false) {
        throw new Exception('ステータス別記事数の取得に失敗しました');
    }

    while ($row = $status_result->fetchArray(SQLITE3_ASSOC)) {
        if ($row !== false) {
            $status_counts[$row['status']] = $row['count'];
        }
    }
} catch (Exception $e) {
    $errors[] = $e->getMessage();
}
$total_pages = ceil($total_posts / $per_page);

// 記事の取得
$result = $db->query(
    "SELECT * FROM posts 
    {$where_clause} 
    ORDER BY {$order_by} 
    LIMIT :limit OFFSET :offset",
    array_merge($params, [
        ':limit' => $per_page,
        ':offset' => $offset
    ])
);

$posts = [];
while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
    $posts[] = $row;
}

// ステータス別の記事数を取得
$status_counts = [
    'all' => $total_posts,
    'published' => 0,
    'draft' => 0
];

$status_result = $db->query(
    "SELECT status, COUNT(*) as count 
    FROM posts 
    WHERE user_id = :user_id 
    GROUP BY status",
    [':user_id' => $_SESSION['user_id']]
);

while ($row = $status_result->fetchArray(SQLITE3_ASSOC)) {
    $status_counts[$row['status']] = $row['count'];
}

$db->close();
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>管理画面 - ブログシステム</title>
    <link rel="stylesheet" href="<?php echo ASSETS_URL; ?>/css/style.css">
    <link rel="stylesheet" href="<?php echo ASSETS_URL; ?>/css/admin.css">
    <!-- グローバル変数の定義 -->
    <script>
        const SITE_URL = '<?php echo SITE_URL; ?>';
        const CURRENT_USER = '<?php echo htmlspecialchars($_SESSION['username'] ?? ''); ?>';
        const CURRENT_TIME = '<?php echo date('Y-m-d H:i:s'); ?>';
    </script>
</head>
<!-- headerの直後に追加 -->
<?php if (!empty($errors)): ?>
    <div class="alert alert-danger">
        <ul>
            <?php foreach ($errors as $error): ?>
                <li><?php echo htmlspecialchars($error); ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>
<body>
    <div class="admin-container">
        <header class="admin-header">
            <div class="header-content">
                <h1>記事管理</h1>
                <div class="header-actions">
                    <a href="<?php echo SITE_URL; ?>/admin/posts" class="btn btn-primary">
                        <i class="fas fa-plus"></i> 新規記事作成
                    </a>
                </div>
            </div>
            <nav class="admin-nav">
                <a href="<?php echo SITE_URL; ?>/admin" class="active">記事一覧</a>
                <a href="<?php echo SITE_URL; ?>/admin/profile">プロフィール</a>
                <a href="<?php echo SITE_URL; ?>/logout">ログアウト</a>
            </nav>
        </header>

        <main class="admin-main">
            <!-- フィルターセクション -->
            <section class="filters">
                <form method="GET" class="filter-form">
                    <div class="filter-group">
                        <input type="text" name="search" 
                               value="<?php echo htmlspecialchars($search); ?>" 
                               placeholder="記事を検索...">
                    </div>

                    <div class="filter-group">
                        <select name="status">
                            <option value="">すべての状態</option>
                            <option value="published" <?php echo $status === 'published' ? 'selected' : ''; ?>>
                                公開済み (<?php echo $status_counts['published']; ?>)
                            </option>
                            <option value="draft" <?php echo $status === 'draft' ? 'selected' : ''; ?>>
                                下書き (<?php echo $status_counts['draft']; ?>)
                            </option>
                        </select>
                    </div>

                    <div class="filter-group">
                        <select name="sort">
                            <option value="newest" <?php echo $sort === 'newest' ? 'selected' : ''; ?>>新しい順</option>
                            <option value="oldest" <?php echo $sort === 'oldest' ? 'selected' : ''; ?>>古い順</option>
                            <option value="title" <?php echo $sort === 'title' ? 'selected' : ''; ?>>タイトル順</option>
                            <option value="views" <?php echo $sort === 'views' ? 'selected' : ''; ?>>閲覧数順</option>
                        </select>
                    </div>

                    <button type="submit" class="btn btn-secondary">適用</button>
                </form>
            </section>

        <table class="posts-table">
            <thead>
                <tr>
                    <th>タイトル</th>
                    <th>状態</th>
                    <th>作成日</th>
                    <th>更新日</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($posts as $post): ?>
                <tr>
                    <td>
                        <a href="<?php echo SITE_URL; ?>/admin/edit-post.php?id=<?php echo $post['id']; ?>">
                            <?php echo htmlspecialchars($post['title']); ?>
                        </a>
                    </td>
                    <td>
                        <span class="status <?php echo $post['status']; ?>">
                            <?php echo $post['status'] === 'published' ? '公開' : '下書き'; ?>
                        </span>
                    </td>
                    <td><?php echo date('Y-m-d H:i', strtotime($post['created_at'])); ?></td>
                    <td><?php echo $post['updated_at'] ? date('Y-m-d H:i', strtotime($post['updated_at'])) : '-'; ?></td>
                    <td class="actions">
                        <a href="<?php echo SITE_URL; ?>/admin/edit-post.php?id=<?php echo $post['id']; ?>" 
                           class="btn btn-small btn-primary">編集</a>
                        <button onclick="confirmDelete(<?php echo $post['id']; ?>, '<?php echo htmlspecialchars($post['title']); ?>')" 
                                class="btn btn-small btn-danger">削除</button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <!-- ページネーション -->
        <?php if ($total_pages > 1): ?>
        <div class="pagination">
            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                <a href="?page=<?php echo $i; ?>&status=<?php echo $current_status; ?>" 
                   class="page-number <?php echo $current_page === $i ? 'active' : ''; ?>">
                    <?php echo $i; ?>
                </a>
            <?php endfor; ?>
        </div>
        <?php endif; ?>
    </main>

    <!-- JavaScript -->
    <script>
        function confirmDelete(postId, postTitle) {
            if (confirm(`"${postTitle}" を削除してもよろしいですか？`)) {
                window.location.href = `${SITE_URL}/admin/delete-post.php?id=${postId}`;
            }
        }

        // フィルターの処理
        document.addEventListener('DOMContentLoaded', function() {
            const filterForm = document.querySelector('.filter-form');
            if (filterForm) {
                filterForm.addEventListener('submit', function(e) {
                    e.preventDefault();
                    const status = document.querySelector('#status-filter').value;
                    const search = document.querySelector('#search-input').value;
                    window.location.href = `${SITE_URL}/admin/?status=${status}&search=${encodeURIComponent(search)}`;
                });
            }
        });

        // 成功メッセージの自動非表示
        document.addEventListener('DOMContentLoaded', function() {
            const successAlert = document.querySelector('.alert-success');
            if (successAlert) {
                setTimeout(() => {
                    successAlert.style.opacity = '0';
                    setTimeout(() => {
                        successAlert.remove();
                    }, 500);
                }, 3000);
            }
        });

        // タイムスタンプの更新
        function updateTimestamp() {
            const timestampElement = document.querySelector('.timestamp');
            if (timestampElement) {
                const now = new Date();
                const formatted = now.toLocaleString('ja-JP', {
                    year: 'numeric',
                    month: '2-digit',
                    day: '2-digit',
                    hour: '2-digit',
                    minute: '2-digit',
                    second: '2-digit'
                });
                timestampElement.textContent = formatted;
            }
        }

        // 1分ごとにタイムスタンプを更新
        setInterval(updateTimestamp, 60000);
    </script>
</body>
</html>
