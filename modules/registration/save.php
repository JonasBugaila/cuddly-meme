<?php
// modules/registration/save.php

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/db_connect.php';
require_once __DIR__ . '/../../config/functions.php';

start_session();

// PRIDĖTA: autentikacijos patikra
if (!is_logged_in()) {
    set_message('Turite prisijungti, kad galėtumėte registruoti dalyvius', 'error');
    redirect(SITE_URL . '/modules/auth/login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die('Neteisingas užklausos metodas.');
}

// PRIDĖTA: CSRF patikra
if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
    set_message('Netinkamas saugumo žetonas.', 'error');
    redirect(SITE_URL . '/modules/registration/index.php');
    exit;
}

$conn = db_connect();

$konkurso_pav = trim($_POST['konkurso_pav'] ?? '');
$var_mokykla = trim($_POST['var_mokykla'] ?? '');
$vardas = trim($_POST['1_vardas'] ?? '');
$pavarde = trim($_POST['1_pavarde'] ?? '');
$klase = trim($_POST['1_klase'] ?? '');
$mokytojas = trim($_POST['1_mok'] ?? '');
$mok_kvali = trim($_POST['1_mok_kvali'] ?? '');

$mok2 = '';
$mok2_kvali = '';
// PATAISYTA: teisingas sesijos raktas
$vart_id = $_SESSION['user_id'] ?? 'SISTEMA';

// SAUGU: paprastas vartotojas negali registruoti dalyvio kitai mokyklai —
// mokyklos pavadinimas visada imamas iš jo profilio, net jei POST duomenys sufalsifikuoti.
if (!is_admin()) {
    $own_school_row = db_get_row(db_query("SELECT var_mokykla FROM vartotojas WHERE vart_id = ?", [$vart_id], 's'));
    $var_mokykla = $own_school_row['var_mokykla'] ?? '';
}

if (empty($konkurso_pav) || empty($var_mokykla) || empty($vardas) || empty($pavarde)) {
    set_message('Neužpildyti privalomi laukai.', 'error');
    redirect(SITE_URL . '/modules/registration/index.php');
    exit;
}

// SAUGU: patikra PRIEŠ įrašymą, ar šis mokinys jau nėra užregistruotas
// būtent į šią olimpiadą (dublikatų apsauga - žr. ir register.php).
$duplicate_check = db_get_row(db_query(
    "SELECT reg_id FROM dalyviai WHERE 1_vardas = ? AND 1_pavarde = ? AND var_mokykla = ? AND konkurso_pav = ?",
    [$vardas, $pavarde, $var_mokykla, $konkurso_pav], 'ssss'
));
if ($duplicate_check) {
    set_message("Mokinys {$vardas} {$pavarde} jau užregistruotas į šią olimpiadą.", 'error');
    redirect(SITE_URL . '/modules/registration/index.php');
    exit;
}

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
    // SAUGU: try/catch - db_connect() naudoja MYSQLI_REPORT_STRICT, todėl DB
    // klaidos (pvz. UNIQUE apribojimo pažeidimas lenktynių sąlygos atveju) meta
    // išimtį, o ne tiesiog grąžina false.
    try {
        if ($stmt->execute()) {
            $new_reg_id = $conn->insert_id;
            // NAUJA: fiksuojame atskirame mokytojų veiklos žurnale
            log_teacher_action(
                'Registravo dalyvį',
                "{$vardas} {$pavarde} ({$klase} kl.) į \"{$konkurso_pav}\" ({$var_mokykla})",
                $new_reg_id
            );
            set_message('Dalyvis sėkmingai užregistruotas.', 'success');
        } else {
            set_message('Klaida išsaugant duomenis.', 'error');
        }
    } catch (mysqli_sql_exception $e) {
        if ($e->getCode() === 1062) {
            set_message("Mokinys {$vardas} {$pavarde} jau užregistruotas į šią olimpiadą.", 'error');
        } else {
            error_log("DB klaida registruojant dalyvį (save.php): " . $e->getMessage());
            set_message('Klaida išsaugant duomenis. Bandykite dar kartą.', 'error');
        }
    }
    $stmt->close();
} else {
    set_message('Klaida ruošiant užklausą.', 'error');
}

$redirect_url = $_SERVER['HTTP_REFERER'] ?? SITE_URL . '/modules/registration/index.php';
redirect($redirect_url);