<?php
/**
 * Pagrindinis sistemos konfigūracijos failas
 */

ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);
ini_set('log_errors', 1);
ini_set('error_log', dirname(__FILE__) . '/../logs/php_errors.log');

// Saugūs sesijų nustatymai
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);

// Duomenų bazės prisijungimo duomenys
define('DB_HOST', 'localhost');
define('DB_USER', 'user');
define('DB_PASS', 'password');
define('DB_NAME', 'testlt_olimpidos');

// Pagrindinis URL adresas
define('SITE_URL', 'https://olimpiada.sprendimas.eu');
define('SITE_NAME', 'Olimpiadų sistema');
define('SESSION_NAME', 'Olimpiadu_Sistema');

date_default_timezone_set('Europe/Vilnius');
?>
