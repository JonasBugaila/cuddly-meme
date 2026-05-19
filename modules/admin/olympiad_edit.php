<?php
/**
 * Olimpiados redagavimo forma
 * 
 * Šis failas leidžia administratoriams redaguoti esamų olimpiadų duomenis
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
    set_message('Nenurodytas olimpiados ID. Prašome pasirinkti olimpiadą iš sąrašo.', 'error');
    redirect(SITE_URL . '/modules/admin/olympiads.php');
}

// Gauname olimpiados duomenis
$olympiad_id = sanitize_input($_GET['id']);
$sql = "SELECT konk_id, konkurso_pav, atsakingas, grupe, status, ne_rajono, smsm_patvirtintas, data, vieta, aprasymas FROM konkursai WHERE konk_id = ?";
$stmt = db_query($sql, [$olympiad_id], 'i');
$olympiad = db_get_row($stmt);

// Apdorojame formą
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['olympiad_id'])) {
    $errors = [];
    $olympiad_id = sanitize_input($_POST['olympiad_id']);
    
    // Tikriname CSRF žetoną
    if (!verify_csrf_token($_POST['csrf_token'])) {
        $errors[] = 'Netinkamas CSRF žetonas';
    }
    
    // Tikriname pavadinimą
    $pavadinimas = sanitize_input($_POST['pavadinimas']);
    if (empty($pavadinimas)) {
        $errors[] = 'Prašome įvesti olimpiados pavadinimą';
    }
    
    // Tikriname atsakingą asmenį
    $atsakingas = sanitize_input($_POST['atsakingas']);
    if (empty($atsakingas)) {
        $errors[] = 'Prašome įvesti atsakingą asmenį';
    }
    
    // Tikriname grupę
    $grupe = sanitize_input($_POST['grupe']);
    if (empty($grupe)) {
        $errors[] = 'Prašome įvesti grupę';
    }
    
    // Tikriname statusą
    $status = isset($_POST['status']) ? 0 : 1; // 0 - aktyvus, 1 - neaktyvus
    
    // Tikriname ne_rajono
    $ne_rajono = isset($_POST['ne_rajono']) ? 1 : 0;
    
    // Tikriname smsm_patvirtintas
    $smsm_patvirtintas = isset($_POST['smsm_patvirtintas']) ? 1 : 0;
    
    // Tikriname datą
    $data = sanitize_input($_POST['data']);
    if (empty($data)) {
        $data = null; // Leidžiame NULL, nes stulpelis leidžia NULL
    } elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $data)) {
        $errors[] = 'Neteisingas datos formatas (turi būti YYYY-MM-DD)';
    }
    
    // Tikriname vietą
    $vieta = sanitize_input($_POST['vieta']);
    if (empty($vieta)) {
        $vieta = null; // Leidžiame NULL
    }
    
    // Tikriname aprašymą
    $aprasymas = sanitize_input($_POST['aprasymas']);
    if (empty($aprasymas)) {
        $aprasymas = null; // Leidžiame NULL
    }
    
    // Jei nėra klaidų, atnaujiname olimpiadą
    if (empty($errors)) {
        $data_array = [
            'konkurso_pav' => $pavadinimas,
            'atsakingas' => $atsakingas,
            'grupe' => $grupe,
            'status' => $status,
            'ne_rajono' => $ne_rajono,
            'smsm_patvirtintas' => $smsm_patvirtintas,
            'data' => $data,
            'vieta' => $vieta,
            'aprasymas' => $aprasymas
        ];
        
        $result = db_update('konkursai', $data_array, 'konk_id = ?', [$olympiad_id]);
        
        if ($result) {
            set_message('Olimpiada sėkmingai atnaujinta', 'success');
            redirect(SITE_URL . '/modules/admin/olympiad_edit.php?id=' . $olympiad_id);
        } else {
            $conn = db_connect();
            error_log("Update failed: " . $conn->error . " | ID: " . $olympiad_id . " | SQL: UPDATE konkursai SET " . implode(', ', array_keys($data_array)) . " WHERE konk_id = $olympiad_id");
            set_message('Klaida atnaujinant olimpiadą: ' . $conn->error, 'error');
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
                    <h1>Olimpiados redagavimas</h1>
                </div>
                <div class="card-body">
                    <?php display_message(); ?>
                    <?php if ($olympiad): ?>
                        <form action="<?php echo SITE_URL; ?>/modules/admin/olympiad_edit.php?id=<?php echo htmlspecialchars($olympiad_id); ?>" method="post" class="needs-validation" novalidate>
                            <input type="hidden" name="olympiad_id" value="<?php echo htmlspecialchars($olympiad['konk_id']); ?>">
                            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group mb-3">
                                        <label for="pavadinimas" class="form-label">Pavadinimas *</label>
                                        <input type="text" class="form-control" id="pavadinimas" name="pavadinimas" value="<?php echo htmlspecialchars($olympiad['konkurso_pav']); ?>" required>
                                        <div class="invalid-feedback">
                                            Prašome įvesti olimpiados pavadinimą
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group mb-3">
                                        <label for="atsakingas" class="form-label">Atsakingas asmuo *</label>
                                        <input type="text" class="form-control" id="atsakingas" name="atsakingas" value="<?php echo htmlspecialchars($olympiad['atsakingas']); ?>" required>
                                        <div class="invalid-feedback">
                                            Prašome įvesti atsakingą asmenį
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group mb-3">
                                        <label for="grupe" class="form-label">Grupė *</label>
                                        <input type="text" class="form-control" id="grupe" name="grupe" value="<?php echo htmlspecialchars($olympiad['grupe']); ?>" required>
                                        <div class="invalid-feedback">
                                            Prašome įvesti grupę
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group mb-3">
                                        <label for="data" class="form-label">Data</label>
                                        <input type="date" class="form-control" id="data" name="data" value="<?php echo htmlspecialchars($olympiad['data']); ?>">
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group mb-3">
                                        <label for="vieta" class="form-label">Vieta</label>
                                        <input type="text" class="form-control" id="vieta" name="vieta" value="<?php echo htmlspecialchars($olympiad['vieta']); ?>">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group mb-3">
                                        <label for="status" class="form-label">Statusas</label>
                                        <div class="form-check">
                                            <input type="checkbox" class="form-check-input" id="status" name="status" value="1" <?php echo $olympiad['status'] == 0 ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="status">Aktyvus</label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group mb-3">
                                        <label for="ne_rajono" class="form-label">Ne rajono renginys</label>
                                        <div class="form-check">
                                            <input type="checkbox" class="form-check-input" id="ne_rajono" name="ne_rajono" value="1" <?php echo $olympiad['ne_rajono'] == 1 ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="ne_rajono">Taip</label>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group mb-3">
                                        <label for="smsm_patvirtintas" class="form-label">ŠMSM patvirtintas</label>
                                        <div class="form-check">
                                            <input type="checkbox" class="form-check-input" id="smsm_patvirtintas" name="smsm_patvirtintas" value="1" <?php echo $olympiad['smsm_patvirtintas'] == 1 ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="smsm_patvirtintas">Taip</label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-12">
                                    <div class="form-group mb-3">
                                        <label for="aprasymas" class="form-label">Aprašymas</label>
                                        <textarea class="form-control" id="aprasymas" name="aprasymas" rows="5"><?php echo htmlspecialchars($olympiad['aprasymas']); ?></textarea>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <button type="submit" class="btn btn-primary">Išsaugoti pakeitimus</button>
                                <a href="<?php echo SITE_URL; ?>/modules/admin/olympiads.php" class="btn btn-secondary ms-2">Grįžti</a>
                            </div>
                        </form>
                    <?php else: ?>
                        <p class="text-warning">Olimpiada su ID „<?php echo htmlspecialchars($olympiad_id); ?>“ nerasta duomenų bazėje.</p>
                        <a href="<?php echo SITE_URL; ?>/modules/admin/olympiads.php" class="btn btn-secondary">Grįžti į olimpiadų sąrašą</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
require_once dirname(dirname(dirname(__FILE__))) . '/includes/footer.php';
?>