<?php
/**
 * Vartotojo pridėjimo forma
 */

require_once dirname(dirname(dirname(__FILE__))) . '/config/config.php';
require_once dirname(dirname(dirname(__FILE__))) . '/config/db_connect.php';
require_once dirname(dirname(dirname(__FILE__))) . '/config/functions.php';

// SAUGU: teisių patikra perkelta į patį pradžią, prieš bet kokį DB užklausimą
if (!is_logged_in()) {
    set_message('Turite prisijungti, kad galėtumėte pasiekti šį puslapį', 'error');
    redirect(SITE_URL . '/modules/auth/login.php');
    exit;
} elseif (!is_admin()) {
    set_message('Neturite teisių pasiekti šį puslapį', 'error');
    redirect(SITE_URL);
    exit;
}

// Gauname mokyklų sąrašą
$sql = "SELECT mokyklos_id, pavadinimas FROM mokyklos ORDER BY pavadinimas ASC";
$stmt = db_query($sql);
$schools = db_get_results($stmt);

// Apdorojame formą
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $errors = [];
    
    if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
        $errors[] = 'Netinkamas CSRF žetonas. Saugumo sumetimais bandykite dar kartą.';
    }
    
    if (empty($_POST['vart_id'])) {
        $errors[] = 'Prašome įvesti vartotojo ID';
    } else {
        $vart_id = sanitize_input($_POST['vart_id']);
        if (strlen($vart_id) > 50) {
            $errors[] = 'Vartotojo ID negali būti ilgesnis nei 50 simbolių';
        }
        $sql = "SELECT vart_id FROM vartotojas WHERE vart_id = ?";
        $stmt = db_query($sql, [$vart_id], 's');
        if (db_get_row($stmt)) {
            $errors[] = 'Šis Vartotojo ID jau egzistuoja.';
        }
    }
    
    if (empty($_POST['mokykla'])) $errors[] = 'Prašome pasirinkti mokyklą';
    if (empty($_POST['vardas'])) $errors[] = 'Prašome įvesti vardą';
    if (empty($_POST['pavarde'])) $errors[] = 'Prašome įvesti pavardę';
    
    if (empty($_POST['elpastas'])) {
        $errors[] = 'Prašome įvesti el. paštą';
    } elseif (!filter_var($_POST['elpastas'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Prašome įvesti galiojantį el. pašto adresą';
    }
    
    $plain_password = $_POST['slaptazodis'] ?? '';
    if (empty($plain_password)) {
        $errors[] = 'Prašome įvesti slaptažodį';
    } elseif (strlen($plain_password) < 10) {
        $errors[] = 'Slaptažodis turi būti bent 10 simbolių ilgio';
    }
    
    $sql = "SELECT vart_id FROM vartotojas WHERE el_pastas = ?";
    $stmt = db_query($sql, [$_POST['elpastas']], 's');
    if (db_get_row($stmt)) {
        $errors[] = 'Vartotojas su šiuo el. pašto adresu jau egzistuoja';
    }
    
    if (empty($errors)) {
        $vardas = sanitize_input($_POST['vardas']);
        $pavarde = sanitize_input($_POST['pavarde']);
        
        $data = [
            'vart_id' => sanitize_input($_POST['vart_id']),
            'var_vardas' => $vardas,
            'var_pavarde' => $pavarde,
            'var_mokykla' => sanitize_input($_POST['mokykla']),
            'vart_lygis' => !empty($_POST['tipas']) ? sanitize_input($_POST['tipas']) : 'user',
            'el_pastas' => sanitize_input($_POST['elpastas']),
            'var_slapt' => hash_password($plain_password),
            'must_change_password' => 1 
        ];
        
        $conn = db_connect();
        db_insert('vartotojas', $data);
        
        // ===============================================
        // Išsaugome laikinus duomenis PDF generavimui!
        // ===============================================
        $_SESSION['flash_credentials'] = [
            'vardas_pavarde' => $vardas . ' ' . $pavarde,
            'vart_id' => $data['vart_id'],
            'password' => $plain_password
        ];
        
        set_message('Vartotojas sėkmingai užregistruotas.', 'success');
        redirect(SITE_URL . '/modules/admin/user_add.php');
        exit;
    } else {
        foreach ($errors as $error) {
            set_message($error, 'error');
        }
    }
}

require_once dirname(dirname(dirname(__FILE__))) . '/includes/header.php';
?>

<div class="container mt-4">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h1 class="h4 mb-0"><i class="fas fa-user-plus me-2"></i> Vartotojo pridėjimas</h1>
                </div>
                <div class="card-body bg-light p-4">
                    
                    <?php display_message(); ?>

                    <!-- MYGTUKAS PDF ATSISIUNTIMUI / SPAUSDINIMUI -->
                    <?php if (isset($_SESSION['flash_credentials'])): ?>
                        <div class="alert alert-success d-flex flex-column flex-md-row justify-content-between align-items-center shadow border-success p-4 mb-4 rounded">
                            <div class="mb-3 mb-md-0 d-flex align-items-center">
                                <i class="fas fa-file-pdf fa-3x text-danger me-4"></i>
                                <div>
                                    <h4 class="mb-1 text-dark fw-bold">Prisijungimo duomenys paruošti!</h4>
                                    <p class="mb-0 text-dark">Paspauskite mygtuką, kad atidarytumėte ir atspausdintumėte prisijungimo duomenis vartotojui.</p>
                                </div>
                            </div>
                            <!-- Privalomas target="_blank", kad neatnaujintų šio puslapio -->
                            <a href="<?php echo SITE_URL; ?>/modules/admin/export_credentials.php" target="_blank" class="btn btn-danger btn-lg fw-bold shadow-sm px-4">
                                <i class="fas fa-print me-2"></i> Atspausdinti PDF
                            </a>
                        </div>
                    <?php endif; ?>
                    
                    <div class="alert alert-info border-info shadow-sm">
                        <i class="fas fa-info-circle me-2"></i><strong>Informacija:</strong> Sukūrus paskyrą, vartotojas per pirmąjį savo prisijungimą privalės pasikeisti administratoriaus priskirtą laikiną slaptažodį.
                    </div>
                    
                    <form action="<?php echo SITE_URL; ?>/modules/admin/user_add.php" method="post" class="needs-validation bg-white p-4 border rounded shadow-sm" novalidate>
                        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group mb-3">
                                    <label for="vardas" class="form-label fw-bold">Vardas *</label>
                                    <input type="text" class="form-control" id="vardas" name="vardas" value="<?php echo isset($_POST['vardas']) ? htmlspecialchars($_POST['vardas']) : ''; ?>" required>
                                    <div class="invalid-feedback">Prašome įvesti vardą</div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group mb-3">
                                    <label for="pavarde" class="form-label fw-bold">Pavardė *</label>
                                    <input type="text" class="form-control" id="pavarde" name="pavarde" value="<?php echo isset($_POST['pavarde']) ? htmlspecialchars($_POST['pavarde']) : ''; ?>" required>
                                    <div class="invalid-feedback">Prašome įvesti pavardę</div>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group mb-3">
                                    <label for="vart_id" class="form-label fw-bold text-secondary">Sistemos sugeneruotas ID *</label>
                                    <input type="text" class="form-control bg-light text-primary fw-bold" id="vart_id" name="vart_id" value="<?php echo isset($_POST['vart_id']) ? htmlspecialchars($_POST['vart_id']) : ''; ?>" readonly tabindex="-1" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group mb-3">
                                    <label for="elpastas" class="form-label fw-bold">El. paštas *</label>
                                    <input type="email" class="form-control" id="elpastas" name="elpastas" value="<?php echo isset($_POST['elpastas']) ? htmlspecialchars($_POST['elpastas']) : ''; ?>" required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group mb-3">
                                    <label for="tipas" class="form-label fw-bold">Paskyros tipas</label>
                                    <select class="form-select form-control" id="tipas" name="tipas">
                                        <option value="user" <?php echo isset($_POST['tipas']) && $_POST['tipas'] == 'user' ? 'selected' : ''; ?>>Mokyklos atstovas</option>
                                        <option value="admin" <?php echo isset($_POST['tipas']) && $_POST['tipas'] == 'admin' ? 'selected' : ''; ?>>Administratorius</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group mb-3">
                                    <label for="mokykla" class="form-label fw-bold">Mokykla *</label>
                                    <select class="form-select form-control" id="mokykla" name="mokykla" required>
                                        <option value="">Pasirinkite mokyklą</option>
                                        <?php foreach ($schools as $school): ?>
                                            <option value="<?php echo htmlspecialchars($school['pavadinimas']); ?>" <?php echo isset($_POST['mokykla']) && $_POST['mokykla'] == $school['pavadinimas'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($school['pavadinimas']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row bg-light border p-3 mx-0 mb-4 rounded">
                            <div class="col-md-12">
                                <div class="form-group mb-0">
                                    <label for="slaptazodis" class="form-label fw-bold text-dark">Laikinas slaptažodis *</label>
                                    <div class="input-group">
                                        <input type="text" class="form-control border-warning" id="slaptazodis" name="slaptazodis" required minlength="10">
                                        <button class="btn btn-warning fw-bold text-dark" type="button" onclick="generatePassword()">Generuoti</button>
                                    </div>
                                    <small class="text-muted mt-1 d-block">Šį slaptažodį galėsite atspausdinti PDF formatu po sukūrimo.</small>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-group border-top pt-3">
                            <button type="submit" class="btn btn-primary btn-lg px-4 shadow-sm"><i class="fas fa-save me-2"></i>Pridėti vartotoją</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function removeLithuanianChars(str) {
    const charMap = {
        'ą': 'a', 'č': 'c', 'ę': 'e', 'ė': 'e', 'į': 'i', 'š': 's', 'ų': 'u', 'ū': 'u', 'ž': 'z',
        'Ą': 'a', 'Č': 'c', 'Ę': 'e', 'Ė': 'e', 'Į': 'i', 'Š': 's', 'Ų': 'u', 'Ū': 'u', 'Ž': 'z'
    };
    return str.replace(/[ąčęėįšųūžĄČĘĖĮŠŲŪŽ]/g, function(match) {
        return charMap[match];
    }).toLowerCase();
}

function generateUserId() {
    let vardasInput = document.getElementById('vardas').value.trim();
    let pavardeInput = document.getElementById('pavarde').value.trim();
    
    let vardas = removeLithuanianChars(vardasInput);
    let pavarde = removeLithuanianChars(pavardeInput);
    
    let idPavarde = pavarde.substring(0, 1);
    
    let d = new Date();
    let month = d.getMonth() + 1; 
    let hour = d.getHours();
    
    if (vardas.length > 0) {
        let dot = (idPavarde.length > 0) ? '.' : '';
        document.getElementById('vart_id').value = vardas + dot + idPavarde + month + hour;
    } else {
        document.getElementById('vart_id').value = '';
    }
}

document.getElementById('vardas').addEventListener('input', generateUserId);
document.getElementById('pavarde').addEventListener('input', generateUserId);

function generatePassword() {
    // SAUGU: crypto.getRandomValues() naudoja kriptografiškai saugų atsitiktinių
    // skaičių generatorių, o ne Math.random() (kuris nėra saugus slaptažodžiams).
    var length = 12,
        charset = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*",
        retVal = "",
        randomValues = new Uint32Array(length);
    crypto.getRandomValues(randomValues);
    for (var i = 0; i < length; i++) {
        retVal += charset.charAt(randomValues[i] % charset.length);
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