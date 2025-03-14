<?php
// セッションが開始されていない場合のみセッション設定を変更
if (session_status() == PHP_SESSION_NONE) {
    // セッション名の設定
    $session_name = 'BLOGSESSID';
    session_name($session_name);

    // セッションの有効期限を設定（24時間）
    $session_lifetime = 86400;
    ini_set('session.gc_maxlifetime', $session_lifetime);

    // セッションクッキーのパラメータを設定
    session_set_cookie_params([
        'lifetime' => $session_lifetime,
        'path' => '/',
        'domain' => '',
        'secure' => true,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);

    // セキュリティ関連のセッション設定
    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_secure', 1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.cookie_samesite', 'Lax');

    // セッションを開始
    session_start();
}

// サイトの基本設定
define('SITE_NAME', 'ブログサイト');
define('SITE_URL', 'https://kondyprog000.xsrv.jp/blog');
define('ASSETS_URL', SITE_URL . '/assets');

// ログイン試行制限の設定
define('MAX_LOGIN_ATTEMPTS', 5); // 最大ログイン試行回数
define('LOGIN_TIMEOUT_MINUTES', 30); // ロックアウト時間（分）
// パスワードに関する設定を追加
define('PASSWORD_MIN_LENGTH', 8); // パスワードの最小文字数

// データベース設定
define('DB_PATH', __DIR__ . '/../blog.db');

// タイムゾーン設定
date_default_timezone_set('UTC');

// エラー表示設定
error_reporting(E_ALL);
ini_set('display_errors', 1);

// CSRF対策のトークンを生成
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// 共通の関数定義
function is_logged_in() {
    return isset($_SESSION['user_id']);
}

function require_login() {
    if (!is_logged_in()) {
        header('Location: ' . SITE_URL . '/login.php');
        exit;
    }
}

function check_csrf_token($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// セッションのアクティビティチェック
function check_session_activity() {
    $timeout = 30 * 60; // 30分
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $timeout)) {
        session_unset();
        session_destroy();
        header('Location: ' . SITE_URL . '/login.php?timeout=1');
        exit;
    }
    $_SESSION['last_activity'] = time();
}

// UTC時間のフォーマット関数
function format_utc_datetime() {
    return gmdate('Y-m-d H:i:s');
}

// ユーザー名を安全に取得する関数
function get_current_username() {
    return htmlspecialchars($_SESSION['username'] ?? 'Guest');
}

// セッションアクティビティのチェックを実行
if (isset($_SESSION['user_id'])) {
    check_session_activity();
}

// セキュリティヘッダーの設定
function set_security_headers() {
    header('X-Frame-Options: DENY');
    header('X-XSS-Protection: 1; mode=block');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header("Content-Security-Policy: default-src 'self' https: data: 'unsafe-inline' 'unsafe-eval';");
    header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
}

// セキュリティヘッダーを設定
set_security_headers();
