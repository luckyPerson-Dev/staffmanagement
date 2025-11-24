<?php
/**
 * crons/monthly_payroll.php
 * Monthly payroll automation
 * 
 * Setup cron: 0 0 1 * * /usr/bin/php /path/to/crons/monthly_payroll.php
 */

require_once __DIR__ . '/../core/autoload.php';

$logger = new Logger();
$logger->info("Monthly payroll cron started");

try {
    // Get previous month
    $lastMonth = date('m', strtotime('first day of last month'));
    $lastYear = date('Y', strtotime('first day of last month'));
    
    $logger->info("Processing payroll for {$lastMonth}/{$lastYear}");
    
    // Run payroll
    require __DIR__ . '/../payroll/run_payroll.php';
    
    $logger->info("Monthly payroll completed successfully");
    
} catch (Exception $e) {
    $logger->error("Monthly payroll failed: " . $e->getMessage());
    exit(1);
}

