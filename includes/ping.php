<?php
/**
 * Sesijos palaikymo (ping) failas
 *
 * PATAISYTA: naudoja start_session() (config/functions.php), o ne žalią
 * session_start(). Anksčiau šis failas nenustatydavo teisingo sesijos
 * pavadinimo (SESSION_NAME) ir neatnaujindavo 'last_activity' žymos,
 * todėl faktiškai NEPRATĘSDAVO vartotojo sesijos - "Pratęsti sesiją"
 * mygtukas frontend'e veikdavo tik vizualiai, bet ne realiai serveryje.
 */
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/config/functions.php';

start_session();

header('Content-Type: application/json; charset=utf-8');

echo json_encode([
    'status' => 'success',
    'message' => 'Sesija sėkmingai pratęsta',
    'time' => date('Y-m-d H:i:s')
]);
exit;