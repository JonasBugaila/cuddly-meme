<?php
/**
 * Sesijos palaikymo (ping) failas
 */

// 1. Pradedame arba pratęsiame PHP sesiją serveryje
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 2. Nustatome, kad naršyklė gaus duomenis JSON formatu
header('Content-Type: application/json; charset=utf-8');

// 3. Grąžiname sėkmingą statusą JavaScript'ui
echo json_encode([
    'status' => 'success',
    'message' => 'Sesija sėkmingai pratęsta',
    'time' => date('Y-m-d H:i:s')
]);
exit;