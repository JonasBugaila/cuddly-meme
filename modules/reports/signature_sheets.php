<?php
/**
 * Dalyvių ataskaitos puslapis su spausdinimo funkcija
 * 
 * Šis failas atvaizduoja dalyvių ataskaitas pagal olimpiadas
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
$print_mode = isset($_GET['print']) && $_GET['print'] == '1';

// Gauname olimpiadų sąrašą
$sql = "SELECT DISTINCT konkurso_pav FROM konkursai ORDER BY konkurso_pav ASC";
$stmt = db_query($sql);
$olympiads = db_get_results($stmt);

// Gauname dalyvių sąrašą
$where = [];
$params = [];

if (!empty($olympiad)) {
    $where[] = "d.konkurso_pav = ?";
    $params[] = $olympiad;
}

$where_clause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";
$sql = "SELECT * FROM dalyviai d $where_clause ORDER BY d.konkurso_pav ASC, d.1_pavarde ASC";

$stmt = db_query($sql, $params);
$winners = db_get_results($stmt);

// Patikriname, ar yra bent vienas įrašas su ne tuščiu 2_mok
$has_second_teacher = false;
foreach ($winners as $winner) {
    if (!empty($winner['2_mok'])) {
        $has_second_teacher = true;
        break;
    }
}

// Jei esame spausdinimo režime, rodome tik spausdinamą lentelę
if ($print_mode) {
    header('Content-Type: text/html; charset=UTF-8');
    // Paruošiame duomenis spausdinimui
    $headers = ['Olimpiada', 'Vardas', 'Pavardė', 'Klasė', 'Mokykla', 'Mokytojas'];
    if ($has_second_teacher) {
        $headers[] = 'Antras mokytojas';
    }
    $headers[] = 'Parašas';
    
    $data = [];
    
    foreach ($winners as $winner) {
        $row = [
            $winner['konkurso_pav'],
            $winner['1_vardas'],
            $winner['1_pavarde'],
            $winner['1_klase'],
            $winner['var_mokykla'],
            $winner['1_mok'],
        ];
        if ($has_second_teacher) {
            $row[] = $winner['2_mok'];
        }
        $row[] = '___________________';
        $data[] = $row;
    }
    
    // Nustatome antraštę
    $title = 'Dalyvių sąrašas';
    if (!empty($olympiad)) {
        $title .= ' - ' . $olympiad;
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

// Įtraukiame antraštę
require_once dirname(dirname(dirname(__FILE__))) . '/includes/header.php';
?>

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h1>Dalyvių ataskaitos</h1>
                <div>
                    <?php if (!empty($winners)): ?>
                        <a href="<?php echo SITE_URL; ?>/modules/reports/signature_sheets.php?olympiad=<?php echo urlencode($olympiad); ?>&print=1" target="_blank" class="btn btn-primary">Spausdinti</a>
                    <?php endif; ?>
                    <a href="<?php echo SITE_URL; ?>/modules/reports/index.php" class="btn btn-secondary">Grįžti į ataskaitas</a>
                </div>
            </div>
            <div class="card-body">
                <form action="<?php echo SITE_URL; ?>/modules/reports/signature_sheets.php" method="get" class="mb-4">
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
                                    <?php if ($has_second_teacher): ?>
                                        <th>Antras mokytojas</th>
                                    <?php endif; ?>
                                    <th>Parašas</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($winners as $winner): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($winner['konkurso_pav']); ?></td>
                                        <td><?php echo htmlspecialchars($winner['1_vardas']); ?></td>
                                        <td><?php echo htmlspecialchars($winner['1_pavarde']); ?></td>
                                        <td><?php echo htmlspecialchars($winner['1_klase']); ?></td>
                                        <td><?php echo htmlspecialchars($winner['var_mokykla']); ?></td>
                                        <td><?php echo htmlspecialchars($winner['1_mok']); ?></td>
                                        <?php if ($has_second_teacher): ?>
                                            <td><?php echo htmlspecialchars($winner['2_mok']); ?></td>
                                        <?php endif; ?>
                                        <td>___________________</td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info">
                        <p>Nėra dalyvių, atitinkančių pasirinktus filtrus.</p>
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