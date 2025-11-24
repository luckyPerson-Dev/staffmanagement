<?php
/**
 * db_connect.php
 * Compatibility wrapper for PDO database connection
 * Delegates to core/Database.php to prevent duplicate function errors
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/core/Database.php';

// getPDO() is now provided by core/Database.php
// This file exists for backward compatibility with existing includes
