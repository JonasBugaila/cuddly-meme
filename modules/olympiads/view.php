<?php
/**
 * Olimpiados peržiūros puslapis
 * * Šis failas atvaizduoja olimpiados informaciją ir dalyvius
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
$stmt = db_query($sql, [$olympiad_id], 'i');
$olympiad = $stmt ? db_get_row($stmt) : null;

if (!$olympiad) {
    set_message('Olimpiada nerasta', 'error');
    redirect(SITE_URL . '/modules/olympiads/index.php');
}

// Gauname vartotojo mokyklą iš duomenų bazės paprasto vartotojo filtravimui
$user_school = '';
if (isset($_SESSION['user_id'])) {
    $sql_u = "SELECT var_mokykla FROM vartotojas WHERE vart_id = ?";
    $stmt_u = db_query($sql_u, [$_SESSION['user_id']], 's');
    if ($stmt_u) {
        $user_data = db_get_row($stmt_u);
        if ($user_data) {
            $user_school = $user_data['var_mokykla'];
        }
    }
}

// Gauname dalyvių sąrašą
$params = [];
$param_types = '';

// Jungiame per pavadinimą, nes dalyviai lentelėje saugomas tekstinis mokyklos pavadinimas
$sql = "SELECT d.*, m.pavadinimas AS mokykla_pilna 
        FROM dalyviai d 
        LEFT JOIN mokyklos m ON d.var_mokykla = m.pavadinimas 
        WHERE d.konkurso_pav = ?";
$params[] = $olympiad['konkurso_pav'];
$param_types .= 's';

// Jei ne administratorius, ribojame dalyvių rodymą tik pagal jo mokyklą
if (!is_admin()) {
    if (empty($user_school)) {
        set_message('Jūsų paskyrai nepriskirta jokia mokykla. Kreipkitės į administratorių.', 'error');
        redirect(SITE_URL . '/modules/olympiads/index.php');
    }
    $sql .= " AND d.var_mokykla = ?";
    $params[] = $user_school;
    $param_types .= 's';
}

$sql .= " ORDER BY d.reg_id ASC";
$stmt = db_query($sql, $params, $param_types);
$participants = $stmt ? db_get_results($stmt) : [];

// Įtraukiame antraštę
require_once dirname(dirname(dirname(__FILE__))) . '/includes/header.php';
?>

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h1 class="h3 mb-0"><?php echo htmlspecialchars($olympiad['konkurso_pav']); ?></h1>
                <div>
                    <?php if ($olympiad['status'] == 0): ?>
                        <a href="<?php echo SITE_URL; ?>/modules/registration/register.php?olympiad_id=<?php echo $olympiad['konk_id']; ?>" class="btn btn-success btn-sm">Registruoti dalyvius</a>
                    <?php endif; ?>
                    <a href="<?php echo SITE_URL; ?>/modules/olympiads/index.php" class="btn btn-secondary btn-sm">Grįžti į sąrašą</a>
                </div>
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
                
                <h4 class="mb-3">Užregistruoti dalyviai</h4>
                <?php if (!empty($participants)): ?>
                    <div class="table-responsive">
                        <table class="table table-striped table-hover align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>Vardas</th>
                                    <th>Pavardė</th>
                                    <th>Klasė</th>
                                    <th>Mokykla</th>
                                    <th>Mokytojas</th>
                                    <th>Balai</th>
                                    <th>Vieta</th>
                                    <th class="text-end">Veiksmai</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($participants as $participant): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($participant['1_vardas']); ?></td>
                                        <td><?php echo htmlspecialchars($participant['1_pavarde']); ?></td>
                                        <td><?php echo htmlspecialchars($participant['1_klase']); ?></td>
                                        <td><?php echo htmlspecialchars($participant['mokykla_pilna'] ?? $participant['var_mokykla']); ?></td>
                                        <td><?php echo htmlspecialchars($participant['1_mok']); ?></td>
                                        <td><strong><?php echo htmlspecialchars($participant['Balai'] ?: '-'); ?></strong></td>
                                        <td>
                                            <?php if (!empty($participant['Vieta'])): ?>
                                                <span class="badge bg-primary"><?php echo htmlspecialchars($participant['Vieta']); ?></span>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end">
                                            <?php if (!empty($participant['Vieta']) && in_array($participant['Vieta'], ['I','II','III','laureat.'])): ?>
                                                <a href="<?php echo SITE_URL; ?>/modules/reports/diplomas.php?id=<?php echo $participant['reg_id']; ?>"
                                                   target="_blank"
                                                   class="btn btn-sm btn-warning">
                                                    <i class="fas fa-certificate"></i> Diplomas
                                                </a>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info">Nėra užregistruotų dalyvių.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once dirname(dirname(dirname(__FILE__))) . '/includes/footer.php'; ?>