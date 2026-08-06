<?php
// 1. Išvalome buferį prieš viską
if (ob_get_length()) ob_end_clean();
ob_start();

require_once dirname(dirname(dirname(__FILE__))) . '/config/config.php';
require_once dirname(dirname(dirname(__FILE__))) . '/config/db_connect.php';
require_once dirname(dirname(dirname(__FILE__))) . '/config/functions.php';

if (!is_logged_in() || !is_admin()) {
    redirect(SITE_URL . '/modules/auth/login.php');
    exit;
}

// 2. FILTRAI
$olympiad = isset($_GET['olympiad']) ? sanitize_input($_GET['olympiad']) : '';
$school = isset($_GET['school']) ? sanitize_input($_GET['school']) : '';

$where_clauses = ["d.Vieta IN ('I', 'II', 'III', 'laureat.')"];
$params = [];
$types = '';

if (!empty($olympiad)) { 
    $where_clauses[] = "d.konkurso_pav = ?"; 
    $params[] = $olympiad; 
    $types .= 's'; 
}
if (!empty($school)) { 
    $where_clauses[] = "d.var_mokykla = ?"; 
    $params[] = $school; 
    $types .= 's'; 
}
$where_clause = 'WHERE ' . implode(' AND ', $where_clauses);

// 3. EKSORTO LOGIKA (Excel)
if (isset($_GET['export']) && $_GET['export'] == 'excel') {
    $sql_export = "SELECT * FROM dalyviai d $where_clause";
    $stmt_exp = db_query($sql_export, $params, $types);
    $all_winners = $stmt_exp ? db_get_results($stmt_exp) : [];

    header("Content-Type: application/vnd.ms-excel; charset=utf-8");
    header("Content-Disposition: attachment; filename=Prizininkai_" . date('Y-m-d') . ".xls");
    
    print("\xEF\xBB\xBF");
    
    echo '<table border="1">';
    echo '<tr><th>Olimpiada</th><th>Vardas</th><th>Pavardė</th><th>Klasė</th><th>Mokykla</th><th>Balai</th><th>Vieta</th></tr>';
    foreach ($all_winners as $w) {
        echo "<tr>
                <td>{$w['konkurso_pav']}</td>
                <td>{$w['1_vardas']}</td>
                <td>{$w['1_pavarde']}</td>
                <td>{$w['1_klase']}</td>
                <td>{$w['var_mokykla']}</td>
                <td>" . ($w['Balai'] ?? '0') . "</td>
                <td>{$w['Vieta']}</td>
              </tr>";
    }
    echo '</table>';
    exit; 
}

// 4. RIKIAVIMAS IR PUSLAPIAVIMAS
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 25;
if (!in_array($limit, [10, 25, 50, 100])) $limit = 25;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

$allowed_sort = ['konkurso_pav', '1_vardas', '1_pavarde', '1_klase', 'Balai', 'Vieta'];
$sort = isset($_GET['sort']) && in_array($_GET['sort'], $allowed_sort) ? $_GET['sort'] : 'konkurso_pav';
$dir = isset($_GET['dir']) && $_GET['dir'] === 'DESC' ? 'DESC' : 'ASC';

$count_sql = "SELECT COUNT(*) as total FROM dalyviai d $where_clause";
$total_items = db_get_row(db_query($count_sql, $params, $types))['total'] ?? 0;

$order_sql = ($sort === 'Balai') ? "CAST(d.Balai AS UNSIGNED) $dir" : "d.$sort $dir";
$sql = "SELECT d.*, m.pavadinimas AS mokykla_pilna 
        FROM dalyviai d 
        LEFT JOIN mokyklos m ON d.var_mokykla = m.pavadinimas 
        $where_clause 
        ORDER BY $order_sql LIMIT ?, ?";

$stmt = db_query($sql, array_merge($params, [$offset, $limit]), $types . 'ii');
$winners = $stmt ? db_get_results($stmt) : [];

$olympiads = db_get_results(db_query("SELECT DISTINCT konkurso_pav FROM konkursai"));
$schools = db_get_results(db_query("SELECT DISTINCT pavadinimas FROM mokyklos"));

require_once dirname(dirname(dirname(__FILE__))) . '/includes/header.php';
?>

<div class="card shadow-sm border-0">
    <div class="card-header bg-primary text-white">
        <h1 class="h4 mb-0"><i class="fas fa-trophy"></i> Prizininkų ataskaita</h1>
    </div>
    <div class="card-body">
        <form method="get" class="row g-3 mb-4">
            <div class="col-md-3">
                <select name="olympiad" class="form-select">
                    <option value="">Visos olimpiados</option>
                    <?php foreach ($olympiads as $o): ?>
                        <option value="<?php echo htmlspecialchars($o['konkurso_pav']); ?>" <?php echo $olympiad === $o['konkurso_pav'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($o['konkurso_pav']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <select name="school" class="form-select">
                    <option value="">Visos mokyklos</option>
                    <?php foreach ($schools as $s): ?>
                        <option value="<?php echo htmlspecialchars($s['pavadinimas']); ?>" <?php echo $school === $s['pavadinimas'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($s['pavadinimas']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="col-md-6 d-flex flex-wrap gap-2 align-items-end">
                <button type="submit" class="btn btn-primary"><i class="fas fa-filter"></i> Filtruoti</button>
                <a href="winners.php" class="btn btn-secondary"><i class="fas fa-undo"></i> Atstatyti</a>
                <a href="winners.php?<?php echo http_build_query(array_merge($_GET, ['export' => 'excel'])); ?>" class="btn btn-success">
                    <i class="fas fa-file-excel"></i> Excel
                </a>
                
                <a href="#" onclick="return handleExportDiplomas(event);" class="btn btn-warning fw-bold" target="_blank">
                    <i class="fas fa-file-pdf"></i> Eksportuoti visus diplomus
                </a>
            </div>
        </form>

        <div class="table-responsive">
            <table class="table table-striped table-hover table-sm align-middle">
                <thead class="table-light">
                    <tr>
                        <th><?php echo generate_sortable_header('konkurso_pav', 'Olimpiada', $sort, $dir); ?></th>
                        <th><?php echo generate_sortable_header('1_vardas', 'Vardas', $sort, $dir); ?></th>
                        <th><?php echo generate_sortable_header('1_pavarde', 'Pavardė', $sort, $dir); ?></th>
                        <th><?php echo generate_sortable_header('1_klase', 'Klasė', $sort, $dir); ?></th>
                        <th><?php echo generate_sortable_header('mokykla_pilna', 'Mokykla', $sort, $dir); ?></th>
                        <th><?php echo generate_sortable_header('Balai', 'Balai', $sort, $dir); ?></th>
                        <th><?php echo generate_sortable_header('Vieta', 'Vieta', $sort, $dir); ?></th>
                        <th>Diplomas</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($winners as $w): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($w['konkurso_pav']); ?></td>
                        <td><?php echo htmlspecialchars($w['1_vardas']); ?></td>
                        <td><?php echo htmlspecialchars($w['1_pavarde']); ?></td>
                        <td><?php echo htmlspecialchars($w['1_klase']); ?></td>
                        <td><?php echo htmlspecialchars($w['mokykla_pilna'] ?? $w['var_mokykla']); ?></td>
                        <td><strong><?php echo htmlspecialchars($w['Balai']); ?></strong></td>
                        <td><span class="badge bg-warning text-dark"><?php echo htmlspecialchars($w['Vieta']); ?></span></td>
                        <td>
                            <a href="../reports/diplomas.php?id=<?php echo $w['reg_id']; ?>" target="_blank" class="btn btn-sm btn-outline-warning">
                                <i class="fas fa-file-pdf"></i> PDF
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php render_pagination($total_items, $limit, $page); ?>
    </div>
</div>

<script type="text/javascript">
function handleExportDiplomas(event) {
    // Gauname esamas reikšmes iš select laukelių
    var olySelect = document.querySelector('select[name="olympiad"]').value;
    var schoolSelect = document.querySelector('select[name="school"]').value;

    // Jei nepasirinkta nei olimpiada, nei mokykla - blokuojame eksportą
    if (olySelect.trim() === '' && schoolSelect.trim() === '') {
        event.preventDefault(); // Sustabdo nuorodos atidarymą
        alert('Prašome išskleidžiamajame meniu pasirinkti Olimpiadą arba Mokyklą, kad galėtumėte masiškai eksportuoti diplomus.');
        return false;
    }

    // Jei pasirinkta, sukonstruojame URL ir perduodame abu galimus parametrus
    var url = 'export_diplomas.php?';
    var params = [];
    if (olySelect.trim() !== '') params.push('olympiad=' + encodeURIComponent(olySelect));
    if (schoolSelect.trim() !== '') params.push('school=' + encodeURIComponent(schoolSelect));
    
    // Priskiriame tikrąją nuorodą elementui prieš jam atsidarant
    event.currentTarget.href = url + params.join('&');
    return true;
}
</script>

<?php require_once dirname(dirname(dirname(__FILE__))) . '/includes/footer.php'; ?>