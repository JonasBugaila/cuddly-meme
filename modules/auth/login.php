<?php
// Įtraukiame konfigūracijos failus
require_once dirname(dirname(dirname(__FILE__))) . '/config/config.php';
require_once dirname(dirname(dirname(__FILE__))) . '/config/db_connect.php';
require_once dirname(dirname(dirname(__FILE__))) . '/config/functions.php';

// Įjungiame klaidų rodymą
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Jei vartotojas jau prisijungęs, nukreipiame į pagrindinį puslapį
if (is_logged_in()) {
    redirect(SITE_URL);
}

// Apdorojame prisijungimo formą
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Saugumo patikra: ar egzistuoja ir sutampa CSRF žetonas
    if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
        if (function_exists('log_action')) {
            log_action('Klaida prisijungiant', 'Klaidingas CSRF žetonas bandant prisijungti.');
        }
        set_message('Klaidingas sesijos žetonas. Saugumo sumetimais bandykite iš naujo.', 'error');
        redirect(SITE_URL . '/modules/auth/login.php');
    }

    // Tikriname ar užpildyti visi laukai
    if (empty($_POST['username']) || empty($_POST['password'])) {
        set_message('Prašome užpildyti visus laukus', 'error');
    } else {
        $username = sanitize_input($_POST['username']);
        $password = $_POST['password']; // Slaptažodžio nesanitizuojame, leidžiame specialius simbolius!
        
        // Saugus SQL vykdymas per `db_query` su parameter binding
        $sql = "SELECT * FROM vartotojas WHERE vart_id = ?";
        $stmt = db_query($sql, [$username], 's');
        
        if ($stmt) {
            $user = db_get_row($stmt);
            
            if ($user) {
                $is_valid = false;
                $needs_rehash = false;

                // MIGRACIJOS LOGIKA SLAPTAŽODŽIAMS:
                // Tikriname, ar slaptažodis DB jau yra užšifruotas (Bcrypt hash visada prasideda "$2y$")
                if (strpos($user['var_slapt'], '$2y$') === 0) {
                    // Jei taip, tikriname naudodami saugią funkciją
                    $is_valid = verify_password($password, $user['var_slapt']);
                } else {
                    // Jei ne, tikriname su senu paprastu tekstu (laikinas sprendimas senoms paskyroms)
                    if ($password === $user['var_slapt']) {
                        $is_valid = true;
                        $needs_rehash = true; // Pažymime, kad šį slaptažodį jau reikia užšifruoti
                    }
                }

                if ($is_valid) {
                    // Jei prisijungta su senu neužšifruotu slaptažodžiu, užšifruojame ir išsaugome DB ateičiai!
                    if ($needs_rehash) {
                        $new_hash = hash_password($password);
                        db_update('vartotojas', ['var_slapt' => $new_hash], "vart_id = ?", [$user['vart_id']]);
                    }

                    // Sėkmingas prisijungimas
                    start_session();
                    $_SESSION['user_id'] = $user['vart_id'];
                    $_SESSION['user_name'] = $user['var_vardas'] . ' ' . $user['var_pavarde'];
                    $_SESSION['user_level'] = $user['vart_lygis'];
                    
                    // Įrašome į sisteminį žurnalą
                    if (function_exists('log_action')) {
                        log_action('Sėkmingas prisijungimas', "Vartotojas {$user['vart_id']} prisijungė prie sistemos.");
                    }
                    
                    // Nukreipiame į pagrindinį puslapį
                    redirect(SITE_URL);
                } else {
                    // Neteisingas slaptažodis
                    if (function_exists('log_action')) {
                        log_action('Nepavykęs prisijungimas', "Neteisingas slaptažodis bandant prisijungti prie paskyros: $username");
                    }
                    set_message('Neteisingas vartotojo vardas arba slaptažodis', 'error');
                }
            } else {
                // Vartotojas nerastas
                if (function_exists('log_action')) {
                    log_action('Nepavykęs prisijungimas', "Bandyta prisijungti prie neegzistuojančios paskyros: $username");
                }
                set_message('Neteisingas vartotojo vardas arba slaptažodis', 'error');
            }
        } else {
            set_message('Sistemos klaida bandant prisijungti.', 'error');
        }
    }
}

// Įtraukiame antraštę
require_once dirname(dirname(dirname(__FILE__))) . '/includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-6">
        <div class="card mt-5">
            <div class="card-header bg-primary text-white">
                <h2 class="mb-0">Prisijungimas</h2>
            </div>
            <div class="card-body">
                <?php display_message(); ?>
                <form action="<?php echo SITE_URL; ?>/modules/auth/login.php" method="post" class="needs-validation" novalidate>
                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">

                    <div class="form-group mb-3">
                        <label for="username" class="form-label fw-bold">Vartotojo vardas</label>
                        <input type="text" class="form-control" id="username" name="username" required>
                    </div>

                    <div class="form-group mb-3">
                        <label for="password" class="form-label fw-bold">Slaptažodis</label>
                        <input type="password" class="form-control" id="password" name="password" required>
                    </div>

                    <div class="form-group mb-4">
                        <button type="submit" class="btn btn-primary w-100">Prisijungti</button>
                    </div>

                    <div class="form-group text-center">
                        <p class="text-muted small">Pamiršote slaptažodį? Susisiekite su sistemos administratoriumi.</p>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once dirname(dirname(dirname(__FILE__))) . '/includes/footer.php'; ?>