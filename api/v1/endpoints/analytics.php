<?php
/**
 * api/v1/endpoints/analytics.php
 * Analytics API Endpoints
 */

$analytics = new Analytics();
$user = $auth->currentUser();

// Check permissions
if (!in_array($user['role'], ['superadmin', 'admin', 'hr', 'supervisor'])) {
    Response::error('Access denied', null, 403);
}

switch ($method) {
    case 'GET':
        $type = $_GET['type'] ?? '';
        
        switch ($type) {
            case 'heatmap':
                $startDate = $_GET['start_date'] ?? date('Y-m-01');
                $endDate = $_GET['end_date'] ?? date('Y-m-t');
                $data = $analytics->getProductivityHeatmap($startDate, $endDate);
                Response::success('Heatmap data retrieved', $data);
                break;
                
            case 'trends':
                $userId = $_GET['user_id'] ?? null;
                $months = intval($_GET['months'] ?? 12);
                $data = $analytics->getMonthlyTrends($userId, $months);
                Response::success('Trends retrieved', $data);
                break;
                
            case 'teams':
                $month = intval($_GET['month'] ?? date('m'));
                $year = intval($_GET['year'] ?? date('Y'));
                $data = $analytics->getTeamComparison($month, $year);
                Response::success('Team comparison retrieved', $data);
                break;
                
            case 'tickets':
                $userId = $_GET['user_id'] ?? null;
                $data = $analytics->getTicketMissPatterns($userId);
                Response::success('Ticket patterns retrieved', $data);
                break;
                
            case 'salary':
                $userId = $_GET['user_id'] ?? null;
                $months = intval($_GET['months'] ?? 12);
                $data = $analytics->getSalaryTrends($userId, $months);
                Response::success('Salary trends retrieved', $data);
                break;
                
            case 'customers':
                $startDate = $_GET['start_date'] ?? date('Y-m-01');
                $endDate = $_GET['end_date'] ?? date('Y-m-t');
                $data = $analytics->getCustomerWorkload($startDate, $endDate);
                Response::success('Customer workload retrieved', $data);
                break;
                
            default:
                Response::error('Invalid analytics type', null, 400);
        }
        break;
        
    default:
        Response::error('Method not allowed', null, 405);
}

