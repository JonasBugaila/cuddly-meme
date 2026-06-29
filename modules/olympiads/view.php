<?php
require_once dirname(dirname(dirname(__FILE__))) . '/config/config.php';
require_once dirname(dirname(dirname(__FILE__))) . '/config/db_connect.php';
require_once dirname(dirname(dirname(__FILE__))) . '/config/functions.php';

if (!is_logged_in()) redirect(SITE_URL . '/modules/auth/login.php');

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$stmt = db_query("SELECT * FROM konkursai WHERE konk_id = ?", [$id], 'i');
$olympiad = db_get_row($stmt);

if (!$olympiad) {
    set_message('Olimpiada nerasta.', 'error');
    redirect(SITE_URL . '/modules/olympiads/index.php');
}

// Duomenų gavimo logika (su GROUP BY, kad nesidubliuotų)
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 25;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;
$sort = isset($_GET['sort']) ? sanitize_input($_GET['sort']) : '1_pavarde';
$dir = isset($_GET['dir']) && $_GET['dir'] === 'DESC' ? 'DESC' : 'ASC';

$where = "d.konkurso_pav = ?";
$params = [$olympiad['konkurso_pav']];
$types = 's';

if (!is_admin()) {
    $user_school = db_get_row(db_query("SELECT var_mokykla FROM vartotojas WHERE vart_id = ?", [$_SESSION['user_id']], 's'))['var_mokykla'] ?? '';
    $where .= " AND d.var_mokykla = ?";
    $params[] = $user_school;
    $types .= 's';
}

$count_sql = "SELECT COUNT(DISTINCT d.reg_id) as total FROM dalyviai d LEFT JOIN mokyklos m ON d.var_mokykla = m.pavadinimas WHERE $where";
$total_items = db_get_row(db_query($count_sql, $params, $types))['total'] ?? 0;

$order_sql = ($sort === 'Balai') ? "CAST(d.Balai AS UNSIGNED) $dir" : "$sort $dir";
$sql = "SELECT d.*, MIN(m.pavadinimas) AS mokykla_pilna 
        FROM dalyviai d 
        LEFT JOIN mokyklos m ON d.var_mokykla = m.pavadinimas 
        WHERE $where 
        GROUP BY d.reg_id 
        ORDER BY $order_sql 
        LIMIT ?, ?";

$stmt_d = db_query($sql, array_merge($params, [$offset, $limit]), $types . 'ii');
$participants = db_get_results($stmt_d);

require_once dirname(dirname(dirname(__FILE__))) . '/includes/header.php';
?>

<div class="card shadow-sm border-0 mb-4">
    <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center py-3">
        <h1 class="h4 mb-0"><i class="fas fa-trophy"></i> <?php echo htmlspecialchars($olympiad['konkurso_pav']); ?></h1>
        <a href="index.php" class="btn btn-sm btn-light text-primary fw-bold"><i class="fas fa-arrow-left"></i> Grįžti į sąrašą</a>
    </div>
    <div class="card-body bg-light">
        <div class="row mb-3">
            <div class="col-md-4">
                <strong><i class="fas fa-users text-muted"></i> Grupė/Klasės:</strong> 
                <?php echo htmlspecialchars($olympiad['grupe'] ?? 'Nenurodyta'); ?>
            </div>
            <div class="col-md-4">
                <strong><i class="fas fa-user-tie text-muted"></i> Atsakingas asmuo:</strong> 
                <?php echo htmlspecialchars($olympiad['atsakingas'] ?? 'Nenurodyta'); ?>
            </div>
            <div class="col-md-4">
                <strong><i class="fas fa-info-circle text-muted"></i> Būsena:</strong> 
                <?php echo (($olympiad['status'] ?? 1) == 0) ? '<span class="badge bg-success">Aktyvi (Vyksta)</span>' : '<span class="badge bg-secondary">Neaktyvi (Baigta)</span>'; ?>
            </div>
        </div>
        
        <hr>
        
        <h5 class="mb-3">Valdymo įrankiai ir Ataskaitos:</h5>
        <div class="d-flex flex-wrap gap-2">
            <?php if (($olympiad['status'] ?? 1) == 0): ?>
                <a href="../registration/register.php?olympiad_id=<?=$olympiad['konk_id']?>" class="btn btn-success"><i class="fas fa-user-plus"></i> Registruoti dalyvį</a>
                <a href="../reports/result_sheet.php?olympiad_id=<?=$olympiad['konk_id']?>" class="btn btn-info text-white"><i class="fas fa-check-square"></i> Suvesti rezultatus</a>
            <?php endif; ?>
            
            <a href="../reports/participant_id.php?olympiad=<?=urlencode($olympiad['konkurso_pav'])?>" target="_blank" class="btn btn-dark"><i class="fas fa-barcode"></i> Kodų lapas</a>
            <a href="../reports/signature_sheets.php?print_empty=1&olympiad=<?=urlencode($olympiad['konkurso_pav'])?>" class="btn btn-warning"><i class="fas fa-file-signature"></i> Parašų lapas</a>
            <a href="../reports/evaluation_sheets.php?print_empty=1&olympiad=<?=urlencode($olympiad['konkurso_pav'])?>" target="_blank" class="btn btn-primary"><i class="fas fa-clipboard-list"></i> Vertinimo lapai</a>
            <a href="../reports/protocols.php?olympiad=<?=urlencode($olympiad['konkurso_pav'])?>" target="_blank" class="btn btn-secondary"><i class="fas fa-file-alt"></i> Protokolas</a>
        </div>
    </div>
</div>

<div class="card shadow-sm border-0">
    <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center">
        <h4 class="mb-0 text-gray-800"><i class="fas fa-list-ol"></i> Užregistruoti dalyviai (<?php echo $total_items; ?>)</h4>
    </div>
    <div class="card-body p-0">
        <?php display_message(); ?>
        
        <div class="table-responsive" style="min-height: 300px;">
            <table class="table table-striped table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3"><?php echo generate_sortable_header('1_vardas', 'Vardas', $sort, $dir); ?></th>
                        <th><?php echo generate_sortable_header('1_pavarde', 'Pavardė', $sort, $dir); ?></th>
                        <th><?php echo generate_sortable_header('1_klase', 'Klasė', $sort, $dir); ?></th>
                        <th>Mokykla</th>
                        <th>Balai</th>
                        <th class="pe-3">Vieta</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($participants)): foreach ($participants as $p): ?>
                    <tr>
                        <td class="ps-3"><?php echo htmlspecialchars($p['1_vardas']); ?></td>
                        <td><strong><?php echo htmlspecialchars($p['1_pavarde']); ?></strong></td>
                        <td><?php echo htmlspecialchars($p['1_klase']); ?></td>
                        <td><?php echo htmlspecialchars($p['mokykla_pilna'] ?? $p['var_mokykla']); ?></td>
                        <td><span class="badge bg-secondary fs-6"><?php echo htmlspecialchars($p['Balai'] ?: '-'); ?></span></td>
                        <td class="pe-3">
                            <?php if (!empty($p['Vieta'])): ?>
                                <?php $v_color = in_array($p['Vieta'], ['I','II','III','laureat.']) ? 'bg-warning text-dark' : 'bg-primary'; ?>
                                <span class="badge <?php echo $v_color; ?> fs-6"><?php echo htmlspecialchars($p['Vieta']); ?></span>
                            <?php else: ?>
                                <span class="text-muted">-</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; else: ?>
                        <tr><td colspan="6" class="text-center text-muted py-5"><i class="fas fa-users-slash fa-2x mb-2"></i><br>Dalyvių šioje olimpiadoje dar nėra.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <div class="card-footer bg-white border-top">
        <?php render_pagination($total_items, $limit, $page); ?>
    </div>
</div>

<?php require_once dirname(dirname(dirname(__FILE__))) . '/includes/footer.php'; ?>