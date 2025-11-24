<?php
/**
 * core/Security.php
 * Advanced security features: 2FA, IP restriction, session fingerprinting, brute-force protection
 */

class Security {
    private $db;
    private $logger;
    
    public function __construct() {
        $this->db = Database::getInstance();
        $this->logger = new Logger();
    }
    
    public function generateFingerprint() {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $acceptLanguage = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '';
        return hash('sha256', $ip . $userAgent . $acceptLanguage);
    }
    
    public function verifyFingerprint($stored) {
        $current = $this->generateFingerprint();
        return hash_equals($stored, $current);
    }
    
    public function checkIPRestriction($user_id) {
        $restrictions = $this->db->fetchAll(
            "SELECT ip_address FROM user_ip_restrictions WHERE user_id = ? AND active = 1",
            [$user_id]
        );
        
        if (empty($restrictions)) {
            return true; // No restrictions
        }
        
        $currentIP = $_SERVER['REMOTE_ADDR'] ?? '';
        foreach ($restrictions as $restriction) {
            if ($this->ipMatches($currentIP, $restriction['ip_address'])) {
                return true;
            }
        }
        
        $this->logger->warning("IP restriction violation", ['user_id' => $user_id, 'ip' => $currentIP]);
        return false;
    }
    
    private function ipMatches($ip, $pattern) {
        // Support CIDR notation
        if (strpos($pattern, '/') !== false) {
            list($subnet, $mask) = explode('/', $pattern);
            $ipLong = ip2long($ip);
            $subnetLong = ip2long($subnet);
            $maskLong = -1 << (32 - (int)$mask);
            return ($ipLong & $maskLong) === ($subnetLong & $maskLong);
        }
        return $ip === $pattern;
    }
    
    public function recordFailedLogin($email) {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $key = 'failed_login_' . md5($email . $ip);
        
        if (!isset($_SESSION[$key])) {
            $_SESSION[$key] = ['count' => 0, 'first_attempt' => time()];
        }
        
        $_SESSION[$key]['count']++;
        $_SESSION[$key]['last_attempt'] = time();
        
        // Lock after 5 failed attempts
        if ($_SESSION[$key]['count'] >= 5) {
            $lockUntil = time() + 900; // 15 minutes
            $_SESSION[$key]['locked_until'] = $lockUntil;
            $this->logger->warning("Account locked due to brute force", ['email' => $email, 'ip' => $ip]);
        }
    }
    
    public function isAccountLocked($email) {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $key = 'failed_login_' . md5($email . $ip);
        
        if (isset($_SESSION[$key]['locked_until'])) {
            if (time() < $_SESSION[$key]['locked_until']) {
                return true;
            } else {
                unset($_SESSION[$key]);
            }
        }
        
        return false;
    }
    
    public function sanitizeInput($input) {
        if (is_array($input)) {
            return array_map([$this, 'sanitizeInput'], $input);
        }
        return htmlspecialchars(strip_tags(trim($input)), ENT_QUOTES, 'UTF-8');
    }
    
    public function generateCSRFToken() {
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
    
    public function verifyCSRFToken($token) {
        return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }
}

