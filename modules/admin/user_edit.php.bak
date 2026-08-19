<?php
/**
 * Vartotojo redagavimo forma
 * 
 * Šis failas leidžia administratoriams redaguoti esamų vartotojų duomenis
 */

require_once dirname(dirname(dirname(__FILE__))) . '/config/config.php';
require_once dirname(dirname(dirname(__FILE__))) . '/config/db_connect.php';
require_once dirname(dirname(dirname(__FILE__))) . '/config/functions.php';

// Rodome visas klaidas
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Gauname mokyklų sąrašą
$sql = "SELECT mokyklos_id, pavadinimas FROM mokyklos ORDER BY pavadinimas ASC";
$stmt = db_query($sql);
$schools = db_get_results($stmt);

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
    set_message('Nenurodytas vartotojo ID. Prašome pasirinkti vartotoją iš sąrašo.', 'error');
    redirect(SITE_URL . '/modules/admin/users.php');
}

// Gauname vartotojo duomenis
$user_id = sanitize_input($_GET['id']);
$sql = "SELECT vart_id, var_vardas, var_pavarde, var_mokykla, vart_lygis, el_pastas FROM vartotojas WHERE vart_id = ?";
$stmt = db_query($sql, [$user_id], 's');
$user = db_get_row($stmt);

// Apdorojame formą
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['user_id'])) {
    $errors = [];
    $user_id = sanitize_input($_POST['user_id']);
    
    // Tikriname CSRF žetoną
    if (!verify_csrf_token($_POST['csrf_token'])) {
        $errors[] = 'Netinkamas CSRF žetonas';
    }
    
    // Tikriname vardą
    $vardas = sanitize_input($_POST['vardas']);
    if (empty($vardas)) {
        $errors[] = 'Prašome įvesti vardą';
    }
    
    // Tikriname pavardę
    $pavarde = sanitize_input($_POST['pavarde']);
    if (empty($pavarde)) {
        $errors[] = 'Prašome įvesti pavardę';
    }
    
    // Tikriname mokyklą
    $mokykla = sanitize_input($_POST['mokykla']);
    if (empty($mokykla)) {
        $errors[] = 'Prašome pasirinkti mokyklą';
    }
    
    // Tikriname rolę
    $tipas = sanitize_input($_POST['tipas']);
    if (!in_array($tipas, ['user', 'admin'])) {
        $errors[] = 'Neteisinga rolė';
    }
    
    // Tikriname slaptažodį (nebūtinas, jei nekeičiamas)
    $slaptazodis = $_POST['slaptazodis'] ?? '';
    if (!empty($slaptazodis) && strlen($slaptazodis) < 6) {
        $errors[] = 'Slaptažodis turi būti bent 6 simbolių ilgio';
    }
    
    // Jei nėra klaidų, atnaujiname vartotoją
    if (empty($errors)) {
        $data = [
            'var_vardas' => $vardas,
            'var_pavarde' => $pavarde,
            'var_mokykla' => $mokykla, // Naudojame mokyklos_id
            'vart_lygis' => $tipas
        ];
        
        if (!empty($slaptazodis)) {
            $data['var_slapt'] = hash_password($slaptazodis); // Naudojame jūsų nešifruotą hash_password
        }
        
        $result = db_update('vartotojas', $data, 'vart_id = ?', [$user_id]);
        
        if ($result) {
            set_message('Vartotojas sėkmingai atnaujintas', 'success');
            redirect(SITE_URL . '/modules/admin/user_edit.php?id=' . $user_id);
        } else {
            global $conn;
            error_log("Update failed: " . $conn->error);
            set_message('Klaida atnaujinant vartotoją: ' . $conn->error, 'error');
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
                    <h1>Vartotojo redagavimas</h1>
                </div>
                <div class="card-body">
                    <?php display_message(); ?>
                    <?php if ($user): ?>
                        <form action="<?php echo SITE_URL; ?>/modules/admin/user_edit.php?id=<?php echo htmlspecialchars($user_id); ?>" method="post" class="needs-validation" novalidate>
                            <input type="hidden" name="user_id" value="<?php echo htmlspecialchars($user['vart_id']); ?>">
                            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group mb-3">
                                        <label for="vardas" class="form-label">Vardas *</label>
                                        <input type="text" class="form-control" id="vardas" name="vardas" value="<?php echo htmlspecialchars($user['var_vardas']); ?>" required>
                                        <div class="invalid-feedback">
                                            Prašome įvesti vardą
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group mb-3">
                                        <label for="pavarde" class="form-label">Pavardė *</label>
                                        <input type="text" class="form-control" id="pavarde" name="pavarde" value="<?php echo htmlspecialchars($user['var_pavarde']); ?>" required>
                                        <div class="invalid-feedback">
                                            Prašome įvesti pavardę
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group mb-3">
                                        <label for="mokykla" class="form-label">Mokykla *</label>
                                        <select class="form-control" id="mokykla" name="mokykla" required>
                                            <option value="">Pasirinkite mokyklą</option>
                                            <?php foreach ($schools as $school): ?>
                                                <option value="<?php echo htmlspecialchars($school['mokyklos_id']); ?>" <?php echo $user['var_mokykla'] == $school['mokyklos_id'] ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($school['pavadinimas']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <div class="invalid-feedback">
                                            Prašome pasirinkti mokyklą
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group mb-3">
                                        <label for="tipas" class="form-label">Rolė *</label>
                                        <select class="form-control" id="tipas" name="tipas" required>
                                            <option value="user" <?php echo $user['vart_lygis'] == 'user' ? 'selected' : ''; ?>>Vartotojas</option>
                                            <option value="admin" <?php echo $user['vart_lygis'] == 'admin' ? 'selected' : ''; ?>>Administratorius</option>
                                        </select>
                                        <div class="invalid-feedback">
                                            Prašome pasirinkti rolę
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group mb-3">
                                        <label for="slaptazodis" class="form-label">Naujas slaptažodis (palikite tuščią, jei nekeičiate)</label>
                                        <input type="password" class="form-control" id="slaptazodis" name="slaptazodis">
                                        <div class="invalid-feedback">
                                            Slaptažodis turi būti bent 6 simbolių ilgio
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <button type="submit" class="btn btn-primary">Išsaugoti pakeitimus</button>
                                <a href="<?php echo SITE_URL; ?>/modules/admin/users.php" class="btn btn-secondary ms-2">Grįžti</a>
                            </div>
                        </form>
                    <?php else: ?>
                        <p class="text-warning">Vartotojas su ID „<?php echo htmlspecialchars($user_id); ?>“ nerastas duomenų bazėje.</p>
                        <a href="<?php echo SITE_URL; ?>/modules/admin/users.php" class="btn btn-secondary">Grįžti į vartotojų sąrašą</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
require_once dirname(dirname(dirname(__FILE__))) . '/includes/footer.php';
?>