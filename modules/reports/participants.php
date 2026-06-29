<?php
require_once dirname(dirname(dirname(__FILE__))) . '/config/config.php';
require_once dirname(dirname(dirname(__FILE__))) . '/config/db_connect.php';
require_once dirname(dirname(dirname(__FILE__))) . '/config/functions.php';

if (!is_logged_in()) { redirect(SITE_URL . '/modules/auth/login.php'); }

$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 25;
if (!in_array($limit, [10, 25, 50, 100])) $limit = 25;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

$allowed_sort = ['1_vardas', '1_pavarde', '1_klase', 'konkurso_pav', 'var_mokykla'];
$sort = isset($_GET['sort']) && in_array($_GET['sort'], $allowed_sort) ? $_GET['sort'] : '1_pavarde';
$dir = isset($_GET['dir']) && $_GET['dir'] === 'DESC' ? 'DESC' : 'ASC';

$params = [];
$types = '';
$where_sql = "1=1";

if (!is_admin()) {
    $user_school = db_get_row(db_query("SELECT var_mokykla FROM vartotojas WHERE vart_id = ?", [$_SESSION['user_id']], 's'))['var_mokykla'] ?? '';
    $where_sql .= " AND var_mokykla = ?";
    $params[] = $user_school;
    $types .= 's';
}

$total_items = db_get_row(db_query("SELECT COUNT(*) as total FROM dalyviai WHERE $where_sql", $params, $types))['total'];
$sql = "SELECT * FROM dalyviai WHERE $where_sql ORDER BY $sort $dir LIMIT ?, ?";
$stmt = db_query($sql, array_merge($params, [$offset, $limit]), $types . 'ii');
$participants = $stmt ? db_get_results($stmt) : [];

require_once dirname(dirname(dirname(__FILE__))) . '/includes/header.php';
?>

<div class="card shadow-sm border-0">
    <div class="card-header bg-primary text-white">
        <h1 class="h4 mb-0"><i class="fas fa-users"></i> Dalyvių sąrašas</h1>
    </div>
    <div class="card-body">
        <div class="table-responsive" style="min-height: 400px;">
            <table class="table table-striped table-hover align-middle">
                <thead>
                    <tr>
                        <th><?php echo generate_sortable_header('1_vardas', 'Vardas', $sort, $dir); ?></th>
                        <th><?php echo generate_sortable_header('1_pavarde', 'Pavardė', $sort, $dir); ?></th>
                        <th><?php echo generate_sortable_header('1_klase', 'Klasė', $sort, $dir); ?></th>
                        <th><?php echo generate_sortable_header('konkurso_pav', 'Olimpiada', $sort, $dir); ?></th>
                        <th><?php echo generate_sortable_header('var_mokykla', 'Mokykla', $sort, $dir); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($participants as $p): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($p['1_vardas']); ?></td>
                        <td><strong><?php echo htmlspecialchars($p['1_pavarde']); ?></strong></td>
                        <td><?php echo htmlspecialchars($p['1_klase']); ?></td>
                        <td><?php echo htmlspecialchars($p['konkurso_pav']); ?></td>
                        <td><?php echo htmlspecialchars($p['var_mokykla']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php render_pagination($total_items, $limit, $page); ?>
    </div>
</div>
<?php require_once dirname(dirname(dirname(__FILE__))) . '/includes/footer.php'; ?>