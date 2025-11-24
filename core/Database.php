<?php
/**
 * core/Database.php
 * Enhanced PDO Database wrapper with connection pooling and query logging
 */

class Database {
    private static $instance = null;
    private $pdo = null;
    private $logger = null;
    
    private function __construct() {
        $host = DB_HOST;
        $db_name = DB_NAME;
        $db_user = DB_USER;
        // Get password - ensure it's always a string, even if empty
        $password = '';
        if (defined('DB_PASS')) {
            $password = DB_PASS;
        } elseif (defined('DB_PASSWORD')) {
            $password = DB_PASSWORD; // Alternative constant name
        }
        // Ensure password is a string (not null)
        $password = (string)$password;
        $charset = DB_CHARSET;
        
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::ATTR_PERSISTENT         => false,
            PDO::ATTR_TIMEOUT            => 10, // 10 second timeout
        ];
        
        // For shared hosting, try multiple connection methods
        $connection_methods = [];
        
        // Method 1: Use DB_HOST as-is (for shared hosting, often 'localhost' works)
        $connection_methods[] = [
            'host' => $host,
            'dsn' => "mysql:host={$host};dbname={$db_name};charset={$charset}",
            'description' => "Using DB_HOST as-is: {$host}"
        ];
        
        // Method 2: If host is '127.0.0.1', try 'localhost' (common on shared hosting)
        if (strtolower($host) === '127.0.0.1') {
            $connection_methods[] = [
                'host' => 'localhost',
                'dsn' => "mysql:host=localhost;dbname={$db_name};charset={$charset}",
                'description' => "Trying 'localhost' instead of '127.0.0.1'"
            ];
        }
        
        // Method 3: If host is 'localhost', try '127.0.0.1' (for some systems)
        if (strtolower($host) === 'localhost') {
            $connection_methods[] = [
                'host' => '127.0.0.1',
                'dsn' => "mysql:host=127.0.0.1;dbname={$db_name};charset={$charset}",
                'description' => "Trying '127.0.0.1' instead of 'localhost'"
            ];
        }
        
        // Try each connection method
        $last_error = null;
        foreach ($connection_methods as $method) {
            try {
                // Always pass password explicitly (even if empty string)
                // This ensures PDO doesn't skip password parameter
                $this->pdo = new PDO($method['dsn'], $db_user, $password, $options);
                // Test the connection
                $this->pdo->query("SELECT 1");
                // Success! Connection established
                return;
            } catch (PDOException $e) {
                $last_error = $e;
                // Continue to next method
                continue;
            }
        }
        
        // Additional check: if password seems missing, provide specific error
        if (empty($password) && strpos($last_error->getMessage(), 'using password: NO') !== false) {
            $error_msg = "Database connection failed: Password not provided or empty.\n\n";
            $error_msg .= "Please check config.php and ensure DB_PASS is set:\n";
            $error_msg .= "define('DB_PASS', 'your_actual_password');\n\n";
            $error_msg .= "Current settings:\n";
            $error_msg .= "- DB_HOST: " . $host . "\n";
            $error_msg .= "- DB_USER: " . $db_user . "\n";
            $error_msg .= "- DB_NAME: " . $db_name . "\n";
            $error_msg .= "- DB_PASS: " . (defined('DB_PASS') ? (empty(DB_PASS) ? '(empty)' : '(set)') : '(not defined)') . "\n";
            $this->getLogger()->error($error_msg);
            throw new Exception($error_msg);
        }
        
        // If all methods failed, throw the last error with helpful message
        $error_msg = "Database connection failed: " . $last_error->getMessage();
        $error_msg .= "\n\nTried connection methods:";
        foreach ($connection_methods as $method) {
            $error_msg .= "\n- " . $method['description'];
        }
        $error_msg .= "\n\nFor shared hosting, common solutions:";
        $error_msg .= "\n1. Use 'localhost' as DB_HOST (not '127.0.0.1')";
        $error_msg .= "\n2. Verify database name, username, and password in cPanel";
        $error_msg .= "\n3. Check if database exists in phpMyAdmin";
        $error_msg .= "\n4. Some hosts use different MySQL hostnames (check cPanel MySQL section)";
        
        $this->getLogger()->error($error_msg);
        throw new Exception($error_msg);
    }
    
    /**
     * Get logger instance (lazy-loaded)
     */
    private function getLogger() {
        if ($this->logger === null) {
            if (class_exists('Logger')) {
                $this->logger = new Logger();
            } else {
                // Fallback logger that uses error_log
                $this->logger = new class {
                    public function error($message, $context = []) {
                        error_log("Database Error: " . $message . (empty($context) ? '' : ' ' . json_encode($context)));
                    }
                };
            }
        }
        return $this->logger;
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function getPDO() {
        return $this->pdo;
    }
    
    public function getConnection() {
        return $this->pdo;
    }
    
    public function query($sql, $params = []) {
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            $this->getLogger()->error("Query failed: " . $e->getMessage(), ['sql' => $sql, 'params' => $params]);
            throw $e;
        }
    }
    
    public function fetch($sql, $params = []) {
        return $this->query($sql, $params)->fetch();
    }
    
    public function fetchAll($sql, $params = []) {
        return $this->query($sql, $params)->fetchAll();
    }
    
    public function insert($table, $data) {
        $fields = array_keys($data);
        $placeholders = ':' . implode(', :', $fields);
        $sql = "INSERT INTO `{$table}` (`" . implode('`, `', $fields) . "`) VALUES ({$placeholders})";
        $this->query($sql, $data);
        return $this->pdo->lastInsertId();
    }
    
    public function update($table, $data, $where, $whereParams = []) {
        $set = [];
        foreach (array_keys($data) as $field) {
            $set[] = "`{$field}` = :{$field}";
        }
        $sql = "UPDATE `{$table}` SET " . implode(', ', $set) . " WHERE {$where}";
        $this->query($sql, array_merge($data, $whereParams));
    }
    
    public function delete($table, $where, $params = []) {
        $sql = "DELETE FROM `{$table}` WHERE {$where}";
        $this->query($sql, $params);
    }
    
    public function beginTransaction() {
        return $this->pdo->beginTransaction();
    }
    
    public function commit() {
        return $this->pdo->commit();
    }
    
    public function rollBack() {
        return $this->pdo->rollBack();
    }
}

// Alias for backward compatibility - guarded to prevent duplicate function errors
if (!function_exists('getPDO')) {
    function getPDO() {
        if (class_exists('Database') && method_exists('Database', 'getInstance')) {
            return Database::getInstance()->getConnection();
        }
        // Fallback: create PDO from constants if Database class not available
        if (defined('DB_HOST') && defined('DB_NAME') && defined('DB_USER') && defined('DB_CHARSET')) {
            $host = DB_HOST;
            $db_name = DB_NAME;
            $db_user = DB_USER;
            // Get password - ensure it's always a string
            $password = '';
            if (defined('DB_PASS')) {
                $password = DB_PASS;
            } elseif (defined('DB_PASSWORD')) {
                $password = DB_PASSWORD;
            }
            $password = (string)$password; // Ensure it's a string
            $charset = DB_CHARSET;
            
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_TIMEOUT => 10,
            ];
            
            // Try multiple connection methods for shared hosting
            $connection_methods = [];
            $connection_methods[] = "mysql:host={$host};dbname={$db_name};charset={$charset}";
            
            if (strtolower($host) === '127.0.0.1') {
                $connection_methods[] = "mysql:host=localhost;dbname={$db_name};charset={$charset}";
            }
            if (strtolower($host) === 'localhost') {
                $connection_methods[] = "mysql:host=127.0.0.1;dbname={$db_name};charset={$charset}";
            }
            
            $last_error = null;
            foreach ($connection_methods as $dsn) {
                try {
                    $pdo = new PDO($dsn, $db_user, $password, $options);
                    $pdo->query("SELECT 1"); // Test connection
                    return $pdo;
                } catch (PDOException $e) {
                    $last_error = $e;
                    continue;
                }
            }
            
            throw new Exception("Database connection failed: " . ($last_error ? $last_error->getMessage() : "Unknown error"));
        }
        throw new Exception("Database configuration not available");
    }
}
