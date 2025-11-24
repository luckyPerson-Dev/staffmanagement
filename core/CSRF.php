<?php
/**
 * core/CSRF.php
 * CSRF protection wrapper
 */

class CSRF {
    private $security;
    
    public function __construct() {
        $this->security = new Security();
    }
    
    public function generate() {
        return $this->security->generateCSRFToken();
    }
    
    public function verify($token) {
        return $this->security->verifyCSRFToken($token);
    }
}

