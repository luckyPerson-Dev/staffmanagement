<?php
/**
 * modules/document.php
 * Document Management Module
 */

class DocumentManager {
    private $db;
    private $logger;
    
    public function __construct() {
        $this->db = Database::getInstance();
        $this->logger = new Logger();
    }
    
    /**
     * Upload document
     */
    public function upload($file, $data) {
        try {
            $uploadDir = UPLOADS_DIR . '/documents/';
            ensure_directory($uploadDir);
            
            $fileName = time() . '_' . basename($file['name']);
            $filePath = $uploadDir . $fileName;
            
            if (!move_uploaded_file($file['tmp_name'], $filePath)) {
                throw new Exception('File upload failed');
            }
            
            $documentId = $this->db->insert('documents', [
                'title' => $data['title'],
                'file_path' => $filePath,
                'file_type' => $file['type'],
                'file_size' => $file['size'],
                'category' => $data['category'] ?? null,
                'tags' => isset($data['tags']) ? json_encode($data['tags']) : null,
                'user_id' => $data['user_id'] ?? null,
                'staff_id' => $data['staff_id'] ?? null,
                'visibility' => $data['visibility'] ?? 'private',
                'created_by' => $_SESSION['user_id'] ?? null
            ]);
            
            $this->logger->audit(
                $_SESSION['user_id'] ?? 0,
                'create',
                'document',
                $documentId,
                "Uploaded document: {$data['title']}"
            );
            
            return $documentId;
            
        } catch (Exception $e) {
            $this->logger->error("Document upload failed: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Get documents for user
     */
    public function getDocuments($userId, $filters = []) {
        $where = ["(visibility = 'public' OR created_by = ? OR user_id = ?)"];
        $params = [$userId, $userId];
        
        if (isset($filters['category'])) {
            $where[] = "category = ?";
            $params[] = $filters['category'];
        }
        
        if (isset($filters['staff_id'])) {
            $where[] = "staff_id = ?";
            $params[] = $filters['staff_id'];
        }
        
        $sql = "SELECT * FROM documents WHERE " . implode(' AND ', $where) . " AND deleted_at IS NULL ORDER BY created_at DESC";
        return $this->db->fetchAll($sql, $params);
    }
    
    /**
     * Delete document
     */
    public function delete($documentId, $userId) {
        $doc = $this->db->fetch("SELECT * FROM documents WHERE id = ?", [$documentId]);
        
        if (!$doc || ($doc['created_by'] != $userId && !in_array($_SESSION['user_role'], ['superadmin', 'admin']))) {
            throw new Exception('Permission denied');
        }
        
        // Soft delete
        $this->db->update('documents', ['deleted_at' => date('Y-m-d H:i:s')], 'id = ?', [$documentId]);
        
        $this->logger->audit($userId, 'delete', 'document', $documentId, "Deleted document: {$doc['title']}");
    }
}

