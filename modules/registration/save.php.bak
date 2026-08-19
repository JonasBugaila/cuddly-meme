<?php
// modules/registration/save.php

session_start();
require_once '../../config/db_connect.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die('Neteisingas užklausos metodas.');
}

// 1. Gauname duomenis iš formos
$konkurso_pav = trim($_POST['konkurso_pav'] ?? '');
$var_mokykla = trim($_POST['var_mokykla'] ?? '');
$vardas = trim($_POST['1_vardas'] ?? '');
$pavarde = trim($_POST['1_pavarde'] ?? '');
$klase = trim($_POST['1_klase'] ?? '');
$mokytojas = trim($_POST['1_mok'] ?? '');
$mok_kvali = trim($_POST['1_mok_kvali'] ?? '');

// Jūsų DB reikalauja šių laukų kaip NOT NULL, todėl perduodame tuščius, jei jų nėra formoje
$mok2 = '';
$mok2_kvali = '';
$vart_id = $_SESSION['vart_id'] ?? 'SISTEMA';

// Bazinė validacija
if (empty($konkurso_pav) || empty($var_mokykla) || empty($vardas) || empty($pavarde)) {
    die('Klaida: Neužpildyti privalomi laukai. Grįžkite atgal ir bandykite dar kartą.');
}

// 2. Saugus išsaugojimas (Prepared Statements apsaugo nuo SQL injekcijų)
$sql = "INSERT INTO dalyviai (
            konkurso_pav, var_mokykla, pil_data, 
            1_vardas, 1_pavarde, 1_klase, 
            1_mok, 1_mok_kvali, 2_mok, 2_mok_kvali, vart_id
        ) VALUES (?, ?, NOW(), ?, ?, ?, ?, ?, ?, ?, ?)";
        
$stmt = $conn->prepare($sql);

if ($stmt) {
    $stmt->bind_param("ssssssssss", 
        $konkurso_pav, $var_mokykla, 
        $vardas, $pavarde, $klase, 
        $mokytojas, $mok_kvali, $mok2, $mok2_kvali, $vart_id
    );
    $stmt->execute();
    $stmt->close();
} else {
    die("Klaida ruošiant užklausą: " . $conn->error);
}

// 3. Sėkmingai išsaugojus - nukreipiame atgal į formą (PRG šablonas)
// Jei naudojate pranešimų sistemą, galite čia priskirti $_SESSION['msg'] = '...';
$redirect_url = $_SERVER['HTTP_REFERER'] ?? '../../index.php';
header("Location: " . $redirect_url);
exit;
?>