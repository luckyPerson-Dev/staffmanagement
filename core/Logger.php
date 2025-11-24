<?php
/**
 * core/Logger.php
 * Advanced logging system with levels and file rotation
 */

class Logger {
    private $logDir;
    private $logLevel;
    
    const LEVEL_DEBUG = 0;
    const LEVEL_INFO = 1;
    const LEVEL_WARNING = 2;
    const LEVEL_ERROR = 3;
    const LEVEL_CRITICAL = 4;
    
    public function __construct() {
        $this->logDir = ROOT_PATH . '/logs';
        if (!is_dir($this->logDir)) {
            mkdir($this->logDir, 0755, true);
        }
        $this->logLevel = defined('LOG_LEVEL') ? LOG_LEVEL : self::LEVEL_INFO;
    }
    
    private function write($level, $message, $context = []) {
        if ($level < $this->logLevel) {
            return;
        }
        
        $levelNames = ['DEBUG', 'INFO', 'WARNING', 'ERROR', 'CRITICAL'];
        $timestamp = date('Y-m-d H:i:s');
        $levelName = $levelNames[$level];
        
        $logFile = $this->logDir . '/app-' . date('Y-m-d') . '.log';
        $contextStr = !empty($context) ? ' ' . json_encode($context) : '';
        $logMessage = "[{$timestamp}] [{$levelName}] {$message}{$contextStr}" . PHP_EOL;
        
        file_put_contents($logFile, $logMessage, FILE_APPEND | LOCK_EX);
        
        // Also log to error_log for critical errors
        if ($level >= self::LEVEL_ERROR) {
            error_log($logMessage);
        }
    }
    
    public function debug($message, $context = []) {
        $this->write(self::LEVEL_DEBUG, $message, $context);
    }
    
    public function info($message, $context = []) {
        $this->write(self::LEVEL_INFO, $message, $context);
    }
    
    public function warning($message, $context = []) {
        $this->write(self::LEVEL_WARNING, $message, $context);
    }
    
    public function error($message, $context = []) {
        $this->write(self::LEVEL_ERROR, $message, $context);
    }
    
    public function critical($message, $context = []) {
        $this->write(self::LEVEL_CRITICAL, $message, $context);
    }
    
    public function audit($user_id, $action, $resource, $resource_id = null, $details = null, $before = null, $after = null) {
        $db = Database::getInstance();
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
        
        $data = [
            'user_id' => $user_id,
            'action' => $action,
            'resource' => $resource,
            'resource_id' => $resource_id,
            'details' => $details,
            'before_snapshot' => $before ? json_encode($before) : null,
            'after_snapshot' => $after ? json_encode($after) : null,
            'ip_address' => $ip,
            'user_agent' => $userAgent,
            'created_at' => date('Y-m-d H:i:s')
        ];
        
        try {
            $db->insert('audit_logs', $data);
        } catch (Exception $e) {
            $this->error("Failed to write audit log", ['error' => $e->getMessage()]);
        }
    }
}

