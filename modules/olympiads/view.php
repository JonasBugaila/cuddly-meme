<?php
/**
 * Olimpiados peržiūros puslapis
 */

require_once dirname(dirname(dirname(__FILE__))) . '/config/config.php';
require_once dirname(dirname(dirname(__FILE__))) . '/config/db_connect.php';
require_once dirname(dirname(dirname(__FILE__))) . '/config/functions.php';

if (!is_logged_in()) { redirect(SITE_URL . '/modules/auth/login.php'); }

$olympiad_id = sanitize_input($_GET['id'] ?? '');
$stmt = db_query("SELECT * FROM konkursai WHERE konk_id = ?", [$olympiad_id], 'i');
$olympiad = $stmt ? db_get_row($stmt) : null;

if (!$olympiad) { redirect(SITE_URL . '/modules/olympiads/index.php'); }

$user_school = '';
if (!is_admin() && isset($_SESSION['user_id'])) {
    $stmt_u = db_query("SELECT var_mokykla FROM vartotojas WHERE vart_id = ?", [$_SESSION['user_id']], 's');
    if ($stmt_u && ($user_data = db_get_row($stmt_u))) {
        $user_school = $user_data['var_mokykla'];
    }
}

$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 25;
if (!in_array($limit, [10, 25, 50, 100])) $limit = 25;

$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

$allowed_sort_columns = ['1_vardas', '1_pavarde', '1_klase', 'mokykla_pilna', '1_mok', 'Balai', 'Vieta'];
$sort = isset($_GET['sort']) && in_array($_GET['sort'], $allowed_sort_columns) ? $_GET['sort'] : '1_vardas';
$dir = isset($_GET['dir']) && strtoupper($_GET['dir']) === 'DESC' ? 'DESC' : 'ASC';

$where_sql = "d.konkurso_pav = ?";
$params = [$olympiad['konkurso_pav']];
$param_types = 's';

if (!is_admin()) {
    $where_sql .= " AND d.var_mokykla = ?";
    $params[] = $user_school;
    $param_types .= 's';
}

$count_stmt = db_query("SELECT COUNT(*) as total FROM dalyviai d WHERE {$where_sql}", $params, $param_types);
$total_items = $count_stmt ? db_get_row($count_stmt)['total'] : 0;

$order_by = "{$sort} {$dir}";
if ($sort === 'Balai') { $order_by = "CAST(d.Balai AS UNSIGNED) {$dir}"; }
elseif ($sort === 'mokykla_pilna') { $order_by = "m.pavadinimas {$dir}, d.var_mokykla {$dir}"; }

$data_sql = "SELECT d.*, m.pavadinimas AS mokykla_pilna FROM dalyviai d LEFT JOIN mokyklos m ON d.var_mokykla = m.pavadinimas WHERE {$where_sql} ORDER BY {$order_by} LIMIT ?, ?";
$stmt = db_query($data_sql, array_merge($params, [$offset, $limit]), $param_types . 'ii');
$participants = $stmt ? db_get_results($stmt) : [];

require_once dirname(dirname(dirname(__FILE__))) . '/includes/header.php';
?>

<div class="row">
    <div class="col-12">
        <div class="card shadow-sm border-0">
            <div class="card-header d-flex justify-content-between align-items-center bg-primary text-white">
                <h1 class="h4 mb-0"><i class="fas fa-clipboard-list"></i> <?php echo htmlspecialchars($olympiad['konkurso_pav']); ?></h1>
                <div class="d-flex gap-2">
                    <?php if ($olympiad['status'] == 0): ?>
                        <a href="<?php echo SITE_URL; ?>/modules/registration/register.php?olympiad_id=<?php echo $olympiad['konk_id']; ?>" class="btn btn-success btn-sm fw-bold"><i class="fas fa-plus"></i> Registruoti dalyvius</a>
                    <?php endif; ?>
                    <a href="<?php echo SITE_URL; ?>/modules/olympiads/index.php" class="btn btn-light btn-sm">Grįžti į sąrašą</a>
                </div>
            </div>
            <div class="card-body">
                <?php display_message(); ?>
                
                <h4 class="mb-3 mt-2">Užregistruoti dalyviai</h4>
                <?php if ($total_items > 0): ?>
                    <div class="table-responsive" style="min-height: 400px;">
                        <table class="table table-striped table-hover align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th><?php echo generate_sortable_header('1_vardas', 'Vardas', $sort, $dir); ?></th>
                                    <th><?php echo generate_sortable_header('1_pavarde', 'Pavardė', $sort, $dir); ?></th>
                                    <th><?php echo generate_sortable_header('1_klase', 'Klasė', $sort, $dir); ?></th>
                                    <th><?php echo generate_sortable_header('mokykla_pilna', 'Mokykla', $sort, $dir); ?></th>
                                    <th><?php echo generate_sortable_header('1_mok', 'Mokytojas', $sort, $dir); ?></th>
                                    <th><?php echo generate_sortable_header('Balai', 'Balai', $sort, $dir); ?></th>
                                    <th><?php echo generate_sortable_header('Vieta', 'Vieta', $sort, $dir); ?></th>
                                    <th class="text-end">Veiksmai</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($participants as $participant): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($participant['1_vardas']); ?></td>
                                        <td><strong><?php echo htmlspecialchars($participant['1_pavarde']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($participant['1_klase']); ?></td>
                                        <td><?php echo htmlspecialchars($participant['mokykla_pilna'] ?? $participant['var_mokykla']); ?></td>
                                        <td><?php echo htmlspecialchars($participant['1_mok']); ?></td>
                                        <td><span class="badge bg-secondary rounded-pill"><?php echo htmlspecialchars($participant['Balai'] ?: '-'); ?></span></td>
                                        <td>
                                            <?php if (!empty($participant['Vieta'])): ?>
                                                <?php $v_color = ($participant['Vieta'] == 'I' || $participant['Vieta'] == 'laureat.') ? 'bg-warning text-dark' : 'bg-primary'; ?>
                                                <span class="badge <?php echo $v_color; ?>"><?php echo htmlspecialchars($participant['Vieta']); ?></span>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end">
                                            <?php if (!empty($participant['Vieta']) && in_array($participant['Vieta'], ['I','II','III','laureat.'])): ?>
                                                <a href="<?php echo SITE_URL; ?>/modules/reports/diplomas.php?id=<?php echo $participant['reg_id']; ?>" target="_blank" class="btn btn-sm btn-outline-warning">
                                                    <i class="fas fa-certificate"></i> Diplomas
                                                </a>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php render_pagination($total_items, $limit, $page); ?>
                <?php else: ?>
                    <div class="alert alert-info">Šioje olimpiadoje dar nėra užregistruotų dalyvių.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once dirname(dirname(dirname(__FILE__))) . '/includes/footer.php'; ?>