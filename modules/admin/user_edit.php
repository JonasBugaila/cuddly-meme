<?php
/**
 * Vartotojo redagavimo forma
 */

require_once dirname(dirname(dirname(__FILE__))) . '/config/config.php';
require_once dirname(dirname(dirname(__FILE__))) . '/config/db_connect.php';
require_once dirname(dirname(dirname(__FILE__))) . '/config/functions.php';

$sql = "SELECT mokyklos_id, pavadinimas FROM mokyklos ORDER BY pavadinimas ASC";
$stmt = db_query($sql);
$schools = db_get_results($stmt);

if (!is_logged_in()) {
    set_message('Turite prisijungti, kad galėtumėte pasiekti šį puslapį', 'error');
    redirect(SITE_URL . '/modules/auth/login.php');
    exit;
} elseif (!is_admin()) {
    set_message('Neturite teisių pasiekti šį puslapį', 'error');
    redirect(SITE_URL);
    exit;
}

if (!isset($_GET['id']) || empty($_GET['id'])) {
    set_message('Nenurodytas vartotojo ID. Prašome pasirinkti vartotoją iš sąrašo.', 'error');
    redirect(SITE_URL . '/modules/admin/users.php');
    exit;
}

$user_id = sanitize_input($_GET['id']);
$sql = "SELECT vart_id, var_vardas, var_pavarde, var_mokykla, vart_lygis, el_pastas FROM vartotojas WHERE vart_id = ?";
$stmt = db_query($sql, [$user_id], 's');
$user = db_get_row($stmt);

if (!$user) {
    set_message('Vartotojas nerastas.', 'error');
    redirect(SITE_URL . '/modules/admin/users.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['user_id'])) {
    $errors = [];
    $post_user_id = sanitize_input($_POST['user_id']);
    
    if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
        set_message('Netinkamas CSRF žetonas. Saugumo sumetimais bandykite dar kartą.', 'error');
        redirect(current_url());
        exit;
    }
    
    $vardas = sanitize_input($_POST['vardas']);
    if (empty($vardas)) $errors[] = 'Prašome įvesti vardą';
    
    $pavarde = sanitize_input($_POST['pavarde']);
    if (empty($pavarde)) $errors[] = 'Prašome įvesti pavardę';
    
    $el_pastas = sanitize_input($_POST['elpastas'] ?? '');
    if (empty($el_pastas) || !filter_var($el_pastas, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Prašome įvesti galiojantį el. pašto adresą';
    }
    
    $mokykla = sanitize_input($_POST['mokykla']);
    if (empty($mokykla)) $errors[] = 'Prašome pasirinkti mokyklą';
    
    $tipas = sanitize_input($_POST['tipas']);
    if (!in_array($tipas, ['user', 'admin'])) $errors[] = 'Neteisinga rolė';
    
    $slaptazodis = $_POST['slaptazodis'] ?? '';
    if (!empty($slaptazodis) && strlen($slaptazodis) < 6) {
        $errors[] = 'Naujas slaptažodis turi būti bent 6 simbolių ilgio';
    }
    
    if (empty($errors)) {
        $data = [
            'var_vardas' => $vardas,
            'var_pavarde' => $pavarde,
            'el_pastas' => $el_pastas,
            'var_mokykla' => $mokykla,
            'vart_lygis' => $tipas
        ];
        
        $password_changed = false;
        if (!empty($slaptazodis)) {
            $data['var_slapt'] = hash_password($slaptazodis);
            $data['must_change_password'] = 1; 
            $password_changed = true;
        }
        
        $result = db_update('vartotojas', $data, 'vart_id = ?', [$post_user_id]);
        
        if ($result) {
            if ($password_changed) {
                // Išsaugome sesiją PDF generavimui
                $_SESSION['flash_credentials'] = [
                    'vardas_pavarde' => $vardas . ' ' . $pavarde,
                    'vart_id' => $post_user_id,
                    'password' => $slaptazodis
                ];
                set_message('Vartotojas sėkmingai atnaujintas.', 'success');
            } else {
                set_message('Vartotojo duomenys sėkmingai atnaujinti.', 'success');
            }
            
            redirect(SITE_URL . '/modules/admin/user_edit.php?id=' . urlencode($post_user_id));
            exit;
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

require_once dirname(dirname(dirname(__FILE__))) . '/includes/header.php';
?>

<div class="container mt-4 mb-5">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center py-3">
                    <h1 class="h4 mb-0"><i class="fas fa-user-edit me-2"></i> Vartotojo redagavimas</h1>
                    <a href="<?php echo SITE_URL; ?>/modules/admin/users.php" class="btn btn-sm btn-light text-primary fw-bold"><i class="fas fa-arrow-left"></i> Grįžti į sąrašą</a>
                </div>
                <div class="card-body p-4 bg-light">
                    
                    <?php display_message(); ?>

                    <!-- MYGTUKAS PDF ATSISIUNTIMUI / SPAUSDINIMUI -->
                    <?php if (isset($_SESSION['flash_credentials'])): ?>
                        <div class="alert alert-success d-flex flex-column flex-md-row justify-content-between align-items-center shadow border-success p-4 mb-4 rounded">
                            <div class="mb-3 mb-md-0 d-flex align-items-center">
                                <i class="fas fa-file-pdf fa-3x text-danger me-4"></i>
                                <div>
                                    <h4 class="mb-1 text-dark fw-bold">Naujas slaptažodis paruoštas!</h4>
                                    <p class="mb-0 text-dark">Paspauskite mygtuką, kad atidarytumėte ir atspausdintumėte prisijungimo duomenis.</p>
                                </div>
                            </div>
                            <!-- Privalomas target="_blank", kad neatnaujintų šio puslapio -->
                            <a href="<?php echo SITE_URL; ?>/modules/admin/export_credentials.php" target="_blank" class="btn btn-danger btn-lg fw-bold shadow-sm px-4">
                                <i class="fas fa-print me-2"></i> Atspausdinti PDF
                            </a>
                        </div>
                    <?php endif; ?>
                    
                    <form action="<?php echo SITE_URL; ?>/modules/admin/user_edit.php?id=<?php echo urlencode($user_id); ?>" method="post" class="needs-validation bg-white p-4 border rounded shadow-sm" novalidate>
                        <input type="hidden" name="user_id" value="<?php echo htmlspecialchars($user['vart_id']); ?>">
                        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                        
                        <div class="row">
                            <div class="col-md-12 mb-3">
                                <label class="form-label fw-bold text-secondary">Vartotojo ID (Prisijungimo vardas)</label>
                                <input type="text" class="form-control bg-light text-primary fw-bold" value="<?php echo htmlspecialchars($user['vart_id']); ?>" disabled readonly>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="vardas" class="form-label fw-bold">Vardas <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="vardas" name="vardas" value="<?php echo htmlspecialchars($user['var_vardas']); ?>" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="pavarde" class="form-label fw-bold">Pavardė <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="pavarde" name="pavarde" value="<?php echo htmlspecialchars($user['var_pavarde']); ?>" required>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="elpastas" class="form-label fw-bold">El. paštas <span class="text-danger">*</span></label>
                                <input type="email" class="form-control" id="elpastas" name="elpastas" value="<?php echo htmlspecialchars($user['el_pastas'] ?? ''); ?>" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="tipas" class="form-label fw-bold">Rolė <span class="text-danger">*</span></label>
                                <select class="form-select form-control" id="tipas" name="tipas" required>
                                    <option value="user" <?php echo $user['vart_lygis'] == 'user' ? 'selected' : ''; ?>>Mokyklos atstovas</option>
                                    <option value="admin" <?php echo $user['vart_lygis'] == 'admin' ? 'selected' : ''; ?>>Administratorius</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-12 mb-3">
                                <label for="mokykla" class="form-label fw-bold">Mokykla <span class="text-danger">*</span></label>
                                <select class="form-select form-control" id="mokykla" name="mokykla" required>
                                    <option value="">Pasirinkite mokyklą</option>
                                    <?php foreach ($schools as $school): ?>
                                        <option value="<?php echo htmlspecialchars($school['pavadinimas']); ?>" <?php echo $user['var_mokykla'] == $school['pavadinimas'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($school['pavadinimas']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="row bg-light border p-3 mx-0 mb-4 rounded mt-2">
                            <div class="col-md-12">
                                <div class="form-group mb-0">
                                    <label for="slaptazodis" class="form-label fw-bold text-dark">Naujas slaptažodis</label>
                                    <div class="input-group">
                                        <input type="text" class="form-control border-warning" id="slaptazodis" name="slaptazodis" minlength="6" placeholder="Palikite tuščią, jei nenorite keisti">
                                        <button class="btn btn-warning fw-bold text-dark" type="button" onclick="generatePassword()">Generuoti</button>
                                    </div>
                                    <small class="text-muted mt-1 d-block">Sukūrę naują slaptažodį, galėsite jį atspausdinti PDF formatu. Vartotojas privalės jį pasikeisti.</small>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-group border-top pt-3">
                            <button type="submit" class="btn btn-primary btn-lg px-4 shadow-sm"><i class="fas fa-save me-2"></i>Išsaugoti pakeitimus</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function generatePassword() {
    var length = 10,
        charset = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*",
        retVal = "";
    for (var i = 0, n = charset.length; i < length; ++i) {
        retVal += charset.charAt(Math.floor(Math.random() * n));
    }
    document.getElementById("slaptazodis").value = retVal;
}

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

<?php require_once dirname(dirname(dirname(__FILE__))) . '/includes/footer.php'; ?>