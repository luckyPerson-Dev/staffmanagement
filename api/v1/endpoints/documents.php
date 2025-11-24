<?php
/**
 * api/v1/endpoints/documents.php
 * Documents API Endpoints
 */

$docManager = new DocumentManager();
$user = $auth->currentUser();

switch ($method) {
    case 'GET':
        $filters = [];
        if (isset($_GET['category'])) $filters['category'] = $_GET['category'];
        if (isset($_GET['staff_id'])) $filters['staff_id'] = $_GET['staff_id'];
        
        $data = $docManager->getDocuments($user['id'], $filters);
        Response::success('Documents retrieved', $data);
        break;
        
    case 'POST':
        if (!isset($_FILES['file'])) {
            Response::error('No file uploaded', null, 400);
        }
        
        $documentId = $docManager->upload($_FILES['file'], [
            'title' => $_POST['title'] ?? '',
            'category' => $_POST['category'] ?? null,
            'tags' => isset($_POST['tags']) ? json_decode($_POST['tags'], true) : null,
            'user_id' => $_POST['user_id'] ?? null,
            'staff_id' => $_POST['staff_id'] ?? null,
            'visibility' => $_POST['visibility'] ?? 'private'
        ]);
        
        Response::success('Document uploaded', ['id' => $documentId]);
        break;
        
    case 'DELETE':
        if (!$id) {
            Response::error('Document ID required', null, 400);
        }
        
        $docManager->delete($id, $user['id']);
        Response::success('Document deleted');
        break;
        
    default:
        Response::error('Method not allowed', null, 405);
}

