<?php
require_once __DIR__ . '/../../config/config.php'; 
require_once __DIR__ . '/../../config/db_connect.php';
require_once __DIR__ . '/../../config/functions.php';

start_session();

// PRIDĖTA: prisijungimo patikra
if (!is_logged_in()) {
    set_message('Turite prisijungti, kad galėtumėte registruoti dalyvius', 'error');
    redirect(SITE_URL . '/modules/auth/login.php');
    exit;
}

// ---------------------------------------------------------
// 1. DUOMENŲ IŠSAUGOJIMO LOGIKA
// ---------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['registruoti_dalyvi'])) {

    // PRIDĖTA: CSRF patikra
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        set_message('Netinkamas saugumo žetonas. Bandykite dar kartą.', 'error');
        redirect(current_url());
        exit;
    }

    $konkurso_pav = sanitize_input($_POST['konkurso_pav'] ?? '');
    $var_mokykla  = sanitize_input($_POST['var_mokykla'] ?? '');
    $vardas       = sanitize_input($_POST['1_vardas'] ?? '');
    $pavarde      = sanitize_input($_POST['1_pavarde'] ?? '');
    $klase        = sanitize_input($_POST['1_klase'] ?? '');
    $mokytojas    = sanitize_input($_POST['1_mok'] ?? '');
    $mok_kvali    = sanitize_input($_POST['1_mok_kvali'] ?? '');
    
    // NAUJI LAUKAI
    $mok2         = sanitize_input($_POST['2_mok'] ?? '');
    $mok2_kvali   = sanitize_input($_POST['2_mok_kvali'] ?? '');
    $inf_1        = sanitize_input($_POST['inf_1'] ?? '');
    $inf_2        = sanitize_input($_POST['inf_2'] ?? '');
    $pastabos     = sanitize_input($_POST['pastabos'] ?? '');
    
    $vart_id = $_SESSION['user_id'] ?? 'SISTEMA'; // Naudojame user_id (pagal jūsų login.php nustatymą)

    if (!empty($konkurso_pav) && !empty($var_mokykla) && !empty($vardas) && !empty($pavarde)) {
        $conn = db_connect();
        
        if ($conn) {
            // SAUGU: patikra PRIEŠ įrašymą, ar šis mokinys (vardas+pavardė+mokykla)
            // jau nėra užregistruotas būtent į šią olimpiadą. Mokinys GALI dalyvauti
            // keliose skirtingose olimpiadose (tai normalu) - draudžiama tik ta pati
            // olimpiada + tas pats mokinys du kartus.
            $duplicate_check = db_get_row(db_query(
                "SELECT reg_id FROM dalyviai WHERE 1_vardas = ? AND 1_pavarde = ? AND var_mokykla = ? AND konkurso_pav = ?",
                [$vardas, $pavarde, $var_mokykla, $konkurso_pav], 'ssss'
            ));

            if ($duplicate_check) {
                set_message("Mokinys {$vardas} {$pavarde} jau užregistruotas į šią olimpiadą. Naudokite greitąją paiešką, kad pasirinktumėte jį kitai olimpiadai, arba patikrinkite \"Mano dalyviai\" sąrašą.", 'error');
                redirect(current_url());
                exit;
            }

            $sql = "INSERT INTO dalyviai (
                        konkurso_pav, var_mokykla, pil_data, 
                        1_vardas, 1_pavarde, 1_klase, 
                        1_mok, 1_mok_kvali, 2_mok, 2_mok_kvali, 
                        inf_1, inf_2, pastabos, vart_id
                    ) VALUES (?, ?, NOW(), ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                    
            $stmt = $conn->prepare($sql);
            if ($stmt) {
                // 13 kintamųjų = "sssssssssssss"
                $stmt->bind_param("sssssssssssss", 
                    $konkurso_pav, $var_mokykla, $vardas, $pavarde, $klase, 
                    $mokytojas, $mok_kvali, $mok2, $mok2_kvali, 
                    $inf_1, $inf_2, $pastabos, $vart_id
                );
                
                // SAUGU: try/catch aplink execute() - db_connect() naudoja
                // MYSQLI_REPORT_STRICT, todėl DB klaidos (pvz. UNIQUE apribojimo
                // pažeidimas) meta išimtį, o ne tiesiog grąžina false. Šis blokas
                // apsaugo nuo netikėtos "fatal error" situacijos lenktynių sąlygos
                // atveju (du vienalaikiai vieno mokinio registravimo bandymai).
                try {
                    if ($stmt->execute()) {
                        $new_reg_id = $conn->insert_id;
                        // NAUJA: dalyvio registravimas fiksuojamas atskirame mokytojų veiklos žurnale
                        log_teacher_action(
                            'Registravo dalyvį',
                            "{$vardas} {$pavarde} ({$klase} kl.) į \"{$konkurso_pav}\" ({$var_mokykla})",
                            $new_reg_id
                        );
                        set_message('Dalyvis sėkmingai užregistruotas į olimpiadą!', 'success');
                    } else {
                        set_message('Klaida išsaugant duomenis: ' . $stmt->error, 'error');
                    }
                } catch (mysqli_sql_exception $e) {
                    if ($e->getCode() === 1062) {
                        set_message("Mokinys {$vardas} {$pavarde} jau užregistruotas į šią olimpiadą.", 'error');
                    } else {
                        error_log("DB klaida registruojant dalyvį: " . $e->getMessage());
                        set_message('Klaida išsaugant duomenis. Bandykite dar kartą.', 'error');
                    }
                }
                $stmt->close();
            }
        }
    } else {
        set_message('Klaida: Neužpildyti privalomi laukai!', 'error');
    }
    
    redirect(current_url());
}

// ---------------------------------------------------------
// 2. KONKURSŲ IR KLASIŲ SĄRAŠAI FORMOS DROPDOWN'AMS
// ---------------------------------------------------------
$konkursai = [];
$conn = db_connect();
if ($conn) {
    $res = $conn->query("SELECT konkurso_pav FROM konkursai WHERE status = 0 ORDER BY sukurimo_data DESC");
    if ($res) {
        while($row = $res->fetch_assoc()) {
            $konkursai[] = $row['konkurso_pav'];
        }
    }
}

// PRIDĖTA: klasių sąrašas pasirinkimui iš dropdown (kaip admin/participant_edit.php)
$classes = db_get_results(db_query("SELECT klases FROM klases ORDER BY klases ASC"));

// PATAISYTA: kvalifikacijų sąrašas dabar imamas iš DB lentelės (kaip admin/participant_edit.php),
// o ne fiksuoto PHP masyvo - anksčiau du sąrašai galėjo išsiskirti, jei administratorius
// pakeistų kvalifikacijos_categorijas per DB.
$qualifications = db_get_results(db_query("SELECT kategorija FROM kvalifikacijos ORDER BY kategorija ASC"));

// PATAISYTA: jei atėjome iš konkrečios olimpiados puslapio (olympiads/active.php,
// olympiads/view.php, registration/index.php visi siunčia ?olympiad_id=X), automatiškai
// pažymime tą olimpiadą dropdown'e - anksčiau šis parametras buvo visiškai ignoruojamas
// ir mokytojui tekdavo pasirinkti olimpiadą iš naujo net paspaudus konkretų "Registruoti" mygtuką.
$preselected_olympiad = '';
if (isset($_GET['olympiad_id']) && ctype_digit((string)$_GET['olympiad_id'])) {
    $pre_row = db_get_row(db_query("SELECT konkurso_pav FROM konkursai WHERE konk_id = ? AND status = 0", [(int)$_GET['olympiad_id']], 'i'));
    if ($pre_row) {
        $preselected_olympiad = $pre_row['konkurso_pav'];
    }
}

// =========================================================
require_once __DIR__ . '/../../includes/header.php';
?>

<style>
    .autocomplete-container { position: relative; }
    #paieskos-rezultatai { position: absolute; top: 100%; left: 0; right: 0; background: #fff; border: 1px solid #ced4da; border-radius: 0 0 4px 4px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); z-index: 1000; display: none; max-height: 250px; overflow-y: auto; }
    .autocomplete-list { list-style: none; margin: 0; padding: 0; }
    .autocomplete-list li { padding: 8px 12px; cursor: pointer; border-bottom: 1px solid #f1f1f1; font-size: 0.9em; }
    .autocomplete-list li:last-child { border-bottom: none; }
    .autocomplete-list li:hover { background-color: #e9ecef; color: #0056b3; }
</style>

<div class="container mt-4 mb-5">
    <?php display_message(); ?>
    <div class="card shadow-sm border-0">
        <div class="card-header bg-primary text-white">
            <h4 class="mb-0 text-white"><i class="fas fa-user-plus"></i> Dalyvio registracija</h4>
        </div>
        <div class="card-body bg-light">
            
            <div class="alert alert-info border-info">
                <strong><i class="fas fa-info-circle"></i> Patarimas:</strong> Pirmiausia pasirinkite olimpiadą, tada ieškokite mokinio pavardės – jei mokinys jau registruotas sistemoje, sistema automatiškai užpildys jo duomenis ir jums liks tik jį patvirtinti. Jei mokinys jau užregistruotas būtent į pasirinktą olimpiadą, jis bus pažymėtas ir jo pasirinkti negalėsite (dublikatų apsauga).
            </div>

           <form action="" method="POST" class="bg-white p-4 border rounded">

    <!-- PRIDĖTA -->
    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">

                <!-- PAGRINDINĖ INFORMACIJA (perkelta prieš paiešką, kad paieška žinotų pasirinktą olimpiadą) -->
                <h5 class="text-secondary mb-3 border-bottom pb-2">Bendra informacija</h5>
                <div class="row">
                    <div class="col-md-6 form-group mb-3">
                        <label class="fw-bold">Konkursas / Olimpiada <span class="text-danger">*</span></label>
                        <select name="konkurso_pav" id="konkurso_pav" class="form-select form-control" required>
                            <option value="">-- Pasirinkite --</option>
                            <?php foreach ($konkursai as $k_pav): ?>
                                <option value="<?php echo htmlspecialchars($k_pav); ?>" <?php echo ($preselected_olympiad !== '' && $preselected_olympiad === $k_pav) ? 'selected' : ''; ?>><?php echo htmlspecialchars($k_pav); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6 form-group mb-3">
                        <label class="fw-bold">Mokykla <span class="text-danger">*</span></label>
                        <!-- Priklausomai nuo to ar adminas, leidžiame keisti mokyklą arba užfiksuojame vartotojo mokyklą -->
                        <?php if (is_admin()): ?>
                            <input type="text" name="var_mokykla" id="var_mokykla" class="form-control" required>
                        <?php else: ?>
                            <input type="text" name="var_mokykla" id="var_mokykla" class="form-control" value="<?php echo htmlspecialchars(db_get_row(db_query("SELECT var_mokykla FROM vartotojas WHERE vart_id = ?", [$_SESSION['user_id']], 's'))['var_mokykla'] ?? ''); ?>" readonly required>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- GREITA PAIEŠKA -->
    <div class="form-group autocomplete-container mb-4">
                    <label for="paieska" class="font-weight-bold text-primary"><i class="fas fa-search"></i> Greita paieška istorijoje (pagal pavardę):</label>
                    <input type="text" id="paieska" class="form-control form-control-lg border-primary" placeholder="Pvz.: Jonaitis..." autocomplete="off">
                    <div id="paieskos-rezultatai"></div>
                </div>

                <hr class="mb-4">

                <!-- MOKINIO INFORMACIJA -->
                <h5 class="text-secondary mt-3 mb-3 border-bottom pb-2">Mokinio duomenys</h5>
                <div class="row">
                    <div class="col-md-4 form-group mb-3">
                        <label class="fw-bold">Mokinio vardas <span class="text-danger">*</span></label>
                        <input type="text" name="1_vardas" id="vardas" class="form-control" required>
                    </div>
                    <div class="col-md-4 form-group mb-3">
                        <label class="fw-bold">Mokinio pavardė <span class="text-danger">*</span></label>
                        <input type="text" name="1_pavarde" id="pavarde" class="form-control" required>
                    </div>
                    <div class="col-md-4 form-group mb-3">
                        <label class="fw-bold">Klasė <span class="text-danger">*</span></label>
                        <select name="1_klase" id="klase" class="form-select form-control" required>
                            <option value="">-- Pasirinkite --</option>
                            <?php foreach ($classes as $class): ?>
                                <option value="<?php echo htmlspecialchars($class['klases']); ?>"><?php echo htmlspecialchars($class['klases']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <!-- MOKYTOJŲ INFORMACIJA -->
                <h5 class="text-secondary mt-3 mb-3 border-bottom pb-2">Mokytojų duomenys</h5>
                
                <div class="row">
                    <div class="col-md-6 form-group mb-3">
                        <label class="fw-bold">Ruošęs mokytojas (Vardas Pavardė) <span class="text-danger">*</span></label>
                        <input type="text" name="1_mok" id="mokytojas" class="form-control" required>
                    </div>
                    
                    <div class="col-md-6 form-group mb-3">
                        <label class="fw-bold">Mokytojo kvalifikacija</label>
                        <select name="1_mok_kvali" id="kvalifikacija" class="form-select form-control">
                            <option value="">-- Pasirinkite (Neprivaloma) --</option>
                            <?php foreach ($qualifications as $q): ?>
                                <option value="<?php echo htmlspecialchars($q['kategorija']); ?>"><?php echo htmlspecialchars($q['kategorija']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 form-group mb-3">
                        <label class="fw-bold text-muted">Antras mokytojas (Neprivaloma)</label>
                        <input type="text" name="2_mok" id="mokytojas_2" class="form-control" placeholder="Vardas Pavardė">
                    </div>
                    
                    <div class="col-md-6 form-group mb-3">
                        <label class="fw-bold text-muted">Antro mokytojo kvalifikacija</label>
                        <select name="2_mok_kvali" id="kvalifikacija_2" class="form-select form-control">
                            <option value="">-- Pasirinkite (Neprivaloma) --</option>
                            <?php foreach ($qualifications as $q): ?>
                                <option value="<?php echo htmlspecialchars($q['kategorija']); ?>"><?php echo htmlspecialchars($q['kategorija']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <!-- PAPILDOMA INFORMACIJA -->
                <h5 class="text-secondary mt-3 mb-3 border-bottom pb-2">Papildoma informacija</h5>
                <div class="row">
                    <div class="col-md-6 form-group mb-3">
                        <label class="fw-bold text-muted">Informacija 1 (Pvz. el. paštas)</label>
                        <input type="text" name="inf_1" id="inf_1" class="form-control">
                    </div>
                    <div class="col-md-6 form-group mb-3">
                        <label class="fw-bold text-muted">Informacija 2 (Pvz. telefono nr.)</label>
                        <input type="text" name="inf_2" id="inf_2" class="form-control">
                    </div>
                </div>

                <div class="form-group mb-3">
                    <label class="fw-bold text-muted">Pastabos (Pvz. maitinimas, alergijos ar kita)</label>
                    <textarea name="pastabos" id="pastabos" class="form-control" rows="2"></textarea>
                </div>

                <div class="mt-4 pt-3 border-top">
                    <button type="submit" name="registruoti_dalyvi" class="btn btn-success btn-lg px-5">
                        <i class="fas fa-check-circle"></i> Saugoti dalyvį
                    </button>
                    <button type="reset" class="btn btn-secondary btn-lg ms-2"> Išvalyti formą</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const paieskaInput = document.getElementById('paieska');
    const vardasInput = document.getElementById('vardas');
    const pavardeInput = document.getElementById('pavarde');
    const klaseInput = document.getElementById('klase');
    const mokyklaInput = document.getElementById('var_mokykla');
    const mokytojasInput = document.getElementById('mokytojas');
    const kvalifikacijaInput = document.getElementById('kvalifikacija');
    const rezultataiDiv = document.getElementById('paieskos-rezultatai');
    const konkursoSelect = document.getElementById('konkurso_pav');
    
    let timeout = null;

    // NAUJA: pasikeitus olimpiadai, paslepiame senus paieškos rezultatus,
    // nes juose žymėta "jau užregistruotas" būsena priklauso nuo olimpiados.
    konkursoSelect.addEventListener('change', function() {
        rezultataiDiv.style.display = 'none';
        rezultataiDiv.innerHTML = '';
    });

    paieskaInput.addEventListener('input', function() {
        clearTimeout(timeout);
        const term = this.value.trim();
        if (term.length < 2) {
            rezultataiDiv.style.display = 'none';
            return;
        }

        timeout = setTimeout(function() {
            const selectedOlympiad = konkursoSelect.value;
            const url = 'ajax_search.php?term=' + encodeURIComponent(term) + '&olympiad=' + encodeURIComponent(selectedOlympiad);

            fetch(url)
                .then(r => r.json())
                .then(data => {
                    rezultataiDiv.innerHTML = '';
                    if (data.length > 0) {
                        const ul = document.createElement('ul');
                        ul.className = 'autocomplete-list';
                        data.forEach(item => {
                            const li = document.createElement('li');

                            if (item.already_registered) {
                                // NAUJA: mokinys jau užregistruotas į PASIRINKTĄ olimpiadą -
                                // rodomas, bet pažymėtas ir neaktyvuojamas (dublikatų apsauga)
                                li.innerHTML = item.label + ' <span class="badge bg-warning text-dark ms-1">jau registruotas šioje olimpiadoje</span>';
                                li.style.opacity = '0.6';
                                li.style.cursor = 'not-allowed';
                                li.addEventListener('click', function(e) {
                                    e.preventDefault();
                                });
                            } else {
                                li.textContent = item.label;
                                li.addEventListener('click', function() {
                                    vardasInput.value = item.vardas;
                                    pavardeInput.value = item.pavarde;
                                    klaseInput.value = item.klase;
                                    <?php if (is_admin()): ?>
                                    mokyklaInput.value = item.mokykla;
                                    <?php endif; ?>
                                    mokytojasInput.value = item.mokytojas;

                                    paieskaInput.value = '';
                                    rezultataiDiv.style.display = 'none';
                                });
                            }
                            ul.appendChild(li);
                        });
                        rezultataiDiv.appendChild(ul);
                        rezultataiDiv.style.display = 'block';
                    } else {
                        rezultataiDiv.style.display = 'none';
                    }
                })
                .catch(err => console.error('Klaida ieškant:', err));
        }, 300);
    });

    document.addEventListener('click', function(e) {
        if (e.target !== paieskaInput && e.target !== rezultataiDiv) {
            rezultataiDiv.style.display = 'none';
        }
    });
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>