<?php
defined('PHPUNIT_TEST') or define('PHPUNIT_TEST', 1);

error_reporting(E_ALL & ~E_WARNING & ~E_DEPRECATED);
@ini_set('session.use_cookies', '0');
@ini_set('session.cache_limiter', '');
if (function_exists('session_status') && session_status() === PHP_SESSION_NONE) {
    @session_start();
}

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/controls.php';
require_once __DIR__ . '/../lib/template-engine.php';
require_once __DIR__ . '/../lib/table-template.php';
require_once __DIR__ . '/Db/TestDb.php';
