<?php
/**
 * Dalyvių registracijos puslapis
 * * Šis failas leidžia registruoti dalyvius į pasirinktą olimpiadą
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
$stmt = db_query($sql, [$olympiad_id], 'i');
$olympiad = $stmt ? db_get_row($stmt) : null;

if (!$olympiad) {
    set_message('Olimpiada nerasta', 'error');
    redirect(SITE_URL . '/modules/registration/index.php');
}

// Tikriname ar olimpiada aktyvi
if ($olympiad['status'] != 0) {
    set_message('Registracija į šią olimpiadą uždaryta', 'error');
    redirect(SITE_URL . '/modules/registration/index.php');
}

// Gauname vartotojo priskirtą mokyklą
$sql = "SELECT var_mokykla FROM vartotojas WHERE vart_id = ?";
$stmt = db_query($sql, [$_SESSION['user_id']], 's');
$user = $stmt ? db_get_row($stmt) : null;
$school = $user['var_mokykla'] ?? '';

// Gauname mokyklų sąrašą
$sql = "SELECT pavadinimas FROM mokyklos ORDER BY pavadinimas ASC";
$stmt = db_query($sql);
$mokyklos = $stmt ? db_get_results($stmt) : [];

// Gauname klases
$sql = "SELECT * FROM klases ORDER BY klases ASC";
$stmt = db_query($sql);
$classes = $stmt ? db_get_results($stmt) : [];

// Gauname mokytojų kvalifikacijas
$sql = "SELECT * FROM kvalifikacijos ORDER BY kategorija ASC";
$stmt = db_query($sql);
$qualifications = $stmt ? db_get_results($stmt) : [];

// Apdorojame formą
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Saugumo patikra: CSRF žetonas
    if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
        set_message('Netinkamas sesijos žetonas (CSRF).', 'error');
        redirect(current_url());
    }

    // Tikriname ar užpildyti visi privalomi laukai
    if (empty($_POST['_vardas']) || empty($_POST['_pavarde']) || empty($_POST['_klase']) || empty($_POST['_mok']) || empty($_POST['_mok_kvali']) || empty($_POST['_mokykla'])) {
        set_message('Prašome užpildyti visus privalomus laukus.', 'error');
    } else {
        // Paruošiame duomenis
        $data = [
            'konkurso_pav' => $olympiad['konkurso_pav'],
            'var_mokykla'  => sanitize_input($_POST['_mokykla']),
            'pil_data'     => date('Y-m-d H:i:s'),
            '1_vardas'     => sanitize_input($_POST['_vardas']),
            '1_pavarde'    => sanitize_input($_POST['_pavarde']),
            '1_klase'      => sanitize_input($_POST['_klase']),
            '1_mok'        => sanitize_input($_POST['_mok']),
            '1_mok_kvali'  => sanitize_input($_POST['_mok_kvali']),
            '2_mok'        => !empty($_POST['2_mok']) ? sanitize_input($_POST['2_mok']) : '',
            '2_mok_kvali'  => !empty($_POST['2_mok_kvali']) ? sanitize_input($_POST['2_mok_kvali']) : '',
            'vart_id'      => $_SESSION['user_id'],
            'inf_1'        => isset($_POST['inf_1']) ? sanitize_input($_POST['inf_1']) : '',
            'inf_2'        => '',
            'Balai'        => '',
            'Vieta'        => ''
        ];
        
        // Įterpiame duomenis naudojant saugią sisteminę db_insert funkciją
        $result = db_insert('dalyviai', $data);
        
        if ($result) {
            set_message('Dalyvis sėkmingai užregistruotas', 'success');
            redirect(SITE_URL . '/modules/olympiads/view.php?id=' . $olympiad_id);
        } else {
            set_message('Sisteminė klaida registruojant dalyvį.', 'error');
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
                <h1 class="h3 mb-0">Dalyvio registracija</h1>
                <a href="<?php echo SITE_URL; ?>/modules/olympiads/view.php?id=<?php echo $olympiad_id; ?>" class="btn btn-secondary btn-sm">Grįžti į olimpiadą</a>
            </div>
            <div class="card-body">
                <?php display_message(); ?>
                
                <div class="row mb-4">
                    <div class="col-md-6">
                        <h5>Olimpiados informacija</h5>
                        <table class="table table-bordered table-sm">
                            <tr>
                                <th>Pavadinimas:</th>
                                <td><?php echo htmlspecialchars($olympiad['konkurso_pav']); ?></td>
                            </tr>
                            <tr>
                                <th>Atsakingas:</th>
                                <td><?php echo htmlspecialchars($olympiad['atsakingas']); ?></td>
                            </tr>
                            <tr>
                                <th>Grupė:</th>
                                <td><?php echo htmlspecialchars($olympiad['grupe']); ?></td>
                            </tr>
                        </table>
                    </div>
                </div>
                
                <h4 class="mb-3">Dalyvio informacija</h4>
                <form action="<?php echo SITE_URL; ?>/modules/registration/register.php?olympiad_id=<?php echo $olympiad_id; ?>" method="post" class="needs-validation" novalidate>
                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="_vardas" class="form-label">Vardas *</label>
                            <input type="text" class="form-control" id="_vardas" name="_vardas" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="_pavarde" class="form-label">Pavardė *</label>
                            <input type="text" class="form-control" id="_pavarde" name="_pavarde" required>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="_klase" class="form-label">Klasė *</label>
                            <select class="form-select" id="_klase" name="_klase" required>
                                <option value="">Pasirinkite klasę...</option>
                                <?php foreach ($classes as $class): ?>
                                    <option value="<?php echo htmlspecialchars($class['klases']); ?>"><?php echo htmlspecialchars($class['klases']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="_mokykla" class="form-label">Mokykla *</label>
                            <select class="form-select" id="_mokykla" name="_mokykla" required>
                                <option value="">Pasirinkite mokyklą...</option>
                                <?php foreach ($mokyklos as $m): ?>
                                    <option value="<?php echo htmlspecialchars($m['pavadinimas']); ?>" 
                                            <?php echo (!empty($school) && $school === $m['pavadinimas']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($m['pavadinimas']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="_mok" class="form-label">Mokytojo vardas, pavardė *</label>
                            <input type="text" class="form-control" id="_mok" name="_mok" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="_mok_kvali" class="form-label">Mokytojo kvalifikacija *</label>
                            <select class="form-select" id="_mok_kvali" name="_mok_kvali" required>
                                <option value="">Pasirinkite kvalifikaciją...</option>
                                <?php foreach ($qualifications as $q): ?>
                                    <option value="<?php echo htmlspecialchars($q['kategorija']); ?>"><?php echo htmlspecialchars($q['kategorija']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="row border-top pt-3 mt-2">
                        <div class="col-md-6 mb-3">
                            <label for="2_mok" class="form-label">Antro mokytojo vardas, pavardė (neprivaloma)</label>
                            <input type="text" class="form-control" id="2_mok" name="2_mok">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="2_mok_kvali" class="form-label">Antro mokytojo kvalifikacija (neprivaloma)</label>
                            <select class="form-select" id="2_mok_kvali" name="2_mok_kvali">
                                <option value="">Nėra</option>
                                <?php foreach ($qualifications as $q): ?>
                                    <option value="<?php echo htmlspecialchars($q['kategorija']); ?>"><?php echo htmlspecialchars($q['kategorija']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-group mb-4">
                        <label for="inf_1" class="form-label">Papildoma informacija</label>
                        <textarea class="form-control" id="inf_1" name="inf_1" rows="3"></textarea>
                    </div>
                    
                    <button type="submit" class="btn btn-primary btn-lg">Registruoti dalyvį</button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once dirname(dirname(dirname(__FILE__))) . '/includes/footer.php'; ?>