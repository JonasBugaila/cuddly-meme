<?php
/**
 * Konfigūracijos failas
 * 
 * Šiame faile saugomos pagrindinės sistemos konfigūracijos konstantos
 */

// Duomenų bazės konfigūracija
define('DB_HOST', 'localhost');
define('DB_USER', 'testlt_oli');
define('DB_PASS', 'olimipic=0LI');
define('DB_NAME', 'testlt_olimpidos');

// Sistemos konfigūracija
define('SITE_NAME', 'Olimpiadų sistema');
define('SITE_URL', 'http://olimpiada.sprendimas.eu');
define('ADMIN_EMAIL', 'admin@example.com');

// Sesijos konfigūracija
define('SESSION_NAME', 'olimpiados_session');
define('SESSION_LIFETIME', 3600); // 1 valanda

// Saugumo konfigūracija
define('HASH_COST', 10); // Slaptažodžio šifravimo stiprumas

// Keliai
define('ROOT_PATH', dirname(dirname(__FILE__)));
define('INCLUDES_PATH', ROOT_PATH . '/includes');
define('MODULES_PATH', ROOT_PATH . '/modules');
define('ASSETS_PATH', ROOT_PATH . '/assets');

// Klaidos ir pranešimai
define('DISPLAY_ERRORS', true);
if (DISPLAY_ERRORS) {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', 0);
    ini_set('display_startup_errors', 0);
    error_reporting(0);
}
