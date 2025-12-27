<?php
/**
 * کلاس ارتباط با دیتابیس
 * Database Connection Class - فقط برای ارتباط با MySQL
 */

require_once 'config.php';

class Database {
    private static $instance = null;
    private $conn;
    
    private function __construct() {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES " . DB_CHARSET
            ];
            
            $this->conn = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch(PDOException $e) {
            error_log("Database Connection Error: " . $e->getMessage());
            die("خطا در اتصال به پایگاه داده. لطفاً با مدیر سیستم تماس بگیرید.");
        }
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function getConnection() {
        return $this->conn;
    }
    
    /**
     * اجرای کوئری SELECT
     */
    public function select($sql, $params = []) {
        try {
            $stmt = $this->conn->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll();
        } catch(PDOException $e) {
            error_log("SELECT Error: " . $e->getMessage() . " | SQL: " . $sql);
            return false;
        }
    }
    
    /**
     * اجرای کوئری SELECT و دریافت یک رکورد
     */
    public function selectOne($sql, $params = []) {
        try {
            $stmt = $this->conn->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetch();
        } catch(PDOException $e) {
            error_log("SELECT ONE Error: " . $e->getMessage() . " | SQL: " . $sql);
            return false;
        }
    }
    
    /**
     * اجرای کوئری INSERT
     */
    public function insert($table, $data) {
        try {
            $keys = array_keys($data);
            $fields = implode(', ', $keys);
            $placeholders = ':' . implode(', :', $keys);
            
            $sql = "INSERT INTO {$table} ({$fields}) VALUES ({$placeholders})";
            $stmt = $this->conn->prepare($sql);
            
            foreach ($data as $key => $value) {
                $stmt->bindValue(':' . $key, $value);
            }
            
            $stmt->execute();
            return $this->conn->lastInsertId();
        } catch(PDOException $e) {
            error_log("INSERT Error: " . $e->getMessage() . " | Table: " . $table);
            return false;
        }
    }
    
    /**
     * اجرای کوئری UPDATE
     */
    public function update($table, $data, $where, $whereParams = []) {
        try {
            $set = [];
            foreach (array_keys($data) as $key) {
                $set[] = "{$key} = :{$key}";
            }
            $setStr = implode(', ', $set);
            
            $sql = "UPDATE {$table} SET {$setStr} WHERE {$where}";
            $stmt = $this->conn->prepare($sql);
            
            foreach ($data as $key => $value) {
                $stmt->bindValue(':' . $key, $value);
            }
            
            foreach ($whereParams as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            
            $stmt->execute();
            return $stmt->rowCount();
        } catch(PDOException $e) {
            error_log("UPDATE Error: " . $e->getMessage() . " | Table: " . $table);
            return false;
        }
    }
    
    /**
     * اجرای کوئری DELETE
     */
    public function delete($table, $where, $whereParams = []) {
        try {
            $sql = "DELETE FROM {$table} WHERE {$where}";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute($whereParams);
            return $stmt->rowCount();
        } catch(PDOException $e) {
            error_log("DELETE Error: " . $e->getMessage() . " | Table: " . $table);
            return false;
        }
    }
    
    /**
     * چک کردن وجود رکورد
     */
    public function exists($table, $where, $params = []) {
        try {
            $sql = "SELECT COUNT(*) as count FROM {$table} WHERE {$where}";
            $result = $this->selectOne($sql, $params);
            return $result && $result['count'] > 0;
        } catch(PDOException $e) {
            error_log("EXISTS Error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * شمارش رکوردها
     */
    public function count($table, $where = '1=1', $params = []) {
        try {
            $sql = "SELECT COUNT(*) as count FROM {$table} WHERE {$where}";
            $result = $this->selectOne($sql, $params);
            return $result ? (int)$result['count'] : 0;
        } catch(PDOException $e) {
            error_log("COUNT Error: " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * شروع Transaction
     */
    public function beginTransaction() {
        return $this->conn->beginTransaction();
    }
    
    /**
     * Commit کردن Transaction
     */
    public function commit() {
        return $this->conn->commit();
    }
    
    /**
     * Rollback کردن Transaction
     */
    public function rollback() {
        return $this->conn->rollback();
    }
    
    /**
     * اجرای کوئری خام (فقط برای موارد خاص)
     */
    public function query($sql, $params = []) {
        try {
            $stmt = $this->conn->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch(PDOException $e) {
            error_log("QUERY Error: " . $e->getMessage() . " | SQL: " . $sql);
            return false;
        }
    }
    
    /**
     * Escape کردن نام جدول یا فیلد
     */
    public function escapeIdentifier($identifier) {
        return '`' . str_replace('`', '``', $identifier) . '`';
    }
    
    /**
     * ساخت شرط IN برای کوئری
     */
    public function prepareInClause($values) {
        if (empty($values)) {
            return ['1=0', []];
        }
        
        $placeholders = [];
        $params = [];
        
        foreach ($values as $i => $value) {
            $key = ':in_' . $i;
            $placeholders[] = $key;
            $params[$key] = $value;
        }
        
        return [implode(',', $placeholders), $params];
    }
    
    /**
     * گرفتن لیست با صفحه‌بندی
     */
    public function paginate($sql, $params = [], $page = 1, $perPage = 20) {
        try {
            // شمارش کل رکوردها
            $countSql = "SELECT COUNT(*) as total FROM (" . $sql . ") as count_table";
            $totalResult = $this->selectOne($countSql, $params);
            $total = $totalResult ? (int)$totalResult['total'] : 0;
            
            // محاسبه offset
            $offset = ($page - 1) * $perPage;
            
            // اضافه کردن LIMIT و OFFSET
            $sql .= " LIMIT :limit OFFSET :offset";
            $params[':limit'] = (int)$perPage;
            $params[':offset'] = (int)$offset;
            
            $stmt = $this->conn->prepare($sql);
            
            // Bind کردن پارامترها
            foreach ($params as $key => $value) {
                if ($key === ':limit' || $key === ':offset') {
                    $stmt->bindValue($key, $value, PDO::PARAM_INT);
                } else {
                    $stmt->bindValue($key, $value);
                }
            }
            
            $stmt->execute();
            $data = $stmt->fetchAll();
            
            return [
                'data' => $data,
                'total' => $total,
                'page' => $page,
                'per_page' => $perPage,
                'total_pages' => ceil($total / $perPage)
            ];
        } catch(PDOException $e) {
            error_log("PAGINATE Error: " . $e->getMessage());
            return [
                'data' => [],
                'total' => 0,
                'page' => 1,
                'per_page' => $perPage,
                'total_pages' => 0
            ];
        }
    }
}

// تابع helper برای دسترسی سریع به دیتابیس
function db() {
    return Database::getInstance();
}
?>