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
    // Tikriname ar užpildyti visi laukai
    if (empty($_POST['username']) || empty($_POST['password'])) {
        set_message('Prašome užpildyti visus laukus', 'error');
    } else {
        $username = sanitize_input($_POST['username']);
        $password = $_POST['password'];
        
        // Tiesioginis SQL vykdymas
        $conn = db_connect();
        $sql = "SELECT * FROM vartotojas WHERE vart_id = '$username'";
        $result = $conn->query($sql);
        
        if ($result && $result->num_rows > 0) {
            $user = $result->fetch_assoc();
            
            if ($password === $user['var_slapt']) {
                // Sėkmingas prisijungimas
                start_session();
                $_SESSION['user_id'] = $user['vart_id'];
                $_SESSION['user_name'] = $user['var_vardas'] . ' ' . $user['var_pavarde'];
                $_SESSION['user_level'] = $user['vart_lygis'];
                
                // Nukreipiame į pagrindinį puslapį
                redirect(SITE_URL);
            } else {
                // Neteisingi prisijungimo duomenys
                set_message('Neteisingas vartotojo vardas arba slaptažodis', 'error');
            }
        } else {
            // Vartotojas nerastas
            set_message('Neteisingas vartotojo vardas arba slaptažodis', 'error');
        }
    }
}

// Įtraukiame antraštę
require_once dirname(dirname(dirname(__FILE__))) . '/includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-6">
        <div class="card mt-5">
            <div class="card-header">
                <h2>Prisijungimas</h2>
            </div>
            <div class="card-body">
                <form action="<?php echo SITE_URL; ?>/modules/auth/login.php" method="post" class="needs-validation" novalidate>
                    <div class="form-group mb-3">
                        <label for="username" class="form-label">Vartotojo vardas</label>
                        <input type="text" class="form-control" id="username" name="username" required>
                    </div>

                    <div class="form-group mb-3">
                        <label for="password" class="form-label">Slaptažodis</label>
                        <input type="password" class="form-control" id="password" name="password" required>
                    </div>

                    <div class="form-group mb-3">
                        <button type="submit" class="btn btn-primary">Prisijungti</button>
                    </div>

                    <div class="form-group">
                        <p>Pamiršote slaptažodį? Susisiekite su administratoriumi.</p>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once dirname(dirname(dirname(__FILE__))) . '/includes/footer.php'; ?>