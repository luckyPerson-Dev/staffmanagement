<?php
/**
 * core/Auth.php
 * Enhanced authentication with 2FA, session management, and security features
 */

class Auth {
    private $db;
    private $logger;
    private $security;
    
    public function __construct() {
        $this->db = Database::getInstance();
        $this->logger = new Logger();
        $this->security = new Security();
        
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }
    
    public function login($email, $password, $remember = false) {
        try {
            $user = $this->db->fetch(
                "SELECT * FROM users WHERE email = ? AND deleted_at IS NULL",
                [$email]
            );
            
            if (!$user || !password_verify($password, $user['password'])) {
                $this->security->recordFailedLogin($email);
                return ['success' => false, 'message' => 'Invalid credentials'];
            }
            
            // Check if 2FA is enabled
            if (!empty($user['two_factor_enabled'])) {
                return ['success' => false, 'requires_2fa' => true, 'user_id' => $user['id']];
            }
            
            // Check IP restriction if enabled
            if (!$this->security->checkIPRestriction($user['id'])) {
                return ['success' => false, 'message' => 'IP address not allowed'];
            }
            
            // Create session
            $this->createSession($user, $remember);
            
            $this->logger->audit($user['id'], 'login', 'user', $user['id'], 'User logged in');
            
            return ['success' => true, 'user' => $user];
            
        } catch (Exception $e) {
            $this->logger->error("Login error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Login failed'];
        }
    }
    
    public function verify2FA($user_id, $code) {
        $user = $this->db->fetch("SELECT * FROM users WHERE id = ?", [$user_id]);
        if (!$user || !$user['two_factor_enabled']) {
            return false;
        }
        
        // Check OTP (stored in session temporarily)
        if (isset($_SESSION['2fa_code']) && $_SESSION['2fa_code'] === $code) {
            unset($_SESSION['2fa_code']);
            $this->createSession($user);
            return true;
        }
        
        return false;
    }
    
    private function createSession($user, $remember = false) {
        session_regenerate_id(true);
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_name'] = $user['name'];
        $_SESSION['user_role'] = $user['role'];
        $_SESSION['last_activity'] = time();
        $_SESSION['session_fingerprint'] = $this->security->generateFingerprint();
        
        if ($remember) {
            // Set remember me cookie (30 days)
            $token = bin2hex(random_bytes(32));
            setcookie('remember_token', $token, time() + (30 * 24 * 60 * 60), '/', '', true, true);
            // Store token in database
            $this->db->insert('remember_tokens', [
                'user_id' => $user['id'],
                'token' => hash('sha256', $token),
                'expires_at' => date('Y-m-d H:i:s', time() + (30 * 24 * 60 * 60))
            ]);
        }
    }
    
    public function isLoggedIn() {
        if (!isset($_SESSION['user_id'])) {
            return false;
        }
        
        // Check session timeout
        if (isset($_SESSION['last_activity']) && 
            (time() - $_SESSION['last_activity'] > SESSION_TIMEOUT)) {
            $this->logout();
            return false;
        }
        
        // Verify session fingerprint
        if (!$this->security->verifyFingerprint($_SESSION['session_fingerprint'] ?? '')) {
            $this->logout();
            return false;
        }
        
        $_SESSION['last_activity'] = time();
        return true;
    }
    
    public function currentUser() {
        if (!$this->isLoggedIn()) {
            return null;
        }
        
        return $this->db->fetch(
            "SELECT id, name, email, role, monthly_salary, two_factor_enabled FROM users WHERE id = ? AND deleted_at IS NULL",
            [$_SESSION['user_id']]
        );
    }
    
    public function requireLogin() {
        if (!$this->isLoggedIn()) {
            header('Location: ' . BASE_URL . '/login.php');
            exit;
        }
    }
    
    public function requireRole($roles) {
        $this->requireLogin();
        $user = $this->currentUser();
        if (!$user || !in_array($user['role'], (array)$roles)) {
            header('Location: ' . BASE_URL . '/dashboard.php');
            exit;
        }
    }
    
    public function logout() {
        if (isset($_SESSION['user_id'])) {
            $this->logger->audit($_SESSION['user_id'], 'logout', 'user', $_SESSION['user_id'], 'User logged out');
        }
        session_destroy();
        setcookie('remember_token', '', time() - 3600, '/');
    }
    
    public function send2FACode($user_id) {
        $code = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
        $_SESSION['2fa_code'] = $code;
        $_SESSION['2fa_user_id'] = $user_id;
        $_SESSION['2fa_expires'] = time() + 300; // 5 minutes
        
        $user = $this->db->fetch("SELECT email, name FROM users WHERE id = ?", [$user_id]);
        
        // Send email (implement email sending)
        $subject = "Your 2FA Code";
        $message = "Hello {$user['name']},\n\nYour verification code is: {$code}\n\nThis code expires in 5 minutes.";
        // mail($user['email'], $subject, $message);
        
        return $code; // For testing, remove in production
    }
}

