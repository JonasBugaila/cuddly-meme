<?php
/**
 * Mokytojų veiklos žurnalo peržiūra (ATSKIRA nuo bendro system_logs).
 * Rodo tik mokytojų (paprastų vartotojų) atliktus veiksmus: dalyvių
 * registravimą ir jų informacijos redagavimą. Puslapiuojama, su
 * filtravimu pagal mokyklą, įvykio tipą ir datos intervalą.
 */

require_once dirname(dirname(dirname(__FILE__))) . '/config/config.php';
require_once dirname(dirname(dirname(__FILE__))) . '/config/db_connect.php';
require_once dirname(dirname(dirname(__FILE__))) . '/config/functions.php';

if (!is_logged_in() || !is_admin()) {
    log_action('Saugumo pažeidimas', 'Bandyta pasiekti mokytojų žurnalą be admin teisių.');
    set_message('Neturite teisių pasiekti šį puslapį', 'error');
    redirect(SITE_URL);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['clear_logs'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        set_message('Netinkamas sesijos žetonas (CSRF).', 'error');
    } else {
        if (db_query("TRUNCATE TABLE teacher_activity_log")) {
            log_action('Žurnalo valymas', 'Administratorius išvalė mokytojų veiklos žurnalą.');
            set_message('Mokytojų žurnalas sėkmingai išvalytas.', 'success');
        } else {
            set_message('Nepavyko išvalyti žurnalo.', 'error');
        }
    }
    redirect(SITE_URL . '/modules/admin/teacher_logs.php');
    exit;
}

// ---------------------------------------------------------
// FILTRAI
// ---------------------------------------------------------
$filter_school = isset($_GET['school']) ? sanitize_input($_GET['school']) : '';
$filter_action = isset($_GET['action']) ? sanitize_input($_GET['action']) : '';
$filter_date_from = isset($_GET['date_from']) ? sanitize_input($_GET['date_from']) : '';
$filter_date_to = isset($_GET['date_to']) ? sanitize_input($_GET['date_to']) : '';

$where_clauses = [];
$params = [];
$types = '';

if ($filter_school !== '') {
    $where_clauses[] = 'var_mokykla = ?';
    $params[] = $filter_school;
    $types .= 's';
}
if ($filter_action !== '') {
    $where_clauses[] = 'action = ?';
    $params[] = $filter_action;
    $types .= 's';
}
if ($filter_date_from !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $filter_date_from)) {
    $where_clauses[] = 'created_at >= ?';
    $params[] = $filter_date_from . ' 00:00:00';
    $types .= 's';
}
if ($filter_date_to !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $filter_date_to)) {
    $where_clauses[] = 'created_at <= ?';
    $params[] = $filter_date_to . ' 23:59:59';
    $types .= 's';
}

$where_sql = !empty($where_clauses) ? 'WHERE ' . implode(' AND ', $where_clauses) : '';

// ---------------------------------------------------------
// PUSLAPIAVIMAS
// ---------------------------------------------------------
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 25;
if (!in_array($limit, [10, 25, 50, 100])) $limit = 25;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;

$count_sql = "SELECT COUNT(*) as total FROM teacher_activity_log $where_sql";
$total_items = db_get_row(db_query($count_sql, $params, $types))['total'] ?? 0;

$sql = "SELECT * FROM teacher_activity_log $where_sql ORDER BY created_at DESC LIMIT ?, ?";
$logs = db_get_results(db_query($sql, array_merge($params, [$offset, $limit]), $types . 'ii'));

// Dropdown'ų reikšmės - imamos tiesiai iš duomenų (be filtro), kad būtų matomi visi variantai
$schools = db_get_results(db_query("SELECT DISTINCT var_mokykla FROM teacher_activity_log WHERE var_mokykla IS NOT NULL AND var_mokykla != '' ORDER BY var_mokykla ASC"));
$distinct_actions = db_get_results(db_query("SELECT DISTINCT action FROM teacher_activity_log ORDER BY action ASC"));

require_once dirname(dirname(dirname(__FILE__))) . '/includes/header.php';
?>

<div class="row">
    <div class="col-12">
        <div class="card shadow-sm">
            <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
                <h1 class="h4 mb-0"><i class="fas fa-chalkboard-teacher"></i> Mokytojų veiklos žurnalas</h1>
                <div>
                    <form method="post" action="" onsubmit="return confirm('AR TIKRAI NORITE IŠVALYTI VISĄ MOKYTOJŲ ŽURNALĄ?');" style="display:inline;">
                        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                        <input type="hidden" name="clear_logs" value="1">
                        <button type="submit" class="btn btn-danger btn-sm"><i class="fas fa-trash"></i> Išvalyti žurnalą</button>
                    </form>
                    <a href="<?php echo SITE_URL; ?>/modules/admin/index.php" class="btn btn-secondary btn-sm ms-2">Grįžti</a>
                </div>
            </div>
            <div class="card-body">
                <?php display_message(); ?>

                <div class="alert alert-secondary">
                    <i class="fas fa-info-circle"></i> Šis žurnalas atskiras nuo bendro sistemos žurnalo ir fiksuoja
                    tik mokytojų (paprastų vartotojų) veiksmus: dalyvių registravimą ir jų informacijos redagavimą.
                </div>

                <!-- FILTRAVIMO FORMA -->
                <form method="get" action="" class="bg-light p-3 rounded mb-3 border">
                    <input type="hidden" name="limit" value="<?php echo (int)$limit; ?>">
                    <div class="row g-2 align-items-end">
                        <div class="col-md-3">
                            <label class="form-label small fw-bold text-muted mb-1">Mokykla</label>
                            <select name="school" class="form-select form-select-sm">
                                <option value="">-- Visos mokyklos --</option>
                                <?php foreach ($schools as $sch): ?>
                                    <option value="<?php echo htmlspecialchars($sch['var_mokykla']); ?>" <?php echo $filter_school === $sch['var_mokykla'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($sch['var_mokykla']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-bold text-muted mb-1">Įvykio tipas</label>
                            <select name="action" class="form-select form-select-sm">
                                <option value="">-- Visi įvykiai --</option>
                                <?php foreach ($distinct_actions as $a): ?>
                                    <option value="<?php echo htmlspecialchars($a['action']); ?>" <?php echo $filter_action === $a['action'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($a['action']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small fw-bold text-muted mb-1">Nuo datos</label>
                            <input type="date" name="date_from" class="form-control form-control-sm" value="<?php echo htmlspecialchars($filter_date_from); ?>">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small fw-bold text-muted mb-1">Iki datos</label>
                            <input type="date" name="date_to" class="form-control form-control-sm" value="<?php echo htmlspecialchars($filter_date_to); ?>">
                        </div>
                        <div class="col-md-2">
                            <button type="submit" class="btn btn-primary btn-sm w-100"><i class="fas fa-filter"></i> Filtruoti</button>
                        </div>
                    </div>
                    <?php if ($filter_school !== '' || $filter_action !== '' || $filter_date_from !== '' || $filter_date_to !== ''): ?>
                        <div class="mt-2">
                            <a href="?limit=<?php echo (int)$limit; ?>" class="btn btn-sm btn-outline-secondary"><i class="fas fa-times"></i> Išvalyti filtrus</a>
                        </div>
                    <?php endif; ?>
                </form>

                <?php if (!empty($logs)): ?>
                    <div class="table-responsive">
                        <table class="table table-striped table-hover align-middle" style="font-size: 0.9em;">
                            <thead class="table-light">
                                <tr>
                                    <th>Data ir Laikas</th>
                                    <th>Mokytojas</th>
                                    <th>Mokykla</th>
                                    <th>Veiksmas</th>
                                    <th>Detalės</th>
                                    <th>Dalyvio ID</th>
                                    <th>IP Adresas</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($logs as $log): ?>
                                    <tr>
                                        <td class="text-nowrap"><?php echo htmlspecialchars($log['created_at']); ?></td>
                                        <td><strong><?php echo htmlspecialchars($log['vart_id']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($log['var_mokykla'] ?? '-'); ?></td>
                                        <td>
                                            <?php $badge = (stripos($log['action'], 'redaga') !== false) ? 'bg-warning text-dark' : 'bg-success'; ?>
                                            <span class="badge <?php echo $badge; ?>"><?php echo htmlspecialchars($log['action']); ?></span>
                                        </td>
                                        <td><?php echo htmlspecialchars($log['details']); ?></td>
                                        <td class="text-muted"><?php echo $log['reg_id'] ? htmlspecialchars($log['reg_id']) : '-'; ?></td>
                                        <td class="text-muted"><small><?php echo htmlspecialchars($log['ip_address']); ?></small></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php render_pagination($total_items, $limit, $page); ?>
                <?php else: ?>
                    <div class="alert alert-info">Pagal pasirinktus filtrus įrašų nerasta.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once dirname(dirname(dirname(__FILE__))) . '/includes/footer.php'; ?>
