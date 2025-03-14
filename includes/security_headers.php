<?php
// セッションがまだ開始されていない場合のみセッション設定を変更
if (session_status() == PHP_SESSION_NONE) {
    // セッションの設定
    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_secure', 1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.cookie_samesite', 'Lax');
    // セッション開始
    session_start();
}

// セキュリティヘッダーの設定
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
header("Content-Security-Policy: default-src 'self' https: data: 'unsafe-inline' 'unsafe-eval';");
header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
header('Permissions-Policy: geolocation=(), microphone=(), camera=()');

// 現在のUTC時刻を取得
$current_utc = gmdate('Y-m-d H:i:s');