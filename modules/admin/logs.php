<?php
/**
 * Sistemos žurnalo peržiūros puslapis
 * Puslapiuojamas, su filtravimu pagal įvykio tipą ir datos intervalą.
 */

require_once dirname(dirname(dirname(__FILE__))) . '/config/config.php';
require_once dirname(dirname(dirname(__FILE__))) . '/config/db_connect.php';
require_once dirname(dirname(dirname(__FILE__))) . '/config/functions.php';

// Tikriname teises (tik administratoriams)
if (!is_logged_in() || !is_admin()) {
    log_action('Saugumo pažeidimas', 'Bandyta pasiekti sistemos žurnalą be admin teisių.');
    set_message('Neturite teisių pasiekti šį puslapį', 'error');
    redirect(SITE_URL);
    exit;
}

// Žurnalo valymas
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['clear_logs'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        set_message('Netinkamas sesijos žetonas (CSRF).', 'error');
    } else {
        if (db_query("TRUNCATE TABLE system_logs")) {
            log_action('Žurnalo valymas', 'Administratorius išvalė visą sistemos žurnalą.');
            set_message('Sistemos žurnalas sėkmingai išvalytas.', 'success');
        } else {
            set_message('Nepavyko išvalyti žurnalo.', 'error');
        }
    }
    // PATAISYTA: anksčiau nukreipdavo į neegzistuojantį system_logs.php
    redirect(SITE_URL . '/modules/admin/logs.php');
    exit;
}

// ---------------------------------------------------------
// FILTRAI
// ---------------------------------------------------------
$filter_action = isset($_GET['action']) ? sanitize_input($_GET['action']) : '';
$filter_date_from = isset($_GET['date_from']) ? sanitize_input($_GET['date_from']) : '';
$filter_date_to = isset($_GET['date_to']) ? sanitize_input($_GET['date_to']) : '';

$where_clauses = [];
$params = [];
$types = '';

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

$count_sql = "SELECT COUNT(*) as total FROM system_logs $where_sql";
$total_items = db_get_row(db_query($count_sql, $params, $types))['total'] ?? 0;

$sql = "SELECT * FROM system_logs $where_sql ORDER BY created_at DESC LIMIT ?, ?";
$logs = db_get_results(db_query($sql, array_merge($params, [$offset, $limit]), $types . 'ii'));

// Galimų veiksmų sąrašas dropdown'ui - imamas tiesiai iš duomenų, kad visada būtų aktualus
$distinct_actions = db_get_results(db_query("SELECT DISTINCT action FROM system_logs ORDER BY action ASC"));

require_once dirname(dirname(dirname(__FILE__))) . '/includes/header.php';
?>

<div class="row">
    <div class="col-12">
        <div class="card shadow-sm">
            <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
                <h1 class="h4 mb-0"><i class="fas fa-clipboard-list"></i> Sistemos žurnalas</h1>
                <div>
                    <form method="post" action="" onsubmit="return confirm('AR TIKRAI NORITE IŠVALYTI VISĄ ŽURNALĄ? Šio veiksmo atšaukti negalima.');" style="display:inline;">
                        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                        <input type="hidden" name="clear_logs" value="1">
                        <button type="submit" class="btn btn-danger btn-sm"><i class="fas fa-trash"></i> Išvalyti žurnalą</button>
                    </form>
                    <a href="<?php echo SITE_URL; ?>/modules/admin/index.php" class="btn btn-secondary btn-sm ms-2">Grįžti</a>
                </div>
            </div>
            <div class="card-body">
                <?php display_message(); ?>

                <!-- FILTRAVIMO FORMA -->
                <form method="get" action="" class="bg-light p-3 rounded mb-3 border">
                    <input type="hidden" name="limit" value="<?php echo (int)$limit; ?>">
                    <div class="row g-2 align-items-end">
                        <div class="col-md-4">
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
                        <div class="col-md-3">
                            <label class="form-label small fw-bold text-muted mb-1">Nuo datos</label>
                            <input type="date" name="date_from" class="form-control form-control-sm" value="<?php echo htmlspecialchars($filter_date_from); ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-bold text-muted mb-1">Iki datos</label>
                            <input type="date" name="date_to" class="form-control form-control-sm" value="<?php echo htmlspecialchars($filter_date_to); ?>">
                        </div>
                        <div class="col-md-2 d-flex gap-2">
                            <button type="submit" class="btn btn-primary btn-sm w-100"><i class="fas fa-filter"></i> Filtruoti</button>
                        </div>
                    </div>
                    <?php if ($filter_action !== '' || $filter_date_from !== '' || $filter_date_to !== ''): ?>
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
                                    <th>Vartotojas</th>
                                    <th>Veiksmas</th>
                                    <th>Detalės</th>
                                    <th>IP Adresas</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($logs as $log): ?>
                                    <tr>
                                        <td class="text-nowrap"><?php echo htmlspecialchars($log['created_at']); ?></td>
                                        <td>
                                            <?php if ($log['user_id'] === 'Svečias'): ?>
                                                <span class="badge bg-secondary">Svečias</span>
                                            <?php else: ?>
                                                <strong><?php echo htmlspecialchars($log['user_id']); ?></strong>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php
                                            $action_class = 'bg-primary';
                                            if (stripos($log['action'], 'klaida') !== false || stripos($log['action'], 'pažeidimas') !== false) { $action_class = 'bg-danger'; }
                                            elseif (stripos($log['action'], 'prisijungimas') !== false) { $action_class = 'bg-success'; }
                                            elseif (stripos($log['action'], 'trynimas') !== false || stripos($log['action'], 'pašalinimas') !== false) { $action_class = 'bg-warning text-dark'; }
                                            ?>
                                            <span class="badge <?php echo $action_class; ?>"><?php echo htmlspecialchars($log['action']); ?></span>
                                        </td>
                                        <td><?php echo htmlspecialchars($log['details']); ?></td>
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
