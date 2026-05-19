<?php
/**
 * Dalyvių registracijos puslapis
 * 
 * Šis failas leidžia registruoti dalyvius į pasirinktą olimpiadą
 */

// Įtraukiame konfigūracijos failus
require_once dirname(dirname(dirname(__FILE__))) . '/config/config.php';
require_once dirname(dirname(dirname(__FILE__))) . '/config/db_connect.php';
require_once dirname(dirname(dirname(__FILE__))) . '/config/functions.php';

// Tikriname ar vartotojas prisijungęs
if (!is_logged_in()) {
    set_message('Turite prisijungti, kad galėtumėte pasiekti šį puslapį', 'error');
    redirect(SITE_URL . '/modules/auth/login.php');
}

// Tikriname ar nurodytas olimpiados ID
if (!isset($_GET['olympiad_id']) || empty($_GET['olympiad_id'])) {
    set_message('Nenurodyta olimpiada', 'error');
    redirect(SITE_URL . '/modules/registration/index.php');
}

$olympiad_id = sanitize_input($_GET['olympiad_id']);

// Gauname olimpiados informaciją
$sql = "SELECT * FROM konkursai WHERE konk_id = ?";
$stmt = db_query($sql, [$olympiad_id]);
$olympiad = db_get_row($stmt);

if (!$olympiad) {
    set_message('Olimpiada nerasta', 'error');
    redirect(SITE_URL . '/modules/registration/index.php');
}

// Tikriname ar olimpiada aktyvi
if ($olympiad['status'] != 0) {
    set_message('Registracija į šią olimpiadą uždaryta', 'error');
    redirect(SITE_URL . '/modules/registration/index.php');
}

// Gauname vartotojo priskirtą mokyklą (tik informacijai)
$sql = "SELECT var_mokykla FROM vartotojas WHERE vart_id = ?";
$stmt = db_query($sql, [$_SESSION['user_id']]);
$user = db_get_row($stmt);
$school = $user['var_mokykla'] ?? '';

// Patikriname, ar $school egzistuoja mokyklų sąraše
$defaultSchoolSet = false;
if (empty($school)) {
    error_log('Vartotojo mokykla nerasta arba tuščia.');
}

// Gauname mokyklų sąrašą
$sql = "SELECT pavadinimas FROM mokyklos ORDER BY pavadinimas ASC";
$stmt = db_query($sql);
$mokyklos = db_get_results($stmt);

// Diagnostika mokyklų gavimui
if (!$mokyklos) {
    error_log('Nepavyko gauti mokyklų sąrašo: ' . (db_connect()->error ?: 'Tuščias rezultatas'));
} else {
    error_log('Gauta mokyklų: ' . count($mokyklos));
}

// Gauname klases
$sql = "SELECT * FROM klases ORDER BY klases ASC";
$stmt = db_query($sql);
$classes = db_get_results($stmt);

// Gauname mokytojų kvalifikacijas
$sql = "SELECT * FROM kvalifikacijos ORDER BY kategorija ASC";
$stmt = db_query($sql);
$qualifications = db_get_results($stmt);

// Apdorojame formą
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Diagnostika
    error_log('POST data at ' . date('Y-m-d H:i:s') . ': ' . print_r($_POST, true));
    var_dump($_POST);

    // Tikriname ar užpildyti visi laukai
    if (empty($_POST['_vardas']) || empty($_POST['_pavarde']) || empty($_POST['_klase']) || empty($_POST['_mok']) || empty($_POST['_mok_kvali']) || empty($_POST['_mokykla'])) {
        set_message('Prašome užpildyti visus privalomus laukus. Debug: vardas=' . ($_POST['_vardas'] ?? 'tuščias') . ', pavarde=' . ($_POST['_pavarde'] ?? 'tuščias') . ', klase=' . ($_POST['_klase'] ?? 'tuščias') . ', mok=' . ($_POST['_mok'] ?? 'tuščias') . ', mok_kvali=' . ($_POST['_mok_kvali'] ?? 'tuščias') . ', mokykla=' . ($_POST['_mokykla'] ?? 'tuščias'), 'error');
    } else {
        // Paruošiame duomenis
        $data = [
            'konkurso_pav' => $olympiad['konkurso_pav'],
            'var_mokykla' => sanitize_input($_POST['_mokykla']),
            'pil_data' => date('Y-m-d H:i:s'),
            '1_vardas' => sanitize_input($_POST['_vardas']),
            '1_pavarde' => sanitize_input($_POST['_pavarde']),
            '1_klase' => sanitize_input($_POST['_klase']),
            '1_mok' => sanitize_input($_POST['_mok']),
            '1_mok_kvali' => sanitize_input($_POST['_mok_kvali']),
            '2_mok' => sanitize_input($_POST['2_mok']),
            '2_mok_kvali' => sanitize_input($_POST['2_mok_kvali']),
            'vart_id' => $_SESSION['user_id'],
            'inf_1' => isset($_POST['inf_1']) ? sanitize_input($_POST['inf_1']) : '',
            'inf_2' => '',
            'Balai' => '',
            'Vieta' => ''
        ];
        
        // Papildoma diagnostika
        error_log('Data to insert at ' . date('Y-m-d H:i:s') . ': ' . print_r($data, true));
        
        // Įterpiame duomenis
        $result = db_insert('dalyviai', $data);
        
        if ($result) {
            set_message('Dalyvis sėkmingai užregistruotas', 'success');
            redirect(SITE_URL . '/modules/olympiads/view.php?id=' . $olympiad_id);
        } else {
            set_message('Klaida registruojant dalyvį: ' . db_connect()->error, 'error');
        }
    }
}

// Įtraukiame antraštę
require_once dirname(dirname(dirname(__FILE__))) . '/includes/header.php';
?>

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h1>Dalyvio registracija</h1>
                <a href="<?php echo SITE_URL; ?>/modules/olympiads/view.php?id=<?php echo $olympiad_id; ?>" class="btn btn-secondary">Grįžti į olimpiadą</a>
            </div>
            <div class="card-body">
                <div class="row mb-4">
                    <div class="col-md-6">
                        <h3>Olimpiados informacija</h3>
                        <table class="table">
                            <tr>
                                <th>Pavadinimas:</th>
                                <td><?php echo $olympiad['konkurso_pav']; ?></td>
                            </tr>
                            <tr>
                                <th>Atsakingas:</th>
                                <td><?php echo $olympiad['atsakingas']; ?></td>
                            </tr>
                            <tr>
                                <th>Grupė:</th>
                                <td><?php echo $olympiad['grupe']; ?></td>
                            </tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <h3>Mokyklos informacija</h3>
                        <table class="table">
                            <tr>
                                <th>Mokykla *</th>
                                <td>
                                    <!-- Laikinas rodymas, kol perkelsime į formą -->
                                    <?php echo $school; ?>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>
                
                <h3>Dalyvio informacija</h3>
                <form action="<?php echo SITE_URL; ?>/modules/registration/register.php?olympiad_id=<?php echo $olympiad_id; ?>" method="post" class="needs-validation" novalidate>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group mb-3">
                                <label for="_vardas" class="form-label">Vardas *</label>
                                <input type="text" class="form-control" id="_vardas" name="_vardas" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group mb-3">
                                <label for="_pavarde" class="form-label">Pavardė *</label>
                                <input type="text" class="form-control" id="_pavarde" name="_pavarde" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group mb-3">
                                <label for="_klase" class="form-label">Klasė *</label>
                                <select class="form-control" id="_klase" name="_klase" required>
                                    <?php foreach ($classes as $class): ?>
                                        <option value="<?php echo $class['klases']; ?>"><?php echo $class['klases']; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group mb-3">
                                <label for="_mokykla" class="form-label">Mokykla *</label>
                                <select class="form-control" id="_mokykla" name="_mokykla" required>
                                    <?php foreach ($mokyklos as $mokykla): ?>
                                        <option value="<?php echo htmlspecialchars($mokykla['pavadinimas']); ?>" 
                                                <?php echo (!empty($school) && $school === $mokykla['pavadinimas']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($mokykla['pavadinimas']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group mb-3">
                                <label for="_mok" class="form-label">Mokytojo vardas, pavardė *</label>
                                <input type="text" class="form-control" id="_mok" name="_mok" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group mb-3">
                                <label for="_mok_kvali" class="form-label">Mokytojo kvalifikacija *</label>
                                <select class="form-control" id="_mok_kvali" name="_mok_kvali" required>
                                    <?php foreach ($qualifications as $qualification): ?>
                                        <option value="<?php echo $qualification['kategorija']; ?>"><?php echo $qualification['kategorija']; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group mb-3">
                                <label for="_mok" class="form-label">Antro mokytojo vardas, pavardė</label>
                                <input type="text" class="form-control" id="2_mok" name="2_mok" >
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group mb-3">
                                <label for="_mok_kvali" class="form-label">Antro mokytojo kvalifikacija</label>
                                <select class="form-control" id="2_mok_kvali" name="2_mok_kvali" >
                                    <?php foreach ($qualifications as $qualification): ?>
                                        <option value="<?php echo $qualification['kategorija']; ?>"><?php echo $qualification['kategorija']; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group mb-3">
                        <label for="inf_1" class="form-label">Papildoma informacija</label>
                        <textarea class="form-control" id="inf_1" name="inf_1" rows="3"></textarea>
                    </div>
                    
                    <div class="form-group">
                        <button type="submit" class="btn btn-primary">Registruoti dalyvį</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php
// Įtraukiame poraštę
require_once dirname(dirname(dirname(__FILE__))) . '/includes/footer.php';
?>