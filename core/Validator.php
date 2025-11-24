<?php
/**
 * core/Validator.php
 * Advanced validation system
 */

class Validator {
    private $errors = [];
    
    public function validate($data, $rules) {
        $this->errors = [];
        
        foreach ($rules as $field => $ruleSet) {
            $value = $data[$field] ?? null;
            $rulesArray = explode('|', $ruleSet);
            
            foreach ($rulesArray as $rule) {
                $this->applyRule($field, $value, $rule, $data);
            }
        }
        
        return empty($this->errors);
    }
    
    private function applyRule($field, $value, $rule, $data) {
        if (strpos($rule, ':') !== false) {
            list($ruleName, $param) = explode(':', $rule, 2);
        } else {
            $ruleName = $rule;
            $param = null;
        }
        
        switch ($ruleName) {
            case 'required':
                if (empty($value) && $value !== '0') {
                    $this->errors[$field][] = ucfirst($field) . ' is required';
                }
                break;
                
            case 'email':
                if (!empty($value) && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    $this->errors[$field][] = ucfirst($field) . ' must be a valid email';
                }
                break;
                
            case 'min':
                if (!empty($value) && strlen($value) < (int)$param) {
                    $this->errors[$field][] = ucfirst($field) . ' must be at least ' . $param . ' characters';
                }
                break;
                
            case 'max':
                if (!empty($value) && strlen($value) > (int)$param) {
                    $this->errors[$field][] = ucfirst($field) . ' must not exceed ' . $param . ' characters';
                }
                break;
                
            case 'numeric':
                if (!empty($value) && !is_numeric($value)) {
                    $this->errors[$field][] = ucfirst($field) . ' must be a number';
                }
                break;
                
            case 'unique':
                $db = Database::getInstance();
                $table = explode(',', $param)[0];
                $column = explode(',', $param)[1] ?? $field;
                $excludeId = $data['id'] ?? null;
                
                $sql = "SELECT COUNT(*) FROM `{$table}` WHERE `{$column}` = ?";
                $params = [$value];
                
                if ($excludeId) {
                    $sql .= " AND id != ?";
                    $params[] = $excludeId;
                }
                
                if ($db->fetch($sql, $params)['COUNT(*)'] > 0) {
                    $this->errors[$field][] = ucfirst($field) . ' already exists';
                }
                break;
                
            case 'confirmed':
                $confirmField = $field . '_confirmation';
                if (!isset($data[$confirmField]) || $value !== $data[$confirmField]) {
                    $this->errors[$field][] = ucfirst($field) . ' confirmation does not match';
                }
                break;
        }
    }
    
    public function getErrors() {
        return $this->errors;
    }
    
    public function hasErrors() {
        return !empty($this->errors);
    }
    
    public function firstError($field) {
        return $this->errors[$field][0] ?? null;
    }
}

