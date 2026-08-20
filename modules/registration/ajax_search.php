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

// NAUJA: pasirinkta olimpiada (jei perduota) - naudojama tik "jau užregistruotas
// šioje olimpiadoje" žymai apskaičiuoti, dublikatų apsaugos funkcijai.
$selected_olympiad = isset($_GET['olympiad']) ? trim($_GET['olympiad']) : '';

// SAUGU: paprastas vartotojas (mokytojas) paieškoje mato TIK savo mokyklos mokinius.
// Anksčiau paieška grąžindavo visų mokyklų mokinius bet kuriam prisijungusiam vartotojui.
if (is_admin()) {
    $sql = "SELECT DISTINCT d.1_vardas, d.1_pavarde, d.1_klase, d.var_mokykla, d.1_mok,
                EXISTS (
                    SELECT 1 FROM dalyviai d2
                    WHERE d2.1_vardas = d.1_vardas AND d2.1_pavarde = d.1_pavarde
                      AND d2.var_mokykla = d.var_mokykla AND d2.konkurso_pav = ?
                ) as already_registered
            FROM dalyviai d
            WHERE d.1_pavarde LIKE ? LIMIT 10";
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param("ss", $selected_olympiad, $search_param);
        $stmt->execute();
    }
} else {
    $own_school_row = db_get_row(db_query("SELECT var_mokykla FROM vartotojas WHERE vart_id = ?", [$_SESSION['user_id']], 's'));
    $own_school = $own_school_row['var_mokykla'] ?? '';

    $sql = "SELECT DISTINCT d.1_vardas, d.1_pavarde, d.1_klase, d.var_mokykla, d.1_mok,
                EXISTS (
                    SELECT 1 FROM dalyviai d2
                    WHERE d2.1_vardas = d.1_vardas AND d2.1_pavarde = d.1_pavarde
                      AND d2.var_mokykla = d.var_mokykla AND d2.konkurso_pav = ?
                ) as already_registered
            FROM dalyviai d
            WHERE d.1_pavarde LIKE ? AND d.var_mokykla = ? LIMIT 10";
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param("sss", $selected_olympiad, $search_param, $own_school);
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
            'mokytojas' => $row['1_mok'],
            // NAUJA: true, jei šis mokinys jau turi registraciją į $selected_olympiad
            // (kai olimpiada dar nepasirinkta, $selected_olympiad === '', todėl visada false)
            'already_registered' => (bool)$row['already_registered']
        ];
    }
    
    echo json_encode($mokiniai);
    $stmt->close();
} else {
    echo json_encode([]);
}
?>
