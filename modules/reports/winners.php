<?php
/**
 * Prizininkų ataskaitos puslapis (Su rikiavimais ir puslapiavimu)
 */

require_once dirname(dirname(dirname(__FILE__))) . '/config/config.php';
require_once dirname(dirname(dirname(__FILE__))) . '/config/db_connect.php';
require_once dirname(dirname(dirname(__FILE__))) . '/config/functions.php';

if (!is_logged_in() || !is_admin()) {
    redirect(SITE_URL . '/modules/auth/login.php');
}

// 1. FILTRAI
$olympiad = isset($_GET['olympiad']) ? sanitize_input($_GET['olympiad']) : '';
$school = isset($_GET['school']) ? sanitize_input($_GET['school']) : '';

// 2. RIKIAVIMAS IR PUSLAPIAVIMAS
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 25;
if (!in_array($limit, [10, 25, 50, 100])) $limit = 25;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// Rikiavimo laukai (Whitelist)
$allowed_sort = ['konkurso_pav', '1_vardas', '1_pavarde', '1_klase', 'mokykla_pilna', 'Balai', 'Vieta'];
$sort = isset($_GET['sort']) && in_array($_GET['sort'], $allowed_sort) ? $_GET['sort'] : 'konkurso_pav';
$dir = isset($_GET['dir']) && $_GET['dir'] === 'DESC' ? 'DESC' : 'ASC';

// 3. SQL UŽKLAUSA
$where = ["d.Vieta IN ('I', 'II', 'III', 'laureat.')"];
$params = [];
$types = '';

if (!empty($olympiad)) { $where[] = "d.konkurso_pav = ?"; $params[] = $olympiad; $types .= 's'; }
if (!empty($school)) { $where[] = "d.var_mokykla = ?"; $params[] = $school; $types .= 's'; }

$where_clause = 'WHERE ' . implode(' AND ', $where);

// Bendras skaičius puslapiavimui
$count_sql = "SELECT COUNT(*) as total FROM dalyviai d $where_clause";
$count_stmt = db_query($count_sql, $params, $types);
$total_items = $count_stmt ? db_get_row($count_stmt)['total'] : 0;

// Duomenys
$order_sql = ($sort === 'Balai') ? "CAST(d.Balai AS UNSIGNED) $dir" : "{$sort} {$dir}";
$sql = "SELECT d.*, m.pavadinimas AS mokykla_pilna 
        FROM dalyviai d 
        LEFT JOIN mokyklos m ON d.var_mokykla = m.pavadinimas 
        $where_clause 
        ORDER BY $order_sql LIMIT ?, ?";

$data_params = array_merge($params, [$offset, $limit]);
$data_types = $types . 'ii';
$stmt = db_query($sql, $data_params, $data_types);
$winners = $stmt ? db_get_results($stmt) : [];

// Pagalbinės sąrašams
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
            <div class="col-md-4">
                <select name="olympiad" class="form-select">
                    <option value="">Visos olimpiados</option>
                    <?php foreach ($olympiads as $o): ?>
                        <option value="<?php echo htmlspecialchars($o['konkurso_pav']); ?>" <?php echo $olympiad === $o['konkurso_pav'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($o['konkurso_pav']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <select name="school" class="form-select">
                    <option value="">Visos mokyklos</option>
                    <?php foreach ($schools as $s): ?>
                        <option value="<?php echo htmlspecialchars($s['pavadinimas']); ?>" <?php echo $school === $s['pavadinimas'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($s['pavadinimas']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <button type="submit" class="btn btn-primary"><i class="fas fa-filter"></i> Filtruoti</button>
                <a href="?" class="btn btn-secondary"><i class="fas fa-undo"></i> Atstatyti</a>
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

<?php require_once dirname(dirname(dirname(__FILE__))) . '/includes/footer.php'; ?>