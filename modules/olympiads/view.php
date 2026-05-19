<?php
/**
 * Olimpiados peržiūros puslapis
 * 
 * Šis failas atvaizduoja olimpiados informaciją ir dalyvius
 */

require_once dirname(dirname(dirname(__FILE__))) . '/config/config.php';
require_once dirname(dirname(dirname(__FILE__))) . '/config/db_connect.php';
require_once dirname(dirname(dirname(__FILE__))) . '/config/functions.php';

// Tikriname ar vartotojas prisijungęs
if (!is_logged_in()) {
    set_message('Turite prisijungti, kad galėtumėte pasiekti šį puslapį', 'error');
    redirect(SITE_URL . '/modules/auth/login.php');
}

// Tikriname ar nurodytas olimpiados ID
if (!isset($_GET['id']) || empty($_GET['id'])) {
    set_message('Nenurodyta olimpiada', 'error');
    redirect(SITE_URL . '/modules/olympiads/index.php');
}

$olympiad_id = sanitize_input($_GET['id']);

// Gauname olimpiados informaciją
$sql = "SELECT * FROM konkursai WHERE konk_id = ?";
$stmt = db_query($sql, [$olympiad_id]);
$olympiad = db_get_row($stmt);

if (!$olympiad) {
    set_message('Olimpiada nerasta', 'error');
    redirect(SITE_URL . '/modules/olympiads/index.php');
}

// Paruošiame sesijos informaciją atvaizdavimui
$session_info = [];
$session_info['Sesijos duomenys'] = !empty($_SESSION) ? print_r($_SESSION, true) : 'Nėra sesijos duomenų';
$session_info['Administratorius'] = is_admin() ? 'Taip' : 'Ne';

// Gauname vartotojo mokyklą iš duomenų bazės
if (isset($_SESSION['user_id'])) {
    $sql = "SELECT var_mokykla FROM vartotojas WHERE vart_id = ?";
    $stmt = db_query($sql, [$_SESSION['user_id']], 's');
    $user_data = db_get_row($stmt);
    $session_info['Mokykla'] = !empty($user_data['var_mokykla']) ? htmlspecialchars($user_data['var_mokykla']) : 'Mokykla nenurodyta';
} else {
    $session_info['Mokykla'] = 'Vartotojo ID nerasta sesijoje';
}

// Gauname olimpiados dalyvius
if (is_admin()) {
    // Administratorius mato visus dalyvius
    $sql = "SELECT * FROM dalyviai WHERE konkurso_pav = ? ORDER BY reg_id ASC";
    $stmt = db_query($sql, [$olympiad['konkurso_pav']]);
} else {
    // Paprastas vartotojas mato tik savo mokyklos dalyvius
    if (!isset($_SESSION['user_id'])) {
        set_message('Jūsų sesijos duomenys neteisingi. Prašome prisijungti iš naujo.', 'error');
        redirect(SITE_URL . '/modules/auth/login.php');
    }
    // Gauname vartotojo mokyklą iš duomenų bazės
    $sql = "SELECT var_mokykla FROM vartotojas WHERE vart_id = ?";
    $stmt = db_query($sql, [$_SESSION['user_id']], 's');
    $user_data = db_get_row($stmt);
    
    if (!$user_data || empty($user_data['var_mokykla'])) {
        set_message('Jūsų mokykla nenurodyta. Susisiekite su administratoriumi.', 'error');
        redirect(SITE_URL . '/modules/olympiads/index.php');
    }
    
    $user_school = $user_data['var_mokykla'];
    $sql = "SELECT * FROM dalyviai WHERE konkurso_pav = ? AND var_mokykla = ? ORDER BY reg_id ASC";
    $stmt = db_query($sql, [$olympiad['konkurso_pav'], $user_school], 'ss');
}
$participants = db_get_results($stmt);

// Įtraukiame antraštę
require_once dirname(dirname(dirname(__FILE__))) . '/includes/header.php';
?>

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h1><?php echo $olympiad['konkurso_pav']; ?></h1>
                <div>
                    <?php if ($olympiad['status'] == 0): ?>
                        <a href="<?php echo SITE_URL; ?>/modules/registration/register.php?olympiad_id=<?php echo $olympiad['konk_id']; ?>" class="btn btn-success">Registruoti dalyvius</a>
                    <?php endif; ?>
                    <a href="<?php echo SITE_URL; ?>/modules/olympiads/index.php" class="btn btn-secondary">Grįžti į sąrašą</a>
                </div>
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
                            <tr>
                                <th>Statusas:</th>
                                <td>
                                    <?php if ($olympiad['status'] == 0): ?>
                                        <span class="badge bg-success">Aktyvus</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Neaktyvus</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>
                
                <h3>Dalyviai</h3>
                <?php if (!empty($participants)): ?>
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>Vardas</th>
                                    <th>Pavardė</th>
                                    <th>Klasė</th>
                                    <th>Mokykla</th>
                                    <th>Mokytojas</th>
                                    <th>Balai</th>
                                    <th>Vieta</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($participants as $participant): ?>
                                    <tr>
                                        <td><?php echo $participant['1_vardas']; ?></td>
                                        <td><?php echo $participant['1_pavarde']; ?></td>
                                        <td><?php echo $participant['1_klase']; ?></td>
                                        <td><?php echo $participant['var_mokykla']; ?></td>
                                        <td><?php echo $participant['1_mok']; ?></td>
                                        <td><?php echo $participant['Balai']; ?></td>
                                        <td>
                                            <?php if (!empty($participant['Vieta'])): ?>
                                                <span class="badge bg-primary"><?php echo $participant['Vieta']; ?></span>
                                            <?php else: ?>
                                                -
                                            <?php endif; ?>
                                        </td>
										<td><?php if (!empty($participant['Vieta']) && in_array($participant['Vieta'], ['I','II','III','laureat.'])): ?>
    <a href="<?php echo SITE_URL; ?>/modules/reports/diplomas.php?id=<?php echo $participant['reg_id']; ?>"
       target="_blank"
       class="btn btn-sm btn-warning">
        <i class="fas fa-certificate"></i> Diplomas
    </a>
<?php endif; ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info">
                        <p>Nėra užregistruotų dalyvių.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php
// Įtraukiame poraštę
require_once dirname(dirname(dirname(__FILE__))) . '/includes/footer.php';
?>