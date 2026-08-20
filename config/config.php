<?php
/**
 * Pagrindinis sistemos konfigūracijos failas
 */

require_once __DIR__ . '/env_loader.php';

// Bandome užkrauti .env (projekto šaknyje, žr. env_loader.php)
load_env(__DIR__ . '/..');

ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);
ini_set('log_errors', 1);
ini_set('error_log', dirname(__FILE__) . '/../logs/php_errors.log');

// Saugūs sesijų nustatymai
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);

// SAUGU: duomenų bazės prisijungimo duomenys dabar imami TIK iš .env failo,
// kuris NĖRA git repo dalis (žr. .gitignore). Jokių slaptažodžių šiame
// faile ar git istorijoje. Žr. .env.example - failo šabloną.
define('DB_HOST', env_required('DB_HOST'));
define('DB_USER', env_required('DB_USER'));
define('DB_PASS', env_required('DB_PASS'));
define('DB_NAME', env_required('DB_NAME'));

// Pagrindinis URL adresas
define('SITE_URL', env_optional('SITE_URL', 'https://olimpiada.sprendimas.eu'));
define('SITE_NAME', 'Olimpiadų sistema');
define('SESSION_NAME', 'Olimpiadu_Sistema');

date_default_timezone_set('Europe/Vilnius');
?>