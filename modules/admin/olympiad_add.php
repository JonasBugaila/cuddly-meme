<?php
/**
 * Olimpiados registravimo forma
 * 
 * Šis failas leidžia registruoti naujas olimpiadas/konkursus
 */

require_once dirname(dirname(dirname(__FILE__))) . '/config/config.php';
require_once dirname(dirname(dirname(__FILE__))) . '/config/db_connect.php';
require_once dirname(dirname(dirname(__FILE__))) . '/config/functions.php';

// Tikriname ar vartotojas prisijungęs ir turi administratoriaus teises
if (!is_logged_in()) {
    set_message('Turite prisijungti, kad galėtumėte pasiekti šį puslapį', 'error');
    redirect(SITE_URL . '/modules/auth/login.php');
} elseif (!is_admin()) {
    set_message('Neturite teisių pasiekti šį puslapį', 'error');
    redirect(SITE_URL);
}

// Gauname vartotojų sąrašą (atsakingų asmenų)
$sql = "SELECT vart_id, var_vardas, var_pavarde FROM vartotojas ORDER BY var_pavarde ASC";
$stmt = db_query($sql);
$users = db_get_results($stmt);

// Gauname grupių sąrašą
$sql = "SELECT DISTINCT grupe FROM konkursai ORDER BY grupe ASC";
$stmt = db_query($sql);
$groups = db_get_results($stmt);

// Jei grupių nėra, pridedame kelias pradines
if (empty($groups)) {
    $groups = [
        ['grupe' => 'Lietuvių kalba'],
        ['grupe' => 'Matematika'],
        ['grupe' => 'Užsienio kalbos'],
        ['grupe' => 'Gamtos mokslai'],
        ['grupe' => 'Socialiniai mokslai'],
        ['grupe' => 'Menai'],
        ['grupe' => 'Technologijos'],
        ['grupe' => 'Kita']
    ];
}

// Apdorojame formą
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $errors = [];
    
    // Tikriname ar įvestas olimpiados pavadinimas
    if (empty($_POST['pavadinimas'])) {
        $errors[] = 'Prašome įvesti olimpiados pavadinimą';
    }
    
    // Tikriname ar pasirinktas atsakingas asmuo
    if (empty($_POST['atsakingas'])) {
        $errors[] = 'Prašome pasirinkti atsakingą asmenį';
    }
    
    // Tikriname ar pasirinkta grupė
    if (empty($_POST['grupe'])) {
        $errors[] = 'Prašome pasirinkti grupę';
    }
    
    // Jei nėra klaidų, registruojame olimpiadą
    if (empty($errors)) {
        // Paruošiame duomenis
        $data = [
            'konkurso_pav' => sanitize_input($_POST['pavadinimas']),
            'atsakingas' => sanitize_input($_POST['atsakingas']),
            'status' => 0, // Aktyvus pagal nutylėjimą
            'grupe' => sanitize_input($_POST['grupe']),
            'ne_rajono' => isset($_POST['ne_rajono']) && $_POST['ne_rajono'] == 'taip' ? 1 : 0,
            'smsm_patvirtintas' => isset($_POST['smsm_patvirtintas']) && $_POST['smsm_patvirtintas'] == 'taip' ? 1 : 0,
            'data' => !empty($_POST['data']) ? sanitize_input($_POST['data']) : null,
            'vieta' => !empty($_POST['vieta']) ? sanitize_input($_POST['vieta']) : null,
            'aprasymas' => !empty($_POST['aprasymas']) ? sanitize_input($_POST['aprasymas']) : null,
            'sukurimo_data' => date('Y-m-d H:i:s')
        ];
        
        // Įterpiame duomenis
        $result = db_insert('konkursai', $data);
        
        if ($result) {
            set_message('Olimpiada sėkmingai užregistruota', 'success');
            redirect(SITE_URL . '/modules/admin/olympiad_add.php');
        } else {
            $conn = db_connect();
            error_log("Klaida registruojant olimpiadą: " . $conn->error);
            set_message('Klaida registruojant olimpiadą. Žiūrėkite serverio klaidų žurnalą.', 'error');
        }
    } else {
        foreach ($errors as $error) {
            set_message($error, 'error');
        }
    }
}

// Įtraukiame antraštę
require_once dirname(dirname(dirname(__FILE__))) . '/includes/header.php';
?>

<div class="container mt-4">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h1>Olimpiados registracija</h1>
                </div>
                <div class="card-body">
                    <form action="<?php echo SITE_URL; ?>/modules/admin/olympiad_add.php" method="post" class="needs-validation" novalidate>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group mb-3">
                                    <label for="pavadinimas" class="form-label">Konkurso (olimpiados) pavadinimas *</label>
                                    <input type="text" class="form-control" id="pavadinimas" name="pavadinimas" value="<?php echo isset($_POST['pavadinimas']) ? htmlspecialchars($_POST['pavadinimas']) : ''; ?>" required>
                                    <div class="invalid-feedback">
                                        Prašome įvesti olimpiados pavadinimą
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group mb-3">
                                    <label for="atsakingas" class="form-label">Atsakingas *</label>
                                    <select class="form-control" id="atsakingas" name="atsakingas" required>
                                        <option value="">Pasirinkite atsakingą asmenį</option>
                                        <?php foreach ($users as $user): ?>
                                            <option value="<?php echo $user['var_vardas'] . ' ' . $user['var_pavarde']; ?>" <?php echo isset($_POST['atsakingas']) && $_POST['atsakingas'] == $user['var_vardas'] . ' ' . $user['var_pavarde'] ? 'selected' : ''; ?>>
                                                <?php echo $user['var_vardas'] . ' ' . $user['var_pavarde']; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="invalid-feedback">
                                        Prašome pasirinkti atsakingą asmenį
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group mb-3">
                                    <label class="form-label">Renginys inicijuotas ne mūsų rajono mokytojų</label>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="ne_rajono" id="ne_rajono_taip" value="taip" <?php echo isset($_POST['ne_rajono']) && $_POST['ne_rajono'] == 'taip' ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="ne_rajono_taip">
                                            Taip
                                        </label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="ne_rajono" id="ne_rajono_ne" value="ne" <?php echo !isset($_POST['ne_rajono']) || $_POST['ne_rajono'] == 'ne' ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="ne_rajono_ne">
                                            Ne
                                        </label>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group mb-3">
                                    <label class="form-label">Renginys patvirtintas pagal ŠMSM renginių grafiką</label>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="smsm_patvirtintas" id="smsm_patvirtintas_taip" value="taip" <?php echo isset($_POST['smsm_patvirtintas']) && $_POST['smsm_patvirtintas'] == 'taip' ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="smsm_patvirtintas_taip">
                                            Taip
                                        </label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="smsm_patvirtintas" id="smsm_patvirtintas_ne" value="ne" <?php echo !isset($_POST['smsm_patvirtintas']) || $_POST['smsm_patvirtintas'] == 'ne' ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="smsm_patvirtintas_ne">
                                            Ne
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group mb-3">
                                    <label for="grupe" class="form-label">Grupė *</label>
                                    <select class="form-control" id="grupe" name="grupe" required>
                                        <option value="">Pasirinkite grupę</option>
                                        <?php foreach ($groups as $group): ?>
                                            <option value="<?php echo $group['grupe']; ?>" <?php echo isset($_POST['grupe']) && $_POST['grupe'] == $group['grupe'] ? 'selected' : ''; ?>>
                                                <?php echo $group['grupe']; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="invalid-feedback">
                                        Prašome pasirinkti grupę
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group mb-3">
                                    <label for="data" class="form-label">Data</label>
                                    <input type="date" class="form-control" id="data" name="data" value="<?php echo isset($_POST['data']) ? htmlspecialchars($_POST['data']) : ''; ?>">
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-group mb-3">
                            <label for="vieta" class="form-label">Vieta</label>
                            <input type="text" class="form-control" id="vieta" name="vieta" value="<?php echo isset($_POST['vieta']) ? htmlspecialchars($_POST['vieta']) : ''; ?>">
                        </div>
                        
                        <div class="form-group mb-3">
                            <label for="aprasymas" class="form-label">Aprašymas</label>
                            <textarea class="form-control" id="aprasymas" name="aprasymas" rows="3"><?php echo isset($_POST['aprasymas']) ? htmlspecialchars($_POST['aprasymas']) : ''; ?></textarea>
                        </div>
                        
                        <div class="form-group">
                            <button type="submit" class="btn btn-primary">Registruoti olimpiadą</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Formos validacija
(function() {
    'use strict';
    window.addEventListener('load', function() {
        var forms = document.getElementsByClassName('needs-validation');
        var validation = Array.prototype.filter.call(forms, function(form) {
            form.addEventListener('submit', function(event) {
                if (form.checkValidity() === false) {
                    event.preventDefault();
                    event.stopPropagation();
                }
                form.classList.add('was-validated');
            }, false);
        });
    }, false);
})();
</script>

<?php
require_once dirname(dirname(dirname(__FILE__))) . '/includes/footer.php';
?>