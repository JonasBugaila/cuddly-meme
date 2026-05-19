<?php
/**
 * Naujos mokyklos pridėjimo forma
 * 
 * Šis failas leidžia administratoriams pridėti naują mokyklą
 */

require_once dirname(dirname(dirname(__FILE__))) . '/config/config.php';
require_once dirname(dirname(dirname(__FILE__))) . '/config/db_connect.php';
require_once dirname(dirname(dirname(__FILE__))) . '/config/functions.php';

// Rodome visas klaidas
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Tikriname, ar vartotojas prisijungęs ir turi administratoriaus teises
if (!is_logged_in()) {
    set_message('Turite prisijungti, kad galėtumėte pasiekti šį puslapį', 'error');
    redirect(SITE_URL . '/modules/auth/login.php');
} elseif (!is_admin()) {
    set_message('Neturite teisių pasiekti šį puslapį', 'error');
    redirect(SITE_URL);
}

// Apdorojame formą
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $errors = [];
    
    // Tikriname CSRF žetoną
    if (!verify_csrf_token($_POST['csrf_token'])) {
        $errors[] = 'Netinkamas CSRF žetonas';
    }
    
    // Tikriname pavadinimą
    $pavadinimas = sanitize_input($_POST['pavadinimas']);
    if (empty($pavadinimas)) {
        $errors[] = 'Prašome įvesti mokyklos pavadinimą';
    }
    
    // Tikriname kitus laukus (neprivalomi)
    $adresas = sanitize_input($_POST['adresas']);
    if (empty($adresas)) {
        $adresas = null;
    }
    
    $telefonas = sanitize_input($_POST['telefonas']);
    if (empty($telefonas)) {
        $telefonas = null;
    }
    
    $el_pastas = sanitize_input($_POST['el_pastas']);
    if (empty($el_pastas)) {
        $el_pastas = null;
    } elseif (!filter_var($el_pastas, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Neteisingas el. pašto formatas';
    }
    
    $direktorius = sanitize_input($_POST['direktorius']);
    if (empty($direktorius)) {
        $direktorius = null;
    }
    
    // Jei nėra klaidų, pridedame mokyklą
    if (empty($errors)) {
        $data_array = [
            'pavadinimas' => $pavadinimas,
            'adresas' => $adresas,
            'telefonas' => $telefonas,
            'el_pastas' => $el_pastas,
            'direktorius' => $direktorius
        ];
        
        $result = db_insert('mokyklos', $data_array);
        
        if ($result) {
            set_message('Mokykla sėkmingai pridėta', 'success');
            redirect(SITE_URL . '/modules/admin/schools.php');
        } else {
            $conn = db_connect();
            error_log("Insert failed: " . $conn->error . " | Data: " . json_encode($data_array));
            set_message('Klaida pridedant mokyklą: ' . $conn->error, 'error');
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
                    <h1>Naujos mokyklos pridėjimas</h1>
                </div>
                <div class="card-body">
                    <?php display_message(); ?>
                    <form action="<?php echo SITE_URL; ?>/modules/admin/school_add.php" method="post" class="needs-validation" novalidate>
                        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group mb-3">
                                    <label for="pavadinimas" class="form-label">Pavadinimas *</label>
                                    <input type="text" class="form-control" id="pavadinimas" name="pavadinimas" value="<?php echo isset($_POST['pavadinimas']) ? htmlspecialchars($_POST['pavadinimas']) : ''; ?>" required>
                                    <div class="invalid-feedback">
                                        Prašome įvesti mokyklos pavadinimą
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group mb-3">
                                    <label for="adresas" class="form-label">Adresas</label>
                                    <input type="text" class="form-control" id="adresas" name="adresas" value="<?php echo isset($_POST['adresas']) ? htmlspecialchars($_POST['adresas']) : ''; ?>">
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group mb-3">
                                    <label for="telefonas" class="form-label">Telefonas</label>
                                    <input type="text" class="form-control" id="telefonas" name="telefonas" value="<?php echo isset($_POST['telefonas']) ? htmlspecialchars($_POST['telefonas']) : ''; ?>">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group mb-3">
                                    <label for="el_pastas" class="form-label">El. paštas</label>
                                    <input type="email" class="form-control" id="el_pastas" name="el_pastas" value="<?php echo isset($_POST['el_pastas']) ? htmlspecialchars($_POST['el_pastas']) : ''; ?>">
                                    <div class="invalid-feedback">
                                        Prašome įvesti teisingą el. pašto adresą
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group mb-3">
                                    <label for="direktorius" class="form-label">Direktorius</label>
                                    <input type="text" class="form-control" id="direktorius" name="direktorius" value="<?php echo isset($_POST['direktorius']) ? htmlspecialchars($_POST['direktorius']) : ''; ?>">
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <button type="submit" class="btn btn-primary">Pridėti mokyklą</button>
                            <a href="<?php echo SITE_URL; ?>/modules/admin/schools.php" class="btn btn-secondary ms-2">Grįžti</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
require_once dirname(dirname(dirname(__FILE__))) . '/includes/footer.php';
?>