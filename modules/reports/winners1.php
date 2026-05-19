<?php
/**
 * Prizininkų ataskaitos puslapis su spausdinimo funkcija
 * 
 * Šis failas atvaizduoja prizininkų ataskaitas pagal olimpiadas ir mokyklas
 * ir leidžia jas spausdinti
 */

// Įtraukiame konfigūracijos failus
require_once dirname(dirname(dirname(__FILE__))) . '/config/config.php';
require_once dirname(dirname(dirname(__FILE__))) . '/config/db_connect.php';
require_once dirname(dirname(dirname(__FILE__))) . '/config/functions.php';

// Tikriname ar vartotojas prisijungęs ir turi administratoriaus teises
if (!is_logged_in()) {
    set_message('Turite prisijungti, kad galėtumėte pasiekti šį puslapį', 'error');
    redirect(SITE_URL . '/modules/auth/login.php');
} elseif (!is_admin()) {
    set_message('Neturite teisių pasiekti šį puslapį', 'error');
    redirect(SITE_URL);
}

// Nustatome filtrus
$olympiad = isset($_GET['olympiad']) ? sanitize_input($_GET['olympiad']) : '';
$school = isset($_GET['school']) ? sanitize_input($_GET['school']) : '';
$topWinners = isset($_GET['topWinners']) && $_GET['topWinners'] == '1';
$print_mode = isset($_GET['print']) && $_GET['print'] == '1';

// Gauname olimpiadų sąrašą
$sql = "SELECT DISTINCT konkurso_pav FROM konkursai ORDER BY konkurso_pav ASC";
$stmt = db_query($sql);
$olympiads = db_get_results($stmt);

// Gauname mokyklų sąrašą
$sql = "SELECT DISTINCT pavadinimas FROM mokyklos ORDER BY pavadinimas ASC";
$stmt = db_query($sql);
$schools = db_get_results($stmt);

// Gauname prizininkų sąrašą
$where = [];
$params = [];

if (!empty($olympiad)) {
    $where[] = "d.konkurso_pav = ?";
    $params[] = $olympiad;
}

if (!empty($school)) {
    $where[] = "d.var_mokykla = ?";
    $params[] = $school;
}

$where[] = "d.Vieta IN ('I', 'II', 'III', 'laureat.')";

if ($topWinners) {
    $sql = "SELECT d.* FROM dalyviai d
            WHERE d.reg_id IN (
                SELECT reg_id FROM dalyviai
                WHERE Vieta IN ('I', 'II', 'III', 'laureat.')
                GROUP BY 1_vardas, 1_pavarde
                HAVING COUNT(*) >= 5
            )";
    if (!empty($where)) {
        $sql .= " AND " . implode(" AND ", $where);
    }
    $sql .= " ORDER BY d.konkurso_pav ASC, FIELD(d.Vieta, 'I', 'II', 'III', 'laureat.'), d.1_pavarde ASC";
} else {
    $where_clause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";
    $sql = "SELECT * FROM dalyviai d $where_clause ORDER BY d.konkurso_pav ASC, FIELD(d.Vieta, 'I', 'II', 'III', 'laureat.'), d.1_pavarde ASC";
}

$stmt = db_query($sql, $params);
$winners = db_get_results($stmt);

// Jei esame spausdinimo režime, rodome tik spausdinamą lentelę
if ($print_mode) {
	header('Content-Type: text/html; charset=UTF-8');
    // Paruošiame duomenis spausdinimui
    $headers = ['Olimpiada', 'Vardas', 'Pavardė', 'Klasė', 'Mokykla', 'Mokytojas', 'Balai', 'Vieta'];
    $data = [];
    
    foreach ($winners as $winner) {
        $row = [
            $winner['konkurso_pav'],
            $winner['1_vardas'],
            $winner['1_pavarde'],
            $winner['1_klase'],
            $winner['var_mokykla'],
            $winner['1_mok'],
            $winner['Balai'],
            formatPlace($winner['Vieta'])
        ];
        $data[] = $row;
    }
    
    // Nustatome antraštę
    $title = 'Prizininkų sąrašas';
    if (!empty($olympiad)) {
        $title .= ' - ' . $olympiad;
    }
    if (!empty($school)) {
        $title .= ' - ' . $school;
    }
    if ($topWinners) {
        $title .= ' - 5+ prizinės vietos';
    }
    
    // Gauname įstaigos pavadinimą
    $institution = 'Švietimo pagalbos tarnyba';
    
    // Spausdiname lentelę
    echo generate_printable_table($title, $institution, $headers, $data, [
        'signature_text' => 'Atsakingo asmens parašas',
        'signature_name' => '',
        'include_back_button' => true
    ]);
    
    exit;
}

// Funkcija formatuoti vietą
function formatPlace($place) {
    if ($place == 'I') {
        return '<span class="badge bg-warning">I vieta</span>';
    } elseif ($place == 'II') {
        return '<span class="badge bg-secondary">II vieta</span>';
    } elseif ($place == 'III') {
        return '<span class="badge bg-danger">III vieta</span>';
    } else {
        return '<span class="badge bg-info">Laureatas</span>';
    }
}

// Įtraukiame antraštę
require_once dirname(dirname(dirname(__FILE__))) . '/includes/header.php';
?>

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h1>Prizininkų ataskaitos</h1>
                <div>
                    <?php if (!empty($winners)): ?>
                        <a href="<?php echo SITE_URL; ?>/modules/reports/winners.php?olympiad=<?php echo urlencode($olympiad); ?>&school=<?php echo urlencode($school); ?>&topWinners=<?php echo $topWinners ? '1' : '0'; ?>&print=1" target="_blank" class="btn btn-primary">Spausdinti</a>
                    <?php endif; ?>
                    <a href="<?php echo SITE_URL; ?>/modules/reports/index.php" class="btn btn-secondary">Grįžti į ataskaitas</a>
                    <a href="?topWinners=1" class="btn btn-success <?php echo $topWinners ? 'active' : ''; ?>">Rodyti 5+ prizines vietas</a>
                </div>
            </div>
            <div class="card-body">
                <form action="<?php echo SITE_URL; ?>/modules/reports/winners.php" method="get" class="mb-4">
                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group mb-3">
                                <label for="olympiad" class="form-label">Olimpiada</label>
                                <select class="form-control" id="olympiad" name="olympiad">
                                    <option value="">Visos olimpiados</option>
                                    <?php foreach ($olympiads as $o): ?>
                                        <option value="<?php echo $o['konkurso_pav']; ?>" <?php echo $olympiad == $o['konkurso_pav'] ? 'selected' : ''; ?>><?php echo $o['konkurso_pav']; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group mb-3">
                                <label for="school" class="form-label">Mokykla</label>
                                <select class="form-control" id="school" name="school">
                                    <option value="">Visos mokyklos</option>
                                    <?php foreach ($schools as $s): ?>
                                        <option value="<?php echo $s['pavadinimas']; ?>" <?php echo $school == $s['pavadinimas'] ? 'selected' : ''; ?>><?php echo $s['pavadinimas']; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group mb-3">
                                <label class="form-label">&nbsp;</label>
                                <button type="submit" class="btn btn-primary d-block">Filtruoti</button>
                            </div>
                        </div>
                    </div>
                </form>
                
                <?php if (!empty($winners)): ?>
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>Olimpiada</th>
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
                                <?php foreach ($winners as $winner): ?>
                                    <tr>
                                        <td><?php echo $winner['konkurso_pav']; ?></td>
                                        <td><?php echo $winner['1_vardas']; ?></td>
                                        <td><?php echo $winner['1_pavarde']; ?></td>
                                        <td><?php echo $winner['1_klase']; ?></td>
                                        <td><?php echo $winner['var_mokykla']; ?></td>
                                        <td><?php echo $winner['1_mok']; ?></td>
                                        <td><?php echo $winner['Balai']; ?></td>
                                        <td>
                                            <?php echo formatPlace($winner['Vieta']); ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info">
                        <p>Nėra prizininkų, atitinkančių pasirinktus filtrus.</p>
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