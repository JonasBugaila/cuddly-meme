<?php
/**
 * Atsijungimo puslapis
 * 
 * Šis failas apdoroja vartotojo atsijungimą
 */

// Įtraukiame konfigūracijos failus
require_once dirname(dirname(dirname(__FILE__))) . '/config/config.php';
require_once dirname(dirname(dirname(__FILE__))) . '/config/functions.php';

// Pradedame sesiją
start_session();

// Sunaikinama sesija
session_unset();
session_destroy();

// Nustatome pranešimą
set_message('Sėkmingai atsijungėte', 'success');

// Nukreipiame į prisijungimo puslapį
redirect(SITE_URL . '/modules/auth/login.php');
?>
