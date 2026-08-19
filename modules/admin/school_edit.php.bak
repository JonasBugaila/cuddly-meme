<?php
/**
 * Mokyklos redagavimo forma
 * 
 * Šis failas leidžia administratoriams redaguoti esamų mokyklų duomenis
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

// Tikriname, ar pateiktas ID parametras
if (!isset($_GET['id']) || empty($_GET['id'])) {
    set_message('Nenurodytas mokyklos ID. Prašome pasirinkti mokyklą iš sąrašo.', 'error');
    redirect(SITE_URL . '/modules/admin/schools.php');
}

// Gauname mokyklos duomenis
$school_id = sanitize_input($_GET['id']);
$sql = "SELECT mokyklos_id, pavadinimas, adresas, telefonas, el_pastas, direktorius FROM mokyklos WHERE mokyklos_id = ?";
$stmt = db_query($sql, [$school_id], 'i');
$school = db_get_row($stmt);

// Apdorojame formą
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['school_id'])) {
    $errors = [];
    $school_id = sanitize_input($_POST['school_id']);
    
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
    
    // Jei nėra klaidų, atnaujiname mokyklą ir susijusius įrašus
    if (empty($errors)) {
        $old_pavadinimas = $school['pavadinimas'];
        $data_array = [
            'pavadinimas' => $pavadinimas,
            'adresas' => $adresas,
            'telefonas' => $telefonas,
            'el_pastas' => $el_pastas,
            'direktorius' => $direktorius
        ];
        
        $conn = db_connect();
        $conn->begin_transaction();

        try {
            // Atnaujiname mokyklos lentelę
            $result = db_update('mokyklos', $data_array, 'mokyklos_id = ?', [$school_id]);
            if (!$result) {
                throw new Exception("Nepavyko atnaujinti mokyklos lentelės: " . $conn->error);
            }

            // Atnaujiname vartotojas lentelę
            $sql = "UPDATE vartotojas SET var_mokykla = ? WHERE var_mokykla = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('ss', $pavadinimas, $old_pavadinimas);
            if (!$stmt->execute()) {
                throw new Exception("Nepavyko atnaujinti vartotojas lentelės: " . $stmt->error);
            }

            // Atnaujiname dalyviai lentelę
            $sql = "UPDATE dalyviai SET var_mokykla = ? WHERE var_mokykla = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('ss', $pavadinimas, $old_pavadinimas);
            if (!$stmt->execute()) {
                throw new Exception("Nepavyko atnaujinti dalyviai lentelės: " . $stmt->error);
            }

            // Atnaujiname konkursai lentelę (vieta)
            $sql = "UPDATE konkursai SET vieta = ? WHERE vieta = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('ss', $pavadinimas, $old_pavadinimas);
            if (!$stmt->execute()) {
                throw new Exception("Nepavyko atnaujinti konkursai lentelės: " . $stmt->error);
            }

            $conn->commit();
            set_message('Mokykla sėkmingai atnaujinta', 'success');
            redirect(SITE_URL . '/modules/admin/school_edit.php?id=' . $school_id);
        } catch (Exception $e) {
            $conn->rollback();
            error_log("Update failed: " . $e->getMessage() . " | ID: " . $school_id);
            set_message('Klaida atnaujinant mokyklą: ' . $e->getMessage(), 'error');
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
                    <h1>Mokyklos redagavimas</h1>
                </div>
                <div class="card-body">
                    <?php display_message(); ?>
                    <?php if ($school): ?>
                        <form action="<?php echo SITE_URL; ?>/modules/admin/school_edit.php?id=<?php echo htmlspecialchars($school_id); ?>" method="post" class="needs-validation" novalidate>
                            <input type="hidden" name="school_id" value="<?php echo htmlspecialchars($school['mokyklos_id']); ?>">
                            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group mb-3">
                                        <label for="pavadinimas" class="form-label">Pavadinimas *</label>
                                        <input type="text" class="form-control" id="pavadinimas" name="pavadinimas" value="<?php echo htmlspecialchars($school['pavadinimas']); ?>" required>
                                        <div class="invalid-feedback">
                                            Prašome įvesti mokyklos pavadinimą
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group mb-3">
                                        <label for="adresas" class="form-label">Adresas</label>
                                        <input type="text" class="form-control" id="adresas" name="adresas" value="<?php echo htmlspecialchars($school['adresas'] ?? ''); ?>">
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group mb-3">
                                        <label for="telefonas" class="form-label">Telefonas</label>
                                        <input type="text" class="form-control" id="telefonas" name="telefonas" value="<?php echo htmlspecialchars($school['telefonas'] ?? ''); ?>">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group mb-3">
                                        <label for="el_pastas" class="form-label">El. paštas</label>
                                        <input type="email" class="form-control" id="el_pastas" name="el_pastas" value="<?php echo htmlspecialchars($school['el_pastas'] ?? ''); ?>">
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
                                        <input type="text" class="form-control" id="direktorius" name="direktorius" value="<?php echo htmlspecialchars($school['direktorius'] ?? ''); ?>">
                                    </div>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <button type="submit" class="btn btn-primary">Išsaugoti pakeitimus</button>
                                <a href="<?php echo SITE_URL; ?>/modules/admin/schools.php" class="btn btn-secondary ms-2">Grįžti</a>
                            </div>
                        </form>
                    <?php else: ?>
                        <p class="text-warning">Mokykla su ID „<?php echo htmlspecialchars($school_id); ?>“ nerasta duomenų bazėje.</p>
                        <a href="<?php echo SITE_URL; ?>/modules/admin/schools.php" class="btn btn-secondary">Grįžti į mokyklų sąrašą</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
require_once dirname(dirname(dirname(__FILE__))) . '/includes/footer.php';
?>