<?php
/**
 * Dalyvio (savo mokyklos mokinio) informacijos redagavimas mokytojui.
 *
 * SVARBU: šis puslapis leidžia keisti TIK mokinio/mokytojo duomenis.
 * Rezultatai (Balai, Vieta, kitas_etapas, status) ir priklausomybė
 * mokyklai/olimpiadai čia NĖRA redaguojami - tai lieka admin ir
 * modules/results/view.php atsakomybėje.
 */

require_once dirname(dirname(dirname(__FILE__))) . '/config/config.php';
require_once dirname(dirname(dirname(__FILE__))) . '/config/db_connect.php';
require_once dirname(dirname(dirname(__FILE__))) . '/config/functions.php';

if (!is_logged_in()) {
    set_message('Turite prisijungti, kad galėtumėte pasiekti šį puslapį', 'error');
    redirect(SITE_URL . '/modules/auth/login.php');
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    set_message('Nenurodytas dalyvio ID.', 'error');
    redirect(SITE_URL . '/modules/registration/my_students.php');
}

$participant = db_get_row(db_query("SELECT * FROM dalyviai WHERE reg_id = ?", [$id], 'i'));
if (!$participant) {
    set_message('Dalyvis nerastas.', 'error');
    redirect(SITE_URL . '/modules/registration/my_students.php');
}

// PRIDĖTA: klasių sąrašas pasirinkimui iš dropdown (kaip admin/participant_edit.php)
$classes = db_get_results(db_query("SELECT klases FROM klases ORDER BY klases ASC"));

// PATAISYTA: kvalifikacijų sąrašas dabar imamas iš DB lentelės (kaip admin/participant_edit.php)
$qualifications = db_get_results(db_query("SELECT kategorija FROM kvalifikacijos ORDER BY kategorija ASC"));

// SAUGU: nuosavybės patikra - paprastas vartotojas gali redaguoti TIK savo mokyklos dalyvius
if (!is_admin()) {
    $own_school_row = db_get_row(db_query("SELECT var_mokykla FROM vartotojas WHERE vart_id = ?", [$_SESSION['user_id']], 's'));
    $own_school = $own_school_row['var_mokykla'] ?? '';

    if (empty($own_school) || $participant['var_mokykla'] !== $own_school) {
        log_action('Saugumo pažeidimas', "Bandyta redaguoti kitos mokyklos dalyvį (reg_id={$id}) be teisių.");
        set_message('Neturite teisių redaguoti šio dalyvio.', 'error');
        redirect(SITE_URL . '/modules/registration/my_students.php');
        exit;
    }

    // NAUJA: mokytojas gali redaguoti dalyvio informaciją TIK kol olimpiada aktyvi
    // (konkursai.status = 0). Administratorius šio apribojimo neturi - gali redaguoti
    // visada, taip pat ir po olimpiados pabaigos (pvz. taisant klaidą po rezultatų paskelbimo).
    $oly_status_row = db_get_row(db_query("SELECT status FROM konkursai WHERE konkurso_pav = ?", [$participant['konkurso_pav']], 's'));
    $olympiad_is_active = $oly_status_row && (int)$oly_status_row['status'] === 0;

    if (!$olympiad_is_active) {
        set_message('Ši olimpiada nebeaktyvi - dalyvio informaciją gali redaguoti tik administratorius.', 'error');
        redirect(SITE_URL . '/modules/registration/my_students.php');
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        set_message('Netinkamas saugumo žetonas. Bandykite dar kartą.', 'error');
        redirect(current_url());
        exit;
    }

    $vardas     = sanitize_input($_POST['1_vardas'] ?? '');
    $pavarde    = sanitize_input($_POST['1_pavarde'] ?? '');
    $klase      = sanitize_input($_POST['1_klase'] ?? '');
    $mokytojas  = sanitize_input($_POST['1_mok'] ?? '');
    $mok_kvali  = sanitize_input($_POST['1_mok_kvali'] ?? '');
    $mok2       = sanitize_input($_POST['2_mok'] ?? '');
    $mok2_kvali = sanitize_input($_POST['2_mok_kvali'] ?? '');
    $inf_1      = sanitize_input($_POST['inf_1'] ?? '');
    $inf_2      = sanitize_input($_POST['inf_2'] ?? '');
    $pastabos   = sanitize_input($_POST['pastabos'] ?? '');

    if (empty($vardas) || empty($pavarde) || empty($klase)) {
        set_message('Prašome užpildyti privalomus laukus (vardas, pavardė, klasė).', 'error');
    } else {
        // SAUGU: patikra PRIEŠ atnaujinimą, ar pakeistas vardas+pavardė+mokykla
        // nesusidurs su KITU jau egzistuojančiu dalyviu toje pačioje olimpiadoje
        // (dublikatų apsauga - reg_id != $id, kad neblokuotų paties savęs).
        $duplicate_check = db_get_row(db_query(
            "SELECT reg_id FROM dalyviai WHERE 1_vardas = ? AND 1_pavarde = ? AND var_mokykla = ? AND konkurso_pav = ? AND reg_id != ?",
            [$vardas, $pavarde, $participant['var_mokykla'], $participant['konkurso_pav'], $id], 'ssssi'
        ));

        if ($duplicate_check) {
            set_message("Kitas dalyvis vardu {$vardas} {$pavarde} jau užregistruotas į šią olimpiadą. Patikrinkite, ar neredaguojate teisingo įrašo.", 'error');
        } else {
        // SAUGU: whitelist'as atnaujinamų stulpelių - Balai/Vieta/status/kitas_etapas/
        // var_mokykla/konkurso_pav sąmoningai NEĮTRAUKTI.
        $update_data = [
            '1_vardas'    => $vardas,
            '1_pavarde'   => $pavarde,
            '1_klase'     => $klase,
            '1_mok'       => $mokytojas,
            '1_mok_kvali' => $mok_kvali,
            '2_mok'       => $mok2,
            '2_mok_kvali' => $mok2_kvali,
            'inf_1'       => $inf_1,
            'inf_2'       => $inf_2,
            'pastabos'    => $pastabos,
        ];

        // SAUGU: try/catch - db_connect() naudoja MYSQLI_REPORT_STRICT, todėl DB
        // klaidos (pvz. UNIQUE apribojimo pažeidimas) meta išimtį per db_update().
        try {
            $result = db_update('dalyviai', $update_data, 'reg_id = ?', [$id]);
        } catch (mysqli_sql_exception $e) {
            $result = false;
            if ($e->getCode() === 1062) {
                set_message("Kitas dalyvis vardu {$vardas} {$pavarde} jau užregistruotas į šią olimpiadą.", 'error');
            } else {
                error_log("DB klaida redaguojant dalyvį: " . $e->getMessage());
                set_message('Klaida atnaujinant duomenis. Bandykite dar kartą.', 'error');
            }
        }

        if ($result) {
            // NAUJA: redagavimas fiksuojamas atskirame mokytojų veiklos žurnale
            log_teacher_action(
                'Redagavo dalyvį',
                "{$vardas} {$pavarde} (\"{$participant['konkurso_pav']}\")",
                $id
            );
            set_message('Dalyvio duomenys sėkmingai atnaujinti.', 'success');
            redirect(SITE_URL . '/modules/registration/my_students.php');
            exit;
        } elseif (!isset($_SESSION['message'])) {
            set_message('Klaida atnaujinant duomenis.', 'error');
        }
        }
    }

    // Perkrauname duomenis formai (jei buvo klaida)
    $participant = db_get_row(db_query("SELECT * FROM dalyviai WHERE reg_id = ?", [$id], 'i'));
}

require_once dirname(dirname(dirname(__FILE__))) . '/includes/header.php';
?>

<div class="container mt-4 mb-5">
    <div class="card shadow-sm border-0">
        <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center py-3">
            <h1 class="h4 mb-0"><i class="fas fa-user-edit"></i> Dalyvio redagavimas</h1>
            <a href="<?php echo SITE_URL; ?>/modules/registration/my_students.php" class="btn btn-sm btn-light text-primary fw-bold">
                <i class="fas fa-arrow-left"></i> Grįžti į sąrašą
            </a>
        </div>
        <div class="card-body bg-light">
            <?php display_message(); ?>

            <div class="alert alert-secondary">
                <i class="fas fa-info-circle"></i>
                Olimpiada: <strong><?php echo htmlspecialchars($participant['konkurso_pav']); ?></strong> |
                Mokykla: <strong><?php echo htmlspecialchars($participant['var_mokykla']); ?></strong>
                <br><small class="text-muted">Rezultatus (balus, vietą) galima keisti tik per "Rezultatai" skiltį.</small>
            </div>

            <form method="post" action="" class="bg-white p-4 border rounded needs-validation" novalidate>
                <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">

                <h5 class="text-secondary mb-3 border-bottom pb-2">Mokinio duomenys</h5>
                <div class="row">
                    <div class="col-md-4 form-group mb-3">
                        <label class="fw-bold">Vardas <span class="text-danger">*</span></label>
                        <input type="text" name="1_vardas" class="form-control" value="<?php echo htmlspecialchars($participant['1_vardas']); ?>" required>
                    </div>
                    <div class="col-md-4 form-group mb-3">
                        <label class="fw-bold">Pavardė <span class="text-danger">*</span></label>
                        <input type="text" name="1_pavarde" class="form-control" value="<?php echo htmlspecialchars($participant['1_pavarde']); ?>" required>
                    </div>
                    <div class="col-md-4 form-group mb-3">
                        <label class="fw-bold">Klasė <span class="text-danger">*</span></label>
                        <select name="1_klase" class="form-select form-control" required>
                            <option value="">-- Pasirinkite --</option>
                            <?php foreach ($classes as $class): ?>
                                <option value="<?php echo htmlspecialchars($class['klases']); ?>" <?php echo ($participant['1_klase'] === $class['klases']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($class['klases']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <h5 class="text-secondary mt-3 mb-3 border-bottom pb-2">Mokytojų duomenys</h5>
                <div class="row">
                    <div class="col-md-6 form-group mb-3">
                        <label class="fw-bold">Ruošęs mokytojas</label>
                        <input type="text" name="1_mok" class="form-control" value="<?php echo htmlspecialchars($participant['1_mok'] ?? ''); ?>">
                    </div>
                    <div class="col-md-6 form-group mb-3">
                        <label class="fw-bold">Mokytojo kvalifikacija</label>
                        <select name="1_mok_kvali" class="form-select form-control">
                            <option value="">-- Pasirinkite --</option>
                            <?php foreach ($qualifications as $q): $kval = $q['kategorija']; ?>
                                <option value="<?php echo htmlspecialchars($kval); ?>" <?php echo ($participant['1_mok_kvali'] ?? '') === $kval ? 'selected' : ''; ?>><?php echo htmlspecialchars($kval); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6 form-group mb-3">
                        <label class="fw-bold text-muted">Antras mokytojas (neprivaloma)</label>
                        <input type="text" name="2_mok" class="form-control" value="<?php echo htmlspecialchars($participant['2_mok'] ?? ''); ?>">
                    </div>
                    <div class="col-md-6 form-group mb-3">
                        <label class="fw-bold text-muted">Antro mokytojo kvalifikacija</label>
                        <select name="2_mok_kvali" class="form-select form-control">
                            <option value="">-- Pasirinkite --</option>
                            <?php foreach ($qualifications as $q): $kval = $q['kategorija']; ?>
                                <option value="<?php echo htmlspecialchars($kval); ?>" <?php echo ($participant['2_mok_kvali'] ?? '') === $kval ? 'selected' : ''; ?>><?php echo htmlspecialchars($kval); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <h5 class="text-secondary mt-3 mb-3 border-bottom pb-2">Papildoma informacija</h5>
                <div class="row">
                    <div class="col-md-6 form-group mb-3">
                        <label class="fw-bold text-muted">Informacija 1</label>
                        <input type="text" name="inf_1" class="form-control" value="<?php echo htmlspecialchars($participant['inf_1'] ?? ''); ?>">
                    </div>
                    <div class="col-md-6 form-group mb-3">
                        <label class="fw-bold text-muted">Informacija 2</label>
                        <input type="text" name="inf_2" class="form-control" value="<?php echo htmlspecialchars($participant['inf_2'] ?? ''); ?>">
                    </div>
                </div>
                <div class="form-group mb-3">
                    <label class="fw-bold text-muted">Pastabos</label>
                    <textarea name="pastabos" class="form-control" rows="2"><?php echo htmlspecialchars($participant['pastabos'] ?? ''); ?></textarea>
                </div>

                <div class="mt-4 pt-3 border-top">
                    <button type="submit" class="btn btn-success px-4"><i class="fas fa-save"></i> Išsaugoti pakeitimus</button>
                    <a href="<?php echo SITE_URL; ?>/modules/registration/my_students.php" class="btn btn-secondary ms-2">Atšaukti</a>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once dirname(dirname(dirname(__FILE__))) . '/includes/footer.php'; ?>
