<?php
/**
 * Pagrindinis sistemos konfigūracijos failas
 */

// Klaidų rodymas (Produkcijoje rekomenduojama pakeisti į 0)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Saugūs sesijų nustatymai
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);

// Duomenų bazės prisijungimo duomenys
define('DB_HOST', 'localhost');
define('DB_USER', '000000000');
define('DB_PASS', 'o00000000I');
define('DB_NAME', 'testlt_olimpidos');

// Pagrindinis URL adresas
define('SITE_URL', 'https://olimpiada.sprendimas.eu');
define('SITE_NAME', 'Olimpiadų sistema');
define('SESSION_NAME', 'Olimpiadu_Sistema');

date_default_timezone_set('Europe/Vilnius');
?>
