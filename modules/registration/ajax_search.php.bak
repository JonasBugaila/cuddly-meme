<?php
// modules/registration/ajax_search.php

require_once __DIR__ . '/../../config/config.php'; 
require_once __DIR__ . '/../../config/db_connect.php';
require_once __DIR__ . '/../../config/functions.php';

header('Content-Type: application/json; charset=utf-8');

// Iškviečiame prisijungimą prie duomenų bazės (jūsų sistemos funkcija)
$conn = db_connect();

if (!isset($_GET['term']) || trim($_GET['term']) === '') {
    echo json_encode([]);
    exit;
}

$term = trim($_GET['term']);

if (mb_strlen($term, 'UTF-8') < 2) {
    echo json_encode([]);
    exit;
}

// Ieškome mokinio pagal pavardę jūsų 'dalyviai' lentelėje
$sql = "SELECT DISTINCT 1_vardas, 1_pavarde, 1_klase, var_mokykla, 1_mok 
        FROM dalyviai 
        WHERE 1_pavarde LIKE ? LIMIT 10";
        
$stmt = $conn->prepare($sql);

if ($stmt) {
    $search_param = '%' . $term . '%';
    $stmt->bind_param("s", $search_param);
    $stmt->execute();
    
    $result = $stmt->get_result();
    $mokiniai = [];
    
    while ($row = $result->fetch_assoc()) {
        $mokiniai[] = [
            'label' => $row['1_vardas'] . ' ' . $row['1_pavarde'] . ' (' . $row['1_klase'] . ') - ' . $row['var_mokykla'],
            'vardas' => $row['1_vardas'],
            'pavarde' => $row['1_pavarde'],
            'klase' => $row['1_klase'],
            'mokykla' => $row['var_mokykla'],
            'mokytojas' => $row['1_mok']
        ];
    }
    
    echo json_encode($mokiniai);
    $stmt->close();
} else {
    echo json_encode([]);
}
?>