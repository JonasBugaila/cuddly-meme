<?php
/**
 * Olimpiadų modulio pagrindinis puslapis (Archyvas ir valdymas)
 */

require_once dirname(dirname(dirname(__FILE__))) . '/config/config.php';
require_once dirname(dirname(dirname(__FILE__))) . '/config/db_connect.php';
require_once dirname(dirname(dirname(__FILE__))) . '/config/functions.php';

if (!is_logged_in()) {
    redirect(SITE_URL . '/modules/auth/login.php');
    exit;
}

// 1. STATUSO KEITIMO APDOROJIMAS (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_status']) && is_admin()) {
    if (verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $konk_id = sanitize_input($_POST['konk_id']);
        $new_status = (int)$_POST['new_status'];
        db_query("UPDATE konkursai SET status = ? WHERE konk_id = ?", [$new_status, $konk_id], 'ii');
        set_message('Olimpiados statusas sėkmingai atnaujintas.', 'success');
    }
    redirect(build_url_with_params([]));
}

// 2. PARINKTŲ FILTRŲ APDOROJIMAS (BŪSENA)
$filter_status = isset($_GET['status_filter']) ? sanitize_input($_GET['status_filter']) : 'all';

$where_clauses = [];
$params = [];
$param_types = '';

if ($filter_status === 'active') {
    $where_clauses[] = "status = 0";
} elseif ($filter_status === 'inactive') {
    $where_clauses[] = "status = 1";
}

$where_sql = !empty($where_clauses) ? 'WHERE ' . implode(' AND ', $where_clauses) : '';

// 3. PUSLAPIAVIMAS IR RIKIAVIMAS
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
if (!in_array($limit, [10, 25, 50, 100])) $limit = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;

$sort = isset($_GET['sort']) ? sanitize_input($_GET['sort']) : 'konk_id';
$dir = isset($_GET['dir']) && strtoupper($_GET['dir']) === 'ASC' ? 'ASC' : 'DESC';

// Gauname bendrą įrašų skaičių filtravimui
$count_sql = "SELECT COUNT(*) as total FROM konkursai $where_sql";
$count_stmt = db_query($count_sql, $params, $param_types);
$total_items = $count_stmt ? db_get_row($count_stmt)['total'] : 0;

// Krauname olimpiadų sąrašą lentelei
$sql = "SELECT * FROM konkursai $where_sql ORDER BY $sort $dir LIMIT ?, ?";
$stmt = db_query($sql, array_merge($params, [$offset, $limit]), $param_types . 'ii');
$olympiads = $stmt ? db_get_results($stmt) : [];

// 4. PASIRINKTOS VIENOS OLIMPIADOS PERŽIŪRA (JEI URL YRA ?id=...)
$selected_olympiad = null;
$selected_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($selected_id > 0) {
    $stmt_sel = db_query("SELECT * FROM konkursai WHERE konk_id = ?", [$selected_id], 'i');
    $selected_olympiad = $stmt_sel ? db_get_row($stmt_sel) : null;
}

require_once dirname(dirname(dirname(__FILE__))) . '/includes/header.php';
?>

<?php if ($selected_olympiad): ?>
<div class="card shadow-sm border-0 mb-4 animate-fade-in">
    <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center py-3">
        <h2 class="h4 mb-0"><i class="fas fa-info-circle"></i> <?php echo htmlspecialchars($selected_olympiad['konkurso_pav']); ?></h2>
        <a href="<?php echo build_url_with_params(['id' => null]); ?>" class="btn btn-sm btn-light text-primary fw-bold">
            <i class="fas fa-times"></i> Uždaryti peržiūrą
        </a>
    </div>
    <div class="card-body bg-light">
        <div class="row mb-3">
            <div class="col-md-4">
                <strong><i class="fas fa-users text-muted"></i> Grupė/Klasės:</strong> 
                <?php echo htmlspecialchars($selected_olympiad['grupe'] ?? 'Nenurodyta'); ?>
            </div>
            <div class="col-md-4">
                <strong><i class="fas fa-user-tie text-muted"></i> Atsakingas asmuo:</strong> 
                <?php echo htmlspecialchars($selected_olympiad['atsakingas'] ?? 'Nenurodyta'); ?>
            </div>
            <div class="col-md-4">
                <strong><i class="fas fa-toggle-on text-muted"></i> Būsena:</strong> 
                <?php echo (($selected_olympiad['status'] ?? 1) == 0) ? '<span class="badge bg-success">Aktyvi</span>' : '<span class="badge bg-secondary">Neaktyvi (Baigta)</span>'; ?>
            </div>
        </div>
        
        <hr>
        
        <h5 class="mb-3">Valdymo įrankiai ir Ataskaitos:</h5>
        <div class="d-flex flex-wrap gap-2">
            <a href="view.php?id=<?=$selected_olympiad['konk_id']?>" class="btn btn-primary"><i class="fas fa-eye"></i> Peržiūrėti dalyvius</a>
            
            <?php if (($selected_olympiad['status'] ?? 1) == 0): ?>
                <a href="../registration/register.php?olympiad_id=<?=$selected_olympiad['konk_id']?>" class="btn btn-success"><i class="fas fa-user-plus"></i> Registruoti dalyvį</a>
                <a href="../reports/result_sheet.php?olympiad_id=<?=$selected_olympiad['konk_id']?>" class="btn btn-info text-white"><i class="fas fa-check-square"></i> Suvesti rezultatus</a>
            <?php endif; ?>
            
            <a href="../reports/participant_id.php?olympiad=<?=urlencode($selected_olympiad['konkurso_pav'])?>" target="_blank" class="btn btn-dark"><i class="fas fa-barcode"></i> Kodų lapas</a>
            <a href="../reports/signature_sheets.php?print_empty=1&olympiad=<?=urlencode($selected_olympiad['konkurso_pav'])?>" class="btn btn-warning"><i class="fas fa-file-signature"></i> Parašų lapas</a>
            <a href="../reports/evaluation_sheets.php?print_empty=1&olympiad=<?=urlencode($selected_olympiad['konkurso_pav'])?>" target="_blank" class="btn btn-primary"><i class="fas fa-clipboard-list"></i> Vertinimo lapai</a>
            <a href="../reports/protocols.php?olympiad=<?=urlencode($selected_olympiad['konkurso_pav'])?>" target="_blank" class="btn btn-secondary"><i class="fas fa-file-alt"></i> Protokolas</a>
        </div>
    </div>
</div>
<?php endif; ?>


<div class="card shadow-sm border-0">
    <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center py-3">
        <h1 class="h4 mb-0"><i class="fas fa-archive"></i> Olimpiadų registras</h1>
    </div>
    
    <div class="card-body">
        <?php display_message(); ?>
        
        <form method="get" action="" id="filterForm" class="bg-light p-3 rounded mb-4">
            <input type="hidden" name="sort" value="<?php echo htmlspecialchars($sort); ?>">
            <input type="hidden" name="dir" value="<?php echo htmlspecialchars($dir); ?>">
            <input type="hidden" name="limit" value="<?php echo htmlspecialchars($limit); ?>">
            
            <div class="d-flex align-items-center flex-wrap gap-4">
                <span class="fw-bold text-secondary"><i class="fas fa-filter"></i> Rodyti olimpiadas:</span>
                
                <div class="form-check form-check-inline">
                    <input class="form-check-input" type="radio" name="status_filter" id="status_all" value="all" <?php echo $filter_status === 'all' ? 'checked' : ''; ?> onchange="document.getElementById('filterForm').submit();">
                    <label class="form-check-label fw-bold" for="status_all">Visas</label>
                </div>
                
                <div class="form-check form-check-inline">
                    <input class="form-check-input" type="radio" name="status_filter" id="status_active" value="active" <?php echo $filter_status === 'active' ? 'checked' : ''; ?> onchange="document.getElementById('filterForm').submit();">
                    <label class="form-check-label text-success fw-bold" for="status_active">Tik aktyvias</label>
                </div>
                
                <div class="form-check form-check-inline">
                    <input class="form-check-input" type="radio" name="status_filter" id="status_inactive" value="inactive" <?php echo $filter_status === 'inactive' ? 'checked' : ''; ?> onchange="document.getElementById('filterForm').submit();">
                    <label class="form-check-label text-danger fw-bold" for="status_inactive">Tik neaktyvias (pasibaigusias)</label>
                </div>
            </div>
        </form>

        <div class="table-responsive" style="min-height: 350px;">
            <table class="table table-striped table-hover align-middle">
                <thead class="table-light">
                    <tr>
                        <th><?php echo generate_sortable_header('konkurso_pav', 'Olimpiados pavadinimas', $sort, $dir); ?></th>
                        <th><?php echo generate_sortable_header('grupe', 'Grupė', $sort, $dir); ?></th>
                        <th><?php echo generate_sortable_header('atsakingas', 'Atsakingas', $sort, $dir); ?></th>
                        <th>Būsena</th>
                        <?php if (is_admin()): ?><th class="text-end">Veiksmas</th><?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($olympiads)): foreach ($olympiads as $oly): ?>
                    <tr class="<?php echo $selected_id === (int)$oly['konk_id'] ? 'table-primary fw-bold' : ''; ?>">
                        <td>
                            <a href="<?php echo build_url_with_params(['id' => $oly['konk_id']]); ?>" class="text-decoration-none text-dark d-block">
                                <i class="fas fa-folder-open text-warning me-2"></i> <?php echo htmlspecialchars($oly['konkurso_pav']); ?>
                            </a>
                        </td>
                        <td><?php echo htmlspecialchars($oly['grupe']); ?></td>
                        <td><?php echo htmlspecialchars($oly['atsakingas']); ?></td>
                        <td>
                            <?php echo (($oly['status'] ?? 1) == 0) ? '<span class="badge bg-success">Aktyvi</span>' : '<span class="badge bg-secondary">Neaktyvi</span>'; ?>
                        </td>
                        <?php if (is_admin()): ?>
                        <td class="text-end">
                            <form method="post" class="m-0 p-0" style="display:inline-block;" onsubmit="return confirm('Ar tikrai norite pakeisti šios olimpiados būseną?');">
                                <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                                <input type="hidden" name="konk_id" value="<?php echo $oly['konk_id']; ?>">
                                <input type="hidden" name="new_status" value="<?php echo (($oly['status'] ?? 1) == 0) ? 1 : 0; ?>">
                                <input type="hidden" name="toggle_status" value="1">
                                <button type="submit" class="btn btn-sm <?php echo (($oly['status'] ?? 1) == 0) ? 'btn-outline-danger' : 'btn-outline-success'; ?>" title="<?php echo (($oly['status'] ?? 1) == 0) ? 'Išjungti olimpiadą' : 'Įjungti olimpiadą'; ?>">
                                    <i class="fas <?php echo (($oly['status'] ?? 1) == 0) ? 'fa-power-off' : 'fa-play'; ?>"></i>
                                    <?php echo (($oly['status'] ?? 1) == 0) ? 'Išjungti' : 'Įjungti'; ?>
                                </button>
                            </form>
                        </td>
                        <?php endif; ?>
                    </tr>
                    <?php endforeach; else: ?>
                        <tr><td colspan="5" class="text-center text-muted py-4">Pagal pasirinktus filtrus jokių olimpiadų nerasta.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <?php render_pagination($total_items, $limit, $page); ?>
    </div>
</div>

<?php require_once dirname(dirname(dirname(__FILE__))) . '/includes/footer.php'; ?>