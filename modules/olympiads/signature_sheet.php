<?php
/**
 * Olimpiados parašų ataskaita
 * 
 * Šis failas atvaizduoja olimpiados dalyvių sąrašą su vieta parašams ir spausdinimo funkcija
 */

require_once dirname(dirname(dirname(__FILE__))) . '/config/config.php';
require_once dirname(dirname(dirname(__FILE__))) . '/config/db_connect.php';
require_once dirname(dirname(dirname(__FILE__))) . '/config/functions.php';

// Tikriname ar vartotojas prisijungęs
if (!is_logged_in()) {
    set_message('Turite prisijungti, kad galėtumėte pasiekti šį puslapį', 'error');
    redirect(SITE_URL . '/modules/auth/login.php');
}

// Nustatome filtrus
$olympiad = isset($_GET['olympiad']) ? sanitize_input($_GET['olympiad']) : '';
$school = isset($_GET['school']) ? sanitize_input($_GET['school']) : '';
$print_mode = isset($_GET['print']) && $_GET['print'] == '1';

// Tikriname ar nurodyta olimpiada
if (empty($olympiad)) {
    set_message('Nenurodyta olimpiada', 'error');
    redirect(SITE_URL . '/modules/olympiads/index.php');
}

// Gauname olimpiados informaciją
$sql = "SELECT * FROM konkursai WHERE konkurso_pav = ?";
$stmt = db_query($sql, [$olympiad], 's');
$olympiad_data = db_get_row($stmt);

if (!$olympiad_data) {
    set_message('Olimpiada nerasta', 'error');
    redirect(SITE_URL . '/modules/olympiads/index.php');
}

// Gauname mokyklų sąrašą
$sql = "SELECT DISTINCT pavadinimas FROM mokyklos ORDER BY pavadinimas ASC";
$stmt = db_query($sql);
$schools = db_get_results($stmt);

// Gauname dalyvių sąrašą
$where = ["konkurso_pav = ?"];
$params = [$olympiad];

if (!empty($school)) {
    $where[] = "var_mokykla = ?";
    $params[] = $school;
}

$sql = "SELECT * FROM dalyviai";
if (!empty($where)) {
    $sql .= " WHERE " . implode(" AND ", $where);
}
$sql .= " ORDER BY reg_id ASC";
$stmt = db_query($sql, $params, str_repeat('s', count($params)));
$participants = db_get_results($stmt);

// Įtraukiame antraštę
require_once dirname(dirname(dirname(__FILE__))) . '/includes/header.php';
?>

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h1><?php echo htmlspecialchars($olympiad); ?> - Parašų lapas</h1>
                <div>
                    <button onclick="window.print()" class="btn btn-primary d-print-none"><i class="bi bi-printer"></i> Spausdinti</button>
                    <a href="<?php echo SITE_URL; ?>/modules/olympiads/index.php" class="btn btn-secondary d-print-none">Grįžti į sąrašą</a>
                </div>
            </div>
            <div class="card-body">
                <?php if (!$print_mode): ?>
                    <form method="GET" class="mb-4 needs-validation" novalidate>
                        <input type="hidden" name="olympiad" value="<?php echo htmlspecialchars($olympiad); ?>">
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label for="school" class="form-label">Mokykla</label>
                                <select class="form-control" id="school" name="school">
                                    <option value="">Visos mokyklos</option>
                                    <?php foreach ($schools as $s): ?>
                                        <option value="<?php echo htmlspecialchars($s['pavadinimas']); ?>" <?php echo $school == $s['pavadinimas'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($s['pavadinimas']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary">Filtruoti</button>
                    </form>
                <?php endif; ?>
                
                <?php if (!empty($participants)): ?>
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>Eil. Nr.</th>
                                    <th>Vardas</th>
                                    <th>Pavardė</th>
                                    <th>Klasė</th>
                                    <th>Mokykla</th>
                                    <th>Mokytojas</th>
                                    <th>Antras mokytojas</th>
                                    <th>Informacija</th>
                                    <th>Parašas</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($participants as $index => $participant): ?>
                                    <tr>
                                        <td><?php echo $index + 1; ?></td>
                                        <td><?php echo htmlspecialchars($participant['1_vardas'] ?? '-'); ?></td>
                                        <td><?php echo htmlspecialchars($participant['1_pavarde'] ?? '-'); ?></td>
                                        <td><?php echo htmlspecialchars($participant['1_klase'] ?? '-'); ?></td>
                                        <td><?php echo htmlspecialchars($participant['var_mokykla'] ?? '-'); ?></td>
                                        <td><?php echo htmlspecialchars($participant['1_mok'] ?? '-'); ?></td>
                                        <td><?php echo htmlspecialchars($participant['2_mok'] ?? '-'); ?></td>
                                        <td><?php echo htmlspecialchars($participant['inf_1'] ?? '-'); ?></td>
                                        <td></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info">
                        <p>Nėra užregistruotų dalyvių šioje olimpiadoje pagal pasirinktus filtrus.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<style>
@media print {
    body {
        margin: 0;
        padding: 0;
    }
    .card {
        border: none;
        box-shadow: none;
    }
    .card-header {
        background: none;
        border-bottom: none;
        padding-bottom: 0;
    }
    .card-body {
        padding: 0;
    }
    .table {
        width: 100%;
        border-collapse: collapse;
        font-size: 12pt;
    }
    .table th, .table td {
        border: 1px solid black;
        padding: 8px;
        text-align: left;
    }
    .table th {
        background-color: #f2f2f2;
    }
    .table td:last-child {
        min-width: 150px;
    }
    .d-print-none {
        display: none !important;
    }
    form {
        display: none;
    }
    @page {
        size: A4;
        margin: 15mm;
    }
}
</style>

<script>
// Automatinis spausdinimas, jei print=1
<?php if ($print_mode): ?>
window.onload = function() {
    window.print();
};
<?php endif; ?>
</script>

<?php
// Įtraukiame poraštę
require_once dirname(dirname(dirname(__FILE__))) . '/includes/footer.php';
?>