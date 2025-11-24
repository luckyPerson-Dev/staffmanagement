<?php
/**
 * api/v1/endpoints/ai.php
 * AI Features API Endpoints
 */

$ai = new AI();
$user = $auth->currentUser();

if (!ENABLE_AI_FEATURES) {
    Response::error('AI features are disabled', null, 403);
}

switch ($method) {
    case 'GET':
        $action = $_GET['action'] ?? '';
        $userId = intval($_GET['user_id'] ?? $user['id']);
        
        switch ($action) {
            case 'analyze':
                $month = intval($_GET['month'] ?? date('m'));
                $year = intval($_GET['year'] ?? date('Y'));
                $insights = $ai->analyzePerformance($userId, $month, $year);
                Response::success('Analysis complete', $insights);
                break;
                
            case 'suggestions':
                $suggestions = $ai->suggestPenalties($userId);
                Response::success('Suggestions retrieved', $suggestions);
                break;
                
            case 'summary':
                $month = intval($_GET['month'] ?? date('m'));
                $year = intval($_GET['year'] ?? date('Y'));
                $summary = $ai->generateMonthlySummary($userId, $month, $year);
                Response::success('Summary generated', ['summary' => $summary]);
                break;
                
            case 'predict':
                $prediction = $ai->predictWorkload($userId);
                Response::success('Prediction generated', $prediction);
                break;
                
            case 'review':
                $month = intval($_GET['month'] ?? date('m'));
                $year = intval($_GET['year'] ?? date('Y'));
                $notes = $ai->generateReviewNotes($userId, $month, $year);
                Response::success('Review notes generated', ['notes' => $notes]);
                break;
                
            default:
                Response::error('Invalid action', null, 400);
        }
        break;
        
    default:
        Response::error('Method not allowed', null, 405);
}

