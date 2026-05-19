<?php
/**
 * Dalyvio redagavimo forma
 * 
 * Šis failas leidžia administratoriams redaguoti esamų dalyvių duomenis
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
    set_message('Nenurodytas dalyvio ID. Prašome pasirinkti dalyvį iš sąrašo.', 'error');
    redirect(SITE_URL . '/modules/admin/participants.php');
}

// Gauname dalyvio duomenis
$participant_id = sanitize_input($_GET['id']);
$sql = "SELECT reg_id, konkurso_pav, var_mokykla, pil_data, 1_vardas, 1_pavarde, 1_klase, 1_mok, 1_mok_kvali, 2_mok, 2_mok_kvali, vart_id, inf_1, Balai, Vieta, status FROM dalyviai WHERE reg_id = ?";
$stmt = db_query($sql, [$participant_id], 'i');
$participant = db_get_row($stmt);

// Gauname mokyklų sąrašą
$sql = "SELECT pavadinimas FROM mokyklos ORDER BY pavadinimas ASC";
$stmt = db_query($sql);
$mokyklos = db_get_results($stmt);

// Gauname klases
$sql = "SELECT klases FROM klases ORDER BY klases ASC";
$stmt = db_query($sql);
$classes = db_get_results($stmt);

// Gauname kvalifikacijas
$sql = "SELECT kategorija FROM kvalifikacijos ORDER BY kategorija ASC";
$stmt = db_query($sql);
$qualifications = db_get_results($stmt);

// Apdorojame formą
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reg_id'])) {
    $errors = [];
    $reg_id = sanitize_input($_POST['reg_id']);
    
    // Tikriname CSRF žetoną
    if (!verify_csrf_token($_POST['csrf_token'])) {
        $errors[] = 'Netinkamas CSRF žetonas';
    }
    
    // Tikriname privalomus laukus
    $konkurso_pav = sanitize_input($_POST['konkurso_pav']);
    if (empty($konkurso_pav)) {
        $errors[] = 'Prašome įvesti konkurso pavadinimą';
    }
    
    $var_mokykla = sanitize_input($_POST['var_mokykla']);
    if (empty($var_mokykla)) {
        $errors[] = 'Prašome pasirinkti mokyklą';
    }
    
    $vardas = sanitize_input($_POST['1_vardas']);
    if (empty($vardas)) {
        $errors[] = 'Prašome įvesti dalyvio vardą';
    }
    
    $pavarde = sanitize_input($_POST['1_pavarde']);
    if (empty($pavarde)) {
        $errors[] = 'Prašome įvesti dalyvio pavardę';
    }
    
    $klase = sanitize_input($_POST['1_klase']);
    if (empty($klase)) {
        $errors[] = 'Prašome pasirinkti klasę';
    }
    
    $mokytojas1 = sanitize_input($_POST['1_mok']);
    if (empty($mokytojas1)) {
        $errors[] = 'Prašome įvesti pirmojo mokytojo vardą ir pavardę';
    }
    
    $mok_kvali1 = sanitize_input($_POST['1_mok_kvali']);
    if (empty($mok_kvali1)) {
        $errors[] = 'Prašome pasirinkti pirmojo mokytojo kvalifikaciją';
    }
    
    $mokytojas2 = sanitize_input($_POST['2_mok']);
    if (empty($mokytojas2)) {
        $mokytojas2 = null;
    }
    
    $mok_kvali2 = sanitize_input($_POST['2_mok_kvali']);
    if (empty($mok_kvali2)) {
        $mok_kvali2 = null;
    }
    
    $vart_id = sanitize_input($_POST['vart_id']);
    if (empty($vart_id)) {
        $errors[] = 'Prašome nurodyti vartotojo ID';
    }
    
    $inf_1 = sanitize_input($_POST['inf_1']);
    if (empty($inf_1)) {
        $inf_1 = null;
    }
    
    $balai = sanitize_input($_POST['Balai']);
    if (empty($balai)) {
        $balai = null;
    }
    
    $vieta = sanitize_input($_POST['Vieta']);
    if (empty($vieta)) {
        $vieta = null;
    }
    
    $status = isset($_POST['status']) ? (int)$_POST['status'] : 1;
    
    // Jei nėra klaidų, atnaujiname dalyvį
    if (empty($errors)) {
        $data_array = [
            'konkurso_pav' => $konkurso_pav,
            'var_mokykla' => $var_mokykla,
            'pil_data' => date('Y-m-d H:i:s'),
            '1_vardas' => $vardas,
            '1_pavarde' => $pavarde,
            '1_klase' => $klase,
            '1_mok' => $mokytojas1,
            '1_mok_kvali' => $mok_kvali1,
            '2_mok' => $mokytojas2,
            '2_mok_kvali' => $mok_kvali2,
            'vart_id' => $vart_id,
            'inf_1' => $inf_1,
            'Balai' => $balai,
            'Vieta' => $vieta,
            'status' => $status
        ];
        
        $conn = db_connect();
        $conn->begin_transaction();

        try {
            // Atnaujiname dalyvių lentelę
            $result = db_update('dalyviai', $data_array, 'reg_id = ?', [$reg_id]);
            if (!$result) {
                throw new Exception("Nepavyko atnaujinti dalyvio lentelės: " . $conn->error);
            }

            $conn->commit();
            set_message('Dalyvis sėkmingai atnaujintas', 'success');
            redirect(SITE_URL . '/modules/admin/participant_edit.php?id=' . $reg_id);
        } catch (Exception $e) {
            $conn->rollback();
            error_log("Update failed: " . $e->getMessage() . " | ID: " . $reg_id);
            set_message('Klaida atnaujinant dalyvį: ' . $e->getMessage(), 'error');
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
                    <h1>Dalyvio redagavimas</h1>
                </div>
                <div class="card-body">
                    <?php display_message(); ?>
                    <?php if ($participant): ?>
                        <form action="<?php echo SITE_URL; ?>/modules/admin/participant_edit.php?id=<?php echo htmlspecialchars($participant_id); ?>" method="post" class="needs-validation" novalidate>
                            <input type="hidden" name="reg_id" value="<?php echo htmlspecialchars($participant['reg_id']); ?>">
                            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group mb-3">
                                        <label for="konkurso_pav" class="form-label">Konkurso pavadinimas *</label>
                                        <input type="text" class="form-control" id="konkurso_pav" name="konkurso_pav" value="<?php echo htmlspecialchars($participant['konkurso_pav']); ?>" required>
                                        <div class="invalid-feedback">
                                            Prašome įvesti konkurso pavadinimą
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group mb-3">
                                        <label for="var_mokykla" class="form-label">Mokykla *</label>
                                        <select class="form-control" id="var_mokykla" name="var_mokykla" required>
                                            <?php foreach ($mokyklos as $mokykla): ?>
                                                <option value="<?php echo htmlspecialchars($mokykla['pavadinimas']); ?>" 
                                                        <?php echo ($participant['var_mokykla'] === $mokykla['pavadinimas']) ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($mokykla['pavadinimas']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <div class="invalid-feedback">
                                            Prašome pasirinkti mokyklą
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group mb-3">
                                        <label for="1_vardas" class="form-label">Vardas *</label>
                                        <input type="text" class="form-control" id="1_vardas" name="1_vardas" value="<?php echo htmlspecialchars($participant['1_vardas']); ?>" required>
                                        <div class="invalid-feedback">
                                            Prašome įvesti dalyvio vardą
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group mb-3">
                                        <label for="1_pavarde" class="form-label">Pavardė *</label>
                                        <input type="text" class="form-control" id="1_pavarde" name="1_pavarde" value="<?php echo htmlspecialchars($participant['1_pavarde']); ?>" required>
                                        <div class="invalid-feedback">
                                            Prašome įvesti dalyvio pavardę
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group mb-3">
                                        <label for="1_klase" class="form-label">Klasė *</label>
                                        <select class="form-control" id="1_klase" name="1_klase" required>
                                            <?php foreach ($classes as $class): ?>
                                                <option value="<?php echo htmlspecialchars($class['klases']); ?>" 
                                                        <?php echo ($participant['1_klase'] === $class['klases']) ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($class['klases']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <div class="invalid-feedback">
                                            Prašome pasirinkti klasę
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group mb-3">
                                        <label for="vart_id" class="form-label">Vartotojo ID *</label>
                                        <input type="text" class="form-control" id="vart_id" name="vart_id" value="<?php echo htmlspecialchars($participant['vart_id']); ?>" required>
                                        <div class="invalid-feedback">
                                            Prašome nurodyti vartotojo ID
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group mb-3">
                                        <label for="1_mok" class="form-label">Pirmojo mokytojo vardas, pavardė *</label>
                                        <input type="text" class="form-control" id="1_mok" name="1_mok" value="<?php echo htmlspecialchars($participant['1_mok']); ?>" required>
                                        <div class="invalid-feedback">
                                            Prašome įvesti pirmojo mokytojo vardą ir pavardę
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group mb-3">
                                        <label for="1_mok_kvali" class="form-label">Pirmojo mokytojo kvalifikacija *</label>
                                        <select class="form-control" id="1_mok_kvali" name="1_mok_kvali" required>
                                            <?php foreach ($qualifications as $qualification): ?>
                                                <option value="<?php echo htmlspecialchars($qualification['kategorija']); ?>" 
                                                        <?php echo ($participant['1_mok_kvali'] === $qualification['kategorija']) ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($qualification['kategorija']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <div class="invalid-feedback">
                                            Prašome pasirinkti pirmojo mokytojo kvalifikaciją
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group mb-3">
                                        <label for="2_mok" class="form-label">Antro mokytojo vardas, pavardė</label>
                                        <input type="text" class="form-control" id="2_mok" name="2_mok" value="<?php echo htmlspecialchars($participant['2_mok'] ?? ''); ?>">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group mb-3">
                                        <label for="2_mok_kvali" class="form-label">Antro mokytojo kvalifikacija</label>
                                        <select class="form-control" id="2_mok_kvali" name="2_mok_kvali">
                                            <option value="">-- Pasirinkite --</option>
                                            <?php foreach ($qualifications as $qualification): ?>
                                                <option value="<?php echo htmlspecialchars($qualification['kategorija']); ?>" 
                                                        <?php echo ($participant['2_mok_kvali'] === $qualification['kategorija']) ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($qualification['kategorija']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group mb-3">
                                        <label for="inf_1" class="form-label">Papildoma informacija</label>
                                        <textarea class="form-control" id="inf_1" name="inf_1" rows="3"><?php echo htmlspecialchars($participant['inf_1'] ?? ''); ?></textarea>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group mb-3">
                                        <label for="Balai" class="form-label">Balai</label>
                                        <input type="text" class="form-control" id="Balai" name="Balai" value="<?php echo htmlspecialchars($participant['Balai'] ?? ''); ?>">
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group mb-3">
                                        <label for="Vieta" class="form-label">Vieta</label>
                                        <input type="text" class="form-control" id="Vieta" name="Vieta" value="<?php echo htmlspecialchars($participant['Vieta'] ?? ''); ?>">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group mb-3">
                                        <label for="status" class="form-label">Statusas</label>
                                        <select class="form-control" id="status" name="status">
                                            <option value="1" <?php echo ($participant['status'] == 1) ? 'selected' : ''; ?>>Aktyvus</option>
                                            <option value="0" <?php echo ($participant['status'] == 0) ? 'selected' : ''; ?>>Neaktyvus</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <button type="submit" class="btn btn-primary">Išsaugoti pakeitimus</button>
                                <a href="<?php echo SITE_URL; ?>/modules/admin/participants.php" class="btn btn-secondary ms-2">Grįžti</a>
                            </div>
                        </form>
                    <?php else: ?>
                        <p class="text-warning">Dalyvis su ID „<?php echo htmlspecialchars($participant_id); ?>“ nerastas duomenų bazėje.</p>
                        <a href="<?php echo SITE_URL; ?>/modules/admin/participants.php" class="btn btn-secondary">Grįžti į dalyvių sąrašą</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
require_once dirname(dirname(dirname(__FILE__))) . '/includes/footer.php';
?>