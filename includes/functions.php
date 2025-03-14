<?php
function sendResetEmail($email, $token) {
    $reset_link = SITE_URL . '/reset-password?token=' . $token;
    $to = $email;
    $subject = 'パスワードリセットのご依頼';
    
    $message = "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
    </head>
    <body>
        <p>パスワードリセットのリクエストを受け付けました。</p>
        <p>下記のリンクをクリックして、新しいパスワードを設定してください：</p>
        <p><a href='{$reset_link}'>{$reset_link}</a></p>
        <p>このリンクの有効期限は24時間です。</p>
        <p>※パスワードリセットをリクエストしていない場合は、このメールを無視してください。</p>
    </body>
    </html>";

    $headers = [
        'MIME-Version: 1.0',
        'Content-Type: text/html; charset=UTF-8',
        'From: ブログシステム <no-reply@' . $_SERVER['SERVER_NAME'] . '>',
        'X-Mailer: PHP/' . phpversion()
    ];

    return mail($to, $subject, $message, implode("\r\n", $headers));
}

function generateToken($length = 32) {
    return bin2hex(random_bytes($length));
}
