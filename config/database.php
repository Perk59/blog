<?php
class Database {
    private $db;
    private $current_user;
    private $current_timestamp;
    private $connected = false;
    
    public function __construct() {
        try {
            $this->db = new SQLite3(DB_PATH);
            $this->db->enableExceptions(true);
            $this->connected = true;
            
            // 現在のユーザーと時刻を設定
            $this->current_user = 'Perk59';
            $this->current_timestamp = '2025-03-11 17:01:16';
            
            $this->createTables();
        } catch (Exception $e) {
            error_log('Database connection error: ' . $e->getMessage());
            $this->connected = false;
            die('データベース接続エラー: ' . $e->getMessage());
        }
    }

    public function isConnected() {
        return $this->connected;
    }

    private function createTables() {
        // ユーザーテーブル
    $this->db->exec("
        CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username VARCHAR(50) UNIQUE NOT NULL,
            email VARCHAR(255) UNIQUE NOT NULL,
            password_hash VARCHAR(255) NOT NULL,
            profile_image VARCHAR(255),
            bio TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP,
            created_by VARCHAR(50),
            updated_by VARCHAR(50),
            last_login TIMESTAMP,
            reset_token VARCHAR(255),
            reset_token_expires_at TIMESTAMP,
            login_attempts INTEGER DEFAULT 0,
            last_attempt_time TIMESTAMP
        )
    ");

        // 投稿テーブル
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS posts (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                title VARCHAR(255) NOT NULL,
                content TEXT,
                status VARCHAR(20) DEFAULT 'draft',
                featured_image VARCHAR(255),
                view_count INTEGER DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP,
                created_by VARCHAR(50),
                updated_by VARCHAR(50),
                FOREIGN KEY (user_id) REFERENCES users(id)
            )
        ");

        // カテゴリーテーブル
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS categories (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name VARCHAR(50) UNIQUE NOT NULL,
                slug VARCHAR(50) UNIQUE NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                created_by VARCHAR(50)
            )
        ");

        // 投稿とカテゴリーの関連テーブル
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS post_categories (
                post_id INTEGER,
                category_id INTEGER,
                PRIMARY KEY (post_id, category_id),
                FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE,
                FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE
            )
        ");

        // ログイン履歴テーブル
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS login_history (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                login_time DATETIME NOT NULL,
                ip_address TEXT NOT NULL,
                user_agent TEXT NOT NULL,
                status TEXT NOT NULL,
                created_at DATETIME NOT NULL,
                FOREIGN KEY (user_id) REFERENCES users(id)
            )
        ");

        // ログインロックテーブル
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS login_locks (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                lock_until DATETIME NOT NULL,
                created_at DATETIME NOT NULL,
                released_at DATETIME,
                FOREIGN KEY (user_id) REFERENCES users(id)
            )
        ");
    }

    public function query($sql, $params = []) {
    try {
        // 監査フィールドのスキップフラグをチェック
        $skip_audit = strpos($sql, '/*SKIP_AUDIT*/') !== false;
        $sql = str_replace('/*SKIP_AUDIT*/', '', $sql);

        // テーブル名を抽出
        $table_name = $this->extractTableName($sql);
        
        // 監査フィールドの自動設定
        if (!$skip_audit && $table_name) {
            // テーブルのカラム情報を取得
            $columns = $this->getTableColumns($table_name);
            
            if (stripos($sql, 'INSERT') !== false) {
                if (in_array('created_by', $columns) && in_array('created_at', $columns)) {
                    $sql = $this->addCreatedBy($sql);
                    $params[':created_by'] = $this->current_user;
                    $params[':created_at'] = $this->current_timestamp;
                }
            }
            
            if (stripos($sql, 'UPDATE') !== false) {
                if (in_array('updated_by', $columns) && in_array('updated_at', $columns)) {
                    $sql = $this->addUpdatedBy($sql);
                    $params[':updated_by'] = $this->current_user;
                    $params[':updated_at'] = $this->current_timestamp;
                }
            }
        }

        $stmt = $this->db->prepare($sql);
        if ($stmt === false) {
            throw new Exception('SQLクエリの準備に失敗しました: ' . $this->db->lastErrorMsg());
        }

        foreach ($params as $param => $value) {
            $type = is_int($value) ? SQLITE3_INTEGER : SQLITE3_TEXT;
            $stmt->bindValue($param, $value, $type);
        }

        $result = $stmt->execute();
        if ($result === false) {
            throw new Exception('クエリの実行に失敗しました: ' . $this->db->lastErrorMsg());
        }

        return $result;

    } catch (Exception $e) {
        error_log(sprintf(
            "SQLエラー: %s\nクエリ: %s\nパラメータ: %s\nユーザー: %s\n時刻: %s",
            $e->getMessage(),
            $sql,
            print_r($params, true),
            $this->current_user,
            $this->current_timestamp
        ));
        throw $e;
    }
}

    // テーブル名を抽出するメソッド
    private function extractTableName($sql) {
        $pattern = '/\b(INSERT\s+INTO|UPDATE|DELETE\s+FROM)\s+([`\'"[]?)([\w]+)([`\'"]]?)/i';
        if (preg_match($pattern, $sql, $matches)) {
            return $matches[3];
        }
        return null;
    }
    
    // テーブルのカラム情報を取得するメソッド
    private function getTableColumns($table_name) {
        $columns = [];
        $result = $this->db->query("PRAGMA table_info(" . $this->escapeString($table_name) . ")");
        
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $columns[] = $row['name'];
        }
        
        return $columns;
    }

    private function addCreatedBy($sql) {
        if (stripos($sql, 'created_by') === false && stripos($sql, 'created_at') === false) {
            $sql = preg_replace(
                '/\)\s*VALUES\s*\(/i',
                ', created_by, created_at) VALUES (:created_by, :created_at,',
                $sql
            );
        }
        return $sql;
    }

    private function addUpdatedBy($sql) {
        if (stripos($sql, 'SET') !== false && 
            stripos($sql, 'updated_by') === false && 
            stripos($sql, 'updated_at') === false) {
            $pos = stripos($sql, 'WHERE');
            if ($pos === false) {
                $sql = rtrim($sql) . ', updated_by = :updated_by, updated_at = :updated_at';
            } else {
                $sql = substr_replace(
                    $sql,
                    ', updated_by = :updated_by, updated_at = :updated_at ',
                    $pos,
                    0
                );
            }
        }
        return $sql;
    }

    public function lastInsertId() {
        return $this->db->lastInsertRowID();
    }

    public function getCurrentUser() {
        return $this->current_user;
    }

    public function getCurrentTimestamp() {
        return $this->current_timestamp;
    }

    public function setCurrentUser($username) {
        $this->current_user = $username;
    }

    public function beginTransaction() {
        return $this->db->exec('BEGIN TRANSACTION');
    }

    public function commit() {
        return $this->db->exec('COMMIT');
    }

    public function rollback() {
        return $this->db->exec('ROLLBACK');
    }

    public function close() {
        if ($this->db) {
            $this->db->close();
        }
    }

    public function __destruct() {
        $this->close();
    }

    // 新しく追加されたメソッド
    public function updateCurrentTimestamp() {
        $this->current_timestamp = gmdate('Y-m-d H:i:s');
    }

    public function getLastError() {
        return $this->db ? $this->db->lastErrorMsg() : 'Database not connected';
    }

    public function escapeString($string) {
        return $this->db ? $this->db->escapeString($string) : $string;
    }
}