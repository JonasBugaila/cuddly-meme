<?php
/**
 * Slaptažodžio keitimo puslapis
 * Palaiko tiek priverstinį (OTP), tiek savanorišką keitimą
 */
require_once dirname(dirname(dirname(__FILE__))) . '/config/config.php';
require_once dirname(dirname(dirname(__FILE__))) . '/config/db_connect.php';
require_once dirname(dirname(dirname(__FILE__))) . '/config/functions.php';

start_session();

// Nustatome, kokiu režimu veikia puslapis
$is_otp_mode = isset($_SESSION['require_password_change']) && $_SESSION['require_password_change'] === true && isset($_SESSION['temp_user_id']);
$is_voluntary_mode = is_logged_in();

// Jei vartotojas neatitinka nei vieno režimo, išmetame
if (!$is_otp_mode && !$is_voluntary_mode) {
    set_message('Neturite teisių pasiekti šį puslapį.', 'error');
    redirect(SITE_URL . '/modules/auth/login.php');
    exit;
}

$user_id = $is_otp_mode ? $_SESSION['temp_user_id'] : $_SESSION['user_id'];
$user_name = $is_otp_mode ? $_SESSION['temp_user_name'] : $_SESSION['user_name'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        set_message('Saugumo klaida. Neteisingas CSRF žetonas.', 'error');
        redirect(current_url());
        exit;
    }

    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $errors = [];

    // Jei savanoriškas keitimas, privalome patikrinti dabartinį slaptažodį
    if ($is_voluntary_mode) {
        if (empty($current_password)) {
            $errors[] = 'Prašome įvesti dabartinį slaptažodį.';
        } else {
            $stmt = db_query("SELECT var_slapt FROM vartotojas WHERE vart_id = ?", [$user_id], 's');
            $user_db = db_get_row($stmt);
            
            // Tikriname hash'ą arba seną plaintext formatą (migracijai)
            $pass_valid = verify_password($current_password, $user_db['var_slapt']) || ($current_password === $user_db['var_slapt']);
            if (!$pass_valid) {
                $errors[] = 'Neteisingas dabartinis slaptažodis.';
            }
        }
    }

    // Griežta naujo slaptažodžio validacija serveryje
    $uppercase = preg_match('@[A-Z]@', $new_password);
    $lowercase = preg_match('@[a-z]@', $new_password);
    $number    = preg_match('@[0-9]@', $new_password);
    $special   = preg_match('@[^\w]@', $new_password);

    if (empty($new_password) || empty($confirm_password)) {
        $errors[] = 'Prašome užpildyti visus naujo slaptažodžio laukus.';
    } elseif ($new_password !== $confirm_password) {
        $errors[] = 'Nauji slaptažodžiai nesutampa.';
    } elseif (strlen($new_password) < 12 || !$uppercase || !$lowercase || !$number || !$special) {
        $errors[] = 'Naujas slaptažodis neatitinka saugumo reikalavimų!';
    }

    if (empty($errors)) {
        // Atnaujiname duomenų bazę
        $hashed_password = hash_password($new_password);
        $data_to_update = [
            'var_slapt' => $hashed_password,
            'must_change_password' => 0
        ];
        
        db_update('vartotojas', $data_to_update, 'vart_id = ?', [$user_id]);

        // Jei tai buvo OTP režimas, "pakeliame" laikiną sesiją į pilną
        if ($is_otp_mode) {
            $_SESSION['user_id'] = $_SESSION['temp_user_id'];
            $_SESSION['user_name'] = $_SESSION['temp_user_name'];
            $_SESSION['user_level'] = $_SESSION['temp_user_level'];
            unset($_SESSION['temp_user_id'], $_SESSION['temp_user_name'], $_SESSION['temp_user_level'], $_SESSION['require_password_change']);
        }
        
        if (function_exists('log_action')) {
            log_action('Slaptažodžio keitimas', "Vartotojas {$user_id} sėkmingai pasikeitė slaptažodį.");
        }

        set_message('Slaptažodis sėkmingai pakeistas!', 'success');
        redirect(SITE_URL . '/index.php');
        exit;
    } else {
        foreach ($errors as $error) {
            set_message($error, 'error');
        }
    }
}

$page_title = $is_otp_mode ? 'Paskyros aktyvavimas' : 'Slaptažodžio keitimas';
$icon_color = $is_otp_mode ? 'text-warning' : 'text-primary';
$border_color = $is_otp_mode ? 'border-warning' : 'border-primary';

require_once dirname(dirname(dirname(__FILE__))) . '/includes/header.php';
?>

<div class="row justify-content-center mb-5">
    <div class="col-md-7">
        <div class="card mt-4 shadow-sm border-0 border-top <?php echo $border_color; ?> border-4">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h2 class="mb-0 h4 text-dark"><i class="fas fa-user-lock <?php echo $icon_color; ?> me-2"></i> <?php echo $page_title; ?></h2>
                <?php if ($is_voluntary_mode): ?>
                    <a href="<?php echo SITE_URL; ?>" class="btn btn-sm btn-light text-secondary"><i class="fas fa-arrow-left"></i> Grįžti</a>
                <?php endif; ?>
            </div>
            <div class="card-body p-4 bg-light">
                <?php display_message(); ?>
                
                <div class="text-center mb-4">
                    <h5 class="text-secondary mb-1"><?php echo $is_otp_mode ? 'Prisijungėte kaip:' : 'Paskyra:'; ?></h5>
                    <h3 class="text-primary fw-bold mb-0"><?php echo htmlspecialchars($user_name); ?></h3>
                    <small class="text-muted">ID: <?php echo htmlspecialchars($user_id); ?></small>
                </div>

                <?php if ($is_otp_mode): ?>
                    <div class="alert alert-warning shadow-sm border-0">
                        <i class="fas fa-exclamation-triangle me-2"></i> Prisijungėte su laikinu (vienkartiniu) slaptažodžiu. Prieš pradedant naudotis sistema, <strong>privalote</strong> jį pasikeisti.
                    </div>
                <?php endif; ?>
                
                <div class="card mb-4 border-info">
                    <div class="card-header bg-info text-white fw-bold py-2">
                        <i class="fas fa-shield-alt me-2"></i> Naujo slaptažodžio reikalavimai:
                    </div>
                    <div class="card-body bg-white py-2">
                        <ul class="mb-0 ps-3 text-muted small" id="password-rules">
                            <li id="rule-length" class="text-danger"><i class="fas fa-times me-1"></i> Ilgis ne trumpesnis kaip <strong>12 simbolių</strong></li>
                            <li id="rule-upper" class="text-danger"><i class="fas fa-times me-1"></i> Bent viena <strong>didžioji raidė</strong> (A-Z)</li>
                            <li id="rule-lower" class="text-danger"><i class="fas fa-times me-1"></i> Bent viena <strong>mažoji raidė</strong> (a-z)</li>
                            <li id="rule-number" class="text-danger"><i class="fas fa-times me-1"></i> Bent vienas <strong>skaičius</strong> (0-9)</li>
                            <li id="rule-special" class="text-danger"><i class="fas fa-times me-1"></i> Bent vienas <strong>specialusis simbolis</strong> (pvz. !, @, #, $)</li>
                        </ul>
                    </div>
                </div>
                
                <form action="" method="post" class="bg-white p-4 rounded border shadow-sm" id="passwordForm">
                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                    
                    <?php if ($is_voluntary_mode): ?>
                        <div class="form-group mb-4 pb-3 border-bottom">
                            <label for="current_password" class="form-label fw-bold text-danger">Dabartinis slaptažodis <span class="text-danger">*</span></label>
                            <input type="password" class="form-control" id="current_password" name="current_password" required>
                            <small class="text-muted">Saugumo sumetimais turite patvirtinti savo tapatybę.</small>
                        </div>
                    <?php endif; ?>
                    
                    <div class="form-group mb-3">
                        <label for="new_password" class="form-label fw-bold">Naujas slaptažodis <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <input type="password" class="form-control" id="new_password" name="new_password" required>
                            <button class="btn btn-outline-secondary" type="button" id="toggleBtn" onclick="togglePasswordVisibility()"><i class="fas fa-eye"></i> Rodyti</button>
                        </div>
                    </div>
                    
                    <div class="form-group mb-4">
                        <label for="confirm_password" class="form-label fw-bold">Pakartokite naują slaptažodį <span class="text-danger">*</span></label>
                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                        <div class="invalid-feedback d-none" id="match-error">Slaptažodžiai nesutampa!</div>
                    </div>
                    
                    <div class="d-flex flex-column flex-sm-row justify-content-between align-items-center border-top pt-3 mt-2 gap-2">
                        <button type="button" class="btn btn-info text-white fw-bold shadow-sm w-100 w-sm-auto" onclick="generateStrongPassword()">
                            <i class="fas fa-magic me-1"></i> Sugeneruoti saugų
                        </button>
                        <button type="submit" class="btn btn-success btn-lg fw-bold shadow-sm w-100 w-sm-auto" id="submitBtn" disabled>
                            <i class="fas fa-save me-1"></i> Pakeisti slaptažodį
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function togglePasswordVisibility() {
    let pwInput1 = document.getElementById("new_password");
    let pwInput2 = document.getElementById("confirm_password");
    let btn = document.getElementById("toggleBtn");
    
    if (pwInput1.type === "password") {
        pwInput1.type = "text";
        pwInput2.type = "text";
        btn.innerHTML = '<i class="fas fa-eye-slash"></i> Slėpti';
    } else {
        pwInput1.type = "password";
        pwInput2.type = "password";
        btn.innerHTML = '<i class="fas fa-eye"></i> Rodyti';
    }
}

function generateStrongPassword() {
    const length = 14; // Automatiškai generuojame dar stipresnį
    const upper = "ABCDEFGHIJKLMNOPQRSTUVWXYZ";
    const lower = "abcdefghijklmnopqrstuvwxyz";
    const numbers = "0123456789";
    const special = "!@#$%^&*-=_+?";
    const allChars = upper + lower + numbers + special;

    let password = "";
    password += upper[Math.floor(Math.random() * upper.length)];
    password += lower[Math.floor(Math.random() * lower.length)];
    password += numbers[Math.floor(Math.random() * numbers.length)];
    password += special[Math.floor(Math.random() * special.length)];

    for (let i = 4; i < length; i++) {
        password += allChars[Math.floor(Math.random() * allChars.length)];
    }
    password = password.split('').sort(function(){return 0.5-Math.random()}).join('');

    let pwInput1 = document.getElementById('new_password');
    let pwInput2 = document.getElementById('confirm_password');
    let btn = document.getElementById("toggleBtn");
    
    pwInput1.type = 'text';
    pwInput2.type = 'text';
    btn.innerHTML = '<i class="fas fa-eye-slash"></i> Slėpti';
    
    pwInput1.value = password;
    pwInput2.value = password;
    
    validatePassword();
}

function validatePassword() {
    let pw = document.getElementById('new_password').value;
    let pwConfirm = document.getElementById('confirm_password').value;
    let submitBtn = document.getElementById('submitBtn');
    
    let hasLength = pw.length >= 12;
    let hasUpper = /[A-Z]/.test(pw);
    let hasLower = /[a-z]/.test(pw);
    let hasNumber = /[0-9]/.test(pw);
    let hasSpecial = /[^\w\s]/.test(pw);
    let matches = (pw === pwConfirm && pw.length > 0);

    updateRuleUI('rule-length', hasLength);
    updateRuleUI('rule-upper', hasUpper);
    updateRuleUI('rule-lower', hasLower);
    updateRuleUI('rule-number', hasNumber);
    updateRuleUI('rule-special', hasSpecial);

    let matchError = document.getElementById('match-error');
    if (pwConfirm.length > 0 && !matches) {
        document.getElementById('confirm_password').classList.add('is-invalid');
        matchError.classList.remove('d-none');
    } else {
        document.getElementById('confirm_password').classList.remove('is-invalid');
        matchError.classList.add('d-none');
    }

    if (hasLength && hasUpper && hasLower && hasNumber && hasSpecial && matches) {
        submitBtn.disabled = false;
    } else {
        submitBtn.disabled = true;
    }
}

function updateRuleUI(elementId, isValid) {
    let el = document.getElementById(elementId);
    if (isValid) {
        el.classList.remove('text-danger');
        el.classList.add('text-success');
        el.innerHTML = el.innerHTML.replace('fa-times', 'fa-check');
    } else {
        el.classList.remove('text-success');
        el.classList.add('text-danger');
        el.innerHTML = el.innerHTML.replace('fa-check', 'fa-times');
    }
}

document.getElementById('new_password').addEventListener('input', validatePassword);
document.getElementById('confirm_password').addEventListener('input', validatePassword);
</script>

<?php require_once dirname(dirname(dirname(__FILE__))) . '/includes/footer.php'; ?>