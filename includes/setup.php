<?php
require_once 'config/config.php';
require_once 'config/database.php';

try {
    $db = new Database();
    
    // テーブルを削除して再作成
    $db->db->exec('DROP TABLE IF EXISTS users');
    
    // ユーザーテーブルの作成
    $db->db->exec("
        CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username VARCHAR(50) UNIQUE NOT NULL,
            email VARCHAR(255) UNIQUE NOT NULL,
            password_hash VARCHAR(255) NOT NULL,
            display_name VARCHAR(100),
            profile_image VARCHAR(255),
            bio TEXT,
            status VARCHAR(20) DEFAULT 'active',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP,
            last_login TIMESTAMP,
            login_attempts INTEGER DEFAULT 0,
            last_attempt_time TIMESTAMP,
            created_by VARCHAR(50),
            updated_by VARCHAR(50)
        )
    ");

    // テストユーザーの作成
    $test_user = [
        'username' => 'testuser',
        'email' => 'test@example.com',
        'password' => 'password123',
        'display_name' => 'Test User'
    ];

    $password_hash = password_hash($test_user['password'], PASSWORD_DEFAULT);

    $insert_query = "
        INSERT INTO users (
            username, 
            email, 
            password_hash, 
            display_name, 
            status, 
            created_at
        ) VALUES (
            :username,
            :email,
            :password_hash,
            :display_name,
            'active',
            datetime('now')
        )
    ";

    $db->query($insert_query, [
        ':username' => $test_user['username'],
        ':email' => $test_user['email'],
        ':password_hash' => $password_hash,
        ':display_name' => $test_user['display_name']
    ]);

    echo "Database setup completed successfully.\n";
    echo "Test user created:\n";
    echo "Username: " . $test_user['username'] . "\n";
    echo "Email: " . $test_user['email'] . "\n";
    echo "Password: " . $test_user['password'] . "\n";

} catch (Exception $e) {
    die('Setup error: ' . $e->getMessage());
}