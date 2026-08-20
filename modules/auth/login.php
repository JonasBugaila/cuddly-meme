<?php
// Įtraukiame konfigūracijos failus
require_once dirname(dirname(dirname(__FILE__))) . '/config/config.php';
require_once dirname(dirname(dirname(__FILE__))) . '/config/db_connect.php';
require_once dirname(dirname(dirname(__FILE__))) . '/config/functions.php';

// Produkcijoje klaidų rodymas išjungiamas
ini_set('display_errors', 0);
error_reporting(0);

// Jei vartotojas jau prisijungęs, nukreipiame į pagrindinį puslapį
if (is_logged_in()) {
    redirect(SITE_URL);
    exit;
}

// Apdorojame prisijungimo formą
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
        set_message('Klaidingas sesijos žetonas. Saugumo sumetimais bandykite iš naujo.', 'error');
        redirect(SITE_URL . '/modules/auth/login.php');
        exit;
    }

    if (empty($_POST['username']) || empty($_POST['password'])) {
        set_message('Prašome užpildyti visus laukus', 'error');
    } else {
        $username = sanitize_input($_POST['username']);
        $password = $_POST['password'];
        
        $sql = "SELECT * FROM vartotojas WHERE vart_id = ?";
        $stmt = db_query($sql, [$username], 's');
        
        if ($stmt) {
            $user = db_get_row($stmt);

            // SAUGU: apsauga nuo brute-force atakų. Jei paskyra šiuo metu užrakinta
            // dėl per daug nesėkmingų bandymų, atsisakome tikrinti slaptažodį iš viso.
            if ($user && !empty($user['locked_until']) && strtotime($user['locked_until']) > time()) {
                $minutes_left = ceil((strtotime($user['locked_until']) - time()) / 60);
                set_message("Paskyra laikinai užrakinta dėl per daug nesėkmingų bandymų. Bandykite dar kartą po {$minutes_left} min.", 'error');
                $user = null; // neleidžiame toliau tikrinti slaptažodžio
            }
            
            if ($user) {
                $is_valid = false;
                $needs_rehash = false;

                if (strpos($user['var_slapt'], '$2y$') === 0) {
                    $is_valid = verify_password($password, $user['var_slapt']);
                } else {
                    if ($password === $user['var_slapt']) {
                        $is_valid = true;
                        $needs_rehash = true;
                    }
                }

                if ($is_valid) {
                    if ($needs_rehash) {
                        $new_hash = hash_password($password);
                        db_update('vartotojas', ['var_slapt' => $new_hash], "vart_id = ?", [$user['vart_id']]);
                    }

                    // SAUGU: sėkmingas prisijungimas atstato nesėkmingų bandymų skaitiklį
                    if (!empty($user['failed_attempts']) || !empty($user['locked_until'])) {
                        db_update('vartotojas', ['failed_attempts' => 0, 'locked_until' => null], "vart_id = ?", [$user['vart_id']]);
                    }

                    start_session();
                    session_regenerate_id(true);
                    
                    if (isset($user['must_change_password']) && $user['must_change_password'] == 1) {
                        $_SESSION['temp_user_id'] = $user['vart_id'];
                        $_SESSION['temp_user_name'] = $user['var_vardas'] . ' ' . $user['var_pavarde'];
                        $_SESSION['temp_user_level'] = $user['vart_lygis'];
                        $_SESSION['require_password_change'] = true;
                        redirect(SITE_URL . '/modules/auth/change_password.php');
                        exit;
                    }
                    
                    $_SESSION['user_id'] = $user['vart_id'];
                    $_SESSION['user_name'] = $user['var_vardas'] . ' ' . $user['var_pavarde'];
                    $_SESSION['user_level'] = $user['vart_lygis'];
                    
                    redirect(SITE_URL);
                    exit;
                } else {
                    // SAUGU: nesėkmingas bandymas - didiname skaitiklį, po 5 bandymų užrakiname 15 min.
                    $new_failed_attempts = (int)($user['failed_attempts'] ?? 0) + 1;
                    $update_data = ['failed_attempts' => $new_failed_attempts];
                    if ($new_failed_attempts >= 5) {
                        $update_data['locked_until'] = date('Y-m-d H:i:s', time() + 900);
                        log_action('Saugumo pažeidimas', "Paskyra '{$username}' užrakinta po {$new_failed_attempts} nesėkmingų prisijungimo bandymų.");
                        set_message('Per daug nesėkmingų bandymų. Paskyra užrakinta 15 minučių.', 'error');
                    } else {
                        set_message('Neteisingas vartotojo vardas arba slaptažodis', 'error');
                    }
                    db_update('vartotojas', $update_data, 'vart_id = ?', [$user['vart_id']]);
                }
            } elseif (!isset($_SESSION['message'])) {
                // Bendra klaida, kad neatskleistume, ar vartotojo ID egzistuoja (username enumeration apsauga)
                set_message('Neteisingas vartotojo vardas arba slaptažodis', 'error');
            }
        }
    }
}

require_once dirname(dirname(dirname(__FILE__))) . '/includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-6">
        <div class="card mt-5 shadow-sm border-0">
            <div class="card-header bg-primary text-white py-3">
                <h2 class="mb-0 h4"><i class="fas fa-sign-in-alt me-2"></i> Prisijungimas</h2>
            </div>
            <div class="card-body p-4">
                <?php display_message(); ?>
                <form action="<?php echo SITE_URL; ?>/modules/auth/login.php" method="post" class="needs-validation" novalidate>
                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">

                    <div class="form-group mb-3">
                        <label for="username" class="form-label fw-bold">Vartotojo vardas</label>
                        <input type="text" class="form-control" id="username" name="username" required>
                    </div>

                    <div class="form-group mb-4">
                        <label for="password" class="form-label fw-bold">Slaptažodis</label>
                        <input type="password" class="form-control" id="password" name="password" required>
                    </div>

                    <!-- SLANKIOJANTI DĖLIONĖ (2FA) -->
                    <div class="mb-4">
                        <label class="form-label fw-bold">Saugumo patikra:</label>
                        <div id="slider-container" class="login-slider">
                            <div id="slider-text" class="login-slider-text">Slinkite, kad atrakintumėte</div>
                            <div id="slider-btn" class="login-slider-btn">
                                <i class="fas fa-chevron-right"></i>
                            </div>
                        </div>
                    </div>

                    <div class="form-group mb-4">
                        <button type="submit" id="login-btn" class="btn btn-primary btn-lg w-100 fw-bold" disabled>Prisijungti</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    const sliderBtn = document.getElementById('slider-btn');
    const sliderContainer = document.getElementById('slider-container');
    const loginBtn = document.getElementById('login-btn');
    const sliderText = document.getElementById('slider-text');
    let isDragging = false;

    // PATAISYTA: pridėtas prisilietimo (touch) įvykių palaikymas - anksčiau
    // slankiklis reaguodavo TIK į pelės įvykius (onmousedown/onmousemove/onmouseup),
    // todėl telefone ar planšetėje jo apskritai nebuvo įmanoma patempti pirštu,
    // ir prisijungti iš mobilaus įrenginio nebuvo galima. Dabar abu įvykių
    // rinkiniai (pelė ir prisilietimas) valdomi tomis pačiomis funkcijomis.

    function getClientX(e) {
        return (e.touches && e.touches.length > 0) ? e.touches[0].clientX : e.clientX;
    }

    function startDrag(e) {
        isDragging = true;
    }

    function moveDrag(e) {
        if (!isDragging) return;
        if (e.cancelable) e.preventDefault(); // apsaugo, kad tempimas nepradėtų slinkti puslapio mobiliame

        let containerRect = sliderContainer.getBoundingClientRect();
        let newLeft = getClientX(e) - containerRect.left - 25;

        if (newLeft < 0) newLeft = 0;
        if (newLeft > containerRect.width - 50) {
            newLeft = containerRect.width - 50;
            isDragging = false;
            loginBtn.disabled = false;
            sliderContainer.classList.add('is-complete');
            sliderText.textContent = 'Patvirtinta';
        }
        sliderBtn.style.left = newLeft + 'px';
    }

    function endDrag() {
        if (isDragging) {
            isDragging = false;
            if (!loginBtn.disabled) return;
            sliderBtn.style.left = '0px';
        }
    }

    sliderBtn.addEventListener('mousedown', startDrag);
    sliderBtn.addEventListener('touchstart', startDrag, { passive: true });

    document.addEventListener('mousemove', moveDrag);
    document.addEventListener('touchmove', moveDrag, { passive: false });

    document.addEventListener('mouseup', endDrag);
    document.addEventListener('touchend', endDrag);
</script>

<?php require_once dirname(dirname(dirname(__FILE__))) . '/includes/footer.php'; ?>