<?php
// modules/registration/ajax_search.php

require_once __DIR__ . '/../../config/config.php'; 
require_once __DIR__ . '/../../config/db_connect.php';
require_once __DIR__ . '/../../config/functions.php';

header('Content-Type: application/json; charset=utf-8');

// PRIDĖTA: autentikacijos patikra
if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['error' => 'Prieiga negalima']);
    exit;
}

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

$search_param = '%' . $term . '%';

// SAUGU: paprastas vartotojas (mokytojas) paieškoje mato TIK savo mokyklos mokinius.
// Anksčiau paieška grąžindavo visų mokyklų mokinius bet kuriam prisijungusiam vartotojui.
if (is_admin()) {
    $sql = "SELECT DISTINCT 1_vardas, 1_pavarde, 1_klase, var_mokykla, 1_mok
            FROM dalyviai
            WHERE 1_pavarde LIKE ? LIMIT 10";
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param("s", $search_param);
        $stmt->execute();
    }
} else {
    $own_school_row = db_get_row(db_query("SELECT var_mokykla FROM vartotojas WHERE vart_id = ?", [$_SESSION['user_id']], 's'));
    $own_school = $own_school_row['var_mokykla'] ?? '';

    $sql = "SELECT DISTINCT 1_vardas, 1_pavarde, 1_klase, var_mokykla, 1_mok
            FROM dalyviai
            WHERE 1_pavarde LIKE ? AND var_mokykla = ? LIMIT 10";
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param("ss", $search_param, $own_school);
        $stmt->execute();
    }
}

if ($stmt) {
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
